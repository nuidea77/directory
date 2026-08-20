<script setup>
import { onMounted, ref, watch } from 'vue';
import { Ticket, TicketCheck, Repeat, Coins } from 'lucide-vue-next';
import { shortDate } from '../../utils/date';
import { api, ApiError } from '../../api';
import AdminPageHeader from '../../components/admin/AdminPageHeader.vue';
import AdminStat from '../../components/admin/AdminStat.vue';
import AdminBadge from '../../components/admin/AdminBadge.vue';

// Промо код: эрхийн бичиг болон сурталчилгааны хөнгөлөлтийг админаас удирдана
const data = ref(null);
const loadError = ref('');
const msg = ref({ type: '', text: '' });
const busyId = ref(null);
const createOpen = ref(false);
const editing = ref(null);
const filters = ref({ scope: '', status: '' });

const fmt = (n) => '₮' + Number(n || 0).toLocaleString();

const emptyForm = () => ({
    code: '',
    scope: 'subscription',
    type: 'percent',
    value: 10,
    min_amount: 0,
    max_uses: '',
    max_uses_per_user: 1,
    starts_at: '',
    expires_at: '',
    is_active: true,
    note: '',
});

const newCode = ref(emptyForm());

// datetime-local input-д тохирох хэлбэр рүү (сервер ISO буцаадаг)
function toInput(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// Хувь бол 1..100, дүн бол ₮ — илгээхийн өмнө хязгаарлана
function payload(f) {
    let value = Number(f.value) || 0;
    if (f.type === 'percent') value = Math.min(100, Math.max(1, value));
    return {
        scope: f.scope,
        type: f.type,
        value,
        min_amount: Number(f.min_amount) || 0,
        max_uses: f.max_uses === '' || f.max_uses === null ? null : Number(f.max_uses),
        max_uses_per_user: Number(f.max_uses_per_user) || 0,
        starts_at: toInput(f.starts_at) || null,
        expires_at: toInput(f.expires_at) || null,
        is_active: !!f.is_active,
        note: f.note || null,
    };
}

function statusOf(c) {
    if (c.is_expired) return ['Хугацаа дууссан', 'bad'];
    return c.is_active ? ['Идэвхтэй', 'good'] : ['Идэвхгүй', 'neutral'];
}

async function fetchData() {
    loadError.value = '';
    try {
        data.value = await api.get('/admin/promo-codes', { scope: filters.value.scope, status: filters.value.status });
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    }
}

watch(filters, fetchData, { deep: true });

async function createCode() {
    msg.value = { type: '', text: '' };
    try {
        const code = newCode.value.code.trim().toUpperCase();
        await api.post('/admin/promo-codes', { code, ...payload(newCode.value) });
        newCode.value = emptyForm();
        createOpen.value = false;
        await fetchData();
        msg.value = { type: 'ok', text: `«${code}» код үүслээ.` };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    }
}

function startEdit(c) {
    msg.value = { type: '', text: '' };
    editing.value = {
        id: c.id,
        code: c.code,
        scope: c.scope,
        type: c.type,
        value: c.value,
        min_amount: c.min_amount ?? 0,
        max_uses: c.max_uses ?? '',
        max_uses_per_user: c.max_uses_per_user ?? 0,
        starts_at: toInput(c.starts_at),
        expires_at: toInput(c.expires_at),
        is_active: !!c.is_active,
        note: c.note || '',
    };
}

async function saveEdit() {
    const f = editing.value;
    msg.value = { type: '', text: '' };
    busyId.value = f.id;
    try {
        await api.put(`/admin/promo-codes/${f.id}`, payload(f));
        editing.value = null;
        await fetchData();
        msg.value = { type: 'ok', text: `«${f.code}» хадгалагдлаа.` };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    } finally {
        busyId.value = null;
    }
}

// Мөрөн дэх идэвхтэй эсэхийг шууд солих
async function toggleActive(c) {
    msg.value = { type: '', text: '' };
    busyId.value = c.id;
    try {
        await api.put(`/admin/promo-codes/${c.id}`, payload(c));
        msg.value = { type: 'ok', text: `«${c.code}» ${c.is_active ? 'идэвхжлээ' : 'идэвхгүй боллоо'}.` };
    } catch (e) {
        c.is_active = !c.is_active;
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    } finally {
        busyId.value = null;
    }
}

async function removeCode(c) {
    if (!confirm(`«${c.code}» кодыг устгах уу?`)) return;
    msg.value = { type: '', text: '' };
    busyId.value = c.id;
    try {
        await api.delete(`/admin/promo-codes/${c.id}`);
        await fetchData();
        msg.value = { type: 'ok', text: `«${c.code}» устгагдлаа.` };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Устгах боломжгүй' };
    } finally {
        busyId.value = null;
    }
}

async function copyCode(c) {
    msg.value = { type: '', text: '' };
    try {
        await navigator.clipboard.writeText(c.code);
        msg.value = { type: 'ok', text: `«${c.code}» хуулагдлаа.` };
    } catch {
        msg.value = { type: 'error', text: 'Хуулах боломжгүй байна. Кодыг гараар хуулна уу.' };
    }
}

// Хугацааны цонх: хоёулаа хоосон бол «Хязгааргүй» гэж нэг л удаа
function dateWindow(c) {
    if (!c.starts_at && !c.expires_at) return 'Хязгааргүй';
    if (!c.starts_at) return `${shortDate(c.expires_at)} хүртэл`;
    if (!c.expires_at) return `${shortDate(c.starts_at)}-нээс`;
    return `${shortDate(c.starts_at)} — ${shortDate(c.expires_at)}`;
}

onMounted(fetchData);
</script>

<template>
    <div class="p-5 sm:p-7">
        <AdminPageHeader
            title="Промо код"
            description="Хоёр ангилалд тусад нь ажиллана: «Эрхийн бичиг» код зөвхөн эрхийн бичгийн худалдан авалтад, «Сурталчилгаа» код зөвхөн зарын төлбөрт хүчинтэй. Хөнгөлөлт хувиар эсвэл тогтмол дүнгээр бодогдоно."
            :meta="data ? [{ label: data.data.length + ' код' }] : []"
        >
            <template #actions>
                <select v-model="filters.scope" class="cursor-pointer rounded-[8px] border border-inputline bg-white px-2.5 py-2 text-[12.5px] font-semibold text-ink outline-none">
                    <option value="">Бүх ангилал</option>
                    <option value="subscription">Эрхийн бичиг</option>
                    <option value="ad">Сурталчилгаа</option>
                </select>
                <select v-model="filters.status" class="cursor-pointer rounded-[8px] border border-inputline bg-white px-2.5 py-2 text-[12.5px] font-semibold text-ink outline-none">
                    <option value="">Бүх төлөв</option>
                    <option value="active">Идэвхтэй</option>
                    <option value="inactive">Идэвхгүй</option>
                    <option value="expired">Хугацаа дууссан</option>
                </select>
                <button class="btn-primary cursor-pointer !px-4 !py-2 !text-[12.5px]" @click="createOpen = !createOpen">Шинэ код үүсгэх</button>
            </template>
        </AdminPageHeader>

        <p v-if="msg.text" class="mb-3 rounded-lg px-4 py-2.5 text-[13px] font-medium" :class="msg.type === 'ok' ? 'bg-greentint text-green' : 'bg-redtint text-red'">{{ msg.text }}</p>

        <!-- Шинэ код -->
        <div v-if="createOpen" class="card mb-4 p-5">
            <div class="text-[14px] font-bold text-ink">Шинэ промо код</div>
            <form class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4" @submit.prevent="createCode">
                <div>
                    <label class="field-label !text-[11px]">Код (латин, дараа өөрчлөгдөхгүй)</label>
                    <input v-model="newCode.code" type="text" class="input font-mono uppercase !py-2 !text-[12.5px]" placeholder="ZUN2026" required maxlength="30" />
                </div>
                <div>
                    <label class="field-label !text-[11px]">Ангилал</label>
                    <select v-model="newCode.scope" class="input cursor-pointer !py-2 !text-[12.5px]">
                        <option value="subscription">Эрхийн бичиг</option>
                        <option value="ad">Сурталчилгаа</option>
                    </select>
                </div>
                <div>
                    <label class="field-label !text-[11px]">Хөнгөлөлтийн төрөл</label>
                    <select v-model="newCode.type" class="input cursor-pointer !py-2 !text-[12.5px]">
                        <option value="percent">Хувиар (%)</option>
                        <option value="fixed">Тогтмол дүн (₮)</option>
                    </select>
                </div>
                <div>
                    <label class="field-label !text-[11px]">{{ newCode.type === 'percent' ? 'Хувь (1–100)' : 'Дүн (₮)' }}</label>
                    <input v-model.number="newCode.value" type="number" min="1" :max="newCode.type === 'percent' ? 100 : null" class="input !py-2 !text-[12.5px]" required />
                </div>
                <div>
                    <label class="field-label !text-[11px]">Доод дүн (₮, 0=хязгааргүй)</label>
                    <input v-model.number="newCode.min_amount" type="number" min="0" class="input !py-2 !text-[12.5px]" />
                </div>
                <div>
                    <label class="field-label !text-[11px]">Нийт ашиглалт (хоосон=∞)</label>
                    <input v-model="newCode.max_uses" type="number" min="1" class="input !py-2 !text-[12.5px]" />
                </div>
                <div>
                    <label class="field-label !text-[11px]">Хэрэглэгч тус бүр (0=∞)</label>
                    <input v-model.number="newCode.max_uses_per_user" type="number" min="0" class="input !py-2 !text-[12.5px]" />
                </div>
                <div>
                    <label class="field-label !text-[11px]">Эхлэх (сонголтоор)</label>
                    <input v-model="newCode.starts_at" type="datetime-local" class="input !py-2 !text-[12.5px]" />
                </div>
                <div>
                    <label class="field-label !text-[11px]">Дуусах (сонголтоор)</label>
                    <input v-model="newCode.expires_at" type="datetime-local" class="input !py-2 !text-[12.5px]" />
                </div>
                <div class="col-span-2">
                    <label class="field-label !text-[11px]">Тэмдэглэл (зөвхөн админд харагдана)</label>
                    <input v-model="newCode.note" type="text" class="input !py-2 !text-[12.5px]" maxlength="200" />
                </div>
                <div class="col-span-2 flex items-end sm:col-span-1">
                    <label class="flex cursor-pointer items-center gap-1.5 pb-2 text-[11.5px] font-semibold text-body"><input v-model="newCode.is_active" type="checkbox" />Идэвхтэй</label>
                </div>
                <div class="col-span-2 sm:col-span-4"><button type="submit" class="btn-primary !px-5 !py-2.5 !text-[12.5px]">Үүсгэх</button></div>
            </form>
        </div>

        <div v-if="loadError" class="card p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="fetchData">Дахин оролдох</button>
        </div>

        <div v-else-if="!data" class="card h-64 animate-pulse"></div>

        <template v-else>
            <!-- KPI -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AdminStat label="Нийт код" :value="Number(data.kpis.total)" :icon="Ticket" />
                <AdminStat label="Идэвхтэй" :value="Number(data.kpis.active)" tone="good" :icon="TicketCheck" hint="Одоо ашиглагдаж болно" />
                <AdminStat label="Ашиглалтын тоо" :value="Number(data.kpis.redemptions)" :icon="Repeat" />
                <AdminStat label="Олгосон нийт хөнгөлөлт" :value="fmt(data.kpis.discount_given)" tone="warn" :icon="Coins" />
            </div>

            <!-- Жагсаалт -->
            <div class="card mt-3.5 overflow-hidden">
                <div class="hidden grid-cols-[1.2fr_.9fr_1fr_.9fr_1fr_.8fr_auto] gap-3 border-b border-divider px-4 py-2.5 text-[10.5px] font-semibold tracking-[.08em] text-mute lg:grid">
                    <span>КОД</span><span>АНГИЛАЛ</span><span>ХӨНГӨЛӨЛТ</span><span>АШИГЛАЛТ</span><span>ХУГАЦАА</span><span>ТӨЛӨВ</span><span>ҮЙЛДЭЛ</span>
                </div>

                <div v-for="c in data.data" :key="c.id" class="border-b border-hairline last:border-0">
                    <div class="grid grid-cols-1 gap-2 px-4 py-3 lg:grid-cols-[1.2fr_.9fr_1fr_.9fr_1fr_.8fr_auto] lg:items-center lg:gap-3" :class="{ 'opacity-60': !c.is_active && !c.is_expired }">
                        <div class="min-w-0">
                            <div class="font-mono text-[14px] font-bold tracking-[.02em] text-ink">{{ c.code }}</div>
                            <div v-if="c.note" class="mt-0.5 truncate text-[11.5px] text-mute">{{ c.note }}</div>
                        </div>

                        <div>
                            <AdminBadge :tone="c.scope === 'subscription' ? 'brand' : 'warn'">{{ c.scope_name }}</AdminBadge>
                            <div v-if="c.min_amount" class="mt-1 text-[11px] text-mute">{{ fmt(c.min_amount) }}-с дээш захиалгад</div>
                        </div>

                        <div>
                            <div class="text-[13px] font-bold text-ink">{{ c.value_label }}</div>
                            <div class="mt-0.5 text-[11.5px] text-mute">{{ fmt(c.discount_given) }} олгосон</div>
                        </div>

                        <div>
                            <span class="font-mono text-[12.5px] font-semibold text-ink">{{ c.used_count }} / {{ c.max_uses || '∞' }}</span>
                            <div class="mt-0.5 text-[11px] text-mute">Хэрэглэгч тус бүр: {{ c.max_uses_per_user ? c.max_uses_per_user : '∞' }}</div>
                        </div>

                        <div class="text-[11.5px] text-body">
                            {{ dateWindow(c) }}
                            <div class="mt-0.5 text-[11px] text-mute">Үүссэн: {{ shortDate(c.created_at) }}</div>
                        </div>

                        <div>
                            <AdminBadge :tone="statusOf(c)[1]">{{ statusOf(c)[0] }}</AdminBadge>
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5 text-[11.5px] font-semibold lg:justify-end">
                            <label class="flex cursor-pointer items-center gap-1.5" :class="c.is_active ? 'text-green' : 'text-mute'">
                                <input v-model="c.is_active" type="checkbox" :disabled="busyId === c.id" @change="toggleActive(c)" />Идэвхтэй
                            </label>
                            <button class="cursor-pointer text-mute hover:text-ink" @click="copyCode(c)">Хуулах</button>
                            <button class="cursor-pointer text-brand" @click="editing?.id === c.id ? (editing = null) : startEdit(c)">Засах</button>
                            <button class="cursor-pointer text-red disabled:opacity-40" :disabled="busyId === c.id" @click="removeCode(c)">Устгах</button>
                        </div>
                    </div>

                    <!-- Засварын хэсэг: код өөрчлөгдөхгүй -->
                    <div v-if="editing?.id === c.id" class="border-t border-hairline bg-panel px-4 py-4">
                        <form class="grid grid-cols-2 gap-3 sm:grid-cols-4" @submit.prevent="saveEdit">
                            <div>
                                <label class="field-label !text-[11px]">Ангилал</label>
                                <select v-model="editing.scope" class="input cursor-pointer !py-2 !text-[12.5px]">
                                    <option value="subscription">Эрхийн бичиг</option>
                                    <option value="ad">Сурталчилгаа</option>
                                </select>
                            </div>
                            <div>
                                <label class="field-label !text-[11px]">Хөнгөлөлтийн төрөл</label>
                                <select v-model="editing.type" class="input cursor-pointer !py-2 !text-[12.5px]">
                                    <option value="percent">Хувиар (%)</option>
                                    <option value="fixed">Тогтмол дүн (₮)</option>
                                </select>
                            </div>
                            <div>
                                <label class="field-label !text-[11px]">{{ editing.type === 'percent' ? 'Хувь (1–100)' : 'Дүн (₮)' }}</label>
                                <input v-model.number="editing.value" type="number" min="1" :max="editing.type === 'percent' ? 100 : null" class="input !py-2 !text-[12.5px]" required />
                            </div>
                            <div>
                                <label class="field-label !text-[11px]">Доод дүн (₮, 0=хязгааргүй)</label>
                                <input v-model.number="editing.min_amount" type="number" min="0" class="input !py-2 !text-[12.5px]" />
                            </div>
                            <div>
                                <label class="field-label !text-[11px]">Нийт ашиглалт (хоосон=∞)</label>
                                <input v-model="editing.max_uses" type="number" min="1" class="input !py-2 !text-[12.5px]" />
                            </div>
                            <div>
                                <label class="field-label !text-[11px]">Хэрэглэгч тус бүр (0=∞)</label>
                                <input v-model.number="editing.max_uses_per_user" type="number" min="0" class="input !py-2 !text-[12.5px]" />
                            </div>
                            <div>
                                <label class="field-label !text-[11px]">Эхлэх</label>
                                <input v-model="editing.starts_at" type="datetime-local" class="input !py-2 !text-[12.5px]" />
                            </div>
                            <div>
                                <label class="field-label !text-[11px]">Дуусах</label>
                                <input v-model="editing.expires_at" type="datetime-local" class="input !py-2 !text-[12.5px]" />
                            </div>
                            <div class="col-span-2">
                                <label class="field-label !text-[11px]">Тэмдэглэл</label>
                                <input v-model="editing.note" type="text" class="input !py-2 !text-[12.5px]" maxlength="200" />
                            </div>
                            <div class="col-span-2 flex items-end gap-3 sm:col-span-1">
                                <label class="flex cursor-pointer items-center gap-1.5 pb-2 text-[11.5px] font-semibold text-body"><input v-model="editing.is_active" type="checkbox" />Идэвхтэй</label>
                            </div>
                            <div class="col-span-2 flex items-end gap-2 sm:col-span-4">
                                <button type="submit" class="btn-primary !px-5 !py-2.5 !text-[12.5px]" :disabled="busyId === c.id">{{ busyId === c.id ? 'Хадгалж байна…' : 'Хадгалах' }}</button>
                                <button type="button" class="btn-outline !px-4 !py-2.5 !text-[12.5px]" @click="editing = null">Болих</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div v-if="!data.data.length" class="p-10 text-center">
                    <p class="text-[13px] font-medium text-ink">Промо код алга</p>
                    <p class="mt-1 text-[12.5px] text-mute">«Шинэ код үүсгэх» товчоор эрхийн бичиг эсвэл сурталчилгааны хөнгөлөлтийн код нэмнэ үү.</p>
                </div>
            </div>
        </template>
    </div>
</template>
