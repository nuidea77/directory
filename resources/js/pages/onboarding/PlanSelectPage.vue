<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../../api';

/**
 * Бүртгэл дууссаны дараах эрх сонгох хуудас (10a):
 * эрх + салбарын нэмэлт + ангиллын онцлох → нэг захиалга → byl.mn
 */
const route = useRoute();
const router = useRouter();

const organization = ref(null);
const business = ref(null);
const plans = ref([]);
const ads = ref([]);
const branchAddon = ref(5000);
const slotState = ref(null);

const selectedPlan = ref('standard');
const planPeriod = ref('yearly'); // yearly | monthly
const featuredDays = ref(0); // 0 = авахгүй
const error = ref('');
const loadError = ref('');
const busy = ref(false);

const fmt = (n) => '₮' + Number(n).toLocaleString();

const extraBranches = computed(() => {
    const count = business.value?.branches?.length || 1;
    return Math.max(0, count - 1);
});

const categoryAd = computed(() => ads.value.find((a) => a.key === 'category_featured'));
const mainBranch = computed(() => business.value?.branches?.[0]);

const planRow = computed(() => plans.value.find((p) => p.key === selectedPlan.value));

// Сонгосон эрхийн үнэ — сараар авах боломжтой бол сарын үнэ
const isMonthly = computed(() => planPeriod.value === 'monthly' && !!planRow.value?.price_monthly);
const planPrice = computed(() => (isMonthly.value ? planRow.value.price_monthly : planRow.value?.price || 0));

const cardPrice = (p) => (planPeriod.value === 'monthly' && p.price_monthly ? p.price_monthly : p.price);
const cardUnit = (p) => (planPeriod.value === 'monthly' && p.price_monthly ? '/ сар' : `/ ${p.term_years} жил`);

const featuredPrice = computed(() => {
    if (!featuredDays.value || !categoryAd.value) return 0;
    const base = categoryAd.value.prices[featuredDays.value];
    const discount = selectedPlan.value === 'business' ? categoryAd.value.business_plan_discount : 0;
    return Math.round(base * (1 - discount));
});

const total = computed(() => {
    let sum = 0;
    if (selectedPlan.value !== 'free') sum += planPrice.value;
    if (selectedPlan.value !== 'free') sum += extraBranches.value * branchAddon.value;
    sum += featuredPrice.value;
    return sum;
});

const totalBreakdown = computed(() => {
    const parts = [];
    if (selectedPlan.value !== 'free' && planRow.value) parts.push(`${planRow.value.name} ${isMonthly.value ? '1 сар' : planRow.value.term_years + ' жил'} ${fmt(planPrice.value)}`);
    if (selectedPlan.value !== 'free' && extraBranches.value) parts.push(`${extraBranches.value} салбар ${fmt(extraBranches.value * branchAddon.value)}`);
    if (featuredPrice.value) parts.push(`онцлох ${fmt(featuredPrice.value)}`);
    return parts.join(' + ');
});

function planCard(p) {
    return {
        tag: p.key === 'free' ? '1 ЖИЛ' : p.key === 'standard' ? 'САНАЛ БОЛГОХ' : `${p.limits.businesses} БИЗНЕС · ${p.term_years} ЖИЛ`,
        note: p.key === 'free' ? 'Одоо ч бүрэн хайлтад харагдана' : p.key === 'standard' ? 'Хязгааргүй салбар · салбар бүрд +₮5,000' : 'Жилд ~₮145,000 · салбар бүрд +₮5,000',
        lines: [
            { text: `${p.limits.businesses > 1 ? p.limits.businesses + ' бизнес хүртэл' : '1 бизнес'} · ${p.limits.images_per_branch} зураг`, on: true },
            { text: p.limits.branches === 0 ? 'Хязгааргүй салбар (+₮5,000 тутам)' : 'Салбар нэмэх боломжгүй', on: p.limits.branches === 0 },
            { text: 'Аналитик: хандалт, залгалт, хайлтын үгс', on: p.analytics },
            { text: 'ТОП жагсаалт, ✓ Баталгаажсан', on: p.top_list },
        ],
    };
}

async function checkout() {
    error.value = '';

    if (selectedPlan.value === 'free' && !featuredDays.value) {
        router.push({ name: 'console' });
        return;
    }

    busy.value = true;
    try {
        const campaigns = [];
        if (featuredDays.value && business.value) {
            campaigns.push({
                type: 'category_featured',
                business_id: business.value.id,
                branch_id: mainBranch.value?.id,
                category_id: business.value.category?.id,
                category_name: business.value.category?.name,
                district: mainBranch.value?.district,
                days: featuredDays.value,
            });
        }

        const data = await api.post('/checkout', {
            organization_id: organization.value.id,
            plan: selectedPlan.value !== 'free' ? selectedPlan.value : null,
            plan_period: selectedPlan.value !== 'free' && isMonthly.value ? 'monthly' : 'yearly',
            extra_branches: selectedPlan.value !== 'free' ? extraBranches.value : 0,
            campaigns,
        });

        const order = data.data;

        if (order.status === 'paid') {
            router.push({ name: 'order-success', params: { id: order.id } });
        } else if (order.invoice_url) {
            // Шууд byl.mn-ийн төлбөрийн хуудас руу (QR, банкны сонголт тэнд бий)
            window.location.href = order.invoice_url;
        } else {
            router.push({ name: 'order-pay', params: { id: order.id } });
        }
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    } finally {
        busy.value = false;
    }
}

async function load() {
    loadError.value = '';
    try {
        const [orgs, pricing] = await Promise.all([
            api.get('/console/organizations'),
            api.get('/pricing'),
        ]);

        organization.value = orgs.data.find((o) => o.id === Number(route.params.orgId)) || orgs.data[0];
        business.value = organization.value?.businesses?.[organization.value.businesses.length - 1];
        plans.value = pricing.plans;
        ads.value = pricing.ads;
        branchAddon.value = pricing.branch_addon_price;

        if ((business.value?.branches?.length || 1) > 1) selectedPlan.value = 'standard';

        if (business.value?.category?.id && mainBranch.value?.district) {
            const slots = await api.get('/slots', {
                type: 'category_featured',
                category_id: business.value.category.id,
                district: mainBranch.value.district,
            });
            slotState.value = slots;
        }
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    }
}

onMounted(load);
</script>

<template>
    <div v-if="loadError" class="flex min-h-screen items-center justify-center bg-white">
        <div class="card p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="load">Дахин оролдох</button>
        </div>
    </div>

    <div v-else class="min-h-screen bg-white">
        <!-- Толгой + шат (10a) -->
        <div class="flex items-center justify-between border-b border-line px-5 py-3.5 sm:px-10">
            <div class="flex items-center gap-2.5">
                <span class="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] bg-brand text-[13px] font-extrabold text-white">Х</span>
                <span class="text-base font-extrabold text-ink">Хаана<span class="text-brand">.mn</span></span>
            </div>
            <div class="hidden items-center gap-2.5 sm:flex">
                <div class="flex items-center gap-2"><span class="flex h-[22px] w-[22px] items-center justify-center rounded-full bg-green text-[10px] font-bold text-white">✓</span><span class="text-[12.5px] font-semibold text-ink">Мэдээлэл</span></div>
                <div class="h-[1.5px] w-6 bg-green"></div>
                <div class="flex items-center gap-2"><span class="flex h-[22px] w-[22px] items-center justify-center rounded-full bg-green text-[10px] font-bold text-white">✓</span><span class="text-[12.5px] font-semibold text-ink">Баталгаажуулалт</span></div>
                <div class="h-[1.5px] w-6 bg-brand"></div>
                <div class="flex items-center gap-2"><span class="flex h-[22px] w-[22px] items-center justify-center rounded-full border-[1.5px] border-brand bg-bluetint text-[10px] font-bold text-brand">3</span><span class="text-[12.5px] font-semibold text-ink">Эрх сонгох</span></div>
            </div>
        </div>

        <!-- Амжилтын banner -->
        <div class="flex flex-wrap items-center gap-3.5 border-b border-greenline bg-greentint px-5 py-4 sm:px-10">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green text-[15px] font-bold text-white">✓</span>
            <div class="flex-1">
                <div class="text-[15px] font-bold text-ink">«{{ business?.name }}» бүртгэгдлээ — хайлтад аль хэдийн харагдаж байна</div>
                <div class="mt-1 text-[12.5px] text-body">Дугаар баталгаажсан · редакцын хяналт 1–2 ажлын өдөрт дуусна</div>
            </div>
            <router-link :to="{ name: 'console' }" class="rounded-lg border border-[#cfe6da] bg-white px-3.5 py-2 text-[12.5px] font-semibold text-ink">Бүртгэлээ үзэх</router-link>
        </div>

        <div class="mx-auto max-w-7xl px-5 pb-8 pt-8 sm:px-10">
            <h1 class="text-[26px] font-extrabold tracking-[-.02em] text-ink">Эрхээ сонгоно уу</h1>
            <p class="mt-2 max-w-[640px] text-[13.5px] leading-[1.65] text-soft">
                Үнэгүй эрх нь 1 бизнес, 1 зураг, салбаргүй — 1 жилийн хугацаатай. Салбар нэмэх, аналитик, ТОП жагсаалт хэрэгтэй бол Стандарт эсвэл Бизнес эрхийг сонгоно.
            </p>

            <!-- Төлөх хугацаа -->
            <div class="mt-5 inline-flex gap-1.5 rounded-[10px] border border-searchline bg-white p-1.5">
                <button
                    class="cursor-pointer rounded-[7px] px-4 py-2 text-[12.5px] font-bold"
                    :class="planPeriod === 'yearly' ? 'bg-ink text-white' : 'text-soft'"
                    @click="planPeriod = 'yearly'"
                >Жилээр</button>
                <button
                    class="cursor-pointer rounded-[7px] px-4 py-2 text-[12.5px] font-bold"
                    :class="planPeriod === 'monthly' ? 'bg-ink text-white' : 'text-soft'"
                    @click="planPeriod = 'monthly'"
                >Сараар</button>
            </div>

            <!-- Эрхийн картууд -->
            <div class="mt-4 grid grid-cols-1 gap-3.5 md:grid-cols-3">
                <button
                    v-for="p in plans"
                    :key="p.key"
                    class="cursor-pointer rounded-xl border-[1.5px] p-5 text-left transition"
                    :class="selectedPlan === p.key ? 'border-brand bg-bluecard' : 'border-line bg-white hover:border-blueline'"
                    @click="selectedPlan = p.key"
                >
                    <div class="flex items-center gap-2.5">
                        <span class="h-[18px] w-[18px] shrink-0 rounded-full border-[1.5px]" :class="selectedPlan === p.key ? 'border-[5px] border-brand bg-white' : 'border-[#cfccc5]'"></span>
                        <span class="text-[15px] font-bold text-ink">{{ p.name }}</span>
                        <span class="ml-auto rounded-full px-2 py-0.5 text-[9.5px] font-semibold" :class="p.key === 'standard' ? 'bg-bluetint text-brand' : 'bg-chip text-chiptext'">{{ planCard(p).tag }}</span>
                    </div>
                    <div class="mt-3.5 flex items-baseline gap-1.5">
                        <span class="text-[26px] font-extrabold tracking-[-.02em] text-ink">{{ fmt(cardPrice(p)) }}</span>
                        <span class="text-[12px] font-medium text-mute">{{ cardUnit(p) }}</span>
                    </div>
                    <div v-if="p.price_monthly" class="mt-0.5 text-[11px] font-medium text-mute">
                        {{ planPeriod === 'yearly' ? `эсвэл сараар ${fmt(p.price_monthly)}/сар` : `жилээр ${fmt(p.price)} (${p.term_years} жил)` }}
                    </div>
                    <div class="mt-1.5 min-h-[32px] text-[11.5px] font-medium" :class="p.key === 'standard' ? 'text-brand' : 'text-mute'">{{ planCard(p).note }}</div>
                    <div class="my-3.5 h-px bg-divider"></div>
                    <div class="flex flex-col gap-2">
                        <div v-for="l in planCard(p).lines" :key="l.text" class="flex gap-2.5">
                            <span class="mt-0.5 flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full border-[1.5px] text-[8.5px] font-bold text-white" :class="l.on ? 'border-green bg-green' : 'border-inputline bg-white'">{{ l.on ? '✓' : '' }}</span>
                            <span class="text-[12px] font-medium leading-normal" :class="l.on ? 'text-body' : 'text-faint'">{{ l.text }}</span>
                        </div>
                    </div>
                </button>
            </div>

            <!-- Онцлох байршил нэмэх -->
            <div v-if="business?.category && mainBranch" class="mt-4 flex flex-wrap items-center gap-3.5 rounded-xl border-[1.5px] border-amberline bg-ambertint p-[18px]">
                <button
                    class="flex h-5 w-5 shrink-0 cursor-pointer items-center justify-center rounded-[5px] text-[11px] font-bold text-white"
                    :class="featuredDays ? 'bg-amber' : 'border-[1.5px] border-inputline bg-white'"
                    @click="featuredDays = featuredDays ? 0 : 30"
                >{{ featuredDays ? '✓' : '' }}</button>
                <div class="min-w-[220px] flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[14px] font-bold text-ink">Онцлох байршил нэмэх — {{ business.category.name }} · {{ mainBranch.district }}</span>
                        <span v-if="slotState" class="badge-featured">{{ slotState.total - slotState.occupied > 0 ? `${slotState.total - slotState.occupied} ЗАЙ СУЛ` : 'ЗАЙ ДҮҮРСЭН · ДАРААЛАЛД' }}</span>
                    </div>
                    <div class="mt-1 text-[12.5px] text-ambertext">Ангилалд дээд тал нь 3 онцлох · Бизнес эрхтэйд −10%</div>
                </div>
                <div class="flex gap-1.5 text-[11.5px] font-semibold">
                    <button
                        v-for="d in [7, 14, 30]"
                        :key="d"
                        class="cursor-pointer rounded-[7px] border px-3 py-2"
                        :class="featuredDays === d ? 'border-ink bg-ink text-white' : 'border-[#e0d3c2] bg-white text-ambertext'"
                        @click="featuredDays = d"
                    >{{ d }} хоног</button>
                </div>
                <div class="whitespace-nowrap text-[17px] font-extrabold text-ink">{{ featuredDays ? '+' + fmt(featuredPrice) : '—' }}</div>
            </div>

            <p v-if="error" class="mt-4 rounded-lg bg-redtint px-4 py-2.5 text-[13px] font-medium text-red">{{ error }}</p>
        </div>

        <!-- Доод нийлбэр -->
        <div class="border-t border-line">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-5 py-5 sm:px-10">
                <div>
                    <div class="text-[12px] font-medium text-mute">Нийт төлөх (НӨАТ орсон)</div>
                    <div class="mt-1 flex flex-wrap items-baseline gap-2">
                        <span class="text-[26px] font-extrabold tracking-[-.02em] text-ink">{{ fmt(total) }}</span>
                        <span class="text-[12px] font-medium text-mute">{{ totalBreakdown }}</span>
                    </div>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <router-link :to="{ name: 'console' }" class="text-[13px] font-semibold text-soft">Үнэгүйгээр үргэлжлүүлэх</router-link>
                    <button class="btn-primary !rounded-[10px] !px-6 !py-3.5" :disabled="busy" @click="checkout">
                        {{ busy ? 'Үүсгэж байна…' : total > 0 ? 'Төлбөр рүү → byl.mn' : 'Үргэлжлүүлэх' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
