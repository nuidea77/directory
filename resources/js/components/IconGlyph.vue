<script setup>
/**
 * Icon-ыг зурна: статик Vue component эсвэл lucide.json-оос ирсэн
 * зурах өгөгдөл (nodes). Хоёул байхгүй бол зай нь хадгалагдана.
 */
defineProps({
    component: { type: [Object, Function], default: null },
    nodes: { type: Array, default: null },
    size: { type: Number, default: 16 },
});
</script>

<template>
    <component :is="component" v-if="component" :size="size" :stroke-width="1.75" aria-hidden="true" />

    <svg
        v-else-if="nodes"
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <component :is="tag" v-for="([tag, attrs], i) in nodes" :key="i" v-bind="attrs" />
    </svg>

    <span v-else :style="{ width: `${size}px`, height: `${size}px` }" aria-hidden="true"></span>
</template>
