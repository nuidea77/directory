<script setup>
defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    label: { type: String, default: '' },
});
</script>

<template>
    <img v-if="src" :src="src" :alt="alt" class="h-full w-full object-cover" loading="lazy" />
    <div v-else class="img-ph flex h-full w-full items-center justify-center font-mono text-[11px] font-medium text-faint">
        {{ label }}
    </div>
</template>
