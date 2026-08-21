<script setup>
import { RouterLink } from 'vue-router';

/**
 * Нүүрийн блокуудын нэгдсэн толгой: icon + гарчиг + тайлбар + холбоос.
 */
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
    to: { type: [Object, String], default: null },
    linkLabel: { type: String, default: 'Бүгдийг үзэх →' },
});
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line pb-3">
        <div class="flex items-center gap-3">
            <span v-if="icon" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] border border-blueline bg-bluetint text-brand">
                <component :is="icon" :size="18" :stroke-width="1.9" aria-hidden="true" />
            </span>
            <div>
                <h2 class="text-[19px] font-bold tracking-[-.015em] text-ink">{{ title }}</h2>
                <p v-if="subtitle" class="mt-0.5 text-[12.5px] text-mute">{{ subtitle }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
            <RouterLink v-if="to" :to="to" class="rounded-lg border border-inputline bg-white px-3.5 py-2 text-[12.5px] font-semibold text-brand transition hover:border-brand">
                {{ linkLabel }}
            </RouterLink>
        </div>
    </div>
</template>
