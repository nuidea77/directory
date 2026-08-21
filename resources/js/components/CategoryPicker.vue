<script setup>
import { computed } from 'vue';
import { flattenCategories, optionLabel } from '../utils/categories';

/**
 * Нэмэлт ангиллын сонголт — нэг бизнес олон ангилалд харагдана
 * (ж: үсчин + хумсны засал + гоо сайхны салон).
 * modelValue: нэмэлт ангиллын id-ийн массив. Үндсэн ангилал энд ордоггүй.
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    primaryId: { type: [Number, String], default: null },
    // Үндсэнийг оруулаад нийт хэдэн ангилал зөвшөөрөх
    max: { type: Number, default: 5 },
});

const emit = defineEmits(['update:modelValue']);

const flat = computed(() => flattenCategories(props.categories));

const selected = computed(() => props.modelValue
    .map((id) => flat.value.find((c) => c.id === Number(id)))
    .filter(Boolean));

const options = computed(() => flat.value.filter((c) => c.id !== Number(props.primaryId)
    && !props.modelValue.map(Number).includes(c.id)));

// Үндсэн ангилал 1 байр эзэлнэ
const full = computed(() => props.modelValue.length + 1 >= props.max);

function add(id) {
    if (!id || full.value) return;
    emit('update:modelValue', [...props.modelValue, Number(id)]);
}

function remove(id) {
    emit('update:modelValue', props.modelValue.filter((x) => Number(x) !== Number(id)));
}
</script>

<template>
    <div>
        <div v-if="selected.length" class="mb-2 flex flex-wrap gap-1.5">
            <span
                v-for="c in selected"
                :key="c.id"
                class="inline-flex items-center gap-1.5 rounded-full border border-blueline bg-bluetint px-2.5 py-1 text-[12px] font-semibold text-brand"
            >
                {{ c.name }}
                <button type="button" class="cursor-pointer text-[13px] leading-none text-brand/70 hover:text-brand" :aria-label="`${c.name} хасах`" @click="remove(c.id)">×</button>
            </span>
        </div>

        <select
            :value="''"
            class="input cursor-pointer"
            :disabled="full || !options.length"
            @change="add($event.target.value); $event.target.value = ''"
        >
            <option value="">{{ full ? `Дээд тал нь ${max} ангилал` : '+ Ангилал нэмэх' }}</option>
            <option v-for="c in options" :key="c.id" :value="c.id">{{ optionLabel(c) }}</option>
        </select>
    </div>
</template>
