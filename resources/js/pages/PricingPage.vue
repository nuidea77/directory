<script setup>
import { computed, onMounted, ref } from 'vue';
import { api } from '../api';

const plans = ref([]);
const ads = ref([]);
const branchAddon = ref(5000);
const period = ref('yearly'); // yearly | monthly
const loadError = ref('');

const fmt = (n) => '₮' + Number(n).toLocaleString();

// Сар/жилийн үнэ — сарын үнэгүй эрх (жишээ нь үнэгүй) жилээрээ харагдана
const priceFor = (p) => (period.value === 'monthly' && p.price_monthly ? p.price_monthly : p.price);
const unitFor = (p) => (period.value === 'monthly' && p.price_monthly ? '/ сар' : `/ ${p.term_years} жил`);

const faq = [
    { q: 'Үнэгүй бүртгэл хугацаа хязгаартай юу?', a: 'Үнэгүй бүртгэл 1 жилийн хугацаатай — дуусахад мэдээлэл устахгүй, хайлтад үлдэнэ. Нэмэлт боломжууд Стандарт/Бизнес эрхээс нээгдэнэ.' },
    { q: 'Онцлох байршил үнэлгээг өсгөх үү?', a: 'Үгүй. Зөвхөн эрэмбийг өөрчилнө, сэтгэгдэл, үнэлгээ бүрэн бодитой хэвээр.' },
    { q: 'Салбарын нэмэлт төлбөр хэрхэн бодогдох вэ?', a: 'Стандарт ба Бизнес эрхэд салбар хязгааргүй. Үнэгүй эрх дээр нэмэлт салбарын эрхийг тус бүрд нь худалдан авч болно.' },
    { q: 'Хугацаа дуусахад юу болох вэ?', a: 'Бүртгэл үнэгүй эрх болж хувирна — 1 зураг, салбаргүй, аналитикгүй. Мэдээлэл устахгүй.' },
];

const planCards = computed(() => plans.value.map((p) => ({
    ...p,
    tag: p.key === 'free' ? '1 ЖИЛ' : p.key === 'standard' ? 'ХАМГИЙН ЭРЭЛТТЭЙ' : '5 БИЗНЕС ХҮРТЭЛ',
    for: p.key === 'free' ? 'Нэг хаягтай жижиг бизнес, шинээр нээсэн газар.' : p.key === 'standard' ? 'Нэг брэнд, хэд хэдэн салбартай бизнес.' : 'Хэд хэдэн брэнд, сүлжээ, франчайз эзэмшигч.',
    note: p.key === 'free' ? 'Картын мэдээлэл шаардахгүй' : 'Салбар хязгааргүй',
    cta: p.key === 'free' ? 'Бизнес нэмэх' : p.name + ' авах',
    features: [
        { text: p.limits.businesses > 1 ? `${p.limits.businesses} бизнес хүртэл` : '1 бизнес', on: true },
        { text: p.limits.branches === 0 ? 'Хязгааргүй салбар' : 'Салбар нэмэх боломжгүй', on: p.limits.branches === 0 },
        { text: `${p.term_years} жилийн хугацаа`, on: true },
        { text: `${p.limits.images_per_branch} зураг`, on: true },
        { text: 'Аналитик (хандалт, залгалт, хайлтын үгс)', on: p.analytics },
        { text: 'ТОП жагсаалт, ✓ Баталгаажсан', on: p.top_list },
    ],
})));

async function fetchPricing() {
    loadError.value = '';
    try {
        const data = await api.get('/pricing');
        plans.value = data.plans;
        ads.value = data.ads;
        branchAddon.value = data.branch_addon_price;
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    }
}

onMounted(fetchPricing);
</script>

<template>
    <div class="bg-white">
        <!-- Hero (8a) -->
        <div class="border-b border-line bg-hero px-5 py-11 text-center sm:px-10">
            <div class="text-[11px] font-semibold tracking-[.12em] text-brand">БИЗНЕСТ ЗОРИУЛСАН</div>
            <h1 class="mx-auto mt-3.5 max-w-3xl text-[28px] font-extrabold leading-tight tracking-[-.025em] text-ink sm:text-4xl">
                Бүртгэл үнэгүй. Салбар, аналитик хэрэгтэй бол эрх авна.
            </h1>
            <p class="mx-auto mt-2.5 max-w-[620px] text-[14.5px] leading-relaxed text-soft">
                Бизнесүүдийн дийлэнх нь үнэгүй бүртгэлээр ажилладаг. Төлбөртэй эрх нь статистик, олон салбар, редакцын дэмжлэг нэмнэ. Онцлох байршил нь нэмэлт бүтээгдэхүүн.
            </p>
            <div class="mt-5 inline-flex gap-1.5 rounded-[10px] border border-searchline bg-white p-1.5">
                <button
                    class="cursor-pointer rounded-[7px] px-4 py-2 text-[12.5px] font-bold"
                    :class="period === 'yearly' ? 'bg-ink text-white' : 'text-soft'"
                    @click="period = 'yearly'"
                >Жилээр</button>
                <button
                    class="cursor-pointer rounded-[7px] px-4 py-2 text-[12.5px] font-bold"
                    :class="period === 'monthly' ? 'bg-ink text-white' : 'text-soft'"
                    @click="period = 'monthly'"
                >Сараар</button>
            </div>
        </div>

        <!-- Эрхийн бичгүүд -->
        <div v-if="loadError" class="mx-auto max-w-xl px-5 py-16 text-center">
            <p class="text-[15px] font-bold text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="fetchPricing">Дахин оролдох</button>
        </div>

        <div v-else class="mx-auto grid max-w-7xl grid-cols-1 border-b border-line md:grid-cols-3">
            <div v-for="p in planCards" :key="p.key" class="border-line px-7 py-8 md:border-r md:last:border-r-0" :class="{ 'bg-bluecard': p.key === 'standard' }">
                <div class="flex items-center gap-2">
                    <span class="text-[17px] font-bold text-ink">{{ p.name }}</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="p.key === 'standard' ? 'bg-bluetint text-brand' : 'bg-chip text-chiptext'">{{ p.tag }}</span>
                </div>
                <div class="mt-2 min-h-[38px] text-[12.5px] leading-relaxed text-soft">{{ p.for }}</div>
                <div class="mt-3.5 flex items-baseline gap-1.5">
                    <span class="text-[32px] font-extrabold tracking-[-.025em] text-ink">{{ fmt(priceFor(p)) }}</span>
                    <span class="text-[12.5px] font-medium text-mute">{{ unitFor(p) }}</span>
                </div>
                <div class="mt-1 text-[11.5px] font-medium text-mute">
                    <template v-if="p.price_monthly">
                        {{ period === 'yearly' ? `эсвэл сараар ${fmt(p.price_monthly)}/сар` : `жилээр авбал ${fmt(p.price)} (${p.term_years} жил)` }}
                    </template>
                </div>
                <div class="mt-1.5 text-[11.5px] font-medium" :class="p.key === 'standard' ? 'text-brand' : 'text-mute'">{{ p.note }}</div>
                <router-link
                    :to="{ name: 'add-business' }"
                    class="mt-4 block rounded-[9px] border py-3 text-center text-[13px] font-bold"
                    :class="p.key === 'standard' ? 'border-brand bg-brand text-white hover:bg-brand-dark' : p.key === 'business' ? 'border-ink bg-ink text-white hover:bg-black' : 'border-inputline bg-white text-ink hover:bg-panel'"
                >{{ p.cta }}</router-link>
                <div class="my-5 h-px bg-divider"></div>
                <div class="flex flex-col gap-2.5">
                    <div v-for="f in p.features" :key="f.text" class="flex gap-2.5">
                        <span class="mt-0.5 flex h-[15px] w-[15px] shrink-0 items-center justify-center rounded-full border-[1.5px] text-[9px] font-bold text-white" :class="f.on ? 'border-green bg-green' : 'border-inputline bg-white'">{{ f.on ? '✓' : '' }}</span>
                        <span class="text-[12.5px] font-medium leading-normal" :class="f.on ? 'text-body' : 'text-faint'">{{ f.text }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Онцлох байршил -->
        <div class="mx-auto max-w-7xl px-5 py-9 sm:px-10">
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div class="max-w-[620px]">
                    <div class="text-[11px] font-semibold tracking-[.12em] text-amber">НЭМЭЛТ БҮТЭЭГДЭХҮҮН</div>
                    <h2 class="mt-2.5 text-2xl font-extrabold tracking-[-.02em] text-ink">Онцлох байршил — хайлтын хамгийн дээр</h2>
                    <p class="mt-2 text-[13.5px] leading-relaxed text-soft">
                        Ангилал + дүүрэг тус бүрт зэрэг ердөө 3 онцлох зай. Хугацаа 7, 14 эсвэл 30 хоног — үнэгүй ба төлбөртэй бүртгэл хоёулаа авч болно. Онцлох зай нь «Онцлох» тэмдэгтэй, хэрэглэгчид ил тод харагдана.
                    </p>
                </div>
                <div class="w-[230px] rounded-[11px] border border-line p-4">
                    <div class="text-[10.5px] font-semibold tracking-[.08em] text-mute">ЖИШЭЭ ҮР ДҮН</div>
                    <div class="mt-2 flex items-baseline gap-1.5"><span class="text-2xl font-extrabold text-ink">ТОП 3</span><span class="text-[12px] font-medium text-soft">байрлалд гарна</span></div>
                    <div class="mt-1.5 text-[11.5px] leading-normal text-mute">Авто засвар ангилалд 30 хоногийн дундаж</div>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3.5 md:grid-cols-3">
                <div v-for="ad in ads" :key="ad.key" class="rounded-xl border-[1.5px] p-5" :class="ad.key === 'category_featured' ? 'border-amberline bg-ambertint' : 'border-line bg-white'">
                    <div class="flex items-center gap-2">
                        <span class="text-[15px] font-bold text-ink">{{ ad.name }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="ad.key === 'category_featured' ? 'bg-amberbadge text-amber' : ad.key === 'home_featured' ? 'bg-bluetint text-brand' : 'bg-chip text-chiptext'">
                            {{ ad.key === 'keyword' ? 'ҮГ ТУТАМД 3 ЗАЙ' : (ad.key === 'category_featured' ? 'ДЭЭД' : '') + ' ' + ad.slots + ' ЗАЙ' }}
                        </span>
                    </div>
                    <div class="mt-2 min-h-[52px] text-[12px] leading-relaxed text-soft">
                        <template v-if="ad.key === 'category_featured'">Сонгосон ангилал + дүүргийн хайлтын дээд 3-т. Ангилал тус бүрт зэрэг ердөө 3 онцлох.</template>
                        <template v-else-if="ad.key === 'home_featured'">Нүүр хуудасны «Онцлох бизнесүүд» хэсэгт, хот бүрт 6 зай, өдөр тутам эргэлддэг.</template>
                        <template v-else>«шүдний эмнэлэг», «авто засвар» гэх мэт хайлтын үгэнд дээгүүр гарах. Үг тутамд 3 зай.</template>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1.5">
                        <span class="text-2xl font-extrabold tracking-[-.02em] text-ink">{{ fmt(ad.prices[30]) }}</span>
                        <span class="text-[12px] font-medium text-mute">/ 30 хоног{{ ad.key === 'keyword' ? ' · үг тутам' : '' }}</span>
                    </div>
                    <div class="mt-1.5 text-[11.5px] font-medium text-mute">
                        7 хоног {{ fmt(ad.prices[7]) }} · 14 хоног {{ fmt(ad.prices[14]) }}{{ ad.business_plan_discount > 0 ? ` · Бизнес эрхтэйд −${ad.business_plan_discount * 100}%` : '' }}
                    </div>
                    <router-link :to="{ name: 'ad-purchase', query: { type: ad.key } }" class="mt-4 block rounded-lg border py-2.5 text-center text-[12.5px] font-bold" :class="ad.key === 'category_featured' ? 'border-ink bg-ink text-white' : 'border-inputline bg-white text-ink'">
                        {{ ad.key === 'category_featured' ? 'Зай сонгох' : ad.key === 'home_featured' ? 'Сул зай харах' : 'Үг сонгох' }}
                    </router-link>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3.5 md:grid-cols-2">
                <!-- Хэрхэн харагдах -->
                <div class="rounded-xl border border-line p-5">
                    <div class="text-[15px] font-bold text-ink">Онцлох байршил хэрхэн харагдах</div>
                    <div class="mt-3.5 flex flex-col gap-2">
                        <div class="flex items-center gap-3 rounded-[10px] border-[1.5px] border-amberline bg-ambertint p-3">
                            <div class="img-ph h-9 w-11 shrink-0 rounded-[7px]"></div>
                            <div>
                                <div class="flex items-center gap-2"><span class="text-[13px] font-bold text-ink">Хангай Авто — БЗД</span><span class="badge-featured">ОНЦЛОХ</span></div>
                                <div class="mt-0.5 text-[11.5px] text-mute">4.6 · Авто засвар · 1.8 км</div>
                            </div>
                        </div>
                        <div v-for="r in [['Түмэн Мотор', '4.3 · Авто засвар · 0.4 км'], ['Мөнх Тех Сервис', '4.4 · Авто засвар · 1.6 км'], ['Драйв Плюс', '4.2 · Тохируулга · 1.7 км']]" :key="r[0]" class="flex items-center gap-3 rounded-[10px] border border-line p-3">
                            <div class="img-ph h-9 w-11 shrink-0 rounded-[7px]"></div>
                            <div>
                                <div class="text-[13px] font-bold text-ink">{{ r[0] }}</div>
                                <div class="mt-0.5 text-[11.5px] text-mute">{{ r[1] }}</div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 text-[11.5px] leading-relaxed text-mute">Онцлох зай нь эрэмбийг л өөрчилнө — үнэлгээ, сэтгэгдлийг хэзээ ч нөлөөлөхгүй.</p>
                </div>

                <!-- FAQ -->
                <div class="rounded-xl border border-line p-5">
                    <div class="text-[15px] font-bold text-ink">Байнга асуудаг</div>
                    <div class="mt-3">
                        <div v-for="item in faq" :key="item.q" class="border-b border-hairline py-3 last:border-0">
                            <div class="text-[12.5px] font-bold text-ink">{{ item.q }}</div>
                            <div class="mt-1.5 text-[12px] leading-relaxed text-soft">{{ item.a }}</div>
                        </div>
                    </div>
                    <p class="mt-3 text-[11.5px] leading-relaxed text-mute">Төлбөр: byl.mn (банкны QR, карт). НӨАТ баримт автоматаар.</p>
                </div>
            </div>
        </div>
    </div>
</template>
