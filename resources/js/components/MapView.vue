<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import mapboxgl from 'mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';
import { resolveMapSource } from '../data/mapTiles';

/**
 * Mapbox GL JS газрын зураг (token байхгүй бол OSM raster руу буцна).
 *  - markers: [{ id, lat, lng, label? }] — дугаарласан pin-үүд
 *  - selectedId: тодруулах marker
 *  - picker: true бол дарж/чирж байршил сонгоно (@pick { lat, lng })
 *  - circle: { lat, lng, radiusKm } — радиусын тойрог
 *
 * Mapbox хэв маяг дээр VITE_MAP_HIDE_LAYERS (анхдагч poi-label) давхаргыг
 * нууна — дэлгүүр/ресторан шошго алга болж, барилга, зам, тээвэр үлдэнэ.
 */
const props = defineProps({
    markers: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    center: { type: Object, default: null }, // { lat, lng }
    zoom: { type: Number, default: 13 },
    picker: { type: Boolean, default: false },
    circle: { type: Object, default: null }, // { lat, lng, radiusKm }
    height: { type: String, default: '170px' },
});

const emit = defineEmits(['pick', 'select']);

const el = ref(null);
const failed = ref(false);
let map = null;
let pinMarkers = [];
let pickMarker = null;
let resizeObserver = null;
let styleReady = false;

const UB = { lat: 47.9184, lng: 106.9177 };
const SOURCE = resolveMapSource(import.meta.env);

function pinElement(label, selected) {
    const div = document.createElement('div');
    div.style.cssText = `display:flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;
        background:${selected ? '#0e8f52' : '#ffffff'};color:${selected ? '#ffffff' : '#0e8f52'};
        border:2px solid #0e8f52;font:700 11px Manrope,sans-serif;box-shadow:0 2px 6px rgba(22,24,28,.25);cursor:pointer`;
    div.textContent = label ?? '';
    return div;
}

// Тойргийг GeoJSON олон өнцөгт болгоно (метр → градус)
function circleGeoJson(lat, lng, radiusKm, steps = 64) {
    const coords = [];
    const dLat = radiusKm / 110.574;
    const dLng = radiusKm / (111.32 * Math.cos((lat * Math.PI) / 180));

    for (let i = 0; i <= steps; i++) {
        const a = (i / steps) * 2 * Math.PI;
        coords.push([lng + dLng * Math.cos(a), lat + dLat * Math.sin(a)]);
    }

    return { type: 'Feature', geometry: { type: 'Polygon', coordinates: [coords] } };
}

function renderMarkers() {
    if (!map) return;
    pinMarkers.forEach((m) => m.remove());
    pinMarkers = [];

    props.markers
        .filter((m) => m.lat !== null && m.lng !== null && m.lat !== undefined)
        .forEach((m, i) => {
            const element = pinElement(m.label ?? i + 1, m.id === props.selectedId);
            element.addEventListener('click', (e) => { e.stopPropagation(); emit('select', m.id); });
            pinMarkers.push(new mapboxgl.Marker({ element, anchor: 'center' }).setLngLat([m.lng, m.lat]).addTo(map));
        });
}

function renderCircle() {
    if (!map || !styleReady) return;
    const data = props.circle?.lat
        ? circleGeoJson(props.circle.lat, props.circle.lng, props.circle.radiusKm)
        : { type: 'FeatureCollection', features: [] };

    if (map.getSource('radius')) {
        map.getSource('radius').setData(data);
        return;
    }

    map.addSource('radius', { type: 'geojson', data });
    map.addLayer({ id: 'radius-fill', type: 'fill', source: 'radius', paint: { 'fill-color': '#0e8f52', 'fill-opacity': 0.06 } });
    map.addLayer({ id: 'radius-line', type: 'line', source: 'radius', paint: { 'line-color': '#0e8f52', 'line-width': 1.5 } });
}

function setPickMarker(lat, lng) {
    if (pickMarker) {
        pickMarker.setLngLat([lng, lat]);
        return;
    }

    pickMarker = new mapboxgl.Marker({ element: pinElement('●', true), anchor: 'center', draggable: true })
        .setLngLat([lng, lat])
        .addTo(map);

    pickMarker.on('dragend', () => {
        const p = pickMarker.getLngLat();
        emit('pick', { lat: +p.lat.toFixed(6), lng: +p.lng.toFixed(6) });
    });
}

function fitView() {
    const pts = props.markers.filter((m) => m.lat !== null && m.lat !== undefined).map((m) => [m.lng, m.lat]);

    if (props.center?.lat) {
        map.jumpTo({ center: [props.center.lng, props.center.lat], zoom: props.zoom });
    } else if (pts.length > 1) {
        const bounds = pts.reduce((b, p) => b.extend(p), new mapboxgl.LngLatBounds(pts[0], pts[0]));
        map.fitBounds(bounds, { padding: 48, maxZoom: 15, duration: 0 });
    } else if (pts.length === 1) {
        map.jumpTo({ center: pts[0], zoom: props.zoom });
    } else {
        map.jumpTo({ center: [UB.lng, UB.lat], zoom: 12 });
    }
}

// Mapbox хэв маяг: POI шошгыг нууж, шошгыг монголоор (name_mn → name) харуулна
function tidyStyle() {
    if (SOURCE.provider !== 'mapbox') return;

    for (const layer of map.getStyle().layers || []) {
        if (SOURCE.hideLayers.some((h) => layer.id === h || layer.id.startsWith(h))) {
            map.setLayoutProperty(layer.id, 'visibility', 'none');
            continue;
        }

        if (layer.type === 'symbol' && layer.layout && layer.layout['text-field']) {
            map.setLayoutProperty(layer.id, 'text-field', ['coalesce', ['get', 'name_mn'], ['get', 'name']]);
        }
    }
}

onMounted(() => {
    try {
        if (SOURCE.token) mapboxgl.accessToken = SOURCE.token;

        map = new mapboxgl.Map({
            container: el.value,
            style: SOURCE.style,
            center: [UB.lng, UB.lat],
            zoom: 12,
            scrollZoom: false,
            attributionControl: true,
            // Монголын хэрэглэгчдэд Mapbox-ийн telemetry шаардлагагүй
            trackResize: true,
        });
    } catch {
        failed.value = true;
        return;
    }

    // Тест/дебаг хийхэд хялбар байхаар container дээр хадгална
    el.value.__map = map;

    map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-left');

    map.on('style.load', () => {
        styleReady = true;
        tidyStyle();
        renderCircle();
    });

    map.on('error', (e) => {
        // Token буруу/хугацаа дууссан гэх мэт — консолд л мэдэгдэнэ, хуудас эвдрэхгүй
        if (e?.error?.status === 401 || e?.error?.status === 403) failed.value = true;
    });

    if (props.picker) {
        map.on('click', (e) => {
            setPickMarker(e.lngLat.lat, e.lngLat.lng);
            emit('pick', { lat: +e.lngLat.lat.toFixed(6), lng: +e.lngLat.lng.toFixed(6) });
        });

        if (props.center?.lat) setPickMarker(props.center.lat, props.center.lng);
    }

    fitView();
    renderMarkers();

    // Таб/алхам солигдож харагдах болоход хэмжээгээ дахин тооцно
    resizeObserver = new ResizeObserver(() => map?.resize());
    resizeObserver.observe(el.value);
});

watch(() => [props.markers, props.selectedId], renderMarkers, { deep: true });
watch(() => props.circle, renderCircle, { deep: true });

watch(
    () => props.center,
    (c) => {
        if (!map || !c?.lat) return;
        map.easeTo({ center: [c.lng, c.lat], zoom: props.zoom, duration: 500 });
        if (props.picker) setPickMarker(c.lat, c.lng);
    },
    { deep: true },
);

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    map?.remove();
});
</script>

<template>
    <div class="relative z-0 w-full" :style="{ height }">
        <div ref="el" class="h-full w-full"></div>
        <div v-if="failed" class="absolute inset-0 flex items-center justify-center bg-panel p-4 text-center text-[12.5px] text-mute">
            Газрын зураг ачаалагдсангүй — Mapbox token-оо шалгана уу.
        </div>
    </div>
</template>
