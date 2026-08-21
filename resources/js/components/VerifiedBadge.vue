<script setup>
// Баталгаажсан бизнесийн цэнхэр тэмдэг — бизнесийн нэрний ард гарна.
defineProps({
    size: { type: Number, default: 18 },
    title: { type: String, default: 'Баталгаажсан бизнес' },
});
</script>

<template>
    <span class="inline-flex shrink-0 align-middle text-brand" :title="title" role="img" :aria-label="title">
        <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="10" fill="currentColor" />
            <path d="M7.6 12.3l3 3 5.8-6" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </span>
</template>
