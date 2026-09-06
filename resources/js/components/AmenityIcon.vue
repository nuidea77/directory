<script setup>
import { amenityFallbackIcon, staticAmenityIcon } from '../data/amenityIcons';
import { useLucideIcon } from '../composables/useLucideIcon';
import IconGlyph from './IconGlyph.vue';

const props = defineProps({
    name: { type: String, default: null },
    size: { type: Number, default: 16 },
});

// Түгээмэл icon шууд; ховор нэрийг lucide.json-оос лениво олно
const { component, nodes } = useLucideIcon(() => props.name || '', staticAmenityIcon, amenityFallbackIcon);
</script>

<template>
    <IconGlyph :component="component" :nodes="nodes" :size="size" />
</template>
