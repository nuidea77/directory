<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Leaflet + OpenStreetMap газрын зураг (key шаардлагагүй, үнэгүй).
 *  - markers: [{ id, lat, lng, label? }] — дугаарласан pin-үүд
 *  - selectedId: тодруулах marker
 *  - picker: true бол дарж/чирж байршил сонгоно (@pick { lat, lng })
 *  - circle: { lat, lng, radiusKm } — радиусын тойрог
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
let map = null;
let layerGroup = null;
let pickMarker = null;
let circleLayer = null;

const UB = { lat: 47.9184, lng: 106.9177 };

function pinIcon(label, selected) {
    return L.divIcon({
        className: '',
        html: `<div style="display:flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;
            background:${selected ? '#1f5fa8' : '#ffffff'};color:${selected ? '#ffffff' : '#1f5fa8'};
            border:2px solid #1f5fa8;font:700 11px Manrope,sans-serif;box-shadow:0 2px 6px rgba(22,24,28,.25)">${label ?? ''}</div>`,
        iconSize: [26, 26],
        iconAnchor: [13, 13],
    });
}

function render() {
    if (!map) return;
    layerGroup.clearLayers();

    props.markers
        .filter((m) => m.lat !== null && m.lng !== null && m.lat !== undefined)
        .forEach((m, i) => {
            const marker = L.marker([m.lat, m.lng], { icon: pinIcon(m.label ?? i + 1, m.id === props.selectedId) });
            marker.on('click', () => emit('select', m.id));
            layerGroup.addLayer(marker);
        });

    if (circleLayer) {
        circleLayer.remove();
        circleLayer = null;
    }

    if (props.circle?.lat) {
        circleLayer = L.circle([props.circle.lat, props.circle.lng], {
            radius: props.circle.radiusKm * 1000,
            color: '#1f5fa8',
            weight: 1.5,
            fillColor: '#1f5fa8',
            fillOpacity: 0.06,
        }).addTo(map);
    }
}

function setPickMarker(lat, lng) {
    if (pickMarker) {
        pickMarker.setLatLng([lat, lng]);
        return;
    }

    pickMarker = L.marker([lat, lng], { icon: pinIcon('●', true), draggable: true }).addTo(map);
    pickMarker.on('dragend', () => {
        const p = pickMarker.getLatLng();
        emit('pick', { lat: +p.lat.toFixed(6), lng: +p.lng.toFixed(6) });
    });
}

function fitView() {
    const pts = props.markers.filter((m) => m.lat !== null && m.lat !== undefined).map((m) => [m.lat, m.lng]);

    if (props.center?.lat) {
        map.setView([props.center.lat, props.center.lng], props.zoom);
    } else if (pts.length > 1) {
        map.fitBounds(L.latLngBounds(pts).pad(0.25));
    } else if (pts.length === 1) {
        map.setView(pts[0], props.zoom);
    } else {
        map.setView([UB.lat, UB.lng], 12);
    }
}

onMounted(() => {
    map = L.map(el.value, { attributionControl: true, scrollWheelZoom: false });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    layerGroup = L.layerGroup().addTo(map);

    if (props.picker) {
        map.on('click', (e) => {
            setPickMarker(e.latlng.lat, e.latlng.lng);
            emit('pick', { lat: +e.latlng.lat.toFixed(6), lng: +e.latlng.lng.toFixed(6) });
        });

        if (props.center?.lat) setPickMarker(props.center.lat, props.center.lng);
    }

    fitView();
    render();
});

watch(() => [props.markers, props.selectedId, props.circle], render, { deep: true });

watch(
    () => props.center,
    (c) => {
        if (!map || !c?.lat) return;
        map.setView([c.lat, c.lng], props.zoom);
        if (props.picker) setPickMarker(c.lat, c.lng);
    },
    { deep: true },
);

onBeforeUnmount(() => map?.remove());
</script>

<template>
    <div ref="el" class="z-0 w-full" :style="{ height }"></div>
</template>
