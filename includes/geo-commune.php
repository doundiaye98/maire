<?php
declare(strict_types=1);

/**
 * Références géographiques — Commune de Rufisque-Est (Sénégal).
 * Centre communal : relation administrative OpenStreetMap « Commune de Rufisque Est ».
 * Siège mairie : quartier Castor / Arafat II (zone administrative communale).
 */
const MAIRE_GEO_COMMUNE_LAT = 14.7176516;
const MAIRE_GEO_COMMUNE_LNG = -17.2550986;
const MAIRE_GEO_MAIRIE_LAT = 14.7152568;
const MAIRE_GEO_MAIRIE_LNG = -17.2722748;

/** Limites approximatives du Sénégal (cadrage carte nationale). */
const MAIRE_GEO_SENEGAL_SOUTH = 12.31;
const MAIRE_GEO_SENEGAL_WEST = -17.55;
const MAIRE_GEO_SENEGAL_NORTH = 16.70;
const MAIRE_GEO_SENEGAL_EAST = -11.35;

/**
 * @return array{lat: float, lng: float, label: string, detail: string}
 */
function maire_geo_commune_centre(): array
{
    return [
        'lat' => MAIRE_GEO_COMMUNE_LAT,
        'lng' => MAIRE_GEO_COMMUNE_LNG,
        'label' => 'Commune de Rufisque-Est',
        'detail' => 'Centre communal — région de Dakar, département de Rufisque',
    ];
}

/**
 * @return array{lat: float, lng: float, label: string, detail: string}
 */
function maire_geo_mairie(): array
{
    return [
        'lat' => MAIRE_GEO_MAIRIE_LAT,
        'lng' => MAIRE_GEO_MAIRIE_LNG,
        'label' => 'Mairie de Rufisque-Est',
        'detail' => 'Castor, face pharmacie DIOR — Arafat II',
    ];
}

function maire_geo_format_dms(float $lat, float $lng): string
{
    $latHem = $lat >= 0 ? 'N' : 'S';
    $lngHem = $lng >= 0 ? 'E' : 'O';
    $lat = abs($lat);
    $lng = abs($lng);
    $latDeg = (int) floor($lat);
    $latMin = (int) floor(($lat - $latDeg) * 60);
    $latSec = round((($lat - $latDeg) * 60 - $latMin) * 60);
    $lngDeg = (int) floor($lng);
    $lngMin = (int) floor(($lng - $lngDeg) * 60);
    $lngSec = round((($lng - $lngDeg) * 60 - $lngMin) * 60);

    return sprintf(
        '%d°%02d′%02d″ %s, %d°%02d′%02d″ %s',
        $latDeg,
        $latMin,
        $latSec,
        $latHem,
        $lngDeg,
        $lngMin,
        $lngSec,
        $lngHem
    );
}
