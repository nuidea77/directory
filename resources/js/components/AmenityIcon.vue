<script setup>
import { computed } from 'vue';
import { amenityIcon } from '../data/amenityIcons';

const props = defineProps({
    name: { type: String, default: null },
    size: { type: Number, default: 16 },
});

const icon = computed(() => amenityIcon(props.name));
</script>

<template>
    <component :is="icon" :size="size" :stroke-width="1.75" aria-hidden="true" />
</template>
