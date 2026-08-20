<script setup>
import { computed } from 'vue';

/**
 * KPI карт: шошго, утга, нэмэлт тайлбар, icon.
 * tone — утгын өнгө: default | good | warn | bad
 */
const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'default' },
    icon: { type: [Object, Function], default: null },
    to: { type: [Object, String], default: null },
});

const valueClass = computed(() => ({
    good: 'text-green',
    warn: 'text-amber',
    bad: 'text-red',
    default: 'text-ink',
}[props.tone] || 'text-ink'));

const iconClass = computed(() => ({
    good: 'border-greenline bg-greentint text-green',
    warn: 'border-amberline bg-amberbadge text-amber',
    bad: 'border-redline bg-redtint text-red',
    default: 'border-blueline bg-bluetint text-brand',
}[props.tone] || 'border-blueline bg-bluetint text-brand'));

const display = computed(() => (typeof props.value === 'number' ? props.value.toLocaleString() : props.value));
</script>

<template>
    <component
        :is="to ? 'router-link' : 'div'"
        :to="to || undefined"
        class="card flex items-start gap-3 p-4"
        :class="to ? 'transition hover:border-blueline hover:shadow-sm' : ''"
    >
        <span v-if="icon" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] border" :class="iconClass">
            <component :is="icon" :size="17" :stroke-width="1.75" aria-hidden="true" />
        </span>
        <div class="min-w-0">
            <div class="text-[11px] font-semibold tracking-[.06em] text-mute">{{ label.toUpperCase() }}</div>
            <div class="mt-1.5 text-[22px] font-extrabold leading-none tracking-[-.02em]" :class="valueClass">{{ display }}</div>
            <div v-if="hint" class="mt-1.5 text-[11.5px] font-medium text-mute">{{ hint }}</div>
        </div>
    </component>
</template>
