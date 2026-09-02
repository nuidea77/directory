<script setup>
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../api';
import ImagePh from '../components/ImagePh.vue';

// Leaflet-тэй тул lazy-load — жагсаалтаар үзэхэд bundle ачаалахгүй
const MapView = defineAsyncComponent(() => import('../components/MapView.vue'));
import CategoryIcon from '../components/CategoryIcon.vue';
import VerifiedBadge from '../components/VerifiedBadge.vue';
import BizLogo from '../components/BizLogo.vue';
import AmenityIcon from '../components/AmenityIcon.vue';
import PaymentBadge from '../components/PaymentBadge.vue';
import { flattenCategories, optionLabel } from '../utils/categories';

const route = useRoute();
const router = useRouter();

const category = ref(null);
const catStats = ref(null);
const ancestors = ref([]);
const categories = ref([]);
const categoryOptions = computed(() => flattenCategories(categories.value));

// Сонгосон дэд ангилал (2-р түвшин) — 3-р түвшний chip-үүдийг үүнээс гаргана
const activeSub = computed(() => {
    const kids = category.value?.children || [];
    return kids.find((c) => c.slug === filters.value.sub)
        || kids.find((c) => (c.children || []).some((g) => g.slug === filters.value.sub))
        || null;
});
const subChildren = computed(() => activeSub.value?.children || []);

// Chip дээрх тоо: өөрийн + дэд ангиллуудынх (жагсаалт тэднийг ч харуулдаг)
function subTotal(cat) {
    return (cat.businesses_count || 0) + (cat.children || []).reduce((s, c) => s + subTotal(c), 0);
}
const branches = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
// Backend хайлтын мөрийг хэрхэн ойлгосон (ангилал, дүүрэг, үсгийн залруулга)
const parsed = ref(null);
const loading = ref(true);
const loadError = ref('');

// «shudnii emneleg bayanzurh» → «Шүдний эмнэлэг · Баянзүрх» гэж ойлгосноо харуулна
const searchHint = computed(() => {
    const p = parsed.value;
    if (!p || !filters.value.q) return null;
    const parts = [...(p.categories || [])];
    if (p.district) parts.push(p.district);
    else if (p.city) parts.push(p.city);
    else if (p.place) parts.push(p.place);
    if (!parts.length) return null;
    return { parts, corrected: Object.keys(p.corrections || {}).length > 0 };
});

// Байршил, үйлчилгээний жагсаалт API-аас
const locations = ref([]);
const amenityOptions = ref([]); // [{ name, icon }] — идэвхтэй ангиллын сан
const paymentOptions = ref([]); // [{ slug, name }] — зээлийн аппууд

// Шүүлтүүрт үзүүлэх amenity сан — сонгосон ангиллаас хамаарна
const activeCategorySlug = computed(() => filters.value.sub || (isCategory.value ? route.params.slug : filters.value.category) || '');

let amenityReq = 0;

async function fetchAmenityOptions() {
    const slug = activeCategorySlug.value;
    const token = ++amenityReq;
    try {
        const res = await api.get('/amenities', slug ? { category: slug } : {});
        if (token !== amenityReq) return; // хожуу ирсэн хуучин хариуг үл тоомсорлоно

        const list = res.data || [];
        // Сонгосон онцлог шинэ ангиллын санд байхгүй бол ч харагдаж, буцааж
        // болиулах боломжтой байх ёстой (үгүй бол далд шүүлтүүр үлдэнэ)
        const active = filters.value.amenity;
        amenityOptions.value = active && !list.some((a) => a.name === active)
            ? [...list, { name: active, icon: 'settings' }]
            : list;
    } catch {
        /* шүүлтүүрийн жагсаалтгүйгээр үргэлжилнэ */
    }
}

// Хот сонгоогүй үед («Бүх байршил») дүүргийн жагсаалтыг Улаанбаатараар харуулна
const districtCity = computed(() => filters.value.city || 'Улаанбаатар');
const districts = computed(() => locations.value.find((l) => l.city === districtCity.value)?.districts || []);

// Дүүрэг сонгоход аль хотынх нь болох нь тодорхой байх ёстой
function toggleDistrict(d) {
    if (filters.value.district === d) {
        filters.value.district = '';
    } else {
        filters.value.district = d;
        if (!filters.value.city) filters.value.city = districtCity.value;
    }
    apply();
}

const filters = ref({
    q: '',
    category: '',
    city: '',
    district: '',
    price: '',
    rating: '',
    open_now: false,
    open_24_7: false,
    verified: false,
    amenity: '',
    sub: '',
    sort: 'rating',
    page: 1,
});

// Жагсаалт / газрын зураг таб (URL-д хадгалагдана)
const view = ref('list');

// «Миний ойролцоо» — байршил авмагц зайгаар эрэмбэлж, радиусаар шүүнэ
const coords = ref(null);
const radius = ref(2);
const locating = ref(false);
const locationError = ref('');
const selectedId = ref(null);

const nearMode = computed(() => coords.value !== null);
const selected = computed(() => branches.value.find((b) => b.id === selectedId.value));

// Газрын зурагт координаттай салбарууд
const mapMarkers = computed(() => branches.value
    .map((b, i) => ({ id: b.id, lat: b.lat, lng: b.lng, label: i + 1 }))
    .filter((m) => m.lat !== null && m.lat !== undefined));

function askLocation() {
    locationError.value = '';

    if (!navigator.geolocation) {
        locationError.value = 'Таны хөтөч байршил дэмжихгүй байна.';
        return;
    }

    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            coords.value = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            locating.value = false;
            view.value = 'map';
            filters.value.sort = 'distance';
            apply();
        },
        () => {
            locating.value = false;
            locationError.value = 'Байршил авах боломжгүй байна. Хөтчийн зөвшөөрлөө шалгана уу.';
        },
        { timeout: 8000 },
    );
}

function clearLocation() {
    coords.value = null;
    locationError.value = '';
    if (filters.value.sort === 'distance') filters.value.sort = 'rating';
    apply();
}

function setRadius(r) {
    radius.value = r;
    apply();
}

const isCategory = computed(() => route.name === 'category');

// Одоогийн хуудсыг тойрсон 5 хуудасны цонх
const pageWindow = computed(() => {
    const last = meta.value.last_page;
    const start = Math.max(1, Math.min(meta.value.current_page - 2, last - 4));
    return Array.from({ length: Math.min(5, last) }, (_, i) => start + i);
});

function syncFromRoute() {
    view.value = route.query.view === 'map' ? 'map' : 'list';

    filters.value = {
        q: route.query.q || '',
        category: route.query.category || '',
        city: route.query.city || '',
        district: route.query.district || '',
        price: route.query.price || '',
        rating: route.query.rating || '',
        open_now: route.query.open_now === '1',
        open_24_7: route.query.open_24_7 === '1',
        verified: route.query.verified === '1',
        amenity: route.query.amenity || '',
        payment: route.query.payment || '',
        sub: route.query.sub || '',
        sort: route.query.sort || 'rating',
        page: Number(route.query.page) || 1,
    };
}

async function fetchCategory() {
    category.value = null;
    catStats.value = null;
    ancestors.value = [];

    if (isCategory.value) {
        const data = await api.get(`/categories/${route.params.slug}`);
        category.value = data.data;
        catStats.value = data.stats;
        ancestors.value = data.ancestors || [];
        document.title = `${category.value.name} | Ойрхон.mn`;
    }
}

async function fetchResults() {
    loading.value = true;
    loadError.value = '';
    try {
        const data = await api.get('/search', {
            q: filters.value.q,
            category: filters.value.sub || (isCategory.value ? route.params.slug : filters.value.category),
            city: filters.value.city,
            district: filters.value.district,
            price: filters.value.price,
            rating: filters.value.rating,
            open_now: filters.value.open_now ? 1 : undefined,
            open_24_7: filters.value.open_24_7 ? 1 : undefined,
            verified: filters.value.verified ? 1 : undefined,
            amenity: filters.value.amenity,
            payment: filters.value.payment,
            sort: filters.value.sort,
            page: filters.value.page,
            // «Ойролцоо» горимд зайгаар шүүж, эрэмбэлнэ
            lat: coords.value?.lat,
            lng: coords.value?.lng,
            radius: coords.value ? radius.value : undefined,
            per_page: view.value === 'map' ? 50 : undefined,
        });
        branches.value = data.data;
        meta.value = data.meta;
        parsed.value = data.parsed || null;
        selectedId.value = branches.value[0]?.id || null;
    } catch {
        loadError.value = 'Илэрц ачаалахад алдаа гарлаа.';
    } finally {
        loading.value = false;
    }
}

function apply(page = 1) {
    filters.value.page = page;
    router.push({
        name: route.name,
        params: route.params,
        query: {
            q: filters.value.q || undefined,
            category: !isCategory.value ? filters.value.category || undefined : undefined,
            city: filters.value.city || undefined,
            district: filters.value.district || undefined,
            price: filters.value.price || undefined,
            rating: filters.value.rating || undefined,
            open_now: filters.value.open_now ? '1' : undefined,
            open_24_7: filters.value.open_24_7 ? '1' : undefined,
            verified: filters.value.verified ? '1' : undefined,
            view: view.value === 'map' ? 'map' : undefined,
            amenity: filters.value.amenity || undefined,
            payment: filters.value.payment || undefined,
            sub: filters.value.sub || undefined,
            sort: filters.value.sort !== 'rating' ? filters.value.sort : undefined,
            page: page > 1 ? page : undefined,
        },
    });
}

function clearFilters() {
    filters.value = { ...filters.value, category: '', city: '', district: '', price: '', rating: '', open_now: false, open_24_7: false, amenity: '', payment: '', sub: '' };
    apply();
}

function stars(rating) {
    return '★'.repeat(Math.round(rating)) + '☆'.repeat(5 - Math.round(rating));
}

function trackView(branch) {
    api.post(`/branches/${branch.id}/event`, { type: 'view', source: isCategory.value ? 'category' : 'search' }).catch(() => {});
}


async function loadAll() {
    // Ангилал/байршлын жагсаалт унавал ч илэрцээ харуулна — хуудас бүхэлдээ унахгүй
    try {
        const [cats, locs] = await Promise.all([api.get('/categories'), api.get('/locations')]);
        categories.value = cats.data;
        locations.value = locs.data;
        paymentOptions.value = locs.payments || [];
    } catch {
        /* шүүлтүүрийн жагсаалтгүйгээр үргэлжилнэ */
    }
    await Promise.all([fetchCategory().catch(() => {}), fetchResults(), fetchAmenityOptions()]);
}

watch(() => route.fullPath, async () => {
    syncFromRoute();
    await Promise.all([fetchCategory().catch(() => {}), fetchResults()]);
});

// Ангилал солигдоход тохирох amenity сангаа шинэчилнэ
watch(activeCategorySlug, fetchAmenityOptions);

onMounted(async () => {
    syncFromRoute();
    await loadAll();

    // /nearby-аас чиглүүлж ирвэл байршлыг шууд асууна
    if (route.query.near === '1' && !coords.value) askLocation();
});
</script>

<template>
    <div class="bg-white">
        <!-- Breadcrumb -->
        <div class="mx-auto flex max-w-7xl gap-2 px-5 pt-4 text-[12.5px] font-medium text-mute sm:px-10">
            <router-link :to="{ name: 'home' }" class="text-brand">Ойрхон</router-link>
            <span>/</span>
            <router-link :to="{ name: 'categories' }" class="text-brand">Ангилал</router-link>
            <template v-for="a in ancestors" :key="a.id">
                <span>/</span>
                <router-link :to="{ name: 'category', params: { slug: a.slug } }" class="text-brand">{{ a.name }}</router-link>
            </template>
            <span v-if="category">/</span>
            <span v-if="category">{{ category.name }}</span>
        </div>

        <!-- Толгой -->
        <div class="border-b border-line">
            <div class="mx-auto max-w-7xl px-5 pb-6 pt-3.5 sm:px-10">
                <div class="flex flex-wrap items-end justify-between gap-6">
                    <div class="max-w-[600px]">
                        <h1 class="flex items-center gap-3 text-[28px] font-extrabold leading-tight tracking-[-.025em] text-ink sm:text-[34px]">
                            <span v-if="category" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-blueline bg-bluetint text-brand">
                                <CategoryIcon :name="category.icon" :size="24" />
                            </span>
                            {{ category ? category.name : (filters.q ? `“${filters.q}” хайлт` : 'Бүх бизнес') }}
                        </h1>
                        <p v-if="category?.description" class="mt-2 text-[14px] leading-relaxed text-soft">{{ category.description }}</p>
                    </div>
                    <div v-if="catStats" class="flex gap-6">
                        <div><div class="text-[22px] font-extrabold text-ink">{{ catStats.total.toLocaleString() }}</div><div class="mt-0.5 text-[11.5px] font-medium text-mute">бизнес</div></div>
                        <div class="w-px bg-line"></div>
                        <div><div class="text-[22px] font-extrabold text-ink">{{ catStats.verified.toLocaleString() }}</div><div class="mt-0.5 text-[11.5px] font-medium text-mute">баталгаажсан</div></div>
                        <div class="w-px bg-line"></div>
                        <div><div class="text-[22px] font-extrabold text-green">{{ catStats.open_now.toLocaleString() }}</div><div class="mt-0.5 text-[11.5px] font-medium text-mute">одоо нээлттэй</div></div>
                    </div>
                </div>

                <!-- Дэд ангилал chips -->
                <div v-if="category?.children?.length" class="mt-5 flex flex-wrap gap-2">
                    <button class="chip" :class="{ 'chip-active': !filters.sub }" @click="filters.sub = ''; apply()">
                        Бүгд <span class="font-mono text-[11px] opacity-60">{{ catStats?.total }}</span>
                    </button>
                    <button
                        v-for="sub in category.children"
                        :key="sub.id"
                        class="chip ring-1"
                        :class="activeSub?.id === sub.id ? '!bg-ink !text-white ring-ink' : '!bg-white !text-ink ring-searchline'"
                        @click="filters.sub = sub.slug; apply()"
                    >
                        {{ sub.name }}
                        <span class="font-mono text-[11px] text-faint" :class="{ 'text-white/60': activeSub?.id === sub.id }">{{ subTotal(sub) }}</span>
                        <span v-if="sub.children?.length" class="ml-0.5 text-[11px] opacity-50">›</span>
                    </button>
                </div>

                <!-- 3 дахь түвшин: сонгосон дэд ангиллын дотоод ангиллууд -->
                <div v-if="subChildren.length" class="mt-2 flex flex-wrap items-center gap-2 border-l-2 border-line pl-3">
                    <span class="text-[11.5px] font-semibold text-mute">{{ activeSub.name }}:</span>
                    <button
                        class="chip !py-1 !text-[11.5px]"
                        :class="filters.sub === activeSub.slug ? '!bg-brand !text-white' : '!bg-white !text-ink ring-1 ring-searchline'"
                        @click="filters.sub = activeSub.slug; apply()"
                    >Бүгд</button>
                    <button
                        v-for="g in subChildren"
                        :key="g.id"
                        class="chip !py-1 !text-[11.5px]"
                        :class="filters.sub === g.slug ? '!bg-brand !text-white' : '!bg-white !text-ink ring-1 ring-searchline'"
                        @click="filters.sub = g.slug; apply()"
                    >
                        {{ g.name }} <span class="font-mono text-[10.5px]" :class="filters.sub === g.slug ? 'text-white/60' : 'text-faint'">{{ subTotal(g) }}</span>
                    </button>
                </div>

                <!-- Хайлтын мөр (ангилал дотор ч ажиллана) -->
                <form class="mt-5 flex max-w-xl gap-2" @submit.prevent="apply()">
                    <input v-model="filters.q" type="search" :placeholder="category ? `${category.name} дотор хайх` : 'Бизнес, үйлчилгээ хайх'" class="input" />
                    <button type="submit" class="btn-primary shrink-0">Хайх</button>
                </form>
            </div>
        </div>

        <div class="mx-auto grid max-w-7xl grid-cols-1 lg:grid-cols-[236px_1fr]">
            <!-- Шүүлтүүр sidebar -->
            <aside class="border-line bg-panel px-5 py-6 sm:px-6 lg:sticky lg:top-0 lg:max-h-screen lg:self-start lg:overflow-y-auto lg:border-r">
                <div class="flex items-baseline justify-between">
                    <div class="text-[13px] font-bold text-ink">Шүүлтүүр</div>
                    <button class="cursor-pointer text-[11.5px] font-medium text-brand" @click="clearFilters">Цэвэрлэх</button>
                </div>

                <template v-if="!isCategory">
                    <div class="mb-2 mt-5 text-[11px] font-bold tracking-[.08em] text-mute">АНГИЛАЛ</div>
                    <select v-model="filters.category" class="input cursor-pointer !py-2 !text-[12.5px]" @change="apply()">
                        <option value="">Бүх ангилал</option>
                        <option v-for="c in categoryOptions" :key="c.slug" :value="c.slug">{{ optionLabel(c) }} ({{ c.businesses_total ?? c.businesses_count }})</option>
                    </select>
                </template>

                <div class="mb-2 mt-5 text-[11px] font-bold tracking-[.08em] text-mute">БАЙРШИЛ</div>
                <select v-model="filters.city" class="input cursor-pointer !py-2 !text-[12.5px]" @change="filters.district = ''; apply()">
                    <option value="">Бүх байршил</option>
                    <option v-for="l in locations" :key="l.city" :value="l.city">{{ l.city }}</option>
                </select>

                <div class="mb-2 mt-4 text-[11px] font-bold tracking-[.08em] text-mute">
                    {{ districtCity === 'Улаанбаатар' ? 'ДҮҮРЭГ' : 'СУМ' }}
                    <span v-if="!filters.city" class="font-semibold text-faint">· {{ districtCity.toUpperCase() }}</span>
                </div>
                <div class="max-h-56 overflow-y-auto pr-1">
                    <button
                        v-for="d in districts"
                        :key="d"
                        class="flex w-full cursor-pointer items-center gap-2 py-1 text-left"
                        :aria-pressed="filters.district === d"
                        @click="toggleDistrict(d)"
                    >
                        <span class="flex h-[15px] w-[15px] shrink-0 items-center justify-center rounded border-[1.5px]" :class="filters.district === d ? 'border-brand bg-brand text-[9px] text-white' : 'border-[#cfccc5]'">{{ filters.district === d ? '✓' : '' }}</span>
                        <span class="text-[13px] font-medium text-body">{{ d }}</span>
                    </button>
                </div>

                <div class="mb-2 mt-5 text-[11px] font-bold tracking-[.08em] text-mute">ҮНИЙН ЗЭРЭГЛЭЛ</div>
                <div class="flex gap-1.5">
                    <button v-for="p in ['₮', '₮₮', '₮₮₮']" :key="p" class="cursor-pointer rounded-[7px] px-3 py-1.5 text-[12px] font-semibold" :class="filters.price === p ? 'bg-brand text-white' : 'bg-chip text-chiptext'" @click="filters.price = filters.price === p ? '' : p; apply()">{{ p }}</button>
                </div>

                <div class="mb-2 mt-5 text-[11px] font-bold tracking-[.08em] text-mute">ҮНЭЛГЭЭ</div>
                <div class="flex gap-1.5">
                    <button v-for="r in ['4.5', '4.0', '3.0']" :key="r" class="cursor-pointer rounded-[7px] px-2.5 py-1.5 text-[12px] font-semibold" :class="filters.rating === r ? 'bg-brand text-white' : 'bg-chip text-chiptext'" @click="filters.rating = filters.rating === r ? '' : r; apply()">{{ r }}+</button>
                </div>

                <div v-if="paymentOptions.length" class="mb-2 mt-5 text-[11px] font-bold tracking-[.08em] text-mute">ЗЭЭЛИЙН АПП</div>
                <div v-if="paymentOptions.length" class="flex flex-wrap gap-1.5">
                    <button
                        v-for="p in paymentOptions"
                        :key="p.slug"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border py-1 pl-1 pr-2.5 text-[12px] font-medium"
                        :class="filters.payment === p.name ? 'border-blueline bg-bluetint text-brand' : 'border-searchline bg-white text-body'"
                        @click="filters.payment = filters.payment === p.name ? '' : p.name; apply()"
                    ><PaymentBadge :name="p.name" :slug="p.slug" :size="18" />{{ p.name }}</button>
                </div>

                <div class="mb-2 mt-5 text-[11px] font-bold tracking-[.08em] text-mute">ОНЦЛОГ</div>
                <div class="flex flex-wrap gap-1.5">
                    <button class="cursor-pointer rounded-full border px-2.5 py-1.5 text-[12px] font-medium" :class="filters.open_now ? 'border-blueline bg-bluetint text-brand' : 'border-searchline bg-white text-body'" @click="filters.open_now = !filters.open_now; apply()">Одоо нээлттэй</button>
                    <button class="cursor-pointer rounded-full border px-2.5 py-1.5 text-[12px] font-medium" :class="filters.open_24_7 ? 'border-blueline bg-bluetint text-brand' : 'border-searchline bg-white text-body'" @click="filters.open_24_7 = !filters.open_24_7; apply()">24/7 ажилладаг</button>
                    <button class="cursor-pointer rounded-full border px-2.5 py-1.5 text-[12px] font-medium" :class="filters.verified ? 'border-blueline bg-bluetint text-brand' : 'border-searchline bg-white text-body'" @click="filters.verified = !filters.verified; apply()">✓ Баталгаажсан</button>
                    <button v-for="a in amenityOptions" :key="a.name" class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[12px] font-medium" :class="filters.amenity === a.name ? 'border-blueline bg-bluetint text-brand' : 'border-searchline bg-white text-body'" @click="filters.amenity = filters.amenity === a.name ? '' : a.name; apply()"><AmenityIcon :name="a.icon" :size="13" />{{ a.name }}</button>
                </div>

                <div class="mt-6 rounded-[11px] border border-blueline bg-bluetint p-3.5">
                    <div class="text-[12.5px] font-bold text-ink">{{ category ? category.name + ' нээсэн үү?' : 'Бизнес эрхэлдэг үү?' }}</div>
                    <div class="mt-1.5 text-[12px] leading-relaxed text-body">Үнэгүй бүртгүүлж, ангиллын хайлтад орно.</div>
                    <router-link :to="{ name: 'add-business' }" class="mt-2.5 block rounded-lg bg-brand py-2 text-center text-[12px] font-bold text-white hover:bg-brand-dark">Бизнес нэмэх</router-link>
                </div>
            </aside>

            <!-- Үр дүн -->
            <main class="px-5 py-5 sm:px-10">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="text-[14px] font-bold text-ink">
                        {{ meta.total.toLocaleString() }} бизнес
                        <span v-if="filters.district || filters.price || filters.rating" class="font-medium text-mute">· {{ [filters.district, filters.price, filters.rating ? filters.rating + '+' : ''].filter(Boolean).join(', ') }}</span>
                    </div>

                    <!-- Хайлтыг хэрхэн ойлгосон -->
                    <div v-if="searchHint" class="flex flex-wrap items-center gap-1.5 text-[12px] text-mute">
                        <span>Ойлгосон нь:</span>
                        <span v-for="part in searchHint.parts" :key="part" class="rounded-full border border-blueline bg-bluetint px-2 py-0.5 text-[11.5px] font-bold text-brand">{{ part }}</span>
                        <span v-if="searchHint.corrected">· үсгийн алдааг залруулав</span>
                    </div>

                    <!-- Жагсаалт / газрын зураг -->
                    <div class="inline-flex gap-1 rounded-[9px] border border-searchline bg-white p-1">
                        <button
                            class="cursor-pointer rounded-[6px] px-3 py-1.5 text-[12px] font-bold"
                            :class="view === 'list' ? 'bg-ink text-white' : 'text-soft'"
                            @click="view = 'list'; apply(meta.current_page)"
                        >Жагсаалт</button>
                        <button
                            class="cursor-pointer rounded-[6px] px-3 py-1.5 text-[12px] font-bold"
                            :class="view === 'map' ? 'bg-ink text-white' : 'text-soft'"
                            @click="view = 'map'; apply()"
                        >Газрын зураг</button>
                    </div>

                    <!-- Миний ойролцоо -->
                    <button
                        v-if="!nearMode"
                        class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-inputline bg-white px-3 py-2 text-[12.5px] font-semibold text-ink hover:bg-panel disabled:opacity-60"
                        :disabled="locating"
                        @click="askLocation"
                    >
                        <span class="h-2.5 w-2.5 rounded-full border-[2.5px] border-brand"></span>
                        {{ locating ? 'Байршил тогтоож байна…' : 'Миний ойролцоо' }}
                    </button>

                    <select v-model="filters.sort" class="ml-auto cursor-pointer rounded-lg border border-inputline bg-white px-3 py-2 text-[12.5px] font-medium text-soft outline-none" @change="apply()">
                        <option value="rating">Эрэмбэ: Үнэлгээгээр</option>
                        <option value="reviews">Сэтгэгдлээр</option>
                        <option value="newest">Шинэ эхэндээ</option>
                        <option v-if="nearMode" value="distance">Ойрхноос холуур</option>
                    </select>
                </div>

                <!-- Байршлын мөр: радиус сонголт -->
                <div v-if="nearMode" class="mt-3 flex flex-wrap items-center gap-2.5 rounded-[10px] border border-blueline bg-bluetint px-3.5 py-2.5">
                    <span class="text-[12.5px] font-semibold text-brand">Миний байршлаас</span>
                    <div class="flex gap-1.5 text-[11.5px] font-semibold">
                        <button
                            v-for="r in [0.5, 2, 5, 20]"
                            :key="r"
                            class="cursor-pointer rounded-[7px] border px-2.5 py-1"
                            :class="radius === r ? 'border-brand bg-brand text-white' : 'border-blueline bg-white text-brand'"
                            @click="setRadius(r)"
                        >{{ r < 1 ? r * 1000 + ' м' : r + ' км' }}</button>
                    </div>
                    <button class="ml-auto cursor-pointer text-[12px] font-semibold text-soft hover:text-red" @click="clearLocation">Байршил хаах</button>
                </div>
                <p v-if="locationError" class="mt-2 text-[12.5px] font-medium text-red">{{ locationError }}</p>

                <div v-if="loading" class="mt-4 space-y-3">
                    <div v-for="i in 5" :key="i" class="card h-36 animate-pulse bg-panel"></div>
                </div>

                <div v-else-if="loadError" class="card mt-4 p-16 text-center">
                    <p class="text-[15px] font-bold text-red">{{ loadError }}</p>
                    <button class="btn-primary mt-4" @click="loadAll">Дахин оролдох</button>
                </div>

                <!-- ГАЗРЫН ЗУРГААР -->
                <div v-else-if="view === 'map'" class="mt-4">
                    <div v-if="branches.length" class="grid grid-cols-1 gap-3 lg:grid-cols-[300px_1fr]">
                        <!-- Зүүн талын товч жагсаалт -->
                        <div class="max-h-[560px] overflow-y-auto rounded-xl border border-line bg-white">
                            <button
                                v-for="(branch, i) in branches"
                                :key="branch.id"
                                class="flex w-full cursor-pointer items-start gap-2.5 border-b border-hairline px-3.5 py-3 text-left last:border-0"
                                :class="selectedId === branch.id ? 'bg-bluetint' : 'hover:bg-panel'"
                                @click="selectedId = branch.id"
                            >
                                <span class="mt-0.5 flex h-[19px] w-[19px] shrink-0 items-center justify-center rounded-full text-[10px] font-bold" :class="selectedId === branch.id ? 'bg-brand text-white' : 'bg-chip text-chiptext'">{{ i + 1 }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-1.5">
                                        <span class="text-[13px] font-bold text-ink">{{ branch.business.name }}</span>
                                        <span v-if="branch.is_24_7" class="rounded-[4px] bg-greentint px-1.5 py-0.5 text-[9.5px] font-bold text-green">24/7</span>
                                        <span v-if="branch.is_featured" class="badge-featured">ОНЦЛОХ</span>
                                    </span>
                                    <span class="mt-0.5 block text-[11.5px] text-mute">
                                        {{ branch.rating_avg.toFixed(1) }} · {{ branch.district }}
                                        <template v-if="branch.distance_km !== undefined"> · {{ branch.distance_km }} км</template>
                                    </span>
                                </span>
                            </button>
                        </div>

                        <!-- Газрын зураг -->
                        <div class="relative overflow-hidden rounded-xl border border-line">
                            <MapView
                                :markers="mapMarkers"
                                :selected-id="selectedId"
                                :center="coords ? [coords.lat, coords.lng] : undefined"
                                :circle="coords ? { lat: coords.lat, lng: coords.lng, radius: radius * 1000 } : null"
                                height="560px"
                                @select="(id) => (selectedId = id)"
                            />

                            <!-- Сонгосон салбарын карт -->
                            <router-link
                                v-if="selected"
                                :to="{ name: 'business', params: { slug: selected.business.slug } }"
                                class="absolute bottom-3 left-3 z-[500] w-[290px] rounded-xl border border-line bg-white p-3.5 shadow-lg"
                                @click="trackView(selected)"
                            >
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-[13.5px] font-bold text-ink">{{ selected.business.name }}</span>
                                    <span v-if="selected.is_24_7" class="rounded-[4px] bg-greentint px-1.5 py-0.5 text-[9.5px] font-bold text-green">24/7</span>
                                </div>
                                <div class="mt-1 text-[11.5px] text-mute">{{ selected.address }}</div>
                                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-[12px] font-medium">
                                    <span class="font-bold text-ink">{{ selected.rating_avg.toFixed(1) }}</span>
                                    <span class="font-semibold" :class="selected.is_open ? 'text-green' : 'text-amberdark'">{{ selected.open_label }}</span>
                                    <span v-if="selected.distance_km !== undefined" class="text-mute">{{ selected.distance_km }} км</span>
                                </div>
                            </router-link>
                        </div>
                    </div>

                    <div v-else class="card p-16 text-center">
                        <p class="text-[15px] font-bold text-ink">Илэрц олдсонгүй</p>
                        <p class="mt-1.5 text-[13px] text-mute">{{ nearMode ? 'Радиусаа өргөсгөж үзнэ үү.' : 'Шүүлтүүрээ өөрчилж дахин оролдоно уу.' }}</p>
                    </div>
                </div>

                <!-- ЖАГСААЛТААР -->
                <div v-else-if="branches.length" class="mt-4 flex flex-col gap-3">
                    <router-link
                        v-for="(branch, i) in branches"
                        :key="branch.id"
                        :to="{ name: 'business', params: { slug: branch.business.slug } }"
                        class="flex gap-4 rounded-xl border p-3.5 transition hover:border-blueline hover:shadow-sm"
                        :class="branch.is_featured ? 'border-blueline bg-bluepale' : 'border-line bg-white'"
                        @click="trackView(branch)"
                    >
                        <div class="hidden h-[110px] w-[150px] shrink-0 overflow-hidden rounded-[9px] sm:block">
                            <ImagePh :src="branch.cover_url" :alt="branch.business.name" label="ЗУРАГ" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-[12px] text-faint">{{ String((meta.current_page - 1) * (meta.per_page || 20) + i + 1).padStart(2, '0') }}</span>
                                <BizLogo :business="branch.business" size="h-7 w-7 rounded-[7px] text-[11px]" />
                                <span class="text-[17px] font-bold text-ink">{{ branch.business.name }}</span>
                                <VerifiedBadge v-if="branch.business.is_verified" :size="17" />
                                <span v-if="branch.is_24_7" class="rounded-full bg-greentint px-2 py-0.5 text-[10px] font-bold text-green">24/7</span>
                                <span v-if="branch.is_featured" class="badge-featured">ОНЦЛОХ</span>
                                <span v-if="branch.distance_km !== undefined" class="text-[11.5px] font-semibold text-brand">{{ branch.distance_km }} км</span>
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-2 text-[13px] font-medium text-soft">
                                <span class="font-bold text-ink">{{ branch.rating_avg.toFixed(1) }}</span>
                                <span class="text-[12px] tracking-tight text-amberdot">{{ stars(branch.rating_avg) }}</span>
                                <span>{{ branch.reviews_count }} сэтгэгдэл</span>
                                <span class="text-[#c9ccd1]">·</span>
                                <span>{{ branch.business.subcategory || branch.business.category?.name }}</span>
                                <span v-if="branch.business.price_level" class="text-[#c9ccd1]">·</span>
                                <span v-if="branch.business.price_level">{{ branch.business.price_level }}</span>
                                <span class="text-[#c9ccd1]">·</span>
                                <span class="font-semibold" :class="branch.is_open ? 'text-green' : 'text-amberdark'">{{ branch.open_label }}</span>
                            </div>
                            <div class="mt-1.5 text-[13px] text-soft">{{ branch.city && branch.city !== 'Улаанбаатар' ? branch.city + ', ' : '' }}{{ branch.district }}{{ branch.khoroo ? ', ' + branch.khoroo : '' }}, {{ branch.address }}</div>
                            <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                                <span v-for="tag in (branch.amenities || []).slice(0, 3)" :key="tag" class="rounded-full border border-line bg-panel px-2.5 py-1 text-[11.5px] font-medium text-body">{{ tag }}</span>
                            </div>
                        </div>
                    </router-link>
                </div>

                <div v-else class="card mt-4 p-16 text-center">
                    <p class="text-[15px] font-bold text-ink">Илэрц олдсонгүй</p>
                    <p class="mt-1.5 text-[13px] text-mute">Шүүлтүүрээ өөрчилж дахин оролдоно уу.</p>
                </div>

                <!-- Pagination -->
                <div v-if="meta.last_page > 1" class="mt-5 flex items-center justify-between">
                    <div class="flex gap-1.5 text-[12.5px] font-semibold">
                        <button class="cursor-pointer rounded-lg border border-inputline px-3 py-2 text-mute disabled:opacity-40" :disabled="meta.current_page <= 1" @click="apply(meta.current_page - 1)">←</button>
                        <button v-for="p in pageWindow" :key="p" class="cursor-pointer rounded-lg px-3.5 py-2" :class="p === meta.current_page ? 'bg-ink text-white' : 'border border-inputline text-ink'" @click="apply(p)">{{ p }}</button>
                        <button class="cursor-pointer rounded-lg border border-inputline px-3 py-2 text-ink disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="apply(meta.current_page + 1)">→</button>
                    </div>
                    <div class="text-[12.5px] font-medium text-mute">{{ (meta.current_page - 1) * meta.per_page + 1 }}–{{ Math.min(meta.current_page * meta.per_page, meta.total) }} / {{ meta.total.toLocaleString() }}</div>
                </div>

                <!-- Дүүргээр үзэх -->
                <div v-if="category" class="mt-8 border-t border-line pt-5 pb-8">
                    <div class="text-[15px] font-bold text-ink">Дүүргээр үзэх</div>
                    <div class="mt-3 flex flex-wrap gap-2 text-[12.5px] font-medium">
                        <button v-for="d in districts" :key="d" class="cursor-pointer rounded-lg border border-blueline bg-bluetint px-3 py-1.5 text-brand" @click="filters.district = d; apply()">
                            {{ d }} — {{ category.name.toLowerCase() }}
                        </button>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
