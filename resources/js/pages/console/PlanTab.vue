<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../../api';
import { useConsoleStore } from '../../stores/console';

// Эрх ба сурталчилгаа (9a): одоогийн эрх, ашиглалт, кампанит ажлууд
const store = useConsoleStore();
const campaigns = ref([]);
const orders = ref([]);

const fmt = (n) => '₮' + Number(n).toLocaleString();

const org = computed(() => store.organization);

const planProgress = computed(() => {
    if (!org.value?.plan_started_at || !org.value?.plan_expires_at) return null;
    const start = new Date(org.value.plan_started_at).getTime();
    const end = new Date(org.value.plan_expires_at).getTime();
    const used = Math.max(0, Date.now() - start);
    const total = end - start;
    return {
        pct: Math.min(100, Math.round((used / total) * 100)),
        usedDays: Math.round(used / 86400000),
        leftDays: Math.max(0, Math.round((end - Date.now()) / 86400000)),
    };
});

const usage = computed(() => {
    if (!org.value) return [];
    const limits = org.value.limits || {};
    const businesses = store.businesses.length;
    const branches = store.branches.length;
    const maxImages = limits.images_per_branch || 1;
    const imagesUsed = store.branches.reduce((s, b) => Math.max(s, (b.images || []).length), 0);
    return [
        { label: 'БИЗНЕС', value: `${businesses} / ${limits.businesses}`, pct: Math.min(100, (businesses / (limits.businesses || 1)) * 100) },
        { label: 'САЛБАР', value: limits.branches === 0 ? `${branches} · хязгааргүй` : `${branches} / ${limits.branches}`, pct: limits.branches === 0 ? Math.min(100, branches * 20) : (branches / limits.branches) * 100 },
        { label: 'ЗУРАГ (салбарт)', value: `${imagesUsed} / ${maxImages}`, pct: Math.min(100, (imagesUsed / maxImages) * 100) },
    ];
});

const statusLabel = {
    active: ['АЖИЛЛАЖ БАЙНА', 'bg-greentint text-green'],
    queued: ['ДАРААЛАЛД', 'bg-amberbadge text-amber'],
    expired: ['ДУУССАН', 'bg-chip text-chiptext'],
    pending_payment: ['ТӨЛБӨР ХҮЛЭЭЖ БАЙНА', 'bg-amberbadge text-amber'],
};

async function fetchData() {
    if (!org.value) return;
    const [c, o] = await Promise.all([
        api.get(`/console/organizations/${org.value.id}/campaigns`),
        api.get('/orders'),
    ]);
    campaigns.value = c.data;
    orders.value = o.data.slice(0, 4);
}

watch(() => store.organization?.id, fetchData);
onMounted(fetchData);
</script>

<template>
    <div class="p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-extrabold tracking-[-.02em] text-ink">Эрх ба сурталчилгаа</h1>
            <router-link :to="{ name: 'ad-purchase' }" class="btn-primary !px-4 !py-2.5 !text-[12.5px]">Онцлох байршил авах</router-link>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3.5 lg:grid-cols-[1.25fr_1fr]">
            <!-- Одоогийн эрх (9a) -->
            <div class="card p-5">
                <div class="flex items-center gap-2.5">
                    <span class="text-[15px] font-bold text-ink">Одоогийн эрх</span>
                    <span class="badge-verified uppercase">{{ org?.plan_name }}{{ org?.plan !== 'free' ? ' · ' + org?.plan_term_years + ' ЖИЛ' : '' }}</span>
                    <router-link :to="{ name: 'pricing' }" class="ml-auto text-[12.5px] font-semibold text-brand">Эрх солих</router-link>
                </div>

                <template v-if="org?.effective_plan !== 'free'">
                    <div class="mt-3.5 flex items-baseline gap-2">
                        <span class="text-[26px] font-extrabold tracking-[-.02em] text-ink">{{ org.plan === 'business' ? fmt(290000) : fmt(120000) }}</span>
                        <span class="text-[12.5px] font-medium text-mute">/ {{ org.plan_term_years }} жил · дараагийн төлбөр {{ org.plan_expires_at ? new Date(org.plan_expires_at).toLocaleDateString() : '—' }}</span>
                    </div>
                    <template v-if="planProgress">
                        <div class="mt-3.5 h-[7px] overflow-hidden rounded-full bg-chip"><div class="h-full bg-brand" :style="{ width: planProgress.pct + '%' }"></div></div>
                        <div class="mt-1.5 flex justify-between text-[11.5px] font-medium text-mute">
                            <span>Эрх ашиглалт: {{ planProgress.usedDays }} хоног</span>
                            <span>{{ planProgress.leftDays }} хоног үлдсэн</span>
                        </div>
                    </template>
                </template>
                <p v-else class="mt-3 text-[13px] leading-relaxed text-soft">
                    Та үнэгүй эрхтэй байна — 1 бизнес, 1 зураг, салбаргүй. Салбар нэмэх, аналитик, ТОП жагсаалт Стандарт/Бизнес эрхээс нээгдэнэ.
                </p>

                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div v-for="u in usage" :key="u.label" class="rounded-[10px] border border-line p-3">
                        <div class="text-[10.5px] font-semibold text-mute">{{ u.label }}</div>
                        <div class="mt-1.5 text-[14px] font-bold text-ink">{{ u.value }}</div>
                        <div class="mt-2 h-[5px] overflow-hidden rounded-full bg-chip"><div class="h-full bg-brand" :style="{ width: u.pct + '%' }"></div></div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2 text-[12.5px] font-semibold">
                    <router-link :to="{ name: 'pricing' }" class="btn-outline !px-3.5 !py-2.5 !text-[12.5px]">{{ org?.effective_plan === 'free' ? 'Эрх авах' : 'Эрх сунгах' }}</router-link>
                </div>
            </div>

            <!-- Зардал ба үр дүн (хар карт) -->
            <div class="rounded-xl bg-dark p-5">
                <div class="text-[10.5px] font-semibold tracking-[.12em] text-bluelight">30 ХОНОГИЙН ЗАРДАЛ ба ҮР ДҮН</div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-[26px] font-extrabold text-white">{{ fmt(campaigns.filter(c => c.status === 'active').reduce((s, c) => s + c.price, 0)) }}</span>
                    <span class="text-[12px] font-medium text-darkmute">/ идэвхтэй кампанит ажил</span>
                </div>
                <div class="mt-4 flex flex-col gap-3">
                    <div v-for="c in campaigns.filter(c => c.status === 'active').slice(0, 3)" :key="c.id">
                        <div class="flex justify-between text-[12px] font-semibold text-white"><span>{{ c.type_name }}{{ c.district ? ' · ' + c.district : '' }}</span><span>{{ fmt(c.price) }}</span></div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full bg-amberdot" :style="{ width: Math.min(100, (c.days_left / c.days) * 100) + '%' }"></div></div>
                    </div>
                    <p v-if="!campaigns.filter(c => c.status === 'active').length" class="text-[12.5px] leading-relaxed text-darkmute">Идэвхтэй сурталчилгаа алга. Онцлох байршил аваад ангиллынхаа дээд 3-т гараарай.</p>
                </div>
                <div class="mt-4 border-t border-white/10 pt-3.5">
                    <div class="flex justify-between text-[12px] font-medium text-darkmute"><span>Онцлохоос ирсэн залгалт</span><b class="text-white">{{ campaigns.reduce((s, c) => s + (c.calls_count || 0), 0) }}</b></div>
                </div>
            </div>
        </div>

        <!-- Кампанит ажлын хүснэгт (9a) -->
        <div class="card mt-3.5 overflow-hidden">
            <div class="flex items-center border-b border-divider px-4 py-3.5">
                <span class="text-[14px] font-bold text-ink">Сурталчилгааны кампанит ажил</span>
                <router-link :to="{ name: 'ad-purchase' }" class="ml-auto text-[12.5px] font-semibold text-brand">Шинэ кампанит ажил →</router-link>
            </div>
            <div class="hidden gap-2.5 border-b border-divider bg-panel px-4 py-2.5 text-[10.5px] font-bold tracking-[.06em] text-mute sm:flex">
                <span class="min-w-[170px] flex-1">КАМПАНИТ АЖИЛ</span><span class="w-[86px]">ҮЗЭЛТ</span><span class="w-[78px]">ЗАЛГАЛТ</span><span class="w-[92px]">ҮЛДСЭН</span><span class="w-[90px]">ҮНЭ</span>
            </div>
            <div v-for="c in campaigns" :key="c.id" class="flex flex-wrap items-center gap-2.5 border-b border-hairline px-4 py-3 last:border-0" :class="{ 'bg-bluepale': c.status === 'active' }">
                <div class="min-w-[170px] flex-1">
                    <div class="flex items-center gap-2">
                        <span class="whitespace-nowrap text-[13px] font-bold text-ink">{{ c.type_name }}{{ c.district ? ' · ' + c.district : c.keyword ? ' · “' + c.keyword + '”' : '' }}</span>
                        <span class="rounded-[4px] px-1.5 py-0.5 text-[9.5px] font-semibold" :class="statusLabel[c.status]?.[1]">{{ statusLabel[c.status]?.[0] }}</span>
                    </div>
                    <div class="mt-0.5 text-[11.5px] text-mute">{{ c.business?.name }}{{ c.slot ? ` · ${c.slot}-р зай` : '' }} · {{ c.days }} хоног</div>
                </div>
                <span class="w-[86px] text-[13px] font-bold text-ink">{{ c.status === 'active' ? (c.views_count || 0).toLocaleString() : '—' }}</span>
                <span class="w-[78px] text-[13px] font-bold text-ink">{{ c.status === 'active' ? (c.calls_count || 0).toLocaleString() : '—' }}</span>
                <span class="w-[92px] text-[11.5px] font-medium text-soft">{{ c.status === 'active' ? c.days_left + ' хоног' : c.status === 'queued' ? 'зай хүлээж' : '—' }}</span>
                <span class="w-[90px] text-[12.5px] font-bold text-ink">{{ fmt(c.price) }}</span>
            </div>
            <div v-if="!campaigns.length" class="p-8 text-center text-[13px] text-mute">Кампанит ажил алга</div>
            <div class="px-4 py-3 text-[12px] font-medium text-mute">Онцлохоос ирсэн үзэлт нь үнэгүй хайлтын үзэлтээс тусад нь бүртгэгддэг.</div>
        </div>
    </div>
</template>
