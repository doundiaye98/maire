/*
 * Arrière-plan 3D pour le site de Rufisque-Est.
 *
 * Objectif : suggérer modernité et profondeur sans imposer.
 * Implémentation : Canvas 2D + projection perspective maison, aucune dépendance.
 *
 * Comportement :
 *   - Polyèdres low-poly (octaèdres + tétraèdres) flottant en 3D, tournant lentement
 *   - Palette synchronisée avec le thème (clair/sombre)
 *   - Léger parallaxe à la souris
 *   - Respect strict de prefers-reduced-motion (rendu d'une image statique)
 *   - Mise en pause quand l'onglet n'est pas visible
 *   - Réactivité au resize, prise en charge du devicePixelRatio
 *   - z-index : -1 fixed (sous le contenu mais au-dessus du fond html)
 */
(function () {
    'use strict';

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }
    if (document.documentElement.dataset.maireBg3d === 'on') {
        return; // déjà initialisé
    }
    document.documentElement.dataset.maireBg3d = 'on';

    var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var canvas = document.createElement('canvas');
    canvas.className = 'maire-bg3d';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.setAttribute('role', 'presentation');

    if (document.body) {
        document.body.insertBefore(canvas, document.body.firstChild);
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            document.body.insertBefore(canvas, document.body.firstChild);
        });
    }

    var ctx = canvas.getContext('2d', { alpha: true });
    if (!ctx) {
        return;
    }

    var W = 0;
    var H = 0;
    var dpr = Math.max(1, Math.min(2, window.devicePixelRatio || 1));

    function resize() {
        W = window.innerWidth;
        H = window.innerHeight;
        canvas.width = Math.floor(W * dpr);
        canvas.height = Math.floor(H * dpr);
        canvas.style.width = W + 'px';
        canvas.style.height = H + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    resize();

    var resizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(resize, 120);
    }, { passive: true });

    var PALETTES = {
        light: {
            faces: [
                'rgba(12, 74, 62, 0.20)',     // primary
                'rgba(217, 119, 6, 0.20)',    // accent
                'rgba(5, 46, 38, 0.18)',      // primary-dark
                'rgba(245, 183, 61, 0.22)'    // accent-bright
            ],
            stroke: 'rgba(217, 119, 6, 0.55)',
            glowFar: 'rgba(12, 74, 62, 0.05)'
        },
        dark: {
            faces: [
                'rgba(245, 183, 61, 0.22)',
                'rgba(217, 119, 6, 0.20)',
                'rgba(255, 218, 145, 0.18)',
                'rgba(255, 255, 255, 0.10)'
            ],
            stroke: 'rgba(245, 183, 61, 0.55)',
            glowFar: 'rgba(245, 183, 61, 0.05)'
        }
    };

    function currentPalette() {
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return dark ? PALETTES.dark : PALETTES.light;
    }

    var OCTA = {
        verts: [
            [1, 0, 0], [-1, 0, 0],
            [0, 1, 0], [0, -1, 0],
            [0, 0, 1], [0, 0, -1]
        ],
        faces: [
            [0, 2, 4], [2, 1, 4], [1, 3, 4], [3, 0, 4],
            [2, 0, 5], [1, 2, 5], [3, 1, 5], [0, 3, 5]
        ]
    };

    var TETRA = {
        verts: [
            [1, 1, 1], [1, -1, -1], [-1, 1, -1], [-1, -1, 1]
        ],
        faces: [
            [0, 1, 2], [0, 3, 1], [0, 2, 3], [1, 3, 2]
        ]
    };

    function rand(min, max) {
        return min + Math.random() * (max - min);
    }

    var SHAPE_COUNT = 18;
    var shapes = [];
    for (var i = 0; i < SHAPE_COUNT; i++) {
        var def = Math.random() < 0.65 ? OCTA : TETRA;
        shapes.push({
            def: def,
            x: rand(-700, 700),
            y: rand(-500, 500),
            z: rand(-350, 350),
            vx: rand(-0.18, 0.18),
            vy: rand(-0.12, 0.12),
            vz: rand(-0.08, 0.08),
            rx: Math.random() * Math.PI * 2,
            ry: Math.random() * Math.PI * 2,
            rz: Math.random() * Math.PI * 2,
            drx: rand(-0.0035, 0.0035),
            dry: rand(-0.0045, 0.0045),
            drz: rand(-0.0025, 0.0025),
            scale: rand(38, 110),
            colorIndex: i % 4
        });
    }

    var mouseX = 0;
    var mouseY = 0;
    var targetMouseX = 0;
    var targetMouseY = 0;
    window.addEventListener('mousemove', function (e) {
        targetMouseX = (e.clientX / W - 0.5) * 2;
        targetMouseY = (e.clientY / H - 0.5) * 2;
    }, { passive: true });

    var FOCAL = 750;

    function rotate(v, rx, ry, rz) {
        var sx = Math.sin(rx), cx = Math.cos(rx);
        var sy = Math.sin(ry), cy = Math.cos(ry);
        var sz = Math.sin(rz), cz = Math.cos(rz);
        var x = v[0], y = v[1], z = v[2];
        // X
        var y1 = y * cx - z * sx;
        var z1 = y * sx + z * cx;
        // Y
        var x2 = x * cy + z1 * sy;
        var z2 = -x * sy + z1 * cy;
        // Z
        var x3 = x2 * cz - y1 * sz;
        var y3 = x2 * sz + y1 * cz;
        return [x3, y3, z2];
    }

    var running = true;
    var firstFrameDrawn = false;

    function frame() {
        ctx.clearRect(0, 0, W, H);

        var palette = currentPalette();
        var faceColors = palette.faces;
        var strokeColor = palette.stroke;

        mouseX += (targetMouseX - mouseX) * 0.05;
        mouseY += (targetMouseY - mouseY) * 0.05;

        var cx = W / 2;
        var cy = H / 2;
        var renderQueue = [];

        for (var i = 0; i < shapes.length; i++) {
            var s = shapes[i];

            if (!prefersReducedMotion) {
                s.rx += s.drx;
                s.ry += s.dry;
                s.rz += s.drz;
                s.x += s.vx;
                s.y += s.vy;
                s.z += s.vz;

                if (s.x > 800) { s.x = -800; }
                if (s.x < -800) { s.x = 800; }
                if (s.y > 600) { s.y = -600; }
                if (s.y < -600) { s.y = 600; }
                if (s.z > 400) { s.z = -400; }
                if (s.z < -400) { s.z = 400; }
            }

            var parallaxFactor = (s.z + 400) / 800; // 0 (loin) → 1 (proche)
            var px = mouseX * 35 * (0.4 + 0.6 * parallaxFactor);
            var py = mouseY * 25 * (0.4 + 0.6 * parallaxFactor);

            var verts2d = new Array(s.def.verts.length);
            for (var v = 0; v < s.def.verts.length; v++) {
                var r = rotate(s.def.verts[v], s.rx, s.ry, s.rz);
                var wx = r[0] * s.scale + s.x + px;
                var wy = r[1] * s.scale + s.y + py;
                var wz = r[2] * s.scale + s.z;
                var depth = FOCAL + wz + 400;
                if (depth < 50) { depth = 50; }
                var f = FOCAL / depth;
                verts2d[v] = {
                    x: wx * f + cx,
                    y: wy * f + cy,
                    z: wz
                };
            }

            for (var fi = 0; fi < s.def.faces.length; fi++) {
                var face = s.def.faces[fi];
                var a = verts2d[face[0]];
                var b = verts2d[face[1]];
                var c = verts2d[face[2]];

                // Backface culling (sens trigonométrique en repère écran)
                var cross = (b.x - a.x) * (c.y - a.y) - (b.y - a.y) * (c.x - a.x);
                if (cross >= 0) { continue; }

                var avgZ = (a.z + b.z + c.z) / 3;
                renderQueue.push({
                    a: a, b: b, c: c,
                    avgZ: avgZ,
                    fill: faceColors[s.colorIndex],
                    stroke: strokeColor
                });
            }
        }

        renderQueue.sort(function (u, v) { return v.avgZ - u.avgZ; });

        for (var q = 0; q < renderQueue.length; q++) {
            var f = renderQueue[q];
            ctx.beginPath();
            ctx.moveTo(f.a.x, f.a.y);
            ctx.lineTo(f.b.x, f.b.y);
            ctx.lineTo(f.c.x, f.c.y);
            ctx.closePath();
            ctx.fillStyle = f.fill;
            ctx.fill();
            ctx.lineWidth = 0.7;
            ctx.strokeStyle = f.stroke;
            ctx.stroke();
        }

        firstFrameDrawn = true;

        if (running && !prefersReducedMotion) {
            requestAnimationFrame(frame);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { requestAnimationFrame(frame); });
    } else {
        requestAnimationFrame(frame);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            running = false;
        } else if (!running && !prefersReducedMotion) {
            running = true;
            requestAnimationFrame(frame);
        }
    });

    // Repeindre quand le thème change (clair ↔ sombre) — utile en mode reduced-motion.
    var themeObserver = new MutationObserver(function () {
        if (prefersReducedMotion && firstFrameDrawn) {
            requestAnimationFrame(frame);
        }
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();
