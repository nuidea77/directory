<script setup>
import { computed, onMounted, ref } from 'vue';
import { Search, Tag, Trash2 } from 'lucide-vue-next';
import { api, ApiError } from '../../api';
import PanelPageHeader from '../../components/panel/PanelPageHeader.vue';
import PanelStat from '../../components/panel/PanelStat.vue';
import { flattenCategories, optionLabel } from '../../utils/categories';

// Хайлтын синоним: хэрэглэгчийн ярианы нэрийг ангилалд холбоно
// («тог» → Цахилгаанчин, «шил хийх» → Цонх, хаалга)
const groups = ref([]);
const total = ref(0);
const categories = ref([]);
const loading = ref(true);
const loadError = ref('');
const msg = ref({ type: '', text: '' });
const busy = ref(false);
const busyId = ref(null);
const q = ref('');

const form = ref({ category_id: '', term: '' });

const categoryOptions = computed(() => flattenCategories(categories.value));

const filtered = computed(() => {
    const needle = q.value.trim().toLowerCase();
    if (!needle) return groups.value;

    return groups.value
        .map((g) => ({ ...g, terms: g.terms.filter((t) => t.term.toLowerCase().includes(needle)) }))
        .filter((g) => g.terms.length || g.category.name?.toLowerCase().includes(needle));
});

async function load() {
    loading.value = true;
    loadError.value = '';
    try {
        const [aliases, cats] = await Promise.all([
            api.get('/admin/search-aliases'),
            api.get('/categories'),
        ]);
        groups.value = aliases.data;
        total.value = aliases.total;
        categories.value = cats.data;
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа.';
    } finally {
        loading.value = false;
    }
}

async function create() {
    if (!form.value.category_id || !form.value.term.trim()) return;
    busy.value = true;
    msg.value = { type: '', text: '' };
    try {
        const res = await api.post('/admin/search-aliases', {
            category_id: Number(form.value.category_id),
            term: form.value.term.trim(),
        });
        msg.value = { type: 'ok', text: res.message };
        form.value.term = '';
        await load();
    } catch (e) {
        msg.value = { type: 'err', text: e instanceof ApiError ? e.message : 'Алдаа гарлаа.' };
    } finally {
        busy.value = false;
    }
}

async function remove(alias) {
    busyId.value = alias.id;
    msg.value = { type: '', text: '' };
    try {
        const res = await api.delete(`/admin/search-aliases/${alias.id}`);
        msg.value = { type: 'ok', text: res.message };
        await load();
    } catch (e) {
        msg.value = { type: 'err', text: e instanceof ApiError ? e.message : 'Алдаа гарлаа.' };
    } finally {
        busyId.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <PanelPageHeader
            title="Хайлтын синоним"
            description="Хэрэглэгчийн ярианы нэрийг ангилалд холбоно. Синоним нэмэхэд тухайн ангиллын салбарууд шууд дахин индексжиж, хайлтад олдоно."
            :meta="[{ label: `${total} синоним` }]"
        />

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <PanelStat label="Нийт синоним" :value="total" :icon="Tag" />
            <PanelStat label="Хамрагдсан ангилал" :value="groups.length" :icon="Search" />
        </div>

        <!-- Шинээр нэмэх -->
        <div class="card mt-4 p-5">
            <div class="text-[15px] font-bold text-ink">Синоним нэмэх</div>
            <p class="mt-1.5 text-[12.5px] text-mute">Жишээ: «тог», «тог татах» → Цахилгаанчин. Кирилл, латин аль алинаар нь хайхад ажиллана.</p>
            <div class="mt-3 flex flex-wrap items-end gap-2.5">
                <div class="min-w-[240px] flex-1">
                    <label class="field-label !text-[12px]">Ангилал</label>
                    <select v-model="form.category_id" class="input cursor-pointer !py-2 !text-[12.5px]">
                        <option value="">— Ангилал сонгох —</option>
                        <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ optionLabel(c) }}</option>
                    </select>
                </div>
                <div class="min-w-[200px] flex-1">
                    <label class="field-label !text-[12px]">Ярианы нэр</label>
                    <input v-model="form.term" class="input !py-2 !text-[12.5px]" placeholder="тог татах" @keyup.enter="create" />
                </div>
                <button class="btn-primary !px-4 !py-2.5 !text-[13px]" :disabled="busy || !form.category_id || !form.term.trim()" @click="create">
                    {{ busy ? 'Нэмж байна…' : 'Нэмэх' }}
                </button>
            </div>
            <p v-if="msg.text" class="mt-2.5 text-[12.5px] font-semibold" :class="msg.type === 'ok' ? 'text-brand' : 'text-red'">{{ msg.text }}</p>
        </div>

        <!-- Жагсаалт -->
        <div class="mt-4 flex items-center gap-2.5">
            <input v-model="q" class="input !py-2 !text-[12.5px] sm:max-w-[320px]" placeholder="Синоним эсвэл ангиллаар шүүх" />
        </div>

        <div v-if="loading" class="card mt-3 p-14 text-center text-[13.5px] text-mute">Ачаалж байна…</div>
        <div v-else-if="loadError" class="card mt-3 p-14 text-center text-[15px] font-bold text-red">{{ loadError }}</div>
        <div v-else-if="!filtered.length" class="card mt-3 p-14 text-center">
            <p class="text-[15px] font-bold text-ink">Синоним алга</p>
            <p class="mt-1.5 text-[13px] text-soft">Дээрх хэсгээс ангилалдаа ярианы нэр нэмнэ үү.</p>
        </div>

        <div v-else class="mt-3 space-y-2.5">
            <div v-for="g in filtered" :key="g.category.id" class="card p-4">
                <div class="text-[13.5px] font-bold text-ink">{{ g.category.name }}</div>
                <div class="mt-0.5 font-mono text-[11px] text-mute">{{ g.category.slug }}</div>
                <div class="mt-2.5 flex flex-wrap gap-2">
                    <span
                        v-for="t in g.terms"
                        :key="t.id"
                        class="inline-flex items-center gap-1.5 rounded-full border border-line bg-panel py-1.5 pl-3 pr-1.5 text-[12.5px] font-medium text-body"
                    >
                        {{ t.term }}
                        <button
                            class="flex h-5 w-5 cursor-pointer items-center justify-center rounded-full text-mute hover:bg-redtint hover:text-red disabled:opacity-40"
                            :disabled="busyId === t.id"
                            :aria-label="`${t.term} устгах`"
                            @click="remove(t)"
                        ><Trash2 :size="12" /></button>
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
