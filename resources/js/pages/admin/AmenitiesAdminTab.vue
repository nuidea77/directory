<script setup>
import { computed, onMounted, ref } from 'vue';
import { LayoutList, Tag, Trash2 } from 'lucide-vue-next';
import { api, ApiError } from '../../api';
import PanelPageHeader from '../../components/panel/PanelPageHeader.vue';
import PanelStat from '../../components/panel/PanelStat.vue';
import AmenityIcon from '../../components/AmenityIcon.vue';
import { amenityIconNames } from '../../data/amenityIcons';
import { flattenCategories, optionLabel } from '../../utils/categories';

// Ангилал бүрийн дагалдах онцлог (үйлчилгээ). Ангилалгүй нь бүх ангилалд гарна.
const groups = ref([]);
const total = ref(0);
const categories = ref([]);
const loading = ref(true);
const loadError = ref('');
const msg = ref({ type: '', text: '' });
const busy = ref(false);
const busyId = ref(null);
const editingId = ref(null);
const editForm = ref({ name: '', icon: '' });
const q = ref('');

const form = ref({ category_id: '', name: '', icon: 'settings' });

const categoryOptions = computed(() => flattenCategories(categories.value));

const filtered = computed(() => {
    const needle = q.value.trim().toLowerCase();
    if (!needle) return groups.value;

    return groups.value
        .map((g) => ({ ...g, items: g.items.filter((i) => i.name.toLowerCase().includes(needle)) }))
        .filter((g) => g.items.length || (g.category?.name || '').toLowerCase().includes(needle));
});

async function load() {
    loading.value = true;
    loadError.value = '';
    try {
        const [rows, cats] = await Promise.all([api.get('/admin/amenities'), api.get('/categories')]);
        groups.value = rows.data;
        total.value = rows.total;
        categories.value = cats.data;
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа.';
    } finally {
        loading.value = false;
    }
}

async function create() {
    if (!form.value.name.trim()) return;
    busy.value = true;
    msg.value = { type: '', text: '' };
    try {
        const res = await api.post('/admin/amenities', {
            category_id: form.value.category_id ? Number(form.value.category_id) : null,
            name: form.value.name.trim(),
            icon: form.value.icon,
        });
        msg.value = { type: 'ok', text: res.message };
        form.value.name = '';
        await load();
    } catch (e) {
        msg.value = { type: 'err', text: e instanceof ApiError ? e.message : 'Алдаа гарлаа.' };
    } finally {
        busy.value = false;
    }
}

function startEdit(item) {
    editingId.value = item.id;
    editForm.value = { name: item.name, icon: item.icon };
}

async function saveEdit(item) {
    busyId.value = item.id;
    msg.value = { type: '', text: '' };
    try {
        const res = await api.put(`/admin/amenities/${item.id}`, {
            name: editForm.value.name.trim(),
            icon: editForm.value.icon,
        });
        msg.value = { type: 'ok', text: res.message };
        editingId.value = null;
        await load();
    } catch (e) {
        msg.value = { type: 'err', text: e instanceof ApiError ? e.message : 'Алдаа гарлаа.' };
    } finally {
        busyId.value = null;
    }
}

async function remove(item) {
    busyId.value = item.id;
    try {
        const res = await api.delete(`/admin/amenities/${item.id}`);
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
            title="Үйлчилгээ, онцлог"
            description="Ангилал бүрийн дагалдах онцлог. Дэд ангилал нь эцгийнхээ багцыг өвлөнө. Нэрийг өөрчлөхөд бүртгэлтэй салбаруудын утга хамт шинэчлэгдэнэ."
            :meta="[{ label: `${total} онцлог` }]"
        />

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <PanelStat label="Нийт онцлог" :value="total" :icon="Tag" />
            <PanelStat label="Багцтай ангилал" :value="groups.length" :icon="LayoutList" />
        </div>

        <!-- Нэмэх -->
        <div class="card mt-4 p-5">
            <div class="text-[15px] font-bold text-ink">Онцлог нэмэх</div>
            <p class="mt-1.5 text-[12.5px] text-mute">Ангилал сонгохгүй бол бүх ангилалд харагдана (ж: Зогсоол, Wi-Fi).</p>
            <div class="mt-3 flex flex-wrap items-end gap-2.5">
                <div class="min-w-[220px] flex-1">
                    <label class="field-label !text-[12px]">Ангилал</label>
                    <select v-model="form.category_id" class="input cursor-pointer !py-2 !text-[12.5px]">
                        <option value="">— Бүх ангилалд —</option>
                        <option v-for="c in categoryOptions" :key="c.id" :value="c.id">{{ optionLabel(c) }}</option>
                    </select>
                </div>
                <div class="min-w-[180px] flex-1">
                    <label class="field-label !text-[12px]">Нэр</label>
                    <input v-model="form.name" class="input !py-2 !text-[12.5px]" placeholder="Усан сан" @keyup.enter="create" />
                </div>
                <div class="min-w-[150px]">
                    <label class="field-label !text-[12px]">Icon</label>
                    <div class="flex items-center gap-2">
                        <span class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-lg border border-blueline bg-bluetint text-brand">
                            <AmenityIcon :name="form.icon" :size="17" />
                        </span>
                        <select v-model="form.icon" class="input cursor-pointer !py-2 !text-[12.5px]">
                            <option v-for="n in amenityIconNames" :key="n" :value="n">{{ n }}</option>
                        </select>
                    </div>
                </div>
                <button class="btn-primary !px-4 !py-2.5 !text-[13px]" :disabled="busy || !form.name.trim()" @click="create">
                    {{ busy ? 'Нэмж байна…' : 'Нэмэх' }}
                </button>
            </div>
            <p v-if="msg.text" class="mt-2.5 text-[12.5px] font-semibold" :class="msg.type === 'ok' ? 'text-brand' : 'text-red'">{{ msg.text }}</p>
        </div>

        <div class="mt-4">
            <input v-model="q" class="input !py-2 !text-[12.5px] sm:max-w-[320px]" placeholder="Онцлог эсвэл ангиллаар шүүх" />
        </div>

        <div v-if="loading" class="card mt-3 p-14 text-center text-[13.5px] text-mute">Ачаалж байна…</div>
        <div v-else-if="loadError" class="card mt-3 p-14 text-center text-[15px] font-bold text-red">{{ loadError }}</div>
        <div v-else-if="!filtered.length" class="card mt-3 p-14 text-center">
            <p class="text-[15px] font-bold text-ink">Онцлог алга</p>
        </div>

        <div v-else class="mt-3 space-y-2.5">
            <div v-for="g in filtered" :key="g.category?.id || 'common'" class="card p-4">
                <div class="text-[13.5px] font-bold text-ink">{{ g.category?.name || 'Бүх ангилалд' }}</div>
                <div class="mt-0.5 font-mono text-[11px] text-mute">{{ g.category?.slug || 'common' }}</div>

                <div class="mt-2.5 flex flex-wrap gap-2">
                    <template v-for="item in g.items" :key="item.id">
                        <!-- Засварлаж байгаа -->
                        <span v-if="editingId === item.id" class="inline-flex items-center gap-1.5 rounded-full border border-brand bg-white py-1 pl-2 pr-1.5">
                            <select v-model="editForm.icon" class="cursor-pointer rounded-md border border-inputline px-1.5 py-1 text-[11px]">
                                <option v-for="n in amenityIconNames" :key="n" :value="n">{{ n }}</option>
                            </select>
                            <input v-model="editForm.name" class="w-[130px] rounded-md border border-inputline px-2 py-1 text-[12px]" @keyup.enter="saveEdit(item)" />
                            <button class="cursor-pointer rounded-full bg-brand px-2.5 py-1 text-[11px] font-bold text-white disabled:opacity-40" :disabled="busyId === item.id" @click="saveEdit(item)">Хадгал</button>
                            <button class="cursor-pointer px-1.5 text-[11px] font-semibold text-mute" @click="editingId = null">✕</button>
                        </span>

                        <!-- Энгийн -->
                        <span v-else class="inline-flex items-center gap-1.5 rounded-full border border-line bg-panel py-1.5 pl-2.5 pr-1.5 text-[12.5px] font-medium text-body">
                            <AmenityIcon :name="item.icon" :size="14" />
                            <button class="cursor-pointer hover:text-brand" @click="startEdit(item)">{{ item.name }}</button>
                            <button
                                class="flex h-5 w-5 cursor-pointer items-center justify-center rounded-full text-mute hover:bg-redtint hover:text-red disabled:opacity-40"
                                :disabled="busyId === item.id"
                                :aria-label="`${item.name} устгах`"
                                @click="remove(item)"
                            ><Trash2 :size="12" /></button>
                        </span>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
