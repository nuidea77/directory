<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../api';
import ImagePh from '../components/ImagePh.vue';

const route = useRoute();
const router = useRouter();

const category = ref(null);
const catStats = ref(null);
const categories = ref([]);
const branches = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(true);
const loadError = ref('');

// Байршил, үйлчилгээний жагсаалт API-аас
const locations = ref([]);
const amenityOptions = ref([]);

const districts = computed(() => locations.value.find((l) => l.city === (filters.value.city || 'Улаанбаатар'))?.districts || []);

const filters = ref({
    q: '',
    category: '',
    city: '',
    district: '',
    price: '',
    rating: '',
    open_now: false,
    verified: false,
    amenity: '',
    sub: '',
    sort: 'rating',
    page: 1,
});

const isCategory = computed(() => route.name === 'category');

// Одоогийн хуудсыг тойрсон 5 хуудасны цонх
const pageWindow = computed(() => {
    const last = meta.value.last_page;
    const start = Math.max(1, Math.min(meta.value.current_page - 2, last - 4));
    return Array.from({ length: Math.min(5, last) }, (_, i) => start + i);
});

function syncFromRoute() {
    filters.value = {
        q: route.query.q || '',
        category: route.query.category || '',
        city: route.query.city || '',
        district: route.query.district || '',
        price: route.query.price || '',
        rating: route.query.rating || '',
        open_now: route.query.open_now === '1',
        verified: route.query.verified === '1',
        amenity: route.query.amenity || '',
        sub: route.query.sub || '',
        sort: route.query.sort || 'rating',
        page: Number(route.query.page) || 1,
    };
}

async function fetchCategory() {
    category.value = null;
    catStats.value = null;

    if (isCategory.value) {
        const data = await api.get(`/categories/${route.params.slug}`);
        category.value = data.data;
        catStats.value = data.stats;
        document.title = `${category.value.name} | Хаана.mn`;
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
            verified: filters.value.verified ? 1 : undefined,
            amenity: filters.value.amenity,
            sort: filters.value.sort,
            page: filters.value.page,
        });
        branches.value = data.data;
        meta.value = data.meta;
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
            verified: filters.value.verified ? '1' : undefined,
            amenity: filters.value.amenity || undefined,
            sub: filters.value.sub || undefined,
            sort: filters.value.sort !== 'rating' ? filters.value.sort : undefined,
            page: page > 1 ? page : undefined,
        },
    });
}

function clearFilters() {
    filters.value = { ...filters.value, category: '', city: '', district: '', price: '', rating: '', open_now: false, amenity: '', sub: '' };
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
        amenityOptions.value = locs.amenities || [];
    } catch {
        /* шүүлтүүрийн жагсаалтгүйгээр үргэлжилнэ */
    }
    await Promise.all([fetchCategory().catch(() => {}), fetchResults()]);
}

watch(() => route.fullPath, async () => {
    syncFromRoute();
    await Promise.all([fetchCategory().catch(() => {}), fetchResults()]);
});

onMounted(async () => {
    syncFromRoute();
    await loadAll();
});
</script>

<template>
    <div class="bg-white">
        <!-- Breadcrumb -->
        <div class="mx-auto flex max-w-7xl gap-2 px-5 pt-4 text-[12.5px] font-medium text-mute sm:px-10">
            <router-link :to="{ name: 'home' }" class="text-brand">Хаана</router-link>
            <span>/</span>
            <router-link :to="{ name: 'categories' }" class="text-brand">Ангилал</router-link>
            <span v-if="category">/</span>
            <span v-if="category">{{ category.name }}</span>
        </div>

        <!-- Толгой -->
        <div class="border-b border-line">
            <div class="mx-auto max-w-7xl px-5 pb-6 pt-3.5 sm:px-10">
                <div class="flex flex-wrap items-end justify-between gap-6">
                    <div class="max-w-[600px]">
                        <h1 class="text-[28px] font-extrabold leading-tight tracking-[-.025em] text-ink sm:text-[34px]">
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
                        class="chip !bg-white !text-ink ring-1 ring-searchline"
                        :class="{ '!bg-ink !text-white !ring-ink': filters.sub === sub.slug }"
                        @click="filters.sub = sub.slug; apply()"
                    >
                        {{ sub.name }} <span class="font-mono text-[11px] text-faint" :class="{ 'text-white/60': filters.sub === sub.slug }">{{ sub.businesses_count }}</span>
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
            <aside class="border-line bg-panel px-5 py-6 sm:px-6 lg:border-r">
                <div class="flex items-baseline justify-between">
                    <div class="text-[13px] font-bold text-ink">Шүүлтүүр</div>
                    <button class="cursor-pointer text-[11.5px] font-medium text-brand" @click="clearFilters">Цэвэрлэх</button>
                </div>

                <template v-if="!isCategory">
                    <div class="mb-2 mt-5 text-[11px] font-bold tracking-[.08em] text-mute">АНГИЛАЛ</div>
                    <select v-model="filters.category" class="input cursor-pointer !py-2 !text-[12.5px]" @change="apply()">
                        <option value="">Бүх ангилал</option>
                        <option v-for="c in categories" :key="c.slug" :value="c.slug">{{ c.name }} ({{ c.businesses_count }})</option>
                    </select>
                </template>

                <div class="mb-2 mt-5 text-[11px] font-bold tracking-[.08em] text-mute">БАЙРШИЛ</div>
                <select v-model="filters.city" class="input cursor-pointer !py-2 !text-[12.5px]" @change="filters.district = ''; apply()">
                    <option value="">Улаанбаатар (бүх дүүрэг)</option>
                    <option v-for="l in locations" :key="l.city" :value="l.city">{{ l.city }}</option>
                </select>

                <div class="mb-2 mt-4 text-[11px] font-bold tracking-[.08em] text-mute">{{ (filters.city || 'Улаанбаатар') === 'Улаанбаатар' ? 'ДҮҮРЭГ' : 'СУМ' }}</div>
                <div class="max-h-56 overflow-y-auto pr-1">
                    <button
                        v-for="d in districts"
                        :key="d"
                        class="flex w-full cursor-pointer items-center gap-2 py-1 text-left"
                        :aria-pressed="filters.district === d"
                        @click="filters.district = filters.district === d ? '' : d; apply()"
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

                <div class="mb-2 mt-5 text-[11px] font-bold tracking-[.08em] text-mute">ОНЦЛОГ</div>
                <div class="flex flex-wrap gap-1.5">
                    <button class="cursor-pointer rounded-full border px-2.5 py-1.5 text-[12px] font-medium" :class="filters.open_now ? 'border-blueline bg-bluetint text-brand' : 'border-searchline bg-white text-body'" @click="filters.open_now = !filters.open_now; apply()">Одоо нээлттэй</button>
                    <button class="cursor-pointer rounded-full border px-2.5 py-1.5 text-[12px] font-medium" :class="filters.verified ? 'border-blueline bg-bluetint text-brand' : 'border-searchline bg-white text-body'" @click="filters.verified = !filters.verified; apply()">✓ Баталгаажсан</button>
                    <button v-for="a in amenityOptions" :key="a" class="cursor-pointer rounded-full border px-2.5 py-1.5 text-[12px] font-medium" :class="filters.amenity === a ? 'border-blueline bg-bluetint text-brand' : 'border-searchline bg-white text-body'" @click="filters.amenity = filters.amenity === a ? '' : a; apply()">{{ a }}</button>
                </div>

                <div class="mt-6 rounded-[11px] border border-blueline bg-bluetint p-3.5">
                    <div class="text-[12.5px] font-bold text-ink">{{ category ? category.name + ' нээсэн үү?' : 'Бизнес эрхэлдэг үү?' }}</div>
                    <div class="mt-1.5 text-[12px] leading-relaxed text-body">Үнэгүй бүртгүүлж, ангиллын хайлтад орно.</div>
                    <router-link :to="{ name: 'add-business' }" class="mt-2.5 block rounded-lg bg-brand py-2 text-center text-[12px] font-bold text-white hover:bg-brand-dark">Бизнес нэмэх</router-link>
                </div>
            </aside>

            <!-- Үр дүн -->
            <main class="px-5 py-5 sm:px-10">
                <div class="flex items-center justify-between">
                    <div class="text-[14px] font-bold text-ink">
                        {{ meta.total.toLocaleString() }} бизнес
                        <span v-if="filters.district || filters.price || filters.rating" class="font-medium text-mute">· {{ [filters.district, filters.price, filters.rating ? filters.rating + '+' : ''].filter(Boolean).join(', ') }}</span>
                    </div>
                    <select v-model="filters.sort" class="cursor-pointer rounded-lg border border-inputline bg-white px-3 py-2 text-[12.5px] font-medium text-soft outline-none" @change="apply()">
                        <option value="rating">Эрэмбэ: Үнэлгээгээр</option>
                        <option value="reviews">Сэтгэгдлээр</option>
                        <option value="newest">Шинэ эхэндээ</option>
                    </select>
                </div>

                <div v-if="loading" class="mt-4 space-y-3">
                    <div v-for="i in 5" :key="i" class="card h-36 animate-pulse bg-panel"></div>
                </div>

                <div v-else-if="loadError" class="card mt-4 p-16 text-center">
                    <p class="text-[15px] font-bold text-red">{{ loadError }}</p>
                    <button class="btn-primary mt-4" @click="loadAll">Дахин оролдох</button>
                </div>

                <div v-else-if="branches.length" class="mt-4 flex flex-col gap-3">
                    <router-link
                        v-for="(branch, i) in branches"
                        :key="branch.id"
                        :to="{ name: 'business', params: { slug: branch.business.slug } }"
                        class="flex gap-4 rounded-xl border p-3.5 transition hover:shadow-sm"
                        :class="branch.business.is_featured ? 'border-blueline bg-bluepale' : 'border-line bg-white'"
                        @click="trackView(branch)"
                    >
                        <div class="hidden h-[110px] w-[150px] shrink-0 overflow-hidden rounded-[9px] sm:block">
                            <ImagePh :src="branch.cover_url" :alt="branch.business.name" label="ЗУРАГ" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-[12px] text-faint">{{ String((meta.current_page - 1) * (meta.per_page || 20) + i + 1).padStart(2, '0') }}</span>
                                <span class="text-[17px] font-bold text-ink">{{ branch.business.name }}</span>
                                <span v-if="branch.business.is_verified" class="badge-verified">✓</span>
                                <span v-if="branch.business.is_featured" class="badge-featured">ОНЦЛОХ</span>
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
                                <span class="ml-auto hidden gap-2 text-[12.5px] font-semibold sm:flex">
                                    <a :href="`tel:${branch.phone}`" class="rounded-lg border border-inputline px-3.5 py-2 text-ink hover:bg-panel" @click.stop.prevent="api.post(`/branches/${branch.id}/event`, { type: 'call' }).catch(() => {}); window.location.href = `tel:${branch.phone}`">{{ branch.phone }}</a>
                                    <span class="rounded-lg bg-brand px-4 py-2 text-white">Дэлгэрэнгүй</span>
                                </span>
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
