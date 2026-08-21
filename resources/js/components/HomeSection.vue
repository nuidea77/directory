<script setup>
import { RouterLink } from 'vue-router';
import BizLogo from './BizLogo.vue';
import VerifiedBadge from './VerifiedBadge.vue';

/**
 * Нүүрийн сэдэвчилсэн блок: «Хаана хооллох вэ?», «Болзоход тохиромжтой» гэх мэт.
 * Жижиг мөр карт — лого, нэр, үнэлгээ, үнийн зэрэглэл, байршил.
 */
defineProps({
    section: { type: Object, required: true },
    city: { type: String, default: '' },
});

function linkTo(section) {
    const l = section.link || {};
    if (l.name === 'category') return { name: 'category', params: { slug: l.slug } };
    return { name: 'search', query: l.query || {} };
}
</script>

<template>
    <section class="mx-auto max-w-7xl px-5 pt-9 sm:px-10">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold tracking-[-.015em] text-ink">{{ section.title }}</h2>
                <p v-if="section.subtitle" class="mt-1 text-[12.5px] text-mute">{{ section.subtitle }}</p>
            </div>
            <RouterLink :to="linkTo(section)" class="text-[13px] font-semibold text-brand hover:text-brand-dark">Бүгдийг үзэх →</RouterLink>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
            <RouterLink
                v-for="item in section.items"
                :key="item.id"
                :to="{ name: 'business', params: { slug: item.slug } }"
                class="flex items-center gap-3 rounded-[11px] border border-transparent p-2 transition hover:border-line hover:bg-panel"
            >
                <div v-if="item.cover_url" class="h-[56px] w-[64px] shrink-0 overflow-hidden rounded-[9px]">
                    <img :src="item.cover_url" :alt="item.name" class="h-full w-full object-cover" loading="lazy" />
                </div>
                <BizLogo v-else :business="item" size="h-[56px] w-[64px] rounded-[9px] text-[15px]" />

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="truncate text-[14px] font-bold text-ink">{{ item.name }}</span>
                        <VerifiedBadge v-if="item.is_verified" :size="14" />
                        <span v-if="item.is_24_7" class="shrink-0 rounded-[4px] bg-greentint px-1.5 py-0.5 text-[9.5px] font-bold text-green">24/7</span>
                    </div>
                    <div class="mt-1 flex items-center gap-1.5 text-[12.5px]">
                        <span class="text-amberdot">★</span>
                        <span class="font-bold text-ink">{{ (item.rating_avg || 0).toFixed(1) }}</span>
                        <span class="text-mute">({{ item.reviews_count }})</span>
                    </div>
                    <div class="mt-0.5 flex items-center gap-2 truncate text-[12px] text-mute">
                        <span v-if="item.price_level" class="font-semibold text-body">{{ item.price_level }}</span>
                        <span class="truncate">{{ item.district }}{{ item.category ? ' · ' + item.category : '' }}</span>
                    </div>
                </div>
            </RouterLink>
        </div>
    </section>
</template>
