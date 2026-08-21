<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { shortDate } from '../../utils/date';
import { api } from '../../api';
import AdminPageHeader from '../../components/admin/AdminPageHeader.vue';
import AdminBadge from '../../components/admin/AdminBadge.vue';
import VerifiedBadge from '../../components/VerifiedBadge.vue';
import { flattenCategories, optionLabel } from '../../utils/categories';

// Бизнесүүд: эрхийн төрөл, хүлээгдэж буй төлбөр/сурталчилгаа/модерац,
// ангилал, байршил (аймаг/нийслэл → сум/дүүрэг) шүүлтүүртэй жагсаалт
const data = ref(null);
const categories = ref([]);
const categoryOptions = computed(() => flattenCategories(categories.value));
const locations = ref([]);
const loading = ref(false);
const page = ref(1);

const filters = ref({
    q: '',
    plan: '',
    pending: '',
    category_id: '',
    city: '',
    district: '',
});

const planOptions = [
    ['', 'Бүх эрх'],
    ['free', 'Үнэгүй'],
    ['standard', 'Стандарт'],
    ['business', 'Бизнес'],
];

const pendingOptions = [
    ['', 'Бүх төлөв'],
    ['plan_order', 'Эрхийн төлбөр хүлээж буй'],
    ['ad', 'Сурталчилгаа хүлээгдэж буй'],
    ['moderation', 'Модерац хүлээж буй'],
];

// Сонгосон аймаг/нийслэлийн сум/дүүргүүд
const districtOptions = computed(() => {
    const city = locations.value.find((l) => l.city === filters.value.city);
    return city ? city.districts : [];
});

// Эрхийн төрөл → AdminBadge-ийн өнгө
const planTone = {
    free: 'neutral',
    standard: 'brand',
    business: 'good',
};

let searchTimer = null;

async function fetchData() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        Object.entries(filters.value).forEach(([k, v]) => v && params.set(k, v));
        if (page.value > 1) params.set('page', page.value);
        data.value = await api.get(`/admin/businesses?${params}`);
    } finally {
        loading.value = false;
    }
}

watch(
    filters,
    (val, old) => {
        page.value = 1;
        clearTimeout(searchTimer);
        // Хайлтын талбарт бичих үед 300мс хүлээнэ, select-үүдэд шууд
        searchTimer = setTimeout(fetchData, 300);
    },
    { deep: true },
);

// Аймаг солиход өмнөх сум/дүүргийн сонголтыг цэвэрлэнэ
watch(() => filters.value.city, () => (filters.value.district = ''));

function goPage(p) {
    page.value = p;
    fetchData();
}

onMounted(async () => {
    fetchData();
    const [cats, locs] = await Promise.all([api.get('/categories'), api.get('/locations')]);
    categories.value = cats.data ?? cats;
    locations.value = locs.data ?? locs;
});
</script>

<template>
    <div class="p-5 sm:p-7">
        <AdminPageHeader
            title="Бизнесүүд"
            description="Бүртгэлтэй бизнесүүдийг нэр, эрхийн төрөл, ангилал, байршлаар шүүж, хүлээгдэж буй төлбөр, сурталчилгаа, модерацыг нь хянана."
            :meta="data ? [{ label: data.meta.total + ' бизнес' }] : []"
        />

        <!-- Шүүлтүүр -->
        <div class="card flex flex-wrap items-center gap-2 p-3">
            <input
                v-model="filters.q"
                type="search"
                placeholder="Нэрээр хайх…"
                class="w-full min-w-[160px] rounded-[8px] border border-inputline px-3 py-2 text-[12.5px] font-medium text-ink outline-none placeholder:text-ph focus:border-brand sm:w-auto sm:flex-1"
            />
            <select v-model="filters.plan" class="cursor-pointer rounded-[8px] border border-inputline bg-white px-2.5 py-2 text-[12.5px] font-semibold text-ink outline-none">
                <option v-for="[v, label] in planOptions" :key="v" :value="v">{{ label }}</option>
            </select>
            <select v-model="filters.pending" class="cursor-pointer rounded-[8px] border border-inputline bg-white px-2.5 py-2 text-[12.5px] font-semibold text-ink outline-none">
                <option v-for="[v, label] in pendingOptions" :key="v" :value="v">{{ label }}</option>
            </select>
            <select v-model="filters.category_id" class="cursor-pointer rounded-[8px] border border-inputline bg-white px-2.5 py-2 text-[12.5px] font-semibold text-ink outline-none">
                <option value="">Бүх ангилал</option>
                <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ optionLabel(c) }}</option>
            </select>
            <select v-model="filters.city" class="cursor-pointer rounded-[8px] border border-inputline bg-white px-2.5 py-2 text-[12.5px] font-semibold text-ink outline-none">
                <option value="">Аймаг / Нийслэл</option>
                <option v-for="l in locations" :key="l.city" :value="l.city">{{ l.city }}</option>
            </select>
            <select
                v-model="filters.district"
                class="cursor-pointer rounded-[8px] border border-inputline bg-white px-2.5 py-2 text-[12.5px] font-semibold text-ink outline-none disabled:opacity-50"
                :disabled="!filters.city"
            >
                <option value="">{{ filters.city === 'Улаанбаатар' ? 'Бүх дүүрэг' : 'Бүх сум' }}</option>
                <option v-for="d in districtOptions" :key="d" :value="d">{{ d }}</option>
            </select>
        </div>

        <div v-if="!data" class="card mt-3.5 h-64 animate-pulse"></div>

        <template v-else>
            <!-- Жагсаалт -->
            <div class="card mt-3.5 overflow-hidden" :class="{ 'opacity-60': loading }">
                <div class="hidden grid-cols-[1.4fr_1fr_1fr_.9fr_1fr] gap-3 border-b border-divider px-4 py-2.5 text-[10.5px] font-semibold tracking-[.08em] text-mute lg:grid">
                    <span>БИЗНЕС</span><span>АНГИЛАЛ · БАЙГУУЛЛАГА</span><span>БАЙРШИЛ</span><span>ЭРХ</span><span>ХҮЛЭЭГДЭЖ БУЙ</span>
                </div>

                <div v-for="b in data.data" :key="b.id" class="grid grid-cols-1 gap-2 border-b border-hairline px-4 py-3 last:border-0 lg:grid-cols-[1.4fr_1fr_1fr_.9fr_1fr] lg:items-center lg:gap-3">
                    <!-- Бизнес -->
                    <div class="flex items-center gap-2.5">
                        <div class="img-ph h-[26px] w-[26px] shrink-0 rounded-md"></div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <router-link :to="{ name: 'business', params: { slug: b.slug } }" class="truncate text-[13px] font-bold text-ink hover:text-brand">{{ b.name }}</router-link>
                                <VerifiedBadge v-if="b.is_verified" :size="14" />
                            </div>
                            <div class="text-[11px] text-mute">{{ b.branches_count }} салбар</div>
                        </div>
                    </div>

                    <!-- Ангилал · Байгууллага -->
                    <div class="text-[12px] text-body">
                        <div class="font-semibold text-ink">{{ b.category || '—' }}</div>
                        <div class="mt-0.5 text-[11.5px] text-mute">{{ b.organization }}</div>
                    </div>

                    <!-- Байршил -->
                    <div class="text-[11.5px] leading-relaxed text-body">
                        <div v-for="loc in b.locations.slice(0, 2)" :key="loc">{{ loc }}</div>
                        <div v-if="b.locations.length > 2" class="text-mute">+{{ b.locations.length - 2 }} байршил</div>
                        <div v-if="!b.locations.length" class="text-mute">—</div>
                    </div>

                    <!-- Эрх -->
                    <div>
                        <AdminBadge :tone="planTone[b.plan]">{{ b.plan_name }}</AdminBadge>
                        <div v-if="b.plan_expires_at" class="mt-1 text-[10.5px] text-mute">{{ shortDate(b.plan_expires_at) }} хүртэл</div>
                    </div>

                    <!-- Хүлээгдэж буй -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <AdminBadge v-if="b.pending_orders_count" tone="warn">₮ төлбөр {{ b.pending_orders_count }}</AdminBadge>
                        <AdminBadge v-if="b.pending_ads_count" tone="brand">Сурталчилгаа {{ b.pending_ads_count }}</AdminBadge>
                        <AdminBadge v-if="b.pending_branches_count" tone="warn">Модерац {{ b.pending_branches_count }}</AdminBadge>
                        <span v-if="!b.pending_orders_count && !b.pending_ads_count && !b.pending_branches_count" class="text-[10.5px] font-medium text-mute">—</span>
                    </div>
                </div>

                <div v-if="!data.data.length" class="p-10 text-center text-[13px] text-mute">Шүүлтүүрт тохирох бизнес олдсонгүй</div>
            </div>

            <!-- Хуудаслалт -->
            <div v-if="data.meta.last_page > 1" class="mt-3.5 flex items-center justify-center gap-2 text-[12.5px] font-semibold">
                <button class="cursor-pointer rounded-[7px] border border-inputline bg-white px-3 py-1.5 text-ink disabled:opacity-40" :disabled="page <= 1" @click="goPage(page - 1)">← Өмнөх</button>
                <span class="px-2 text-mute">{{ data.meta.current_page }} / {{ data.meta.last_page }}</span>
                <button class="cursor-pointer rounded-[7px] border border-inputline bg-white px-3 py-1.5 text-ink disabled:opacity-40" :disabled="page >= data.meta.last_page" @click="goPage(page + 1)">Дараах →</button>
            </div>
        </template>
    </div>
</template>
