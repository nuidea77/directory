<script setup>
// Цагийн хуваарийн editor (3a/6a дизайн): өдөр бүр toggle + from/to
const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const days = [
    ['mon', 'Даваа'], ['tue', 'Мягмар'], ['wed', 'Лхагва'], ['thu', 'Пүрэв'],
    ['fri', 'Баасан'], ['sat', 'Хагас'], ['sun', 'Бүтэн'],
];

function dayValue(key) {
    return props.modelValue?.[key] || { from: '09:00', to: '19:00', closed: key === 'sun' };
}

function update(key, patch) {
    emit('update:modelValue', { ...props.modelValue, [key]: { ...dayValue(key), ...patch } });
}

function copyWeekdays() {
    const monday = dayValue('mon');
    const next = { ...props.modelValue };
    for (const [key] of days) {
        next[key] = { ...monday, closed: false };
    }
    emit('update:modelValue', next);
}

defineExpose({ copyWeekdays });
</script>

<template>
    <div>
        <div class="flex items-baseline justify-between">
            <slot name="title" />
            <button type="button" class="cursor-pointer text-[12.5px] font-semibold text-brand" @click="copyWeekdays">Даваагийн цагийг бүгдэд хуулах</button>
        </div>
        <div class="mt-3 overflow-hidden rounded-xl border border-line">
            <div
                v-for="[key, label] in days"
                :key="key"
                class="flex items-center gap-3 border-b border-hairline px-4 py-2.5 last:border-0"
                :class="{ 'bg-panel': dayValue(key).closed }"
            >
                <span class="w-[70px] text-[13px] font-semibold text-ink">{{ label }}</span>
                <button
                    type="button"
                    class="relative h-[21px] w-[38px] shrink-0 cursor-pointer rounded-full transition"
                    :class="dayValue(key).closed ? 'bg-[#dcdad4]' : 'bg-brand'"
                    @click="update(key, { closed: !dayValue(key).closed })"
                >
                    <span class="absolute top-0.5 h-[17px] w-[17px] rounded-full bg-white shadow transition-all" :style="{ left: dayValue(key).closed ? '2px' : '19px' }"></span>
                </button>
                <div class="flex items-center gap-1.5 text-[13px] font-medium" :class="dayValue(key).closed ? 'text-ph' : 'text-ink'">
                    <input
                        type="time"
                        :value="dayValue(key).from"
                        :disabled="dayValue(key).closed"
                        class="rounded-[7px] border border-searchline bg-white px-2 py-1.5 outline-none disabled:opacity-50"
                        @input="update(key, { from: $event.target.value })"
                    />
                    <span class="text-[#c9ccd1]">–</span>
                    <input
                        type="time"
                        :value="dayValue(key).to"
                        :disabled="dayValue(key).closed"
                        class="rounded-[7px] border border-searchline bg-white px-2 py-1.5 outline-none disabled:opacity-50"
                        @input="update(key, { to: $event.target.value })"
                    />
                </div>
                <span v-if="dayValue(key).closed" class="ml-auto text-[12px] font-medium text-mute">Амарна</span>
            </div>
        </div>
    </div>
</template>
