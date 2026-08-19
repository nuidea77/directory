<script setup>
const props = defineProps({
    rating: { type: Number, default: 0 },
    editable: { type: Boolean, default: false },
    size: { type: String, default: 'h-4 w-4' },
});

const emit = defineEmits(['update:rating']);

function set(star) {
    if (props.editable) emit('update:rating', star);
}
</script>

<template>
    <div class="flex items-center gap-0.5" :class="{ 'cursor-pointer': editable }">
        <button
            v-for="star in 5"
            :key="star"
            type="button"
            :disabled="!editable"
            :class="[size, star <= Math.round(rating) ? 'text-amber-400' : 'text-slate-300', editable ? 'transition hover:scale-110' : 'cursor-default']"
            @click="set(star)"
        >
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-full w-full">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.077 10.1c-.783-.57-.38-1.81.588-1.81h4.915a1 1 0 00.95-.69l1.519-4.674z" />
            </svg>
        </button>
    </div>
</template>
