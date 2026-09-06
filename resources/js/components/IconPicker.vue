<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { amenityIcon, amenityIconNames } from '../data/amenityIcons';
import { categoryIcon, iconNames as categoryIconNames } from '../data/categoryIcons';

/**
 * Icon сонгогч: нэрээр биш, ДҮРСЭЭР нь харж сонгоно.
 * Товч дээр дарахад бүх icon-ыг тор хэлбэрээр харуулна.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    // amenity = үйлчилгээ/онцлогийн сан, category = ангиллын сан
    type: { type: String, default: 'amenity' },
    // Icon-гүй (ерөнхий) болгох сонголт үзүүлэх эсэх
    clearable: { type: Boolean, default: false },
    size: { type: Number, default: 17 },
});

const emit = defineEmits(['update:modelValue']);

const isCategory = computed(() => props.type === 'category');
const names = computed(() => (isCategory.value ? categoryIconNames : amenityIconNames));
const resolve = (name) => (isCategory.value ? categoryIcon(name) : amenityIcon(name));

const open = ref(false);
const q = ref('');
const root = ref(null);
// Дэлгэцийн ирмэгээс хальж таслагдахгүйн тулд талыг нь тохируулна
const alignRight = ref(false);

const PANEL_WIDTH = 292;

function toggle() {
    if (! open.value && root.value) {
        const rect = root.value.getBoundingClientRect();
        alignRight.value = rect.left + PANEL_WIDTH > window.innerWidth - 16;
    }

    open.value = ! open.value;
}

const filtered = computed(() => {
    const needle = q.value.trim().toLowerCase();
    return needle ? names.value.filter((n) => n.includes(needle)) : names.value;
});

function pick(name) {
    emit('update:modelValue', name);
    open.value = false;
    q.value = '';
}

// Гадуур дарахад хаана
function onDocClick(e) {
    if (open.value && root.value && !root.value.contains(e.target)) open.value = false;
}

function onKey(e) {
    if (e.key === 'Escape') open.value = false;
}

onMounted(() => {
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKey);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex cursor-pointer items-center gap-2 rounded-lg border border-inputline bg-white px-2 py-1.5 hover:border-brand"
            :aria-expanded="open"
            aria-label="Icon сонгох"
            @click="toggle"
        >
            <span class="flex h-[26px] w-[26px] items-center justify-center rounded-md border border-blueline bg-bluetint text-brand">
                <component :is="resolve(modelValue)" :size="size" :stroke-width="1.75" aria-hidden="true" />
            </span>
            <span class="text-[11.5px] text-mute">▾</span>
        </button>

        <div
            v-if="open"
            class="absolute top-[calc(100%+6px)] z-50 w-[292px] rounded-xl border border-line bg-white p-2.5 shadow-lg"
            :class="alignRight ? 'right-0' : 'left-0'"
        >
            <input
                v-model="q"
                class="input !py-1.5 !text-[12px]"
                placeholder="Icon хайх (parking, wifi …)"
                @click.stop
            />
            <div class="mt-2 grid max-h-[230px] grid-cols-8 gap-1 overflow-y-auto">
                <button
                    v-if="clearable && !q"
                    type="button"
                    title="Ерөнхий (icon-гүй)"
                    class="flex h-[31px] w-[31px] cursor-pointer items-center justify-center rounded-md border text-[13px] font-bold"
                    :class="!modelValue ? 'border-brand bg-bluetint text-brand' : 'border-transparent text-mute hover:bg-panel'"
                    @click.stop="pick('')"
                >✕</button>
                <button
                    v-for="n in filtered"
                    :key="n"
                    type="button"
                    :title="n"
                    class="flex h-[31px] w-[31px] cursor-pointer items-center justify-center rounded-md border"
                    :class="n === modelValue ? 'border-brand bg-bluetint text-brand' : 'border-transparent text-body hover:bg-panel'"
                    @click.stop="pick(n)"
                >
                    <component :is="resolve(n)" :size="16" :stroke-width="1.75" aria-hidden="true" />
                </button>
            </div>
            <p v-if="!filtered.length" class="py-4 text-center text-[12px] text-mute">Олдсонгүй</p>
        </div>
    </div>
</template>
