<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';
import RatingStars from '../components/RatingStars.vue';

const listings = ref([]);
const loading = ref(true);

async function fetchListings() {
    loading.value = true;
    try {
        const data = await api.get('/my/listings');
        listings.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function remove(listing) {
    if (!confirm(`"${listing.name}" бүртгэлийг устгах уу? Энэ үйлдлийг буцаах боломжгүй.`)) return;
    await api.delete(`/my/listings/${listing.id}`);
    await fetchListings();
}

onMounted(fetchListings);
</script>

<template>
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Миний бизнесүүд</h1>
                <p class="mt-1 text-sm text-slate-500">Бүртгэлээ удирдаж, онцлох байршуулалт аваарай.</p>
            </div>
            <router-link :to="{ name: 'listing-create' }" class="btn-primary">+ Бизнес нэмэх</router-link>
        </div>

        <div v-if="loading" class="mt-8 space-y-4">
            <div v-for="i in 3" :key="i" class="card h-28 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else-if="listings.length" class="mt-8 space-y-4">
            <div v-for="listing in listings" :key="listing.id" class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                <img
                    v-if="listing.logo_url"
                    :src="listing.logo_url"
                    class="h-14 w-14 rounded-xl border border-slate-200 object-cover"
                />
                <div v-else class="flex h-14 w-14 items-center justify-center rounded-xl bg-brand-50 text-2xl">
                    {{ listing.category?.icon || '🏢' }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <router-link :to="{ name: 'listing', params: { slug: listing.slug } }" class="font-bold text-slate-900 hover:text-brand-700">
                            {{ listing.name }}
                        </router-link>
                        <span v-if="listing.is_featured" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700">
                            ★ {{ new Date(listing.featured_until).toLocaleDateString() }} хүртэл онцлох
                        </span>
                    </div>
                    <div class="mt-1 flex items-center gap-3 text-sm text-slate-500">
                        <span class="flex items-center gap-1">
                            <RatingStars :rating="listing.rating_avg" size="h-3.5 w-3.5" />
                            {{ listing.rating_avg?.toFixed(1) }}
                        </span>
                        <span>{{ listing.views_count }} үзэлт</span>
                        <span>{{ listing.reviews_count }} үнэлгээ</span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <router-link :to="{ name: 'listing-feature', params: { id: listing.id } }" class="btn-primary !bg-amber-500 !px-4 !py-2 text-xs hover:!bg-amber-600">
                        ★ Онцлох болгох
                    </router-link>
                    <router-link :to="{ name: 'listing-edit', params: { id: listing.id } }" class="btn-secondary !px-4 !py-2 text-xs">Засах</router-link>
                    <button class="btn-secondary !border-red-200 !px-4 !py-2 text-xs !text-red-600 hover:!bg-red-50" @click="remove(listing)">Устгах</button>
                </div>
            </div>
        </div>

        <div v-else class="card mt-8 p-16 text-center">
            <p class="text-4xl">🏪</p>
            <p class="mt-4 font-semibold text-slate-700">Танд бүртгэлтэй бизнес алга</p>
            <p class="mt-1 text-sm text-slate-500">Эхний бизнесээ бүртгүүлээд мянга мянган хэрэглэгчид хүрээрэй.</p>
            <router-link :to="{ name: 'listing-create' }" class="btn-primary mt-6">Бизнес нэмэх</router-link>
        </div>
    </div>
</template>
