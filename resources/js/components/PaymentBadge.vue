<script setup>
import { computed } from 'vue';
import { paymentBrand } from '../data/paymentBrands';

/**
 * Зээлийн аппын тэмдэг: бодит лого байвал түүнийг, үгүй бол брэндийн
 * өнгөтэй товчлолыг харуулна (public/img/payments/{slug}.svg).
 */
const props = defineProps({
    // Брэндийн нэр («LendMN») эсвэл slug («lendmn»)
    name: { type: String, required: true },
    slug: { type: String, default: '' },
    logo: { type: String, default: '' },
    size: { type: Number, default: 24 },
});

const brand = computed(() => paymentBrand(props.slug || props.name));
</script>

<template>
    <img
        v-if="logo"
        :src="logo"
        :alt="name"
        class="shrink-0 rounded-[6px] object-contain"
        :style="{ width: `${size}px`, height: `${size}px` }"
        loading="lazy"
    />
    <span
        v-else
        class="flex shrink-0 items-center justify-center rounded-[7px] font-bold text-white"
        :style="{ width: `${size}px`, height: `${size}px`, backgroundColor: brand.color, fontSize: `${Math.round(size * 0.42)}px` }"
        aria-hidden="true"
    >{{ brand.short }}</span>
</template>
