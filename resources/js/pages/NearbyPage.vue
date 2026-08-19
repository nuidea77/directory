<script setup>
import { computed, defineAsyncComponent, onMounted, ref } from 'vue';
import { api } from '../api';

// Leaflet газрын зураг — lazy-load
const MapView = defineAsyncComponent(() => import('../components/MapView.vue'));

const state = ref('ask'); // ask | loading | ready | denied
const branches = ref([]);
const total = ref(0);
const coords = ref(null);
const radius = ref(2);
const query = ref('');
const openOnly = ref(false);
const rating45 = ref(false);
const district = ref('');
const selectedId = ref(null);
const mobileView = ref('list'); // list | map — мобайл дээр сэлгэнэ

// Газрын зургийн pin-үүд: бодит координаттай салбарууд
const mapMarkers = computed(() => branches.value
    .map((b, i) => ({ id: b.id, lat: b.lat, lng: b.lng, label: i + 1 }))
    .filter((m) => m.lat !== null && m.lat !== undefined));

const districts = ['Сүхбаатар', 'Чингэлтэй', 'Баянзүрх', 'Хан-Уул', 'Баянгол', 'Сонгинохайрхан'];
// Улаанбаатарын дүүргүүдийн ойролцоо төв цэгүүд (гараар сонгоход)
const districtCenters = {
    'Сүхбаатар': [47.9184, 106.9177], 'Чингэлтэй': [47.9221, 106.9155], 'Баянзүрх': [47.9102, 106.9541],
    'Хан-Уул': [47.8935, 106.9002], 'Баянгол': [47.9137, 106.8797], 'Сонгинохайрхан': [47.9146, 106.8156],
};

const selected = computed(() => branches.value.find((b) => b.id === selectedId.value));

function askLocation() {
    state.value = 'loading';
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            coords.value = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            state.value = 'ready';
            fetchNearby();
        },
        () => { state.value = 'denied'; },
        { timeout: 8000 },
    );
}

function pickDistrict(d) {
    district.value = d;
    const [lat, lng] = districtCenters[d];
    coords.value = { lat, lng };
    state.value = 'ready';
    fetchNearby();
}

async function fetchNearby() {
    if (!coords.value) return;
    const data = await api.get('/search', {
        q: query.value,
        lat: coords.value.lat,
        lng: coords.value.lng,
        radius: radius.value,
        open_now: openOnly.value ? 1 : undefined,
        rating: rating45.value ? 4.5 : undefined,
        sort: 'distance',
        per_page: 30,
    });
    branches.value = data.data;
    total.value = data.meta.total;
    selectedId.value = branches.value[0]?.id || null;
}


function setRadius(r) {
    radius.value = r;
    fetchNearby();
}

onMounted(() => {
    if (!navigator.geolocation) state.value = 'denied';
});
</script>

<template>
    <div class="bg-hero">
        <!-- Байршил зөвшөөрөх (6b) -->
        <div v-if="state !== 'ready'" class="mx-auto max-w-md px-5 py-16">
            <div class="card overflow-hidden !rounded-3xl shadow-xl">
                <div class="map-ph h-40 blur-[1.5px]"></div>
                <div class="p-6">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl border border-blueline bg-bluetint">
                        <div class="h-4 w-4 rounded-full border-[3px] border-brand"></div>
                    </div>
                    <h1 class="mt-4 text-[21px] font-extrabold leading-snug tracking-[-.02em] text-ink">Байршлаа хуваалцвал ойрын бизнесийг эрэмбэлж харуулна</h1>
                    <p class="mt-2.5 text-[13px] leading-relaxed text-soft">Зөвхөн хайлт хийх үед л ашиглана, хадгалахгүй. Дүүргээ гараар сонгож ч болно.</p>
                    <button class="btn-primary mt-4 w-full !rounded-[11px] !py-3.5" :disabled="state === 'loading'" @click="askLocation">
                        {{ state === 'loading' ? 'Байршил тогтоож байна…' : 'Байршил зөвшөөрөх' }}
                    </button>
                    <p v-if="state === 'denied'" class="mt-2.5 rounded-lg bg-redtint px-3 py-2 text-[12px] font-medium text-red">Байршил авч чадсангүй — доороос дүүргээ сонгоно уу.</p>
                    <div class="mt-4 border-t border-divider pt-4">
                        <div class="text-center text-[12px] font-medium text-mute">Дүүрэг гараар сонгох</div>
                        <div class="mt-2.5 grid grid-cols-2 gap-2">
                            <button v-for="d in districts" :key="d" class="btn-outline !py-2.5 !text-[12.5px]" @click="pickDistrict(d)">{{ d }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desktop үр дүн (6b доод хэсэг) -->
        <div v-else class="mx-auto max-w-7xl px-5 py-7 sm:px-10">
            <div class="card overflow-hidden !rounded-[14px]">
                <!-- Хайлтын мөр -->
                <div class="flex items-center gap-3 border-b border-line px-4 py-3">
                    <div class="flex h-[38px] flex-1 items-center gap-2.5 rounded-[9px] border border-searchline bg-search pl-3.5 pr-1.5">
                        <input v-model="query" type="search" placeholder="Юу хайх вэ?" class="w-24 flex-1 bg-transparent text-[13px] font-medium text-ink outline-none placeholder:text-ph" @keyup.enter="fetchNearby" />
                        <div class="h-[18px] w-px bg-[#dcdad4]"></div>
                        <div class="flex items-center gap-1.5 whitespace-nowrap px-2 text-[13px] font-semibold text-brand">
                            <div class="h-3 w-3 rounded-full border-2 border-brand"></div>Миний ойролцоо
                        </div>
                        <button class="cursor-pointer rounded-[7px] bg-brand px-4 py-1.5 text-[12.5px] font-semibold text-white" @click="fetchNearby">Хайх</button>
                    </div>
                </div>

                <!-- Шүүлтүүрийн мөр -->
                <div class="flex flex-wrap items-center gap-2.5 border-b border-line bg-panel px-4 py-2.5">
                    <span class="text-[11.5px] font-semibold text-body">Радиус</span>
                    <div class="flex gap-1.5 text-[11.5px] font-semibold">
                        <button v-for="r in [[0.5, '500 м'], [2, '2 км'], [5, '5 км'], [30, 'Бүх хот']]" :key="r[0]"
                            class="cursor-pointer rounded-[7px] px-2.5 py-1.5"
                            :class="radius === r[0] ? 'bg-brand text-white' : 'bg-chip text-chiptext'"
                            @click="setRadius(r[0])"
                        >{{ r[1] }}</button>
                    </div>
                    <div class="h-5 w-px bg-searchline"></div>
                    <button class="cursor-pointer rounded-[7px] border px-2.5 py-1.5 text-[11.5px] font-semibold" :class="openOnly ? 'border-blueline bg-bluetint text-brand' : 'border-searchline bg-white text-chiptext'" @click="openOnly = !openOnly; fetchNearby()">Одоо нээлттэй</button>
                    <button class="cursor-pointer rounded-[7px] border px-2.5 py-1.5 text-[11.5px] font-semibold" :class="rating45 ? 'border-blueline bg-bluetint text-brand' : 'border-searchline bg-white text-chiptext'" @click="rating45 = !rating45; fetchNearby()">4.5+</button>
                    <button class="cursor-pointer rounded-[7px] border border-searchline bg-white px-2.5 py-1.5 text-[11.5px] font-semibold text-chiptext sm:hidden" @click="mobileView = mobileView === 'list' ? 'map' : 'list'">
                        {{ mobileView === 'list' ? '🗺 Зураг' : '☰ Жагсаалт' }}
                    </button>
                    <span class="ml-auto whitespace-nowrap text-[11.5px] font-medium text-mute">
                        Байршил: {{ district || 'GPS' }} · <button class="cursor-pointer font-semibold text-brand" @click="state = 'ask'">Өөрчлөх</button>
                    </span>
                </div>

                <div class="flex h-[520px]">
                    <!-- Жагсаалт -->
                    <div class="w-full flex-col overflow-y-auto border-r border-line sm:flex sm:w-[266px]" :class="mobileView === 'list' ? 'flex' : 'hidden'">
                        <div class="flex items-baseline justify-between border-b border-divider px-3.5 py-3">
                            <span class="text-[12.5px] font-bold text-ink">{{ total }} бизнес · {{ radius >= 30 ? 'бүх хот' : radius + ' км' }}</span>
                            <span class="text-[11.5px] font-medium text-soft">Ойрхноос холуур эрэмбэлэв</span>
                        </div>
                        <router-link
                            v-for="(b, i) in branches"
                            :key="b.id"
                            :to="{ name: 'business', params: { slug: b.business.slug } }"
                            class="flex gap-2.5 border-b border-hairline px-3.5 py-3"
                            :class="{ 'bg-bluepale': b.id === selectedId }"
                            @mouseenter="selectedId = b.id"
                            @click="api.post(`/branches/${b.id}/event`, { type: 'view', source: 'map' }).catch(() => {})"
                        >
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold" :class="b.id === selectedId ? 'bg-brand text-white' : 'bg-bluetint text-brand'">{{ i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="text-[12.5px] font-bold text-ink">{{ b.business.name }}</div>
                                <div class="mt-0.5 text-[11px] text-mute">{{ b.business.subcategory || b.business.category?.name }} · {{ b.district }}</div>
                                <div class="mt-1.5 flex items-center gap-2 text-[11px] font-medium text-mute">
                                    <b class="text-ink">{{ b.rating_avg.toFixed(1) }}</b>
                                    <span class="font-mono">{{ b.distance_km }} км</span>
                                    <span class="font-semibold" :class="b.is_open ? 'text-green' : 'text-amberdark'">{{ b.is_open ? 'Нээлттэй' : b.open_label }}</span>
                                </div>
                            </div>
                        </router-link>
                        <div v-if="!branches.length" class="p-8 text-center text-[13px] text-mute">Энэ радиуст бизнес олдсонгүй. Радиусаа томсгоно уу.</div>
                    </div>

                    <!-- Зураглал (Leaflet + OpenStreetMap) -->
                    <div class="relative flex-1" :class="mobileView === 'map' ? 'block' : 'hidden sm:block'">
                        <MapView
                            :markers="mapMarkers"
                            :selected-id="selectedId"
                            :circle="coords && radius < 30 ? { lat: coords.lat, lng: coords.lng, radiusKm: radius } : null"
                            :center="coords ? { lat: coords.lat, lng: coords.lng } : null"
                            :zoom="radius <= 0.5 ? 15 : radius <= 2 ? 14 : radius <= 5 ? 12 : 11"
                            height="100%"
                            @select="(id) => (selectedId = id)"
                        />

                        <!-- Сонгосон салбарын карт -->
                        <div v-if="selected" class="absolute bottom-4 left-4 z-[500] w-[280px] rounded-[11px] bg-white p-3.5 shadow-xl">
                            <div class="flex items-center gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand text-[10px] font-bold text-white">{{ branches.indexOf(selected) + 1 }}</span>
                                <router-link :to="{ name: 'business', params: { slug: selected.business.slug } }" class="truncate text-[13.5px] font-bold text-ink hover:text-brand">{{ selected.business.name }}</router-link>
                                <span class="ml-auto font-mono text-[12px] font-bold text-ink">{{ selected.distance_km }} км</span>
                            </div>
                            <div class="mt-1.5 text-[11.5px] text-mute">{{ selected.address }}</div>
                            <div class="mt-2 flex items-center gap-2 text-[11.5px] font-medium">
                                <b class="text-ink">{{ selected.rating_avg.toFixed(1) }}</b>
                                <span class="font-semibold" :class="selected.is_open ? 'text-green' : 'text-amberdark'">{{ selected.open_label }}</span>
                            </div>
                            <div class="mt-2.5 flex gap-2 text-[11.5px] font-semibold">
                                <a :href="`tel:${selected.phone}`" class="flex-1 rounded-[7px] border border-inputline py-2 text-center text-ink">Залгах</a>
                                <a v-if="selected.lat" :href="`https://www.google.com/maps/dir/?api=1&destination=${selected.lat},${selected.lng}`" target="_blank" rel="noopener" class="flex-1 rounded-[7px] bg-brand py-2 text-center text-white">Зам заах</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
