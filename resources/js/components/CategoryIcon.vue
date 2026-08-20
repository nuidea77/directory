<script setup>
import { computed } from 'vue';
import { categoryIcon } from '../data/categoryIcons';

// Ангиллын icon-ыг нэгэн жигд хэмжээ, өнгөөр харуулна
const props = defineProps({
    name: { type: String, default: null },
    size: { type: Number, default: 18 },
});

const icon = computed(() => categoryIcon(props.name));
</script>

<template>
    <component :is="icon" :size="size" :stroke-width="1.75" aria-hidden="true" />
</template>
