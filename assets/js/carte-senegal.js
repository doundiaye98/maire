/**
 * Carte du Sénégal avec localisation de Rufisque-Est (Leaflet).
 * Fonds : plan (OSM) et satellite (Esri World Imagery).
 */
(function () {
    'use strict';

    function initMaireCarte() {
        var el = document.getElementById('carte-senegal-commune');
        var cfg = window.maireCarteConfig;

        if (!el) {
            return;
        }

        if (!cfg || typeof L === 'undefined') {
            el.innerHTML =
                '<p class="p-6 text-center text-sm text-slate-600">La carte n’a pas pu être chargée. Vérifiez votre connexion ou réessayez plus tard.</p>';
            return;
        }

        var senegalBounds = L.latLngBounds(
            [cfg.senegal.south, cfg.senegal.west],
            [cfg.senegal.north, cfg.senegal.east]
        );

        var rufisqueBounds = L.latLngBounds(
            [cfg.commune.lat - 0.04, cfg.commune.lng - 0.05],
            [cfg.commune.lat + 0.04, cfg.commune.lng + 0.05]
        );

        var map = L.map(el, {
            scrollWheelZoom: false,
            attributionControl: true,
        });

        var planLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        });

        var satelliteLayer = L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                maxZoom: 19,
                attribution:
                    'Imagerie &copy; <a href="https://www.esri.com/">Esri</a>, Maxar, Earthstar Geographics',
            }
        );

        var baseLayers = {
            Plan: planLayer,
            Satellite: satelliteLayer,
        };

        planLayer.addTo(map);

        L.control.layers(baseLayers, null, {
            position: 'topright',
            collapsed: false,
        }).addTo(map);

        var iconCommune = L.divIcon({
            className: 'maire-map-pin maire-map-pin--commune',
            html: '<span aria-hidden="true"></span>',
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });

        var iconMairie = L.divIcon({
            className: 'maire-map-pin maire-map-pin--mairie',
            html: '<span aria-hidden="true"></span>',
            iconSize: [22, 22],
            iconAnchor: [11, 11],
        });

        function popupHtml(point) {
            return (
                '<div class="maire-map-popup">' +
                '<strong>' + point.label + '</strong>' +
                '<p>' + point.detail + '</p>' +
                '<p class="maire-map-popup-coords">' + point.dms + '</p>' +
                '</div>'
            );
        }

        var commune = cfg.commune;
        var mairie = cfg.mairie;

        L.marker([commune.lat, commune.lng], { icon: iconCommune })
            .addTo(map)
            .bindPopup(popupHtml(commune));

        L.marker([mairie.lat, mairie.lng], { icon: iconMairie })
            .addTo(map)
            .bindPopup(popupHtml(mairie));

        L.circle([commune.lat, commune.lng], {
            radius: 2200,
            color: '#0c4a3e',
            fillColor: '#14b8a6',
            fillOpacity: 0.12,
            weight: 2,
            dashArray: '6 4',
        }).addTo(map);

        map.fitBounds(senegalBounds, { padding: [24, 24] });

        L.control.scale({ imperial: false }).addTo(map);

        map.on('baselayerchange', function (e) {
            if (e.name === 'Satellite') {
                map.fitBounds(rufisqueBounds, { padding: [32, 32], maxZoom: 14 });
            } else {
                map.fitBounds(senegalBounds, { padding: [24, 24] });
            }
            setTimeout(function () {
                map.invalidateSize();
            }, 80);
        });

        function refreshMapSize() {
            map.invalidateSize();
        }

        setTimeout(refreshMapSize, 50);
        setTimeout(refreshMapSize, 350);
        window.addEventListener('resize', refreshMapSize);

        if (typeof IntersectionObserver !== 'undefined') {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        refreshMapSize();
                    }
                });
            });
            observer.observe(el);
        }

        el.addEventListener('click', function () {
            map.scrollWheelZoom.enable();
        });
        map.on('mouseout', function () {
            map.scrollWheelZoom.disable();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMaireCarte);
    } else {
        initMaireCarte();
    }
})();
