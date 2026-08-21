<script setup>
import { onMounted, ref, watch } from 'vue';
import { Megaphone, ListOrdered, Clock, CalendarClock, Coins } from 'lucide-vue-next';
import { shortDate } from '../../utils/date';
import { api } from '../../api';
import PanelPageHeader from '../../components/panel/PanelPageHeader.vue';
import PanelStat from '../../components/panel/PanelStat.vue';
import PanelBadge from '../../components/panel/PanelBadge.vue';

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
        <PanelPageHeader
            title="Сурталчилгаа"
            description="Худалдагдсан онцлох байршлууд, тэдгээрийн хугацаа, үр дүн. Зайн ачаалал хэсэгт аль байршил дүүрснийг харна."
        >
            <template #actions>
            <select v-model="filters.status" class="cursor-pointer rounded-[8px] border border-inputline bg-white px-2.5 py-2 text-[12.5px] font-semibold text-ink outline-none">
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
            </select>
            </template>
        </PanelPageHeader>

        <div v-if="loadError" class="card p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="fetchData">Дахин оролдох</button>
        </div>

        <div v-else-if="!data" class="card h-64 animate-pulse"></div>

        <template v-else>
            <!-- KPI -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <PanelStat label="Явж байгаа зар" :value="Number(data.kpis.active)" tone="good" :icon="Megaphone" />
                <PanelStat label="Дараалалд" :value="Number(data.kpis.queued)" :tone="data.kpis.queued ? 'warn' : 'default'" :icon="ListOrdered" />
                <PanelStat label="Төлбөр хүлээж буй" :value="Number(data.kpis.pending_payment)" :icon="Clock" />
                <PanelStat label="7 хоногт дуусах" :value="Number(data.kpis.expiring_7d)" :tone="data.kpis.expiring_7d ? 'warn' : 'default'" :icon="CalendarClock" hint="Сунгалт санал болгох" />
                <PanelStat label="Идэвхтэй зарын орлого" :value="fmt(data.kpis.running_revenue)" tone="good" :icon="Coins" />
            </div>

            <!-- Зайн ачаалал: аль байршил дүүрсэн, хэзээ сулрах -->
            <div v-if="data.spaces?.length" class="card mt-3.5 overflow-hidden">
                <div class="flex items-center gap-2 border-b border-divider px-4 py-3.5">
                    <span class="text-[14px] font-bold text-ink">Зайн ачаалал</span>
                    <span class="text-[11.5px] text-mute">— дүүрсэн байршилд шинэ зар зарагдахгүй</span>
                </div>
                <div class="hidden grid-cols-[1.4fr_1fr_.8fr_1fr] gap-3 border-b border-divider bg-panel px-4 py-2.5 text-[10.5px] font-semibold tracking-[.08em] text-mute lg:grid">
                    <span>БАЙРШИЛ</span><span>ЭЗЭЛСЭН</span><span>СУЛ</span><span>ДАРААГИЙН СУЛ ЗАЙ</span>
                </div>
                <div v-for="sp in data.spaces" :key="sp.type + sp.target" class="grid grid-cols-1 gap-1.5 border-b border-hairline px-4 py-3 last:border-0 lg:grid-cols-[1.4fr_1fr_.8fr_1fr] lg:items-center lg:gap-3">
                    <div>
                        <div class="text-[13px] font-bold text-ink">{{ sp.target }}</div>
                        <div class="mt-0.5 text-[11.5px] text-mute">{{ sp.type_name }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-[6px] w-[70px] overflow-hidden rounded-full bg-chip">
                            <div class="h-full" :class="sp.free === 0 ? 'bg-amber' : 'bg-green'" :style="{ width: Math.min(100, ((sp.occupied + sp.pending + sp.queued) / sp.total) * 100) + '%' }"></div>
                        </div>
                        <span class="font-mono text-[12px] font-semibold text-ink">{{ sp.occupied }}/{{ sp.total }}</span>
                        <PanelBadge v-if="sp.pending" tone="warn">+{{ sp.pending }} төлбөр хүлээж</PanelBadge>
                    </div>
                    <div>
                        <PanelBadge :tone="sp.free === 0 ? 'warn' : 'good'">{{ sp.free === 0 ? 'ДҮҮРСЭН' : sp.free + ' сул' }}</PanelBadge>
                    </div>
                    <div class="text-[12.5px] font-medium" :class="sp.free === 0 ? 'text-ink' : 'text-mute'">
                        {{ sp.free === 0 ? shortDate(sp.next_free_at) : 'одоо боломжтой' }}
                    </div>
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
                            {{ shortDate(c.ends_at) }} хүртэл
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
