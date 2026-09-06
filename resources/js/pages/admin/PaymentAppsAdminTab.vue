<script setup>
import { onMounted, ref } from 'vue';
import { CreditCard, ImageOff, Trash2, Upload } from 'lucide-vue-next';
import { api, ApiError } from '../../api';
import PanelPageHeader from '../../components/panel/PanelPageHeader.vue';
import PanelStat from '../../components/panel/PanelStat.vue';
import PaymentBadge from '../../components/PaymentBadge.vue';

// Зээл, хэсэгчилсэн төлбөрийн аппууд — нэр, өнгө, лого, дараалал
const apps = ref([]);
const loading = ref(true);
const loadError = ref('');
const msg = ref({ type: '', text: '' });
const busyId = ref(null);
const busy = ref(false);
const createOpen = ref(false);

const emptyForm = () => ({ slug: '', name: '', color: '#0e8f52', wordmark: false, is_active: true });
const newApp = ref(emptyForm());

async function load() {
    loading.value = true;
    loadError.value = '';
    try {
        const res = await api.get('/admin/payment-apps');
        apps.value = res.data;
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа.';
    } finally {
        loading.value = false;
    }
}

async function create() {
    busy.value = true;
    msg.value = { type: '', text: '' };
    try {
        const res = await api.post('/admin/payment-apps', newApp.value);
        msg.value = { type: 'ok', text: res.message };
        newApp.value = emptyForm();
        createOpen.value = false;
        await load();
    } catch (e) {
        msg.value = { type: 'err', text: e instanceof ApiError ? e.message : 'Алдаа гарлаа.' };
    } finally {
        busy.value = false;
    }
}

async function save(app) {
    busyId.value = app.id;
    msg.value = { type: '', text: '' };
    try {
        const res = await api.put(`/admin/payment-apps/${app.id}`, {
            name: app.name,
            color: app.color,
            wordmark: app.wordmark,
            is_active: app.is_active,
            sort_order: app.sort_order,
        });
        msg.value = { type: 'ok', text: res.message };
        await load();
    } catch (e) {
        msg.value = { type: 'err', text: e instanceof ApiError ? e.message : 'Алдаа гарлаа.' };
    } finally {
        busyId.value = null;
    }
}

async function uploadLogo(app, event) {
    const file = event.target.files?.[0];
    if (!file) return;

    busyId.value = app.id;
    msg.value = { type: '', text: '' };
    try {
        const fd = new FormData();
        fd.append('logo', file);
        const res = await api.postForm(`/admin/payment-apps/${app.id}/logo`, fd);
        msg.value = { type: 'ok', text: res.message };
        await load();
    } catch (e) {
        msg.value = { type: 'err', text: e instanceof ApiError ? e.message : 'Лого байршуулж чадсангүй.' };
    } finally {
        busyId.value = null;
        event.target.value = '';
    }
}

async function removeLogo(app) {
    busyId.value = app.id;
    try {
        const res = await api.delete(`/admin/payment-apps/${app.id}/logo`);
        msg.value = { type: 'ok', text: res.message };
        await load();
    } catch (e) {
        msg.value = { type: 'err', text: e instanceof ApiError ? e.message : 'Алдаа гарлаа.' };
    } finally {
        busyId.value = null;
    }
}

async function remove(app) {
    busyId.value = app.id;
    try {
        const res = await api.delete(`/admin/payment-apps/${app.id}`);
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
            title="Зээлийн апп"
            description="Бизнес бүртгэх, салбар засах хэсэгт гарах зээл, хэсэгчилсэн төлбөрийн аппууд. Логог байршуулбал бүх хуудсанд шууд харагдана."
            :meta="[{ label: `${apps.length} апп` }]"
        />

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <PanelStat label="Нийт апп" :value="apps.length" :icon="CreditCard" />
            <PanelStat label="Идэвхтэй" :value="apps.filter((a) => a.is_active).length" tone="good" />
            <PanelStat label="Логотой" :value="apps.filter((a) => a.logo).length" />
        </div>

        <div class="mt-4 flex items-center justify-between gap-3">
            <p v-if="msg.text" class="text-[12.5px] font-semibold" :class="msg.type === 'ok' ? 'text-brand' : 'text-red'">{{ msg.text }}</p>
            <span v-else></span>
            <button class="btn-outline !px-3.5 !py-2 !text-[12.5px]" @click="createOpen = !createOpen">{{ createOpen ? 'Болих' : '+ Апп нэмэх' }}</button>
        </div>

        <!-- Шинэ апп -->
        <div v-if="createOpen" class="card mt-3 p-5">
            <div class="text-[15px] font-bold text-ink">Шинэ апп</div>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div>
                    <label class="field-label !text-[12px]">Нэр</label>
                    <input v-model="newApp.name" class="input !py-2 !text-[12.5px]" placeholder="LendMN" />
                </div>
                <div>
                    <label class="field-label !text-[12px]">Slug (логоны файлын нэр)</label>
                    <input v-model="newApp.slug" class="input !py-2 !text-[12.5px]" placeholder="lendmn" />
                </div>
                <div>
                    <label class="field-label !text-[12px]">Өнгө</label>
                    <input v-model="newApp.color" type="color" class="h-[38px] w-full cursor-pointer rounded-lg border border-inputline" />
                </div>
                <div class="flex items-end">
                    <button class="btn-primary !px-4 !py-2.5 !text-[13px]" :disabled="busy || !newApp.name || !newApp.slug" @click="create">Нэмэх</button>
                </div>
            </div>
        </div>

        <div v-if="loading" class="card mt-3 p-14 text-center text-[13.5px] text-mute">Ачаалж байна…</div>
        <div v-else-if="loadError" class="card mt-3 p-14 text-center text-[15px] font-bold text-red">{{ loadError }}</div>

        <div v-else class="mt-3 space-y-2.5">
            <div v-for="app in apps" :key="app.id" class="card p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <PaymentBadge :name="app.name" :slug="app.slug" :logo="app.logo || ''" :color="app.color" :size="38" />

                    <div class="min-w-[150px] flex-1">
                        <input v-model="app.name" class="input !py-1.5 !text-[13px] font-bold" />
                        <div class="mt-1 font-mono text-[11px] text-mute">{{ app.slug }}</div>
                    </div>

                    <div>
                        <label class="field-label !mb-1 !text-[11px]">Өнгө</label>
                        <input v-model="app.color" type="color" class="h-[32px] w-[54px] cursor-pointer rounded-md border border-inputline" />
                    </div>

                    <div>
                        <label class="field-label !mb-1 !text-[11px]">Дараалал</label>
                        <input v-model.number="app.sort_order" type="number" min="0" class="input !w-[76px] !py-1.5 !text-[12.5px]" />
                    </div>

                    <label class="flex cursor-pointer items-center gap-1.5 text-[12.5px] font-medium text-body">
                        <input v-model="app.is_active" type="checkbox" class="h-4 w-4 cursor-pointer" /> Идэвхтэй
                    </label>

                    <label class="flex cursor-pointer items-center gap-1.5 text-[12.5px] font-medium text-body" title="Лого нь нэрээ агуулсан бол текстийг давхардуулахгүй">
                        <input v-model="app.wordmark" type="checkbox" class="h-4 w-4 cursor-pointer" /> Wordmark
                    </label>

                    <div class="flex items-center gap-1.5">
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-inputline px-2.5 py-1.5 text-[12px] font-semibold text-body hover:bg-panel">
                            <Upload :size="13" /> Лого
                            <input type="file" accept=".svg,.png,.webp" class="hidden" @change="uploadLogo(app, $event)" />
                        </label>
                        <button
                            v-if="app.has_upload"
                            class="flex h-[30px] w-[30px] cursor-pointer items-center justify-center rounded-lg border border-inputline text-mute hover:text-red"
                            aria-label="Логог устгах"
                            @click="removeLogo(app)"
                        ><ImageOff :size="13" /></button>
                        <button class="btn-primary !px-3 !py-1.5 !text-[12px]" :disabled="busyId === app.id" @click="save(app)">Хадгал</button>
                        <button
                            class="flex h-[30px] w-[30px] cursor-pointer items-center justify-center rounded-lg border border-inputline text-mute hover:bg-redtint hover:text-red"
                            aria-label="Апп устгах"
                            @click="remove(app)"
                        ><Trash2 :size="13" /></button>
                    </div>
                </div>
            </div>
        </div>

        <p class="mt-3 text-[12px] leading-relaxed text-mute">
            Лого: SVG, PNG, WebP — 512 KB хүртэл. Дөрвөлжин тэмдэг байвал хажууд нь аппын нэр гарна;
            лого нь өөрөө нэрээ агуулсан бол «Wordmark»-ыг тэмдэглэнэ. Лого байхгүй үед сонгосон өнгөөр
            товчлол харагдана. Аппын нэрийг өөрчлөхөд бүртгэлтэй салбаруудын утга хамт шинэчлэгдэнэ.
        </p>
    </div>
</template>
