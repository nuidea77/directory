<script setup>
import ImagePh from './ImagePh.vue';

// Нүүрийн "Онцлох бизнесүүд" карт (1a дизайн)
defineProps({
    business: { type: Object, required: true },
});

function coverOf(business) {
    const branch = (business.branches || []).find((b) => b.cover_url);
    return branch?.cover_url || null;
}

function mainBranch(business) {
    return (business.branches || [])[0] || null;
}
</script>

<template>
    <router-link :to="{ name: 'business', params: { slug: business.slug } }" class="card block overflow-hidden bg-white transition hover:-translate-y-0.5 hover:shadow-md">
        <div class="relative h-[132px]">
            <ImagePh :src="coverOf(business)" :alt="business.name" label="ЗУРАГ 4:3" />
            <span v-if="business.is_featured" class="badge-featured absolute left-3 top-3 shadow-sm">ОНЦЛОХ</span>
        </div>
        <div class="p-4">
            <div class="flex items-center gap-2">
                <div class="truncate text-[15px] font-bold text-ink">{{ business.name }}</div>
                <span v-if="business.is_verified" class="rounded-[4px] bg-bluetint px-1.5 py-0.5 text-[10px] font-semibold text-brand">✓</span>
            </div>
            <div class="mt-1 text-[12.5px] text-mute">{{ business.category?.name }} · {{ mainBranch(business)?.district }}</div>
            <div class="mt-2.5 flex items-center gap-2.5">
                <div class="text-[13px] font-bold text-ink">{{ (business.rating_avg || 0).toFixed(1) }}</div>
                <div class="text-[12px] text-mute">{{ business.reviews_total || 0 }} сэтгэгдэл</div>
                <div class="ml-auto text-[12px] font-semibold" :class="mainBranch(business)?.is_open ? 'text-green' : 'text-amberdark'">
                    {{ mainBranch(business)?.is_open ? 'Нээлттэй' : (mainBranch(business)?.open_label || '') }}
                </div>
            </div>
            <div class="my-3 h-px bg-divider"></div>
            <div class="flex gap-2 text-[12.5px] font-semibold">
                <div class="flex-1 rounded-lg border border-inputline py-2 text-center text-ink">{{ mainBranch(business)?.phone }}</div>
                <div class="flex-1 rounded-lg bg-brand py-2 text-center text-white">Дэлгэрэнгүй</div>
            </div>
        </div>
    </router-link>
</template>
