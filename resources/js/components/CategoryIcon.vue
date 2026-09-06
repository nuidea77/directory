<script setup>
import { categoryFallbackIcon, staticCategoryIcon } from '../data/categoryIcons';
import { useLucideIcon } from '../composables/useLucideIcon';
import IconGlyph from './IconGlyph.vue';

// Ангиллын icon-ыг нэгэн жигд хэмжээ, өнгөөр харуулна
const props = defineProps({
    name: { type: String, default: null },
    size: { type: Number, default: 18 },
});

const { component, nodes } = useLucideIcon(() => props.name || '', staticCategoryIcon, categoryFallbackIcon);
</script>

<template>
    <IconGlyph :component="component" :nodes="nodes" :size="size" />
</template>
