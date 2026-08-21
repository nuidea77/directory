<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, ApiError } from '../../api';
import CategoryIcon from '../../components/CategoryIcon.vue';
import PanelPageHeader from '../../components/panel/PanelPageHeader.vue';
import PanelBadge from '../../components/panel/PanelBadge.vue';
import { iconNames } from '../../data/categoryIcons';
import { flattenCategories, optionLabel } from '../../utils/categories';

/**
 * Ангиллын удирдлага — 3 түвшин хүртэл (үндсэн → дэд → дэд дэд).
 * Ж: Боловсрол → Хэлний сургалт → Англи хэл.
 * Мод хавтгай мөр болж дүрслэгддэг тул гүн нэмэгдсэн ч код өөрчлөгдөхгүй.
 */
const MAX_DEPTH = 3;

const categories = ref(null);
const loadError = ref('');
const msg = ref({ type: '', text: '' });
const query = ref('');
const collapsed = ref(new Set()); // хаасан салбаруудын id
const busyId = ref(null);

const newCat = ref({ name: '', parent_id: '', icon: '' });
const editing = ref(null);
const addingUnder = ref(null);
const newChildName = ref('');

const flat = computed(() => flattenCategories(categories.value || []));

const totals = computed(() => ({
    level1: flat.value.filter((c) => c.depth === 1).length,
    level2: flat.value.filter((c) => c.depth === 2).length,
    level3: flat.value.filter((c) => c.depth === 3).length,
    businesses: flat.value.reduce((s, c) => s + (c.businesses_count || 0), 0),
}));

// Хайлт: тохирсон мөр + түүний бүх өвөг эцэг харагдана
const matchedIds = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return null;

    const parentOf = new Map(flat.value.map((c) => [c.id, c.parent_id]));
    const ids = new Set();

    for (const c of flat.value) {
        if (!c.name.toLowerCase().includes(q)) continue;
        ids.add(c.id);
        let p = parentOf.get(c.id);
        while (p) { ids.add(p); p = parentOf.get(p); }
    }
    return ids;
});

// Дэлгэцэнд гарах мөрүүд: хаасан салбарын доторхыг алгасана
const rows = computed(() => {
    const hidden = new Set();
    return flat.value.filter((c) => {
        if (c.parent_id && hidden.has(c.parent_id)) { hidden.add(c.id); return false; }
        if (collapsed.value.has(c.id)) hidden.add(c.id);
        if (matchedIds.value) return matchedIds.value.has(c.id);
        return true;
    });
});

// Эцэг болгож сонгож болох ангиллууд (гүн хүрээгүй, өөрөө/үр удам биш)
function parentOptions(exceptId = null) {
    const banned = new Set();
    if (exceptId) {
        banned.add(exceptId);
        const walk = (id) => flat.value.filter((c) => c.parent_id === id).forEach((c) => { banned.add(c.id); walk(c.id); });
        walk(exceptId);
    }
    return flat.value.filter((c) => c.depth < MAX_DEPTH && !banned.has(c.id));
}

function childrenOf(id) {
    return flat.value.filter((c) => c.parent_id === id);
}

function toggle(id) {
    const next = new Set(collapsed.value);
    next.has(id) ? next.delete(id) : next.add(id);
    collapsed.value = next;
}

async function fetchCategories() {
    loadError.value = '';
    try {
        const data = await api.get('/categories');
        categories.value = data.data;
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    }
}

function startEdit(cat) {
    editing.value = {
        id: cat.id,
        depth: cat.depth,
        name: cat.name,
        icon: cat.icon || '',
        description: cat.description || '',
        parent_id: cat.parent_id || '',
    };
}

async function saveEdit() {
    const e = editing.value;
    msg.value = { type: '', text: '' };
    busyId.value = e.id;
    try {
        await api.put(`/admin/categories/${e.id}`, {
            name: e.name,
            icon: e.icon || '',
            description: e.description || '',
            parent_id: e.parent_id || null,
        });
        editing.value = null;
        await fetchCategories();
        msg.value = { type: 'ok', text: 'Хадгалагдлаа.' };
    } catch (err) {
        msg.value = { type: 'error', text: err instanceof ApiError ? err.firstError() : 'Алдаа гарлаа' };
    } finally {
        busyId.value = null;
    }
}

// Эрэмбэ солих: хөрш ангилалтайгаа sort_order-оо солино (ижил эцэгтэй хөрш)
async function move(cat, delta) {
    const siblings = cat.parent_id ? childrenOf(cat.parent_id) : flat.value.filter((c) => c.depth === 1);
    const i = siblings.findIndex((c) => c.id === cat.id);
    const target = i + delta;
    if (target < 0 || target >= siblings.length) return;

    busyId.value = cat.id;
    try {
        await Promise.all([
            api.put(`/admin/categories/${cat.id}`, { sort_order: target }),
            api.put(`/admin/categories/${siblings[target].id}`, { sort_order: i }),
        ]);
        await fetchCategories();
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    } finally {
        busyId.value = null;
    }
}

async function createCategory() {
    msg.value = { type: '', text: '' };
    try {
        await api.post('/admin/categories', {
            name: newCat.value.name,
            parent_id: newCat.value.parent_id || undefined,
            icon: newCat.value.icon || undefined,
        });
        newCat.value = { name: '', parent_id: '', icon: '' };
        await fetchCategories();
        msg.value = { type: 'ok', text: 'Ангилал үүслээ.' };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    }
}

// Тухайн ангилал дор дэд ангилал шууд нэмнэ
async function createChild(parent) {
    if (!newChildName.value.trim()) return;
    msg.value = { type: '', text: '' };
    busyId.value = parent.id;
    try {
        await api.post('/admin/categories', { name: newChildName.value.trim(), parent_id: parent.id });
        newChildName.value = '';
        addingUnder.value = null;
        const next = new Set(collapsed.value);
        next.delete(parent.id);
        collapsed.value = next;
        await fetchCategories();
        msg.value = { type: 'ok', text: 'Дэд ангилал нэмэгдлээ.' };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    } finally {
        busyId.value = null;
    }
}

async function deleteCategory(cat) {
    const kids = childrenOf(cat.id).length;
    const warn = kids ? ` (${kids} дэд ангилал хамт устана)` : '';
    if (!confirm(`«${cat.name}» ангиллыг устгах уу?${warn}`)) return;

    msg.value = { type: '', text: '' };
    try {
        await api.delete(`/admin/categories/${cat.id}`);
        await fetchCategories();
        msg.value = { type: 'ok', text: 'Устгагдлаа.' };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Устгах боломжгүй' };
    }
}

onMounted(fetchCategories);
</script>

<template>
    <div class="p-5 sm:p-7">
        <PanelPageHeader
            title="Бизнесүүдийн ангилал"
            description="3 түвшин хүртэл: үндсэн → дэд → дэд дэд. Ж: Боловсрол → Хэлний сургалт → Англи хэл."
            :meta="categories ? [
                { label: `${totals.level1} үндсэн` },
                { label: `${totals.level2} дэд` },
                { label: `${totals.level3} дэд дэд` },
                { label: `${totals.businesses} бизнес` },
            ] : []"
        >
            <template #actions>
                <input
                    v-model="query"
                    type="search"
                    placeholder="Ангилал хайх…"
                    class="w-[240px] rounded-[8px] border border-inputline bg-white px-3 py-2 text-[12.5px] outline-none focus:border-brand"
                />
            </template>
        </PanelPageHeader>

        <p v-if="msg.text" class="rounded-lg px-4 py-2.5 text-[13px] font-medium" :class="msg.type === 'ok' ? 'bg-greentint text-green' : 'bg-redtint text-red'">{{ msg.text }}</p>

        <!-- Шинэ ангилал -->
        <div class="card mt-3 p-4">
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="createCategory">
                <div class="min-w-[200px] flex-1">
                    <label class="field-label !text-[11px]">Нэр</label>
                    <input v-model="newCat.name" type="text" class="input !py-2.5" placeholder="Шинэ ангиллын нэр" required maxlength="100" />
                </div>
                <div class="min-w-[210px]">
                    <label class="field-label !text-[11px]">Эцэг ангилал (дэд бол)</label>
                    <select v-model="newCat.parent_id" class="input cursor-pointer !py-2.5">
                        <option value="">— Үндсэн ангилал —</option>
                        <option v-for="c in parentOptions()" :key="c.id" :value="c.id">{{ optionLabel(c) }}</option>
                    </select>
                </div>
                <div class="min-w-[170px]">
                    <label class="field-label !text-[11px]">Icon</label>
                    <div class="flex items-center gap-2">
                        <span class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-lg border border-blueline bg-bluetint text-brand">
                            <CategoryIcon :name="newCat.icon" :size="18" />
                        </span>
                        <select v-model="newCat.icon" class="input cursor-pointer !py-2.5">
                            <option value="">— Ерөнхий —</option>
                            <option v-for="n in iconNames" :key="n" :value="n">{{ n }}</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary !px-5 !py-2.5 !text-[12.5px]">Нэмэх</button>
            </form>
        </div>

        <div v-if="loadError" class="card mt-4 p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="fetchCategories">Дахин оролдох</button>
        </div>

        <div v-else-if="!categories" class="card mt-4 h-64 animate-pulse"></div>

        <div v-else-if="!rows.length" class="card mt-4 p-10 text-center text-[13px] text-mute">
            «{{ query }}» хайлтад тохирох ангилал алга
        </div>

        <div v-else class="card mt-4 overflow-hidden">
            <div v-for="cat in rows" :key="cat.id" class="border-b border-hairline last:border-0" :class="cat.depth === 1 ? 'bg-white' : 'bg-panel/40'">
                <div class="flex flex-wrap items-center gap-2.5 px-4 py-2.5" :style="{ paddingLeft: `${16 + (cat.depth - 1) * 26}px` }">
                    <!-- Задлах/хаах -->
                    <button
                        v-if="childrenOf(cat.id).length"
                        class="w-4 cursor-pointer text-[11px] text-mute hover:text-brand"
                        :title="collapsed.has(cat.id) ? 'Задлах' : 'Хаах'"
                        @click="toggle(cat.id)"
                    >{{ collapsed.has(cat.id) ? '▸' : '▾' }}</button>
                    <span v-else class="w-4"></span>

                    <span v-if="cat.depth === 1" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] border border-blueline bg-bluetint text-brand">
                        <CategoryIcon :name="cat.icon" :size="18" />
                    </span>
                    <span v-else class="text-[12px] text-faint">└</span>

                    <span :class="cat.depth === 1 ? 'text-[13.5px] font-bold text-ink' : 'text-[12.5px] font-semibold text-body'">{{ cat.name }}</span>
                    <span class="font-mono text-[10.5px] text-faint">{{ cat.slug }}</span>

                    <PanelBadge mono>{{ cat.businesses_count || 0 }}</PanelBadge>
                    <PanelBadge v-if="childrenOf(cat.id).length" tone="brand" mono>{{ childrenOf(cat.id).length }} дэд</PanelBadge>
                    <PanelBadge v-if="cat.depth === 1 && !cat.icon" tone="warn">icon-гүй</PanelBadge>

                    <div class="ml-auto flex items-center gap-2 text-[11.5px] font-semibold">
                        <template v-if="!query">
                            <button class="cursor-pointer rounded border border-inputline px-1.5 py-0.5 text-mute hover:text-brand disabled:opacity-30" :disabled="busyId === cat.id" title="Дээш" @click="move(cat, -1)">↑</button>
                            <button class="cursor-pointer rounded border border-inputline px-1.5 py-0.5 text-mute hover:text-brand disabled:opacity-30" :disabled="busyId === cat.id" title="Доош" @click="move(cat, 1)">↓</button>
                        </template>
                        <button v-if="cat.depth < MAX_DEPTH" class="cursor-pointer text-brand" @click="addingUnder = cat.id; newChildName = ''">+ Дэд</button>
                        <button class="cursor-pointer text-brand" @click="startEdit(cat)">Засах</button>
                        <button class="cursor-pointer text-red" @click="deleteCategory(cat)">Устгах</button>
                    </div>
                </div>

                <!-- Дэд ангилал нэмэх -->
                <form v-if="addingUnder === cat.id" class="flex flex-wrap items-center gap-2 border-t border-hairline bg-panel px-4 py-2.5" :style="{ paddingLeft: `${28 + (cat.depth - 1) * 26}px` }" @submit.prevent="createChild(cat)">
                    <input v-model="newChildName" type="text" class="input !w-[260px] !py-2" :placeholder="`«${cat.name}» доторх дэд ангиллын нэр`" required maxlength="100" />
                    <button type="submit" class="btn-primary !px-4 !py-2 !text-[12.5px]" :disabled="busyId === cat.id">Нэмэх</button>
                    <button type="button" class="btn-outline !px-3 !py-2 !text-[12.5px]" @click="addingUnder = null">Болих</button>
                </form>

                <!-- Засвар -->
                <div v-if="editing?.id === cat.id" class="border-t border-hairline bg-panel px-4 py-4">
                    <form class="grid grid-cols-1 gap-3 sm:grid-cols-[1.1fr_1fr_1.6fr_auto]" @submit.prevent="saveEdit">
                        <div>
                            <label class="field-label !text-[11px]">Нэр</label>
                            <input v-model="editing.name" type="text" class="input !py-2" required maxlength="100" />
                        </div>
                        <div>
                            <label class="field-label !text-[11px]">Эцэг ангилал</label>
                            <select v-model="editing.parent_id" class="input cursor-pointer !py-2">
                                <option value="">— Үндсэн ангилал —</option>
                                <option v-for="c in parentOptions(editing.id)" :key="c.id" :value="c.id">{{ optionLabel(c) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label !text-[11px]">Тайлбар (ангиллын хуудсанд, SEO-д)</label>
                            <input v-model="editing.description" type="text" class="input !py-2" maxlength="500" placeholder="Ж: Улаанбаатар дахь англи хэлний сургалтууд" />
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="btn-primary !px-4 !py-2 !text-[12.5px]" :disabled="busyId === cat.id">Хадгалах</button>
                            <button type="button" class="btn-outline !px-3 !py-2 !text-[12.5px]" @click="editing = null">Болих</button>
                        </div>

                        <div v-if="editing.depth === 1" class="sm:col-span-4">
                            <label class="field-label !text-[11px]">Icon</label>
                            <div class="flex items-center gap-2">
                                <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-lg border border-blueline bg-bluetint text-brand">
                                    <CategoryIcon :name="editing.icon" :size="17" />
                                </span>
                                <select v-model="editing.icon" class="input cursor-pointer !w-[240px] !py-2">
                                    <option value="">— Ерөнхий —</option>
                                    <option v-for="n in iconNames" :key="n" :value="n">{{ n }}</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
