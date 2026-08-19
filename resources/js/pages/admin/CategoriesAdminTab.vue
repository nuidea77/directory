<script setup>
import { onMounted, ref } from 'vue';
import { api, ApiError } from '../../api';

// Ангиллын CRUD — үндсэн + дэд ангиллуудыг админаас удирдана
const categories = ref(null);
const loadError = ref('');
const msg = ref({ type: '', text: '' });
const newCat = ref({ name: '', parent_id: '' });

async function fetchCategories() {
    loadError.value = '';
    try {
        const data = await api.get('/categories');
        categories.value = data.data;
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    }
}

async function createCategory() {
    msg.value = { type: '', text: '' };
    try {
        await api.post('/admin/categories', {
            name: newCat.value.name,
            parent_id: newCat.value.parent_id || undefined,
        });
        newCat.value = { name: '', parent_id: '' };
        await fetchCategories();
        msg.value = { type: 'ok', text: 'Ангилал үүслээ.' };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    }
}

async function renameCategory(cat) {
    const name = prompt('Шинэ нэр:', cat.name);
    if (!name || name === cat.name) return;
    try {
        await api.put(`/admin/categories/${cat.id}`, { name });
        await fetchCategories();
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
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
        <h1 class="text-[15px] font-bold text-ink">Бизнесүүдийн ангилал</h1>

        <p v-if="msg.text" class="mt-3 rounded-lg px-4 py-2.5 text-[13px] font-medium" :class="msg.type === 'ok' ? 'bg-greentint text-green' : 'bg-redtint text-red'">{{ msg.text }}</p>

        <!-- Шинэ ангилал -->
        <div class="card mt-4 p-4">
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
                <button type="submit" class="btn-primary !px-5 !py-2.5 !text-[12.5px]">Нэмэх</button>
            </form>
        </div>

        <div v-if="loadError" class="card mt-4 p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="fetchCategories">Дахин оролдох</button>
        </div>

        <div v-else-if="!categories" class="card mt-4 h-64 animate-pulse"></div>

        <div v-else class="card mt-4 overflow-hidden">
            <div v-for="cat in categories" :key="cat.id" class="border-b border-hairline last:border-0">
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="text-[13.5px] font-bold text-ink">{{ cat.name }}</span>
                    <span class="rounded-full bg-chip px-2 py-0.5 font-mono text-[10.5px] font-semibold text-chiptext">{{ cat.businesses_count || 0 }} бизнес</span>
                    <div class="ml-auto flex gap-2 text-[11.5px] font-semibold">
                        <button class="cursor-pointer text-brand" @click="renameCategory(cat)">Нэр солих</button>
                        <button class="cursor-pointer text-red" @click="deleteCategory(cat)">Устгах</button>
                    </div>
                </div>
                <div v-if="cat.children?.length" class="flex flex-wrap gap-1.5 px-4 pb-3">
                    <span v-for="child in cat.children" :key="child.id" class="group flex items-center gap-1.5 rounded-full border border-searchline bg-search px-2.5 py-1 text-[11.5px] font-semibold text-body">
                        {{ child.name }}
                        <button class="cursor-pointer text-mute hover:text-brand" title="Нэр солих" @click="renameCategory(child)">✎</button>
                        <button class="cursor-pointer text-mute hover:text-red" title="Устгах" @click="deleteCategory(child)">✕</button>
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
