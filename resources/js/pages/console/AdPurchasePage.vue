<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../../api';
import { useConsoleStore } from '../../stores/console';

/**
 * Онцлох байршил худалдан авах (8b): төрөл/салбар/ангилал сонгох,
 * сул зай харах, хугацаа сонгох, төлбөрийн тойм → checkout.
 */
const route = useRoute();
const router = useRouter();
const store = useConsoleStore();

const type = ref(route.query.type || 'category_featured');
const pricing = ref(null);
const slots = ref(null);
const selectedBusinessId = ref(null);
const selectedBranchId = ref(null);
const keyword = ref('');
const days = ref(30);
const error = ref('');
const loadError = ref('');
const pricingError = ref('');
const busy = ref(false);

// Промо код — сурталчилгааны ангилалд хүчинтэй кодууд
const promoInput = ref('');
const promo = ref(null);
const promoError = ref('');
const promoBusy = ref(false);

const fmt = (n) => '₮' + Number(n).toLocaleString();

const business = computed(() => store.businesses.find((b) => b.id === selectedBusinessId.value) || store.businesses[0]);
const branch = computed(() => (business.value?.branches || []).find((b) => b.id === selectedBranchId.value) || business.value?.branches?.[0]);

const adConfig = computed(() => pricing.value?.ads?.find((a) => a.key === type.value));

const discount = computed(() => {
    if (store.organization?.effective_plan === 'business' && adConfig.value?.business_plan_discount > 0) {
        return adConfig.value.business_plan_discount;
    }
    return 0;
});

const price = computed(() => adConfig.value?.prices?.[days.value] || 0);
const discountAmount = computed(() => Math.round(price.value * discount.value));
const subtotal = computed(() => price.value - discountAmount.value);
const total = computed(() => Math.max(0, subtotal.value - (promo.value?.discount || 0)));

// Үнэхээр авах боломжтой зай: төлбөр хүлээж буй болон дараалалд байгаа нь
// зайг барьдаг тул server-ийн тооцсон free-г шууд ашиглана
const slotsFree = computed(() => slots.value?.free ?? 0);
const slotsFull = computed(() => !!slots.value && slotsFree.value === 0);

// Огноог YYYY.MM.DD хэлбэрээр — browser бүрд mn-MN locale байдаггүй
const shortDate = (v) => {
    if (!v) return '';
    const d = new Date(v);
    return `${d.getFullYear()}.${String(d.getMonth() + 1).padStart(2, '0')}.${String(d.getDate()).padStart(2, '0')}`;
};

const nextFreeLabel = computed(() => shortDate(slots.value?.next_free_at));

const daysUntilFree = computed(() => {
    if (!slots.value?.next_free_at) return null;
    return Math.max(0, Math.ceil((new Date(slots.value.next_free_at) - Date.now()) / 86400000));
});

// Нүүрийн онцлох нь ХОТ тус бүрт тусдаа зайтай — сонгосон салбарын хот.
// Өмнө нь үргэлж «Улаанбаатар» гэж хатуу бичигдсэн тул хөдөө орон нутгийн
// бизнес өөрийнхөө хотод зар авах боломжгүй байв.
const homeCity = computed(() => branch.value?.city || 'Улаанбаатар');

const scopeLabel = computed(() => {
    if (type.value === 'category_featured') return `${business.value?.category?.name || ''} · ${branch.value?.district || ''}`;
    if (type.value === 'home_featured') return `Нүүр хуудас · ${homeCity.value}`;
    return keyword.value ? `«${keyword.value}»` : 'Хайлтын үг';
});

async function fetchSlots() {
    slots.value = null;
    loadError.value = '';
    if (type.value === 'keyword' && !keyword.value.trim()) return;
    if (type.value === 'category_featured' && (!business.value?.category?.id || !branch.value?.district)) return;

    try {
        const data = await api.get('/slots', {
            type: type.value,
            category_id: type.value === 'category_featured' ? business.value.category.id : undefined,
            district: type.value === 'category_featured' ? branch.value.district : undefined,
            city: type.value === 'home_featured' ? homeCity.value : undefined,
            keyword: type.value === 'keyword' ? keyword.value.trim().toLowerCase() : undefined,
        });
        slots.value = data;
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    }
}

function orderPayload() {
    return {
        organization_id: store.organization.id,
        campaigns: [{
            type: type.value,
            business_id: business.value.id,
            branch_id: branch.value?.id,
            category_id: type.value === 'category_featured' ? business.value.category.id : undefined,
            category_name: type.value === 'category_featured' ? business.value.category.name : undefined,
            district: type.value === 'category_featured' ? branch.value.district : undefined,
            city: type.value === 'home_featured' ? homeCity.value : undefined,
            keyword: type.value === 'keyword' ? keyword.value.trim().toLowerCase() : undefined,
            days: days.value,
        }],
    };
}

// Кодыг серверээр шалгаж хөнгөлөлтийг харуулна
async function applyPromo() {
    promoError.value = '';
    promo.value = null;

    if (!promoInput.value.trim()) return;

    promoBusy.value = true;
    try {
        const data = await api.post('/checkout/quote', {
            ...orderPayload(),
            promo_code: promoInput.value.trim(),
        });
        promo.value = { code: data.promo_code, discount: data.discount };
    } catch (e) {
        promoError.value = e instanceof ApiError ? e.firstError() : 'Кодыг шалгахад алдаа гарлаа';
    } finally {
        promoBusy.value = false;
    }
}

function clearPromo() {
    promo.value = null;
    promoInput.value = '';
    promoError.value = '';
}

async function checkout() {
    error.value = '';
    busy.value = true;
    try {
        const data = await api.post('/checkout', {
            ...orderPayload(),
            promo_code: promo.value?.code || undefined,
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

watch([type, selectedBusinessId, selectedBranchId], fetchSlots);
// Сонголт/хугацаа өөрчлөгдвөл хөнгөлөлтийг дахин шалгуулна — дүн өөрчлөгдсөн
watch([type, days, selectedBusinessId, selectedBranchId, keyword], () => {
    if (promo.value) clearPromo();
});

async function loadPage() {
    pricingError.value = '';
    await store.load();
    try {
        pricing.value = await api.get('/pricing');
    } catch {
        pricingError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
        return;
    }
    fetchSlots();
}

onMounted(loadPage);
</script>

<template>
    <div class="min-h-screen bg-page">
        <!-- Толгой (8b) -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-white px-5 py-3 sm:px-7">
            <div class="flex items-center gap-3.5">
                <router-link :to="{ name: 'console' }" class="flex items-center gap-2.5">
                    <span class="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] bg-brand text-[13px] font-extrabold text-white">Х</span>
                    <span class="text-[15px] font-extrabold text-ink">Бизнес зөвлөл</span>
                </router-link>
                <div class="hidden items-center gap-1.5 text-[12.5px] font-medium text-mute sm:flex">
                    <span class="text-brand">{{ store.organization?.name }}</span><span>/</span>
                    <router-link :to="{ name: 'console-plan' }" class="text-brand">Сурталчилгаа</router-link><span>/</span>
                    <span class="font-semibold text-ink">Онцлох байршил</span>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <span class="badge-verified uppercase">{{ store.organization?.plan_name }}</span>
                <span v-if="store.organization?.plan_expires_at" class="text-[12.5px] font-medium text-soft">Эрх дуусах {{ shortDate(store.organization.plan_expires_at) }}</span>
            </div>
        </div>

        <div class="mx-auto grid max-w-7xl grid-cols-1 lg:grid-cols-[1fr_372px]">
            <div class="border-line px-5 py-6 sm:px-7 lg:border-r">
                <h1 class="text-2xl font-extrabold tracking-[-.02em] text-ink">Онцлох байршил худалдан авах</h1>
                <p class="mt-2 max-w-[600px] text-[13.5px] leading-[1.65] text-soft">
                    Төрөл, салбар, ангилал сонгоно. <b>Ангилал + дүүрэг тус бүрт ердөө {{ slots?.total || 3 }} зай</b> — бүгд эзэлсэн бол зай сулрахыг хүлээнэ. Хугацаа: 7, 14 эсвэл 30 хоног.
                </p>

                <div v-if="pricingError" class="card mt-5 max-w-[620px] p-10 text-center">
                    <p class="text-[13px] font-medium text-red">{{ pricingError }}</p>
                    <button class="btn-primary mt-4" @click="loadPage">Дахин оролдох</button>
                </div>

                <!-- Төрөл -->
                <div class="mt-5 flex flex-wrap gap-2">
                    <button
                        v-for="ad in pricing?.ads || []"
                        :key="ad.key"
                        class="cursor-pointer rounded-[9px] border-[1.5px] px-4 py-2.5 text-[12.5px] font-bold"
                        :class="type === ad.key ? 'border-brand bg-bluecard text-brand' : 'border-line bg-white text-body'"
                        @click="type = ad.key"
                    >{{ ad.name }}</button>
                </div>

                <!-- Сонголтууд -->
                <div class="mt-5 grid max-w-[620px] grid-cols-1 gap-3 sm:grid-cols-3">
                    <div v-if="store.businesses.length > 1">
                        <label class="field-label">Бизнес</label>
                        <select v-model="selectedBusinessId" class="input cursor-pointer">
                            <option v-for="b in store.businesses" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div v-if="type !== 'keyword'">
                        <label class="field-label">Салбар{{ type === 'home_featured' ? ' (хот тодорхойлно)' : '' }}</label>
                        <select v-model="selectedBranchId" class="input cursor-pointer">
                            <option v-for="b in business?.branches || []" :key="b.id" :value="b.id">
                                {{ b.city && b.city !== 'Улаанбаатар' ? b.city + ' · ' : '' }}{{ b.district }}
                            </option>
                        </select>
                    </div>
                    <div v-if="type === 'category_featured'">
                        <label class="field-label">Ангилал</label>
                        <div class="input pointer-events-none flex justify-between !bg-panel"><span>{{ business?.category?.name }}</span></div>
                    </div>
                    <div v-if="type === 'keyword'" class="sm:col-span-2">
                        <label class="field-label">Хайлтын үг</label>
                        <div class="flex gap-2">
                            <input v-model="keyword" type="text" placeholder="жш: авто засвар" class="input" @keyup.enter="fetchSlots" />
                            <button class="btn-outline shrink-0 !py-2.5" @click="fetchSlots">Шалгах</button>
                        </div>
                    </div>
                </div>

                <!-- Сул зай -->
                <div class="mb-3 mt-6 flex flex-wrap items-baseline gap-2">
                    <span class="text-[15px] font-bold text-ink">Сул зай — {{ scopeLabel }}</span>
                    <span v-if="slots" class="rounded-full px-2 py-0.5 text-[10.5px] font-bold" :class="slotsFull ? 'bg-amberbadge text-amber' : 'bg-greentint text-green'">
                        {{ slotsFull ? 'ЗАЙ ДҮҮРСЭН' : slotsFree + ' ЗАЙ СУЛ' }}
                    </span>
                </div>

                <!-- Зай дүүрсэн: хэзээ боломжтой болохыг хэлнэ -->
                <div v-if="slotsFull" class="mb-3 max-w-[620px] rounded-[11px] border-[1.5px] border-amberline bg-ambertint p-4">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber text-[13px] font-bold text-white">!</span>
                        <span class="text-[13.5px] font-bold text-ink">
                            {{ scopeLabel }} — бүх {{ slots.total }} зай эзэлсэн байна
                        </span>
                    </div>
                    <div class="mt-2 text-[12.5px] leading-[1.65] text-ambertext">
                        <template v-if="nextFreeLabel">
                            Дараагийн сул зай <b>{{ nextFreeLabel }}</b>-нд гарна<template v-if="daysUntilFree !== null"> ({{ daysUntilFree }} хоногийн дараа)</template>. Тэр үед энэ хуудсаар дахин орж худалдан авна уу.
                        </template>
                        <template v-else>Зай сулрах хугацаа тодорхойгүй байна.</template>
                        <template v-if="slots.pending"> Одоогоор {{ slots.pending }} захиалга төлбөр хүлээж байна — цуцлагдвал зай эрт суларч болно.</template>
                    </div>
                    <div class="mt-2.5 text-[12px] font-medium text-ambertext">
                        Өөр дүүрэг эсвэл өөр төрлийн зар (нүүр хуудас, хайлтын үг) сонгож үзээрэй.
                    </div>
                </div>
                <div v-if="slots" class="flex max-w-[620px] flex-col gap-2.5">
                    <div
                        v-for="slot in slots.slots"
                        :key="slot.slot"
                        class="flex items-center gap-3.5 rounded-[11px] border-[1.5px] p-3.5"
                        :class="slot.available ? (slot.slot === slots.occupied + 1 ? 'border-brand bg-bluecard' : 'border-line bg-white') : 'border-line bg-panel'"
                    >
                        <span class="h-[18px] w-[18px] shrink-0 rounded-full border-[1.5px]" :class="slot.slot === slots.occupied + 1 && slot.available ? 'border-[5px] border-brand bg-white' : 'border-[#cfccc5]'"></span>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-[13.5px] font-bold text-ink">{{ slot.slot }}-р зай</span>
                                <span class="rounded-[4px] px-1.5 py-0.5 text-[9.5px] font-semibold" :class="slot.available ? 'bg-greentint text-green' : 'bg-chip text-mute'">{{ slot.available ? 'СУЛ' : 'ЭЗЭЛСЭН' }}</span>
                            </div>
                            <div class="mt-1 text-[11.5px] text-mute">
                                {{ slot.available ? scopeLabel : (slot.occupied_until ? shortDate(slot.occupied_until) + ' хүртэл эзэлсэн' : 'Эзэлсэн') }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[14px] font-bold text-ink">{{ slot.available ? fmt(price) : '—' }}</div>
                            <div class="mt-0.5 text-[11px] font-medium text-mute">{{ slot.available ? `/ ${days} хоног` : 'эзэлсэн' }}</div>
                        </div>
                    </div>
                </div>
                <div v-else-if="loadError" class="card max-w-[620px] p-10 text-center">
                    <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
                    <button class="btn-primary mt-4" @click="fetchSlots">Дахин оролдох</button>
                </div>
                <p v-else class="text-[13px] text-mute">{{ type === 'keyword' ? 'Хайлтын үгээ оруулаад шалгана уу.' : 'Ачаалж байна…' }}</p>

                <!-- Хугацаа -->
                <div class="mb-3 mt-6 text-[15px] font-bold text-ink">Хугацаа</div>
                <div class="flex max-w-[620px] gap-2.5">
                    <button
                        v-for="d in [7, 14, 30]"
                        :key="d"
                        class="flex-1 cursor-pointer rounded-[11px] border-[1.5px] p-3.5 text-left"
                        :class="days === d ? 'border-brand bg-bluecard' : 'border-line bg-white'"
                        @click="days = d"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-[13.5px] font-bold text-ink">{{ d }} хоног</span>
                            <span v-if="d > 7 && adConfig" class="ml-auto rounded-full bg-[#e5f3ea] px-1.5 py-0.5 text-[9.5px] font-semibold text-green">
                                −{{ Math.round((1 - (adConfig.prices[d] / d) / (adConfig.prices[7] / 7)) * 100) }}%
                            </span>
                        </div>
                        <div class="mt-2 text-[19px] font-extrabold text-ink">{{ adConfig ? fmt(adConfig.prices[d]) : '—' }}</div>
                        <div class="mt-1 text-[11.5px] font-medium text-mute">{{ adConfig ? fmt(Math.round(adConfig.prices[d] / d)) : '—' }} / хоног</div>
                    </button>
                </div>

                <!-- Ил тод байдал -->
                <div class="mt-5 max-w-[620px] rounded-[11px] border border-amberline bg-ambertint p-4">
                    <div class="text-[13px] font-bold text-ink">Ил тод байдлын дүрэм</div>
                    <div class="mt-1.5 text-[12.5px] leading-[1.65] text-ambertext">
                        Онцлох бүртгэл нь «ОНЦЛОХ» тэмдэгтэй харагдана. Эрэмбийг л өөрчилнө — статистикт зөвхөн бодит үзэлт бүртгэгдэнэ.
                    </div>
                </div>

                <p v-if="error" class="mt-4 max-w-[620px] rounded-lg bg-redtint px-4 py-2.5 text-[13px] font-medium text-red">{{ error }}</p>

                <div class="mt-5 flex max-w-[620px] flex-wrap items-center gap-2.5">
                    <button v-if="!slotsFull" class="btn-primary !px-6" :disabled="busy || !total || !slots" @click="checkout()">Төлбөр рүү</button>
                    <button v-else class="btn-outline !px-5 cursor-not-allowed opacity-60" disabled>
                        Зай дүүрсэн{{ nextFreeLabel ? ` — ${nextFreeLabel}-нд боломжтой` : '' }}
                    </button>
                    <span class="ml-auto text-[12px] font-medium text-mute">Хүссэн үедээ цуцлана · хугацаа дуустал үргэлжилнэ</span>
                </div>
            </div>

            <!-- Төлбөрийн тойм -->
            <aside class="bg-white px-5 py-6 sm:px-6">
                <div class="kicker">ТӨЛБӨРИЙН ТОЙМ</div>
                <div class="card mt-3 p-[18px]">
                    <div class="flex items-start gap-2.5">
                        <div>
                            <div class="text-[12.5px] font-semibold text-ink">{{ adConfig?.name }}</div>
                            <div class="mt-0.5 text-[11px] text-mute">{{ scopeLabel }} · {{ days }} хоног</div>
                        </div>
                        <div class="ml-auto whitespace-nowrap text-[12.5px] font-semibold text-ink">{{ fmt(price) }}</div>
                    </div>
                    <div v-if="discountAmount" class="mt-3 flex items-start gap-2.5">
                        <div>
                            <div class="text-[12.5px] font-semibold text-ink">Бизнес эрхийн хөнгөлөлт ({{ discount * 100 }}%)</div>
                            <div class="mt-0.5 text-[11px] text-mute">Онцлох байршлын үнэд</div>
                        </div>
                        <div class="ml-auto whitespace-nowrap text-[12.5px] font-semibold text-green">−{{ fmt(discountAmount) }}</div>
                    </div>
                    <!-- Промо кодын хөнгөлөлт -->
                    <div v-if="promo" class="mt-3 flex items-start gap-2.5">
                        <div>
                            <div class="text-[12.5px] font-semibold text-ink">Промо код</div>
                            <div class="mt-0.5 font-mono text-[11px] text-mute">{{ promo.code }}</div>
                        </div>
                        <div class="ml-auto whitespace-nowrap text-[12.5px] font-semibold text-green">−{{ fmt(promo.discount) }}</div>
                    </div>

                    <div class="my-4 h-px bg-divider"></div>
                    <div class="flex items-baseline justify-between">
                        <span class="text-[13.5px] font-bold text-ink">Нийт төлөх</span>
                        <span class="text-[22px] font-extrabold tracking-[-.02em] text-ink">{{ fmt(total) }}</span>
                    </div>
                </div>

                <!-- Промо код оруулах -->
                <div v-if="!slotsFull" class="kicker mt-5">ПРОМО КОД</div>
                <div v-if="!slotsFull" class="mt-2.5">
                    <div v-if="!promo" class="flex flex-wrap gap-2">
                        <input
                            v-model="promoInput"
                            type="text"
                            placeholder="Кодоо оруулна уу"
                            class="input !py-2.5 uppercase"
                            @keyup.enter="applyPromo"
                        />
                        <button class="btn-outline w-full !py-2.5 !text-[12.5px]" :disabled="promoBusy || !promoInput.trim()" @click="applyPromo">
                            {{ promoBusy ? 'Шалгаж байна…' : 'Хэрэглэх' }}
                        </button>
                    </div>
                    <div v-else class="flex flex-wrap items-center gap-2.5 rounded-[10px] border border-greenline bg-greentint px-3 py-2.5">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green text-[11px] font-bold text-white">✓</span>
                        <span class="font-mono text-[12.5px] font-bold text-ink">{{ promo.code }}</span>
                        <button class="ml-auto cursor-pointer text-[12px] font-semibold text-soft hover:text-red" @click="clearPromo">Хасах</button>
                    </div>
                    <p v-if="promoError" class="mt-2 text-[12px] font-medium text-red">{{ promoError }}</p>
                </div>

                <div class="kicker mt-5">ТӨЛБӨРИЙН АРГА</div>
                <div class="mt-2.5 flex items-center gap-2.5 rounded-[10px] border-[1.5px] border-brand bg-bluecard p-3">
                    <span class="h-4 w-4 rounded-full border-[5px] border-brand bg-white"></span>
                    <span class="text-[12.5px] font-semibold text-ink">byl.mn QR</span>
                    <span class="ml-auto text-[11px] font-medium text-mute">Банкны аппаар</span>
                </div>

                <button v-if="total && !slotsFull" class="btn-primary mt-4 w-full !rounded-[10px] !py-3.5" :disabled="busy" @click="checkout()">{{ fmt(total) }} төлөх</button>
                <!-- Зай дүүрсэн бол төлөх боломжгүй — сервер ч татгалзана -->
                <div v-else-if="slotsFull" class="mt-4 rounded-[10px] border border-amberline bg-ambertint px-4 py-3 text-center">
                    <div class="text-[12.5px] font-bold text-ink">Энэ байршилд зай дүүрсэн</div>
                    <div class="mt-1 text-[11.5px] leading-normal text-ambertext">
                        {{ nextFreeLabel ? `${nextFreeLabel}-нд сул зай гарна` : 'Зай сулрахыг хүлээнэ үү' }}
                    </div>
                </div>
                <p class="mt-3 text-center text-[11.5px] leading-relaxed text-mute">Төлбөр батлагдмагц онцлох байршил 10 минутын дотор нээгдэнэ.</p>
            </aside>
        </div>
    </div>
</template>
