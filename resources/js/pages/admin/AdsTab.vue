<script setup>
import { onMounted, ref, watch } from 'vue';
import { api } from '../../api';

// Сурталчилгааны тойм: хэдэн зар явж байгаа, дуусах хугацаа, дараалал
const data = ref(null);
const loadError = ref('');
const filters = ref({ status: '', type: '' });
const page = ref(1);

const fmt = (n) => '₮' + Number(n).toLocaleString();

const statusLabel = {
    active: ['ЯВЖ БАЙНА', 'bg-greentint text-green'],
    queued: ['ДАРААЛАЛД', 'bg-amberbadge text-amber'],
    pending_payment: ['ТӨЛБӨР ХҮЛЭЭЖ БАЙНА', 'bg-amberbadge text-amber'],
    expired: ['ДУУССАН', 'bg-chip text-chiptext'],
    canceled: ['ЦУЦЛАГДСАН', 'bg-chip text-chiptext'],
};

async function fetchData() {
    loadError.value = '';
    try {
        const params = new URLSearchParams();
        Object.entries(filters.value).forEach(([k, v]) => v && params.set(k, v));
        if (page.value > 1) params.set('page', page.value);
        data.value = await api.get(`/admin/campaigns?${params}`);
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    }
}

watch(filters, () => { page.value = 1; fetchData(); }, { deep: true });

function goPage(p) {
    page.value = p;
    fetchData();
}

onMounted(fetchData);
</script>

<template>
    <div class="p-5 sm:p-7">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-[15px] font-bold text-ink">Сурталчилгаа (зарууд)</h1>
            <select v-model="filters.status" class="ml-auto cursor-pointer rounded-[8px] border border-inputline bg-white px-2.5 py-2 text-[12.5px] font-semibold text-ink outline-none">
                <option value="">Бүх төлөв</option>
                <option value="active">Явж байгаа</option>
                <option value="queued">Дараалалд</option>
                <option value="pending_payment">Төлбөр хүлээж буй</option>
                <option value="expired">Дууссан</option>
                <option value="canceled">Цуцлагдсан</option>
            </select>
            <select v-model="filters.type" class="cursor-pointer rounded-[8px] border border-inputline bg-white px-2.5 py-2 text-[12.5px] font-semibold text-ink outline-none">
                <option value="">Бүх төрөл</option>
                <option value="category_featured">Ангиллын онцлох</option>
                <option value="home_featured">Нүүрийн онцлох</option>
                <option value="keyword">Хайлтын үг</option>
            </select>
        </div>

        <div v-if="loadError" class="card mt-4 p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="fetchData">Дахин оролдох</button>
        </div>

        <div v-else-if="!data" class="card mt-4 h-64 animate-pulse"></div>

        <template v-else>
            <!-- KPI -->
            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div v-for="k in [
                    ['Явж байгаа зар', data.kpis.active, 'text-green'],
                    ['Дараалалд', data.kpis.queued, data.kpis.queued ? 'text-amber' : 'text-ink'],
                    ['Төлбөр хүлээж буй', data.kpis.pending_payment, 'text-ink'],
                    ['7 хоногт дуусах', data.kpis.expiring_7d, data.kpis.expiring_7d ? 'text-amber' : 'text-ink'],
                ]" :key="k[0]" class="card p-4">
                    <div class="text-[11px] font-semibold text-mute">{{ k[0] }}</div>
                    <div class="mt-2 text-2xl font-extrabold tracking-[-.02em]" :class="k[2]">{{ Number(k[1]).toLocaleString() }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-[11px] font-semibold text-mute">Идэвхтэй зарын орлого</div>
                    <div class="mt-2 text-[20px] font-extrabold tracking-[-.02em] text-ink">{{ fmt(data.kpis.running_revenue) }}</div>
                </div>
            </div>

            <!-- Жагсаалт -->
            <div class="card mt-3.5 overflow-hidden">
                <div class="hidden grid-cols-[1.3fr_1.2fr_.6fr_.9fr_1fr_.8fr] gap-3 border-b border-divider px-4 py-2.5 text-[10.5px] font-semibold tracking-[.08em] text-mute lg:grid">
                    <span>БИЗНЕС · ТӨРӨЛ</span><span>БАЙРШИЛ</span><span>ЗАЙ</span><span>ТӨЛӨВ</span><span>ХУГАЦАА</span><span>ҮР ДҮН</span>
                </div>
                <div v-for="c in data.data" :key="c.id" class="grid grid-cols-1 gap-2 border-b border-hairline px-4 py-3 last:border-0 lg:grid-cols-[1.3fr_1.2fr_.6fr_.9fr_1fr_.8fr] lg:items-center lg:gap-3">
                    <div>
                        <router-link v-if="c.business_slug" :to="{ name: 'business', params: { slug: c.business_slug } }" class="text-[13px] font-bold text-ink hover:text-brand">{{ c.business }}</router-link>
                        <span v-else class="text-[13px] font-bold text-ink">{{ c.business || '—' }}</span>
                        <div class="mt-0.5 text-[11.5px] text-mute">{{ c.type_name }} · {{ c.organization }}</div>
                    </div>
                    <div class="text-[12px] text-body">{{ c.target || '—' }}</div>
                    <div class="font-mono text-[12px] font-semibold text-ink">{{ c.slot ? `#${c.slot}` : '—' }}</div>
                    <div><span class="rounded-[5px] px-1.5 py-0.5 text-[10px] font-bold" :class="statusLabel[c.status]?.[1]">{{ statusLabel[c.status]?.[0] }}</span></div>
                    <div class="text-[11.5px] text-body">
                        <template v-if="c.ends_at">
                            {{ new Date(c.ends_at).toLocaleDateString() }} хүртэл
                            <div class="font-semibold" :class="c.days_left <= 3 ? 'text-amberdark' : 'text-mute'">{{ c.days_left }} хоног үлдсэн</div>
                        </template>
                        <span v-else class="text-mute">{{ c.days }} хоног (эхлээгүй)</span>
                    </div>
                    <div class="text-[11.5px] text-mute">{{ (c.views_count || 0).toLocaleString() }} үзсэн · {{ c.calls_count || 0 }} залгасан<div class="font-semibold text-ink">{{ fmt(c.price) }}</div></div>
                </div>
                <div v-if="!data.data.length" class="p-10 text-center text-[13px] text-mute">Зар олдсонгүй</div>
            </div>

            <div v-if="data.meta.last_page > 1" class="mt-3.5 flex items-center justify-center gap-2 text-[12.5px] font-semibold">
                <button class="cursor-pointer rounded-[7px] border border-inputline bg-white px-3 py-1.5 text-ink disabled:opacity-40" :disabled="page <= 1" @click="goPage(page - 1)">← Өмнөх</button>
                <span class="px-2 text-mute">{{ data.meta.current_page }} / {{ data.meta.last_page }}</span>
                <button class="cursor-pointer rounded-[7px] border border-inputline bg-white px-3 py-1.5 text-ink disabled:opacity-40" :disabled="page >= data.meta.last_page" @click="goPage(page + 1)">Дараах →</button>
            </div>
        </template>
    </div>
</template>
