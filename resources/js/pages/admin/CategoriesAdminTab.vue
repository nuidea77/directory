<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, ApiError } from '../../api';
import CategoryIcon from '../../components/CategoryIcon.vue';
import AdminPageHeader from '../../components/admin/AdminPageHeader.vue';
import AdminBadge from '../../components/admin/AdminBadge.vue';
import { iconNames } from '../../data/categoryIcons';

/**
 * Ангиллын удирдлага: үндсэн + дэд ангиллын нэр, icon, тайлбар, эрэмбэ,
 * дэд ангиллыг өөр эцэг рүү зөөх.
 */
const categories = ref(null);
const loadError = ref('');
const msg = ref({ type: '', text: '' });
const query = ref('');
const openId = ref(null);
const busyId = ref(null);

const newCat = ref({ name: '', parent_id: '', icon: '' });
const editing = ref(null); // { id, name, icon, description, sort_order, parent_id }

const totals = computed(() => {
    const list = categories.value || [];
    return {
        parents: list.length,
        children: list.reduce((s, c) => s + (c.children?.length || 0), 0),
        businesses: list.reduce((s, c) => s + (c.businesses_count || 0)
            + (c.children || []).reduce((x, ch) => x + (ch.businesses_count || 0), 0), 0),
    };
});

// Хайлт: ангилал болон дэд ангиллын нэрээр
const visible = computed(() => {
    const list = categories.value || [];
    const q = query.value.trim().toLowerCase();
    if (!q) return list;

    return list
        .map((c) => {
            const hitParent = c.name.toLowerCase().includes(q);
            const kids = (c.children || []).filter((ch) => ch.name.toLowerCase().includes(q));
            if (!hitParent && !kids.length) return null;
            return hitParent ? c : { ...c, children: kids };
        })
        .filter(Boolean);
});

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

// Эрэмбэ солих: хөрш ангилалтайгаа sort_order-оо солино
async function move(index, delta) {
    const list = categories.value;
    const target = index + delta;
    if (target < 0 || target >= list.length) return;

    const a = list[index];
    const b = list[target];
    busyId.value = a.id;
    try {
        await Promise.all([
            api.put(`/admin/categories/${a.id}`, { sort_order: target }),
            api.put(`/admin/categories/${b.id}`, { sort_order: index }),
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
const addingUnder = ref(null);
const newChildName = ref('');

async function createChild(parent) {
    if (!newChildName.value.trim()) return;
    msg.value = { type: '', text: '' };
    busyId.value = parent.id;
    try {
        await api.post('/admin/categories', { name: newChildName.value.trim(), parent_id: parent.id });
        newChildName.value = '';
        addingUnder.value = null;
        await fetchCategories();
        msg.value = { type: 'ok', text: 'Дэд ангилал нэмэгдлээ.' };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    } finally {
        busyId.value = null;
    }
}

// Дэд ангиллыг өөр эцэг ангилал руу зөөх
async function moveToParent(child, parentId) {
    msg.value = { type: '', text: '' };
    busyId.value = child.id;
    try {
        await api.put(`/admin/categories/${child.id}`, { parent_id: Number(parentId) });
        await fetchCategories();
        msg.value = { type: 'ok', text: `«${child.name}» зөөгдлөө.` };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Зөөх боломжгүй' };
        await fetchCategories();
    } finally {
        busyId.value = null;
    }
}

async function deleteCategory(cat) {
    if (!confirm(`«${cat.name}» ангиллыг устгах уу?`)) return;
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
        <AdminPageHeader
            title="Бизнесүүдийн ангилал"
            description="Ангиллын нэр, icon, тайлбар, эрэмбийг удирдана. Дэд ангиллыг өөр ангилал руу зөөж болно."
            :meta="categories ? [
                { label: `${totals.parents} үндсэн` },
                { label: `${totals.children} дэд` },
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
        </AdminPageHeader>

        <p v-if="msg.text" class="rounded-lg px-4 py-2.5 text-[13px] font-medium" :class="msg.type === 'ok' ? 'bg-greentint text-green' : 'bg-redtint text-red'">{{ msg.text }}</p>

        <!-- Шинэ ангилал -->
        <div class="card mt-3 p-4">
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="createCategory">
                <div class="min-w-[200px] flex-1">
                    <label class="field-label !text-[11px]">Нэр</label>
                    <input v-model="newCat.name" type="text" class="input !py-2.5" placeholder="Шинэ ангиллын нэр" required maxlength="100" />
                </div>
                <div class="min-w-[180px]">
                    <label class="field-label !text-[11px]">Эцэг ангилал (дэд бол)</label>
                    <select v-model="newCat.parent_id" class="input cursor-pointer !py-2.5">
                        <option value="">— Үндсэн ангилал —</option>
                        <option v-for="c in categories || []" :key="c.id" :value="c.id">{{ c.name }}</option>
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

        <div v-else-if="!visible.length" class="card mt-4 p-10 text-center text-[13px] text-mute">
            «{{ query }}» хайлтад тохирох ангилал алга
        </div>

        <div v-else class="card mt-4 overflow-hidden">
            <div v-for="(cat, i) in visible" :key="cat.id" class="border-b border-hairline last:border-0">
                <!-- Үндсэн ангиллын мөр -->
                <div class="flex flex-wrap items-center gap-3 px-4 py-3" :class="{ 'bg-panel': openId === cat.id }">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] border border-blueline bg-bluetint text-brand">
                        <CategoryIcon :name="cat.icon" :size="18" />
                    </span>

                    <button class="cursor-pointer text-left" @click="openId = openId === cat.id ? null : cat.id">
                        <span class="text-[13.5px] font-bold text-ink">{{ cat.name }}</span>
                        <span class="ml-2 font-mono text-[11px] text-faint">{{ cat.slug }}</span>
                    </button>

                    <AdminBadge mono>{{ cat.businesses_count || 0 }} бизнес</AdminBadge>
                    <AdminBadge v-if="cat.children?.length" tone="brand" mono>{{ cat.children.length }} дэд</AdminBadge>
                    <AdminBadge v-if="!cat.icon" tone="warn">icon-гүй</AdminBadge>

                    <div class="ml-auto flex items-center gap-2 text-[11.5px] font-semibold">
                        <!-- Эрэмбэ (хайлтгүй үед л) -->
                        <template v-if="!query">
                            <button class="cursor-pointer rounded border border-inputline px-1.5 py-0.5 text-mute hover:text-brand disabled:opacity-30" :disabled="i === 0 || busyId === cat.id" title="Дээш" @click="move(i, -1)">↑</button>
                            <button class="cursor-pointer rounded border border-inputline px-1.5 py-0.5 text-mute hover:text-brand disabled:opacity-30" :disabled="i === visible.length - 1 || busyId === cat.id" title="Доош" @click="move(i, 1)">↓</button>
                        </template>
                        <button class="cursor-pointer text-brand" @click="startEdit(cat)">Засах</button>
                        <button class="cursor-pointer text-red" @click="deleteCategory(cat)">Устгах</button>
                        <button class="cursor-pointer text-mute" @click="openId = openId === cat.id ? null : cat.id">{{ openId === cat.id ? '▲' : '▼' }}</button>
                    </div>
                </div>

                <!-- Засварын хэсэг -->
                <div v-if="editing?.id === cat.id" class="border-t border-hairline bg-panel px-4 py-4">
                    <form class="grid grid-cols-1 gap-3 sm:grid-cols-[1.2fr_1fr_2fr_auto]" @submit.prevent="saveEdit">
                        <div>
                            <label class="field-label !text-[11px]">Нэр</label>
                            <input v-model="editing.name" type="text" class="input !py-2" required maxlength="100" />
                        </div>
                        <div>
                            <label class="field-label !text-[11px]">Icon</label>
                            <div class="flex items-center gap-2">
                                <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-lg border border-blueline bg-bluetint text-brand">
                                    <CategoryIcon :name="editing.icon" :size="17" />
                                </span>
                                <select v-model="editing.icon" class="input cursor-pointer !py-2">
                                    <option value="">— Ерөнхий —</option>
                                    <option v-for="n in iconNames" :key="n" :value="n">{{ n }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="field-label !text-[11px]">Тайлбар (ангиллын хуудсанд, SEO-д)</label>
                            <input v-model="editing.description" type="text" class="input !py-2" maxlength="500" placeholder="Ж: Улаанбаатар дахь ресторан, кафе, хоолны газрууд" />
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="btn-primary !px-4 !py-2 !text-[12.5px]" :disabled="busyId === cat.id">Хадгалах</button>
                            <button type="button" class="btn-outline !px-3 !py-2 !text-[12.5px]" @click="editing = null">Болих</button>
                        </div>
                    </form>
                </div>

                <!-- Дэд ангиллууд -->
                <div v-if="openId === cat.id || query" class="border-t border-hairline px-4 py-3">
                    <div v-if="cat.children?.length" class="flex flex-col gap-1.5">
                        <div v-for="child in cat.children" :key="child.id" class="flex flex-wrap items-center gap-2.5 rounded-lg border border-line bg-white px-3 py-2">
                            <span class="text-[12.5px] font-semibold text-body">{{ child.name }}</span>
                            <span class="font-mono text-[10.5px] text-faint">{{ child.slug }}</span>
                            <AdminBadge mono>{{ child.businesses_count || 0 }}</AdminBadge>

                            <div class="ml-auto flex items-center gap-2 text-[11.5px] font-semibold">
                                <!-- Өөр ангилал руу зөөх -->
                                <select
                                    :value="child.parent_id"
                                    class="cursor-pointer rounded-[7px] border border-inputline bg-white px-2 py-1 text-[11px] font-medium text-body outline-none"
                                    :disabled="busyId === child.id"
                                    title="Өөр ангилал руу зөөх"
                                    @change="moveToParent(child, $event.target.value)"
                                >
                                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                                <button class="cursor-pointer text-brand" @click="startEdit(child)">Засах</button>
                                <button class="cursor-pointer text-red" @click="deleteCategory(child)">Устгах</button>
                            </div>

                            <!-- Дэд ангиллын засвар -->
                            <form v-if="editing?.id === child.id" class="mt-2 flex w-full flex-wrap items-end gap-2 border-t border-hairline pt-2" @submit.prevent="saveEdit">
                                <div class="min-w-[180px] flex-1">
                                    <label class="field-label !text-[11px]">Нэр</label>
                                    <input v-model="editing.name" type="text" class="input !py-2" required maxlength="100" />
                                </div>
                                <div class="min-w-[220px] flex-1">
                                    <label class="field-label !text-[11px]">Тайлбар</label>
                                    <input v-model="editing.description" type="text" class="input !py-2" maxlength="500" />
                                </div>
                                <button type="submit" class="btn-primary !px-4 !py-2 !text-[12.5px]" :disabled="busyId === child.id">Хадгалах</button>
                                <button type="button" class="btn-outline !px-3 !py-2 !text-[12.5px]" @click="editing = null">Болих</button>
                            </form>
                        </div>
                    </div>
                    <p v-else class="text-[12.5px] text-mute">Дэд ангилал алга</p>

                    <!-- Дэд ангилал нэмэх -->
                    <div class="mt-2.5">
                        <button v-if="addingUnder !== cat.id" class="cursor-pointer text-[12px] font-semibold text-brand" @click="addingUnder = cat.id; newChildName = ''">+ Дэд ангилал нэмэх</button>
                        <form v-else class="flex flex-wrap items-center gap-2" @submit.prevent="createChild(cat)">
                            <input v-model="newChildName" type="text" class="input !w-[240px] !py-2" placeholder="Дэд ангиллын нэр" required maxlength="100" />
                            <button type="submit" class="btn-primary !px-4 !py-2 !text-[12.5px]" :disabled="busyId === cat.id">Нэмэх</button>
                            <button type="button" class="btn-outline !px-3 !py-2 !text-[12.5px]" @click="addingUnder = null">Болих</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
