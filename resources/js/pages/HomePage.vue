<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api';
import BusinessCard from '../components/BusinessCard.vue';

const router = useRouter();
const categories = ref([]);
const featured = ref([]);
const stats = ref({ businesses: 0 });
const query = ref('');
const city = ref('Улаанбаатар');
const loading = ref(true);
const featuredFilter = ref('all');

const popular = ['хоолны газар', 'шүдний эмнэлэг', 'нотариат', 'барилгын материал'];

onMounted(async () => {
    try {
        const data = await api.get('/home');
        categories.value = data.categories;
        featured.value = data.featured;
        stats.value = data.stats;
    } finally {
        loading.value = false;
    }
});

function search(term) {
    router.push({ name: 'search', query: term || query.value ? { q: term || query.value } : {} });
}

function filteredFeatured() {
    return featured.value.filter((b) => {
        if (featuredFilter.value === 'open') return b.branches?.some((br) => br.is_open);
        if (featuredFilter.value === 'rating') return (b.rating_avg || 0) >= 4.5;
        if (featuredFilter.value === 'verified') return b.is_verified;
        return true;
    });
}
</script>

<template>
    <div>
        <!-- Hero (1a) -->
        <section class="border-b border-line bg-hero">
            <div class="mx-auto max-w-7xl px-5 py-12 sm:px-10 sm:py-14">
                <div class="max-w-[720px]">
                    <h1 class="text-[32px] font-extrabold leading-[1.15] tracking-[-.025em] text-ink sm:text-[40px]">
                        Монгол дахь {{ stats.businesses > 100 ? stats.businesses.toLocaleString() : '42,000' }} бизнесийг нэг дороос
                    </h1>
                    <p class="mt-3 text-[15px] leading-relaxed text-soft">
                        Ресторан, эмнэлэг, авто засвар, хууль зүйн үйлчилгээ — хаяг, цагийн хуваарь, үнэлгээ бүхий баталгаажсан лавлах.
                    </p>
                </div>

                <form class="mt-6 flex max-w-[900px] flex-col gap-2.5 sm:flex-row" @submit.prevent="search()">
                    <div class="flex flex-[1.4] items-center gap-2.5 rounded-[10px] border border-inputline bg-white px-4 py-3.5">
                        <svg class="h-4 w-4 shrink-0 text-ph" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4-4"/></svg>
                        <input v-model="query" type="search" placeholder="Бизнес, үйлчилгээ, брэнд хайх" class="w-full bg-transparent text-[14px] font-medium text-ink outline-none placeholder:text-ph" />
                    </div>
                    <div class="flex flex-1 items-center justify-between rounded-[10px] border border-inputline bg-white px-4 py-3.5">
                        <span class="text-[14px] font-medium text-ink">{{ city }}</span>
                        <span class="text-[12px] text-ph">▾</span>
                    </div>
                    <button type="submit" class="btn-primary !rounded-[10px] !px-8 !py-3.5 !text-[14px]">Хайх</button>
                </form>

                <div class="mt-4 flex flex-wrap items-center gap-2 text-[12.5px] font-medium">
                    <span class="text-soft">Их хайсан:</span>
                    <template v-for="(term, i) in popular" :key="term">
                        <button class="cursor-pointer text-brand hover:text-brand-dark" @click="search(term)">{{ term }}</button>
                        <span v-if="i < popular.length - 1" class="text-[#c9ccd1]">·</span>
                    </template>
                </div>
            </div>
        </section>

        <!-- Ангиллаар үзэх -->
        <section class="mx-auto max-w-7xl px-5 pb-2 pt-9 sm:px-10">
            <div class="flex items-baseline justify-between">
                <h2 class="text-xl font-bold tracking-[-.015em] text-ink">Ангиллаар үзэх</h2>
                <router-link :to="{ name: 'search' }" class="text-[13px] font-semibold text-brand hover:text-brand-dark">Бүх ангилал →</router-link>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <router-link
                    v-for="category in categories"
                    :key="category.id"
                    :to="{ name: 'category', params: { slug: category.slug } }"
                    class="rounded-[10px] border border-line bg-white p-4 transition hover:-translate-y-0.5 hover:border-blueline hover:shadow-sm"
                >
                    <div class="h-8 w-8 rounded-lg border border-blueline bg-bluetint"></div>
                    <div class="mt-3 text-[13.5px] font-semibold text-ink">{{ category.name }}</div>
                    <div class="mt-0.5 text-[12px] text-mute">{{ (category.businesses_count || 0).toLocaleString() }} бизнес</div>
                </router-link>
            </div>
        </section>

        <!-- Онцлох бизнесүүд -->
        <section class="mx-auto max-w-7xl px-5 py-9 sm:px-10 sm:pb-11">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold tracking-[-.015em] text-ink">Онцлох бизнесүүд</h2>
                <div class="flex gap-2 text-[12.5px] font-semibold">
                    <button class="chip" :class="{ 'chip-active': featuredFilter === 'all' }" @click="featuredFilter = 'all'">Бүгд</button>
                    <button class="chip" :class="{ 'chip-active': featuredFilter === 'open' }" @click="featuredFilter = 'open'">Нээлттэй</button>
                    <button class="chip" :class="{ 'chip-active': featuredFilter === 'rating' }" @click="featuredFilter = 'rating'">4.5+ үнэлгээ</button>
                    <button class="chip" :class="{ 'chip-active': featuredFilter === 'verified' }" @click="featuredFilter = 'verified'">Баталгаажсан</button>
                </div>
            </div>

            <div v-if="loading" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="i in 6" :key="i" class="card h-72 animate-pulse bg-panel"></div>
            </div>
            <div v-else class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <BusinessCard v-for="business in filteredFeatured()" :key="business.id" :business="business" />
            </div>
        </section>
    </div>
</template>
