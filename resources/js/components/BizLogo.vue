<script setup>
import { computed } from 'vue';

/**
 * Бизнесийн лого. Лого оруулаагүй бол нэрнээс нь тогтмол өнгөтэй
 * үсэг-лого (avatar) үүсгэнэ — жагсаалт хоосон харагдахгүй.
 */
const props = defineProps({
    business: { type: Object, required: true },
    size: { type: String, default: 'h-9 w-9 text-[14px] rounded-[9px]' },
});

// Нэр бүрт ижил өнгө оноогдоно (санамсаргүй биш — дахин ачаалахад өөрчлөгдөхгүй)
const PALETTE = [
    'bg-[#0e8f52]', 'bg-[#0f766e]', 'bg-[#166534]', 'bg-[#115e59]',
    'bg-[#3f6212]', 'bg-[#0369a1]', 'bg-[#7c2d12]', 'bg-[#4c1d95]',
];

const name = computed(() => (props.business?.name || '').trim());

const initials = computed(() => {
    const words = name.value.split(/\s+/).filter(Boolean);
    if (!words.length) return '?';
    const first = words[0][0] || '';
    const second = words.length > 1 ? words[1][0] : '';
    return (first + second).toUpperCase();
});

const color = computed(() => {
    let hash = 0;
    for (const ch of name.value) hash = (hash * 31 + ch.codePointAt(0)) % 9973;
    return PALETTE[hash % PALETTE.length];
});
</script>

<template>
    <img
        v-if="business.logo_url"
        :src="business.logo_url"
        :alt="business.name"
        class="shrink-0 border border-line bg-white object-cover"
        :class="size"
        loading="lazy"
    />
    <div
        v-else
        class="flex shrink-0 items-center justify-center font-extrabold uppercase leading-none tracking-tight text-white"
        :class="[size, color]"
        :title="business.name"
        aria-hidden="true"
    >{{ initials }}</div>
</template>
