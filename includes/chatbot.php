<?php
declare(strict_types=1);

/**
 * Chatbot citoyen — moteur local (Phase X).
 *
 * Approche pragmatique sans appel LLM externe :
 *  1. Normalise la question utilisateur (minuscules, sans accents, sans ponctuation)
 *  2. Calcule un score par entrée FAQ basé sur : nombre de mots-clés présents,
 *     pondération du champ question et du champ mots_cles, bonus si la priorité
 *     est élevée.
 *  3. Retourne la meilleure réponse au-dessus d'un seuil, ou une réponse
 *     « je ne sais pas » avec suggestions des FAQ les plus fréquentes.
 *
 * Cette base permet déjà de répondre instantanément aux questions courantes
 * (extrait de naissance, paiement de taxe, signalement, etc.). Pour passer à
 * un LLM, il suffit de remplacer maire_chatbot_repondre() par un appel API.
 */

function maire_ensure_chatbot_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chatbot_faq (
            id INT AUTO_INCREMENT PRIMARY KEY,
            categorie VARCHAR(60) NOT NULL DEFAULT 'general',
            question VARCHAR(300) NOT NULL,
            reponse TEXT NOT NULL,
            mots_cles VARCHAR(500) NOT NULL,
            lien_action VARCHAR(255) NULL,
            libelle_action VARCHAR(80) NULL,
            priorite INT NOT NULL DEFAULT 0,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            nb_consultations INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_faq_categ (categorie),
            INDEX idx_faq_actif (actif)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chatbot_conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            citoyen_id INT NULL,
            session_token CHAR(32) NOT NULL,
            question TEXT NOT NULL,
            reponse TEXT NOT NULL,
            faq_id INT NULL,
            score DECIMAL(5, 2) NULL,
            satisfait TINYINT(1) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_chat_session (session_token),
            INDEX idx_chat_citoyen (citoyen_id),
            INDEX idx_chat_date (created_at)
        )
    ");
}

/**
 * Normalise une chaîne pour scoring : minuscules, sans accents, sans ponctuation.
 */
function maire_chatbot_normaliser(string $texte): string
{
    $texte = mb_strtolower(trim($texte), 'UTF-8');
    // Translittération basique des accents (UTF-8 → ASCII)
    $translit = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a',
        'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ÿ' => 'y', 'ý' => 'y',
        'ñ' => 'n',
        '’' => "'", '“' => '"', '”' => '"', '«' => '"', '»' => '"',
    ];
    $texte = strtr($texte, $translit);
    // Ponctuation → espace
    $texte = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $texte) ?? $texte;
    // Espaces multiples
    $texte = preg_replace('/\s+/u', ' ', $texte) ?? $texte;
    return trim($texte);
}

/**
 * Tokenize une chaîne normalisée en mots utiles (longueur >= 2, hors stop-words).
 * @return list<string>
 */
function maire_chatbot_tokens(string $texteNormalise): array
{
    $stop = [
        'le','la','les','de','du','des','un','une','et','ou','pour','sur','dans','avec','par','en','au','aux','a','est','sont','je','tu','il','elle','nous','vous','ils','elles','que','qui','quoi','comment','quand','ou','y','t','d','l','m','s','c','n','si','ne','pas','plus','mais','donc','car','quel','quelle','quels','quelles','ma','mon','mes','ta','ton','tes','sa','son','ses','notre','nos','votre','vos','leur','leurs','etre','avoir','faire',
    ];
    $tokens = preg_split('/\s+/u', $texteNormalise) ?: [];
    $out = [];
    foreach ($tokens as $t) {
        if (mb_strlen($t) >= 2 && !in_array($t, $stop, true)) {
            $out[] = $t;
        }
    }
    return $out;
}

/**
 * Calcule un score d'adéquation entre la question utilisateur et une entrée FAQ.
 */
function maire_chatbot_scorer(array $userTokens, array $faqRow): float
{
    if (empty($userTokens)) {
        return 0.0;
    }
    $questionTokens = maire_chatbot_tokens(maire_chatbot_normaliser((string) $faqRow['question']));
    $motsClesTokens = array_filter(array_map(
        'trim',
        explode(',', maire_chatbot_normaliser((string) $faqRow['mots_cles']))
    ));

    $score = 0.0;
    foreach ($userTokens as $tk) {
        // Match exact dans mots-clés (poids fort)
        foreach ($motsClesTokens as $mc) {
            if ($mc === '') continue;
            if ($mc === $tk) {
                $score += 5.0;
                break;
            }
            // Partiel : le token utilisateur contient le mot-clé ou inversement
            if (mb_strlen($mc) >= 3 && (str_contains($tk, $mc) || str_contains($mc, $tk))) {
                $score += 2.5;
                break;
            }
        }
        // Match dans la question (poids moyen)
        if (in_array($tk, $questionTokens, true)) {
            $score += 1.5;
        }
    }

    // Bonus priorité (entre 0 et 10)
    $score += ((int) ($faqRow['priorite'] ?? 0)) * 0.15;
    // Normalisation : on rapporte au nombre de tokens utilisateur pour comparer
    $score = $score / max(1, count($userTokens));
    return round($score, 2);
}

/**
 * Retourne la meilleure réponse FAQ pour une question donnée.
 *
 * @return array{
 *   trouve: bool,
 *   reponse: string,
 *   score: float,
 *   faq_id: ?int,
 *   lien_action: ?string,
 *   libelle_action: ?string,
 *   suggestions: list<array{id:int,question:string}>
 * }
 */
function maire_chatbot_repondre(PDO $pdo, string $question): array
{
    maire_ensure_chatbot_tables($pdo);

    $normalized = maire_chatbot_normaliser($question);
    $tokens = maire_chatbot_tokens($normalized);

    if (empty($tokens)) {
        return [
            'trouve' => false,
            'reponse' => "Bonjour ! Posez-moi une question sur les démarches, paiements, signalements ou consultations.",
            'score' => 0.0,
            'faq_id' => null,
            'lien_action' => null,
            'libelle_action' => null,
            'suggestions' => [],
        ];
    }

    $faqs = [];
    try {
        $faqs = $pdo->query("SELECT * FROM chatbot_faq WHERE actif = 1")->fetchAll() ?: [];
    } catch (Throwable $e) {
        // tolérant
    }

    $best = null;
    $bestScore = 0.0;
    foreach ($faqs as $row) {
        $s = maire_chatbot_scorer($tokens, $row);
        if ($s > $bestScore) {
            $bestScore = $s;
            $best = $row;
        }
    }

    $seuil = 1.2;
    if ($best === null || $bestScore < $seuil) {
        return [
            'trouve' => false,
            'reponse' => "Je n'ai pas trouvé de réponse précise à votre question. Vous pouvez reformuler ou <a href=\"contact.php\">contacter directement la mairie</a>.",
            'score' => $bestScore,
            'faq_id' => null,
            'lien_action' => null,
            'libelle_action' => null,
            'suggestions' => [],
        ];
    }

    try {
        $pdo->prepare('UPDATE chatbot_faq SET nb_consultations = nb_consultations + 1 WHERE id = :id')
            ->execute(['id' => (int) $best['id']]);
    } catch (Throwable $e) {
        // tolérant
    }

    return [
        'trouve' => true,
        'reponse' => (string) $best['reponse'],
        'score' => $bestScore,
        'faq_id' => (int) $best['id'],
        'lien_action' => $best['lien_action'] !== null ? (string) $best['lien_action'] : null,
        'libelle_action' => $best['libelle_action'] !== null ? (string) $best['libelle_action'] : null,
        'suggestions' => [],
    ];
}

/**
 * @return list<array{id:int,question:string}>
 */
function maire_chatbot_top_questions(PDO $pdo, int $limit = 5): array
{
    try {
        $st = $pdo->query("SELECT id, question FROM chatbot_faq WHERE actif = 1 ORDER BY priorite DESC, nb_consultations DESC LIMIT " . max(1, min(20, $limit)));
        $out = [];
        foreach ($st->fetchAll() ?: [] as $r) {
            $out[] = ['id' => (int) $r['id'], 'question' => (string) $r['question']];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function maire_chatbot_log_conversation(PDO $pdo, string $sessionToken, ?int $citoyenId, string $question, array $resp): void
{
    try {
        $st = $pdo->prepare('
            INSERT INTO chatbot_conversations (citoyen_id, session_token, question, reponse, faq_id, score)
            VALUES (:cid, :tok, :q, :r, :fid, :sc)
        ');
        $st->execute([
            'cid' => $citoyenId,
            'tok' => $sessionToken,
            'q' => mb_substr($question, 0, 4000),
            'r' => mb_substr((string) $resp['reponse'], 0, 4000),
            'fid' => $resp['faq_id'],
            'sc' => $resp['score'],
        ]);
    } catch (Throwable $e) {
        // tolérant
    }
}

function maire_chatbot_compteurs(PDO $pdo): array
{
    maire_ensure_chatbot_tables($pdo);
    $r = ['conversations' => 0, 'questions_repondues' => 0, 'taux_succes' => 0.0, 'top_faq' => []];
    try {
        $row = $pdo->query("SELECT COUNT(*) AS total, SUM(faq_id IS NOT NULL) AS ok FROM chatbot_conversations")->fetch();
        if ($row !== false) {
            $r['conversations'] = (int) $row['total'];
            $r['questions_repondues'] = (int) $row['ok'];
            $r['taux_succes'] = $r['conversations'] > 0 ? round(($r['questions_repondues'] / $r['conversations']) * 100, 1) : 0.0;
        }
        $top = $pdo->query("SELECT id, question, nb_consultations FROM chatbot_faq WHERE actif = 1 ORDER BY nb_consultations DESC LIMIT 5")->fetchAll();
        $r['top_faq'] = $top ?: [];
    } catch (Throwable $e) {
        // tolérant
    }
    return $r;
}

function maire_chatbot_session_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['chatbot_token'])) {
        $_SESSION['chatbot_token'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['chatbot_token'];
}
