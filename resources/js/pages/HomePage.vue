<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api';
import ListingCard from '../components/ListingCard.vue';

const router = useRouter();
const categories = ref([]);
const featured = ref([]);
const latest = ref([]);
const query = ref('');
const loading = ref(true);

onMounted(async () => {
    try {
        const [cats, feats, news] = await Promise.all([
            api.get('/categories'),
            api.get('/listings/featured'),
            api.get('/listings', { sort: 'newest', per_page: 8 }),
        ]);
        categories.value = cats.data;
        featured.value = feats.data;
        latest.value = news.data;
    } finally {
        loading.value = false;
    }
});

function search() {
    router.push({ name: 'search', query: query.value ? { q: query.value } : {} });
}
</script>

<template>
    <div>
        <!-- Hero -->
        <section class="bg-gradient-to-br from-brand-700 via-brand-600 to-brand-500">
            <div class="mx-auto max-w-7xl px-4 py-16 text-center sm:px-6 sm:py-24">
                <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-5xl">
                    Хэрэгтэй байгууллагаа <span class="text-amber-300">хормын дотор</span> ол
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base text-brand-100 sm:text-lg">
                    Монголын байгууллага, үйлчилгээний нэгдсэн лавлах. Хайж ол, харьцуул, үнэлгээ өг.
                </p>

                <form class="mx-auto mt-8 flex max-w-xl gap-2 rounded-2xl bg-white p-2 shadow-xl" @submit.prevent="search">
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Ресторан, эмнэлэг, засварын газар..."
                        class="w-full rounded-xl border-0 px-4 py-3 text-sm text-slate-900 placeholder-slate-400 outline-none"
                    />
                    <button type="submit" class="btn-primary shrink-0 !px-6 !py-3">Хайх</button>
                </form>
            </div>
        </section>

        <!-- Categories -->
        <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
            <div class="mb-6 flex items-end justify-between">
                <h2 class="text-xl font-bold text-slate-900 sm:text-2xl">Ангилалууд</h2>
                <router-link :to="{ name: 'search' }" class="text-sm font-semibold text-brand-600 hover:text-brand-700">Бүгдийг харах →</router-link>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <router-link
                    v-for="category in categories"
                    :key="category.id"
                    :to="{ name: 'category', params: { slug: category.slug } }"
                    class="card flex flex-col items-center gap-2 p-4 text-center transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md"
                >
                    <span class="text-3xl">{{ category.icon }}</span>
                    <span class="text-sm font-semibold text-slate-800">{{ category.name }}</span>
                    <span class="text-xs text-slate-400">{{ category.listings_count }} байгууллага</span>
                </router-link>
            </div>
        </section>

        <!-- Featured -->
        <section v-if="featured.length" class="bg-white">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
                <div class="mb-6 flex items-end justify-between">
                    <h2 class="text-xl font-bold text-slate-900 sm:text-2xl">⭐ Онцлох байгууллагууд</h2>
                    <router-link :to="{ name: 'search', query: { featured: 1 } }" class="text-sm font-semibold text-brand-600 hover:text-brand-700">Бүгдийг харах →</router-link>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <ListingCard v-for="listing in featured" :key="listing.id" :listing="listing" />
                </div>
            </div>
        </section>

        <!-- Latest -->
        <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
            <div class="mb-6 flex items-end justify-between">
                <h2 class="text-xl font-bold text-slate-900 sm:text-2xl">Шинээр нэмэгдсэн</h2>
            </div>
            <div v-if="loading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="i in 4" :key="i" class="card h-64 animate-pulse bg-slate-100"></div>
            </div>
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <ListingCard v-for="listing in latest" :key="listing.id" :listing="listing" />
            </div>
        </section>

        <!-- CTA -->
        <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6">
            <div class="card overflow-hidden bg-gradient-to-r from-slate-900 to-slate-800 p-8 text-center sm:p-12">
                <h2 class="text-2xl font-extrabold text-white sm:text-3xl">Танд бизнес байна уу?</h2>
                <p class="mx-auto mt-3 max-w-xl text-slate-300">
                    Байгууллагаа үнэгүй бүртгүүлж, мянга мянган хэрэглэгчид хүрээрэй. Онцлох байршуулалтаар илүү олон харагдана.
                </p>
                <router-link :to="{ name: 'listing-create' }" class="btn-primary mt-6 !px-8 !py-3">Бизнесээ бүртгүүлэх</router-link>
            </div>
        </section>
    </div>
</template>
