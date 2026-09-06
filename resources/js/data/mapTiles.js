/**
 * Газрын зургийн эх сурвалжийг .env-ээс шийднэ (Mapbox GL JS).
 *
 *  - VITE_MAPBOX_TOKEN байвал Mapbox-ийн vector хэв маяг (VITE_MAPBOX_STYLE,
 *    анхдагч mapbox/light-v11). POI давхаргыг кодоор нууж болно.
 *  - Token байхгүй бол raster fallback: VITE_MAP_TILES (дурын) эсвэл OSM стандарт.
 *    Raster дээр POI icon-ыг арилгах боломжгүй.
 */
export function resolveMapSource(env = {}) {
    if (env.VITE_MAPBOX_TOKEN) {
        const style = (env.VITE_MAPBOX_STYLE || 'mapbox/light-v11').replace(/^\/+|\/+$/g, '');

        return {
            provider: 'mapbox',
            token: env.VITE_MAPBOX_TOKEN,
            style: `mapbox://styles/${style}`,
            hideLayers: splitList(env.VITE_MAP_HIDE_LAYERS, ['poi-label']),
        };
    }

    const tiles = env.VITE_MAP_TILES
        ? env.VITE_MAP_TILES.replace('{s}', 'a').replace('{r}', '')
        : 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

    return {
        provider: env.VITE_MAP_TILES ? 'custom' : 'osm',
        token: null,
        hideLayers: [],
        style: {
            version: 8,
            sources: {
                base: {
                    type: 'raster',
                    tiles: [tiles],
                    tileSize: 256,
                    maxzoom: 19,
                    attribution: env.VITE_MAP_ATTRIBUTION
                        || '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                },
            },
            layers: [{ id: 'base', type: 'raster', source: 'base' }],
        },
    };
}

function splitList(value, fallback) {
    if (value === undefined || value === null) return fallback;
    const list = String(value).split(',').map((s) => s.trim()).filter(Boolean);
    return list;
}
