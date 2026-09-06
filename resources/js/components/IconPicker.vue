<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { amenityFallbackIcon, amenityIconNames, staticAmenityIcon } from '../data/amenityIcons';
import { categoryFallbackIcon, iconNames as categoryIconNames, staticCategoryIcon } from '../data/categoryIcons';
import { allIconsSync, loadAllIcons } from '../data/lucideAll';
import IconGlyph from './IconGlyph.vue';

/**
 * Icon сонгогч: нэрээр биш, ДҮРСЭЭР нь харж сонгоно.
 * Нээхэд lucide-ийн бүх icon (1,700+) лениво татагдана.
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

// Нэг дор хэт олон SVG зурахгүй — үлдсэнийг нь хайлтаар олно
const RENDER_LIMIT = 168;
const PANEL_WIDTH = 292;

const isCategory = computed(() => props.type === 'category');
const staticResolve = (name) => (isCategory.value ? staticCategoryIcon(name) : staticAmenityIcon(name));
const fallbackIcon = computed(() => (isCategory.value ? categoryFallbackIcon : amenityFallbackIcon));

const open = ref(false);
const q = ref('');
const root = ref(null);
const alignRight = ref(false);
const all = ref(allIconsSync());
const loading = ref(false);

// Түгээмэл (сангийн) icon-уудыг эхэнд, бусдыг цагаан толгойн дарааллаар
const common = computed(() => (isCategory.value ? categoryIconNames : amenityIconNames));

const names = computed(() => {
    if (! all.value) return common.value;

    const seen = new Set(common.value);
    const rest = Object.keys(all.value).filter((n) => ! seen.has(n)).sort();

    return [...common.value, ...rest];
});

const matches = computed(() => {
    const needle = q.value.trim().toLowerCase();
    return needle ? names.value.filter((n) => n.includes(needle)) : names.value;
});

const shown = computed(() => matches.value.slice(0, RENDER_LIMIT));
const hidden = computed(() => Math.max(0, matches.value.length - shown.value.length));

// Статик component эсвэл lucide.json-оос ирсэн зурах өгөгдөл
function glyph(name) {
    const fromStatic = staticResolve(name);

    if (fromStatic) return { component: fromStatic, nodes: null };

    const data = all.value && all.value[name];

    return data ? { component: null, nodes: data } : { component: fallbackIcon.value, nodes: null };
}

async function toggle() {
    if (open.value) {
        open.value = false;

        return;
    }

    if (root.value) {
        const rect = root.value.getBoundingClientRect();
        alignRight.value = rect.left + PANEL_WIDTH > window.innerWidth - 16;
    }

    open.value = true;

    if (! all.value) {
        loading.value = true;
        all.value = await loadAllIcons();
        loading.value = false;
    }
}

function pick(name) {
    emit('update:modelValue', name);
    open.value = false;
    q.value = '';
}

// Гадуур дарахад хаана
function onDocClick(e) {
    if (open.value && root.value && ! root.value.contains(e.target)) open.value = false;
}

function onKey(e) {
    if (e.key === 'Escape') open.value = false;
}

// Нээхэд сонгосон icon руу гүйлгэнэ
watch(open, (isOpen) => {
    if (! isOpen) q.value = '';
});

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
                <IconGlyph v-bind="glyph(modelValue)" :size="size" />
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
                placeholder="Icon хайх (parking, wifi, pizza …)"
                @click.stop
            />

            <div v-if="loading" class="py-8 text-center text-[12px] text-mute">Icon сан ачаалж байна…</div>

            <template v-else>
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
                        v-for="n in shown"
                        :key="n"
                        type="button"
                        :title="n"
                        class="flex h-[31px] w-[31px] cursor-pointer items-center justify-center rounded-md border"
                        :class="n === modelValue ? 'border-brand bg-bluetint text-brand' : 'border-transparent text-body hover:bg-panel'"
                        @click.stop="pick(n)"
                    >
                        <IconGlyph v-bind="glyph(n)" :size="16" />
                    </button>
                </div>

                <p v-if="!matches.length" class="py-4 text-center text-[12px] text-mute">Олдсонгүй</p>
                <p v-else-if="hidden" class="mt-2 text-center text-[11px] text-mute">
                    +{{ hidden.toLocaleString() }} icon · нэрээр нь хайж олно ({{ names.length.toLocaleString() }} нийт)
                </p>
            </template>
        </div>
    </div>
</template>
