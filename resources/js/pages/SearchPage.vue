<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../api';
import ListingCard from '../components/ListingCard.vue';
import AppPagination from '../components/AppPagination.vue';

const route = useRoute();
const router = useRouter();

const listings = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const categories = ref([]);
const loading = ref(true);

const filters = ref({
    q: '',
    category: '',
    district: '',
    sort: 'newest',
    featured: false,
    page: 1,
});

const districts = ['Сүхбаатар', 'Чингэлтэй', 'Баянгол', 'Баянзүрх', 'Хан-Уул', 'Сонгинохайрхан', 'Налайх', 'Багануур'];

function syncFromRoute() {
    filters.value = {
        q: route.query.q || '',
        category: route.name === 'category' ? route.params.slug : route.query.category || '',
        district: route.query.district || '',
        sort: route.query.sort || 'newest',
        featured: route.query.featured === '1',
        page: Number(route.query.page) || 1,
    };
}

async function fetchListings() {
    loading.value = true;
    try {
        const data = await api.get('/listings', {
            q: filters.value.q,
            category: filters.value.category,
            district: filters.value.district,
            sort: filters.value.sort,
            featured: filters.value.featured ? 1 : undefined,
            page: filters.value.page,
        });
        listings.value = data.data;
        meta.value = data.meta;
    } finally {
        loading.value = false;
    }
}

function applyFilters(page = 1) {
    filters.value.page = page;
    router.push({
        name: 'search',
        query: {
            q: filters.value.q || undefined,
            category: filters.value.category || undefined,
            district: filters.value.district || undefined,
            sort: filters.value.sort !== 'newest' ? filters.value.sort : undefined,
            featured: filters.value.featured ? '1' : undefined,
            page: page > 1 ? page : undefined,
        },
    });
}

watch(() => route.fullPath, () => {
    syncFromRoute();
    fetchListings();
});

onMounted(async () => {
    syncFromRoute();
    const cats = await api.get('/categories');
    categories.value = cats.data;
    await fetchListings();
});
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <h1 class="text-2xl font-bold text-slate-900">Байгууллага хайх</h1>
        <p class="mt-1 text-sm text-slate-500">Нийт {{ meta.total }} үр дүн</p>

        <!-- Filters -->
        <div class="card mt-6 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <input
                v-model="filters.q"
                type="search"
                placeholder="Нэр, тайлбараар хайх..."
                class="input"
                @keyup.enter="applyFilters()"
            />
            <select v-model="filters.category" class="input" @change="applyFilters()">
                <option value="">Бүх ангилал</option>
                <option v-for="c in categories" :key="c.id" :value="c.slug">{{ c.icon }} {{ c.name }}</option>
            </select>
            <select v-model="filters.district" class="input" @change="applyFilters()">
                <option value="">Бүх дүүрэг</option>
                <option v-for="d in districts" :key="d" :value="d">{{ d }}</option>
            </select>
            <select v-model="filters.sort" class="input" @change="applyFilters()">
                <option value="newest">Шинэ эхэндээ</option>
                <option value="rating">Үнэлгээгээр</option>
                <option value="views">Үзэлтээр</option>
                <option value="name">Нэрээр (А-Я)</option>
            </select>
            <button class="btn-primary" @click="applyFilters()">Хайх</button>
        </div>

        <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600">
            <input v-model="filters.featured" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600" @change="applyFilters()" />
            Зөвхөн онцлох байгууллагууд
        </label>

        <!-- Results -->
        <div v-if="loading" class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="i in 8" :key="i" class="card h-64 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else-if="listings.length" class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <ListingCard v-for="listing in listings" :key="listing.id" :listing="listing" />
        </div>

        <div v-else class="card mt-8 p-16 text-center">
            <p class="text-4xl">🔍</p>
            <p class="mt-4 font-semibold text-slate-700">Илэрц олдсонгүй</p>
            <p class="mt-1 text-sm text-slate-500">Хайлтын нөхцөлөө өөрчилж дахин оролдоно уу.</p>
        </div>

        <AppPagination :meta="meta" @change="applyFilters" />
    </div>
</template>
