<script setup>
defineProps({
    meta: { type: Object, required: true },
});

defineEmits(['change']);
</script>

<template>
    <div v-if="meta.last_page > 1" class="mt-8 flex items-center justify-center gap-2">
        <button
            class="btn-secondary !px-3.5 !py-2"
            :disabled="meta.current_page <= 1"
            @click="$emit('change', meta.current_page - 1)"
        >←</button>
        <span class="px-3 text-sm font-medium text-slate-600">
            {{ meta.current_page }} / {{ meta.last_page }}
        </span>
        <button
            class="btn-secondary !px-3.5 !py-2"
            :disabled="meta.current_page >= meta.last_page"
            @click="$emit('change', meta.current_page + 1)"
        >→</button>
    </div>
</template>
