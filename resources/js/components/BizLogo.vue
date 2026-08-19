<script setup>
defineProps({
    business: { type: Object, required: true },
    size: { type: String, default: 'h-9 w-9 text-[14px] rounded-[9px]' },
});
</script>

<template>
    <img
        v-if="business.logo_url"
        :src="business.logo_url"
        :alt="business.name"
        class="shrink-0 border border-line object-cover"
        :class="size"
    />
    <div v-else class="flex shrink-0 items-center justify-center bg-ink font-extrabold text-white" :class="size">
        {{ business.name?.charAt(0)?.toUpperCase() }}
    </div>
</template>
