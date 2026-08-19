<script setup>
import RatingStars from './RatingStars.vue';

defineProps({
    listing: { type: Object, required: true },
});
</script>

<template>
    <router-link
        :to="{ name: 'listing', params: { slug: listing.slug } }"
        class="card group flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-md"
    >
        <div class="relative h-36 bg-gradient-to-br from-brand-50 to-slate-100">
            <img
                v-if="listing.cover_url"
                :src="listing.cover_url"
                :alt="listing.name"
                class="h-full w-full object-cover"
                loading="lazy"
            />
            <div v-else class="flex h-full items-center justify-center text-4xl opacity-60">
                {{ listing.category?.icon || '🏢' }}
            </div>
            <span
                v-if="listing.is_featured"
                class="absolute left-3 top-3 rounded-full bg-amber-400 px-2.5 py-1 text-xs font-bold text-amber-950 shadow"
            >★ Онцлох</span>
        </div>

        <div class="flex flex-1 flex-col gap-2 p-4">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-bold text-slate-900 group-hover:text-brand-700">{{ listing.name }}</h3>
                <img
                    v-if="listing.logo_url"
                    :src="listing.logo_url"
                    :alt="listing.name"
                    class="h-9 w-9 shrink-0 rounded-lg border border-slate-200 object-cover"
                    loading="lazy"
                />
            </div>

            <p v-if="listing.category" class="text-xs font-medium text-brand-600">
                {{ listing.category.icon }} {{ listing.category.name }}
            </p>

            <p v-if="listing.description" class="line-clamp-2 text-sm text-slate-500">{{ listing.description }}</p>

            <div class="mt-auto flex items-center justify-between pt-2">
                <div class="flex items-center gap-1.5">
                    <RatingStars :rating="listing.rating_avg" />
                    <span class="text-xs font-semibold text-slate-600">{{ listing.rating_avg?.toFixed(1) }}</span>
                    <span class="text-xs text-slate-400">({{ listing.reviews_count }})</span>
                </div>
                <span v-if="listing.district" class="text-xs text-slate-400">📍 {{ listing.district }}</span>
            </div>
        </div>
    </router-link>
</template>
