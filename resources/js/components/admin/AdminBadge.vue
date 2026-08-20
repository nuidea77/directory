<script setup>
import { computed } from 'vue';

// Төлөв, тоо зэргийг нэгэн жигд харуулах жижиг тэмдэг
const props = defineProps({
    tone: { type: String, default: 'neutral' }, // neutral | brand | good | warn | bad
    mono: { type: Boolean, default: false },
});

const toneClass = computed(() => ({
    neutral: 'bg-chip text-chiptext',
    brand: 'bg-bluetint text-brand',
    good: 'bg-greentint text-green',
    warn: 'bg-amberbadge text-amber',
    bad: 'bg-redtint text-red',
}[props.tone] || 'bg-chip text-chiptext'));
</script>

<template>
    <span class="rounded-full px-2 py-0.5 text-[10.5px] font-semibold" :class="[toneClass, mono ? 'font-mono' : '']">
        <slot />
    </span>
</template>
