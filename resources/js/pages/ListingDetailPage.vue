<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../api';
import { useAuthStore } from '../stores/auth';
import RatingStars from '../components/RatingStars.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const listing = ref(null);
const loading = ref(true);
const notFound = ref(false);

const reviewForm = ref({ rating: 5, comment: '' });
const reviewError = ref('');
const reviewSubmitting = ref(false);
const favoriteBusy = ref(false);

const weekdays = {
    mon: 'Даваа', tue: 'Мягмар', wed: 'Лхагва', thu: 'Пүрэв',
    fri: 'Баасан', sat: 'Бямба', sun: 'Ням',
};

async function fetchListing() {
    loading.value = true;
    notFound.value = false;
    try {
        const data = await api.get(`/listings/${route.params.slug}`);
        listing.value = data.data;
    } catch (e) {
        if (e instanceof ApiError && e.status === 404) notFound.value = true;
    } finally {
        loading.value = false;
    }
}

async function toggleFavorite() {
    if (!auth.isLoggedIn) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    favoriteBusy.value = true;
    try {
        const data = await api.post(`/listings/${listing.value.id}/favorite`);
        listing.value.is_favorited = data.favorited;
    } finally {
        favoriteBusy.value = false;
    }
}

async function submitReview() {
    if (!auth.isLoggedIn) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }
    reviewError.value = '';
    reviewSubmitting.value = true;
    try {
        await api.post(`/listings/${listing.value.id}/reviews`, reviewForm.value);
        reviewForm.value = { rating: 5, comment: '' };
        await fetchListing();
    } catch (e) {
        reviewError.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    } finally {
        reviewSubmitting.value = false;
    }
}

watch(() => route.params.slug, fetchListing);
onMounted(fetchListing);
</script>

<template>
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        <div v-if="loading" class="space-y-4">
            <div class="card h-56 animate-pulse bg-slate-100"></div>
            <div class="card h-40 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else-if="notFound" class="card p-16 text-center">
            <p class="text-4xl">🤷</p>
            <p class="mt-4 font-semibold text-slate-700">Байгууллага олдсонгүй</p>
            <router-link :to="{ name: 'search' }" class="btn-primary mt-6">Хайлт руу буцах</router-link>
        </div>

        <template v-else-if="listing">
            <!-- Cover -->
            <div class="card relative h-52 overflow-hidden sm:h-64">
                <img v-if="listing.cover_url" :src="listing.cover_url" :alt="listing.name" class="h-full w-full object-cover" />
                <div v-else class="flex h-full items-center justify-center bg-gradient-to-br from-brand-100 to-slate-100 text-7xl">
                    {{ listing.category?.icon || '🏢' }}
                </div>
                <span v-if="listing.is_featured" class="absolute left-4 top-4 rounded-full bg-amber-400 px-3 py-1 text-xs font-bold text-amber-950 shadow">★ Онцлох</span>
            </div>

            <!-- Header -->
            <div class="card -mt-10 relative z-10 mx-4 p-6 sm:mx-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-4">
                        <img v-if="listing.logo_url" :src="listing.logo_url" :alt="listing.name" class="h-16 w-16 rounded-2xl border border-slate-200 object-cover" />
                        <div>
                            <h1 class="text-2xl font-extrabold text-slate-900">{{ listing.name }}</h1>
                            <p v-if="listing.category" class="mt-1 text-sm font-medium text-brand-600">
                                {{ listing.category.icon }} {{ listing.category.name }}
                            </p>
                            <div class="mt-2 flex items-center gap-2">
                                <RatingStars :rating="listing.rating_avg" />
                                <span class="text-sm font-bold text-slate-700">{{ listing.rating_avg?.toFixed(1) }}</span>
                                <span class="text-sm text-slate-400">· {{ listing.reviews_count }} үнэлгээ · {{ listing.views_count }} үзэлт</span>
                            </div>
                        </div>
                    </div>
                    <button
                        class="btn-secondary shrink-0"
                        :class="{ '!border-red-200 !bg-red-50 !text-red-600': listing.is_favorited }"
                        :disabled="favoriteBusy"
                        @click="toggleFavorite"
                    >
                        {{ listing.is_favorited ? '❤️ Хадгалсан' : '🤍 Хадгалах' }}
                    </button>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main column -->
                <div class="space-y-6 lg:col-span-2">
                    <div v-if="listing.description" class="card p-6">
                        <h2 class="mb-3 font-bold text-slate-900">Танилцуулга</h2>
                        <p class="whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ listing.description }}</p>
                    </div>

                    <div v-if="listing.images?.length" class="card p-6">
                        <h2 class="mb-3 font-bold text-slate-900">Зургууд</h2>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <a v-for="img in listing.images" :key="img.id" :href="img.url" target="_blank" rel="noopener">
                                <img :src="img.url" class="h-32 w-full rounded-xl object-cover transition hover:opacity-90" loading="lazy" />
                            </a>
                        </div>
                    </div>

                    <!-- Reviews -->
                    <div class="card p-6">
                        <h2 class="mb-4 font-bold text-slate-900">Үнэлгээ, сэтгэгдэл</h2>

                        <form class="mb-6 space-y-3 rounded-xl bg-slate-50 p-4" @submit.prevent="submitReview">
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-medium text-slate-700">Таны үнэлгээ:</span>
                                <RatingStars v-model:rating="reviewForm.rating" :rating="reviewForm.rating" editable size="h-6 w-6" />
                            </div>
                            <textarea
                                v-model="reviewForm.comment"
                                rows="2"
                                placeholder="Сэтгэгдлээ бичнэ үү (заавал биш)..."
                                class="input resize-none"
                            ></textarea>
                            <p v-if="reviewError" class="text-sm text-red-600">{{ reviewError }}</p>
                            <button type="submit" class="btn-primary" :disabled="reviewSubmitting">
                                {{ auth.isLoggedIn ? 'Үнэлгээ өгөх' : 'Нэвтэрч үнэлгээ өгөх' }}
                            </button>
                        </form>

                        <div v-if="listing.reviews?.length" class="space-y-4">
                            <div v-for="review in listing.reviews" :key="review.id" class="border-b border-slate-100 pb-4 last:border-0">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">
                                            {{ review.user?.name?.charAt(0)?.toUpperCase() }}
                                        </span>
                                        <span class="text-sm font-semibold text-slate-800">{{ review.user?.name }}</span>
                                    </div>
                                    <RatingStars :rating="review.rating" />
                                </div>
                                <p v-if="review.comment" class="mt-2 text-sm text-slate-600">{{ review.comment }}</p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-slate-400">Одоогоор үнэлгээ алга. Та анхны үнэлгээг өгөөрэй!</p>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <div class="card p-6">
                        <h2 class="mb-4 font-bold text-slate-900">Холбоо барих</h2>
                        <div class="space-y-3 text-sm">
                            <a :href="`tel:${listing.phone}`" class="flex items-center gap-3 font-semibold text-brand-600 hover:text-brand-700">
                                📞 {{ listing.phone }}
                            </a>
                            <a v-if="listing.email" :href="`mailto:${listing.email}`" class="flex items-center gap-3 text-slate-600 hover:text-brand-600">
                                ✉️ {{ listing.email }}
                            </a>
                            <a v-if="listing.website" :href="listing.website" target="_blank" rel="noopener" class="flex items-center gap-3 text-slate-600 hover:text-brand-600">
                                🌐 Вэбсайт
                            </a>
                            <p v-if="listing.address" class="flex items-start gap-3 text-slate-600">
                                📍 {{ listing.address }}
                            </p>
                            <div v-if="listing.socials" class="flex gap-3 pt-1">
                                <a v-if="listing.socials.facebook" :href="listing.socials.facebook" target="_blank" rel="noopener" class="text-slate-500 hover:text-brand-600">Facebook</a>
                                <a v-if="listing.socials.instagram" :href="listing.socials.instagram" target="_blank" rel="noopener" class="text-slate-500 hover:text-brand-600">Instagram</a>
                            </div>
                        </div>
                    </div>

                    <div v-if="listing.opening_hours && Object.keys(listing.opening_hours).length" class="card p-6">
                        <h2 class="mb-4 font-bold text-slate-900">Цагийн хуваарь</h2>
                        <div class="space-y-2 text-sm">
                            <div v-for="(label, key) in weekdays" :key="key" class="flex justify-between">
                                <span class="text-slate-500">{{ label }}</span>
                                <span class="font-medium text-slate-700">{{ listing.opening_hours[key] || 'Амарна' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
