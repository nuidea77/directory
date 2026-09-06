/**
 * Суурь газрын зургийн tile-ийн эх сурвалжийг .env-ээс шийднэ.
 *
 * Дараалал:
 *  1. VITE_MAP_TILES — дурын үйлчилгээний бүтэн URL (хамгийн уян хатан)
 *  2. VITE_MAPBOX_TOKEN — Mapbox Static Tiles (анхдагч хэв маяг: mapbox/light-v11,
 *     POI icon-гүй цэвэрхэн: барилга, зам, тээвэр, газрын нэр)
 *  3. Юу ч байхгүй бол OSM стандарт (түлхүүргүй, харин POI icon-той)
 */
export function resolveTileLayer(env = {}) {
    if (env.VITE_MAP_TILES) {
        return {
            url: env.VITE_MAP_TILES,
            attribution: env.VITE_MAP_ATTRIBUTION || '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            options: { maxZoom: 19, subdomains: 'abcd' },
            provider: 'custom',
        };
    }

    if (env.VITE_MAPBOX_TOKEN) {
        const style = (env.VITE_MAPBOX_STYLE || 'mapbox/light-v11').replace(/^\/+|\/+$/g, '');

        return {
            // Mapbox 512px tile өгдөг тул Leaflet-д tileSize 512 + zoomOffset -1 заавал
            url: `https://api.mapbox.com/styles/v1/${style}/tiles/512/{z}/{x}/{y}{r}?access_token=${encodeURIComponent(env.VITE_MAPBOX_TOKEN)}`,
            // Mapbox-ийн нөхцлөөр attribution + «Improve this map» холбоос заавал
            attribution: '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> <a href="https://www.mapbox.com/map-feedback/" target="_blank" rel="noopener">Improve this map</a>',
            options: { maxZoom: 19, tileSize: 512, zoomOffset: -1 },
            provider: 'mapbox',
        };
    }

    return {
        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        options: { maxZoom: 19 },
        provider: 'osm',
    };
}
