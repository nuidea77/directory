<script setup>
import { computed } from 'vue';

/**
 * Дэд ангиллын ОЛОН сонголт — сонгосон үндсэн ангилал дотор.
 * Ж: Гоо сайхан → «Үсчин» ба «Хумсны засал» хоёуланг нь сонгож болно.
 * 3 дахь түвшний ангилал дэд ангиллынхаа доор догол мөрөөр гарна.
 */
const props = defineProps({
    // Үндсэн ангиллын шууд дэд ангиллууд (children, тус бүр children-тэй)
    items: { type: Array, default: () => [] },
    // Сонгосон ангиллын id-ийн массив
    modelValue: { type: Array, default: () => [] },
    max: { type: Number, default: 5 },
});

const emit = defineEmits(['update:modelValue']);

const selected = computed(() => props.modelValue.map(Number));
const full = computed(() => selected.value.length >= props.max);

function isOn(id) {
    return selected.value.includes(Number(id));
}

function toggle(id) {
    const value = Number(id);
    if (isOn(value)) {
        emit('update:modelValue', selected.value.filter((x) => x !== value));
        return;
    }
    if (full.value) return;
    emit('update:modelValue', [...selected.value, value]);
}
</script>

<template>
    <div>
        <div v-if="!items.length" class="rounded-[9px] border border-dashed border-inputline px-3.5 py-3 text-[12.5px] text-mute">
            Энэ ангилалд дэд ангилал алга — үндсэн ангилалаараа бүртгэгдэнэ.
        </div>

        <!-- Дэд ангилал зэрэгцээ; доторх 3 дахь түвшинтэй нь бүтэн мөр эзэлнэ -->
        <div v-else class="flex flex-wrap items-start gap-2">
            <div v-for="sub in items" :key="sub.id" :class="sub.children?.length ? 'w-full' : ''">
                <button
                    type="button"
                    class="cursor-pointer rounded-full border px-3 py-1.5 text-[12.5px] font-semibold transition"
                    :class="isOn(sub.id) ? 'border-brand bg-brand text-white' : full ? 'border-inputline bg-white text-ph' : 'border-inputline bg-white text-body hover:border-brand'"
                    :aria-pressed="isOn(sub.id)"
                    @click="toggle(sub.id)"
                >
                    <span v-if="isOn(sub.id)">✓ </span>{{ sub.name }}
                </button>

                <!-- 3 дахь түвшин -->
                <div v-if="sub.children?.length" class="ml-4 mt-1.5 flex flex-wrap gap-1.5 border-l border-line pl-3">
                    <button
                        v-for="g in sub.children"
                        :key="g.id"
                        type="button"
                        class="cursor-pointer rounded-full border px-2.5 py-1 text-[11.5px] font-semibold transition"
                        :class="isOn(g.id) ? 'border-brand bg-bluetint text-brand' : full ? 'border-searchline bg-white text-ph' : 'border-searchline bg-white text-body hover:border-brand'"
                        :aria-pressed="isOn(g.id)"
                        @click="toggle(g.id)"
                    >
                        <span v-if="isOn(g.id)">✓ </span>{{ g.name }}
                    </button>
                </div>
            </div>
        </div>

        <p class="mt-2 text-[11.5px] text-mute">
            {{ selected.length ? `${selected.length} / ${max} сонгосон` : 'Хэд хэдэн дэд ангилал сонгож болно' }}
        </p>
    </div>
</template>
