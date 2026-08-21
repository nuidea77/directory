<script setup>
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { shortDate } from '../utils/date';
import { dayLabel } from '../utils/hours';
import { api, ApiError } from '../api';
import { useAuthStore } from '../stores/auth';
import ImagePh from '../components/ImagePh.vue';
import BizLogo from '../components/BizLogo.vue';
import VerifiedBadge from '../components/VerifiedBadge.vue';

// Leaflet-тэй тул lazy-load — үндсэн bundle томрохгүй
const MapView = defineAsyncComponent(() => import('../components/MapView.vue'));

// Газрын зурагт бүх идэвхтэй, координаттай салбаруудыг pin-ээр харуулна
const mapMarkers = () => (business.value?.branches || [])
    .filter((b) => b.lat !== null && b.lat !== undefined)
    .map((b, i) => ({ id: b.id, lat: b.lat, lng: b.lng, label: i + 1 }));

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const business = ref(null);

// Ангиллын chip: үндсэн ангилал эхэнд
const categoryChips = computed(() => {
    const list = business.value?.categories || [];
    const primary = business.value?.category?.id;
    return [...list].sort((a, b) => (b.id === primary) - (a.id === primary));
});
const similar = ref([]);
const selectedBranchId = ref(null);
const loading = ref(true);
const notFound = ref(false);
const loadError = ref('');
const tab = ref('overview');

// Анхдагчаар од сонгоогүй байна — 5 од урьдчилж тавибал бүх үнэлгээ 5 болно
const reviewForm = ref({ rating: 0, comment: '' });
const reviewError = ref('');
const reviewBusy = ref(false);
const favoriteBusy = ref(false);

const correctionOpen = ref(false);
const correctionText = ref('');
const correctionMsg = ref('');

async function sendCorrection() {
    if (!auth.isLoggedIn) return router.push({ name: 'login', query: { redirect: route.fullPath } });
    try {
        const data = await api.post(`/branches/${branch.value.id}/corrections`, { text: correctionText.value });
        correctionMsg.value = data.message;
        correctionText.value = '';
    } catch (e) {
        if (e instanceof ApiError && e.data?.code === 'phone_unverified') return router.push({ name: 'verify' });
        correctionMsg.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    }
}

async function reportReview(review) {
    if (!auth.isLoggedIn) return router.push({ name: 'login', query: { redirect: route.fullPath } });
    if (!confirm('Энэ сэтгэгдлийг зохисгүй гэж мэдэгдэх үү?')) return;
    try {
        await api.post(`/branches/${branch.value.id}/reviews/${review.id}/report`);
        alert('Мэдэгдэл хүлээн авлаа. Редакц шалгана.');
    } catch (e) {
        if (e instanceof ApiError && e.data?.code === 'phone_unverified') return router.push({ name: 'verify' });
        alert(e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа');
    }
}

async function markHelpful(review) {
    if (!auth.isLoggedIn) return router.push({ name: 'login', query: { redirect: route.fullPath } });
    try {
        const data = await api.post(`/reviews/${review.id}/helpful`);
        review.helpful_count = data.helpful_count;
        review._helpful = data.helpful;
    } catch {
        // чимээгүй
    }
}

async function deleteMyReview() {
    if (!confirm('Сэтгэгдлээ устгах уу?')) return;
    try {
        await api.delete(`/branches/${branch.value.id}/reviews`);
        await fetchBusiness();
    } catch {
        alert('Устгахад алдаа гарлаа.');
    }
}

// Энэ салбарт бичсэн миний сэтгэгдэл
const myReview = computed(() => branch.value?.reviews?.find((r) => r.user?.id === auth.user?.id));

const weekdays = { mon: 'Даваа', tue: 'Мягмар', wed: 'Лхагва', thu: 'Пүрэв', fri: 'Баасан', sat: 'Хагас', sun: 'Бүтэн' };
const todayKey = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'][(new Date().getDay() + 6) % 7];

const branch = computed(() => business.value?.branches?.find((b) => b.id === selectedBranchId.value) || business.value?.branches?.[0]);

// Хэрэглэгч өмнө нь бичсэн бол формд нь буулгана — засаад дахин илгээх боломжтой
// (branch-аас хамаардаг тул түүний ДАРАА тодорхойлно)
watch(myReview, (r) => {
    reviewForm.value = r ? { rating: r.rating, comment: r.comment || '' } : { rating: 0, comment: '' };
});
const hasMultipleBranches = computed(() => (business.value?.branches?.length || 0) > 1);
const gallery = computed(() => branch.value?.images || []);

async function fetchBusiness() {
    loading.value = true;
    notFound.value = false;
    loadError.value = '';
    try {
        const data = await api.get(`/businesses/${route.params.slug}`);
        business.value = data.data;
        similar.value = data.similar;
        document.title = `${business.value.name} — ${business.value.category?.name || 'Бизнес'} | Хаана.mn`;

        // Сэтгэгдэл бичсэний дараа дахин ачаалахад сонгосон салбар 1-рт
        // үсэрдэг байсан — байгаа сонголтоо хадгална
        const stillExists = business.value.branches?.some((b) => b.id === selectedBranchId.value);
        selectedBranchId.value = stillExists ? selectedBranchId.value : business.value.branches?.[0]?.id || null;

        if (branch.value) {
            // Аппын доторх шилжилтэд эх сурвалжийг жагсаалтын хуудас аль хэдийн
            // бүртгэсэн тул давхардуулахгүй; шууд орж ирсэн үед л direct гэж тоолно
            const cameFromApp = !!window.history.state?.back;
            api.post(`/branches/${branch.value.id}/event`, cameFromApp ? { type: 'view' } : { type: 'view', source: 'direct' }).catch(() => {});
        }
    } catch (e) {
        // 404 бол «олдсонгүй», бусад алдаанд (500, сүлжээ) дахин оролдох мөр
        if (e instanceof ApiError && e.status === 404) notFound.value = true;
        else loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    } finally {
        loading.value = false;
    }
}

function track(type) {
    if (branch.value) api.post(`/branches/${branch.value.id}/event`, { type }).catch(() => {});
}

async function toggleFavorite() {
    if (!auth.isLoggedIn) return router.push({ name: 'login', query: { redirect: route.fullPath } });
    favoriteBusy.value = true;
    try {
        const data = await api.post(`/businesses/${business.value.id}/favorite`);
        business.value.is_favorited = data.favorited;
    } finally {
        favoriteBusy.value = false;
    }
}

async function submitReview() {
    if (!auth.isLoggedIn) return router.push({ name: 'login', query: { redirect: route.fullPath } });
    reviewError.value = '';
    reviewBusy.value = true;
    try {
        await api.post(`/branches/${branch.value.id}/reviews`, reviewForm.value);
        await fetchBusiness();
        tab.value = 'reviews';
    } catch (e) {
        if (e instanceof ApiError && e.data?.code === 'phone_unverified') {
            router.push({ name: 'verify' });
            return;
        }
        reviewError.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    } finally {
        reviewBusy.value = false;
    }
}


function stars(n) {
    return '★ ' + Number(n).toFixed(1);
}

watch(() => route.params.slug, fetchBusiness);
onMounted(fetchBusiness);
</script>

<template>
    <div class="bg-white">
        <div v-if="loading" class="mx-auto max-w-7xl space-y-4 px-5 py-8 sm:px-10">
            <div class="card h-64 animate-pulse bg-panel"></div>
            <div class="card h-44 animate-pulse bg-panel"></div>
        </div>

        <div v-else-if="notFound" class="mx-auto max-w-md px-5 py-24 text-center">
            <p class="text-lg font-bold text-ink">Бизнес олдсонгүй</p>
            <router-link :to="{ name: 'search' }" class="btn-primary mt-6">Хайлт руу буцах</router-link>
        </div>

        <div v-else-if="loadError" class="mx-auto max-w-md px-5 py-24 text-center">
            <p class="text-[15px] font-bold text-red">{{ loadError }}</p>
            <button class="btn-primary mt-6" @click="fetchBusiness">Дахин оролдох</button>
        </div>

        <template v-else-if="business">
            <!-- Breadcrumb -->
            <div class="mx-auto flex max-w-7xl gap-2 px-5 py-3.5 text-[12.5px] font-medium text-mute sm:px-10">
                <router-link :to="{ name: 'home' }" class="text-brand">Хаана</router-link>
                <span>/</span>
                <router-link :to="{ name: 'category', params: { slug: business.category?.slug } }" class="text-brand">{{ business.category?.name }}</router-link>
                <span>/</span>
                <span>{{ business.name }}</span>
            </div>

            <!-- Галерей (2a) -->
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-2 px-5 sm:grid-cols-3 sm:px-10">
                <div class="h-[200px] overflow-hidden rounded-xl sm:col-span-2 sm:h-[280px] sm:rounded-r-none">
                    <ImagePh :src="gallery[0]?.url" :alt="business.name" label="НҮҮР ЗУРАГ 16:9" />
                </div>
                <div class="hidden grid-rows-2 gap-2 sm:grid">
                    <div class="overflow-hidden rounded-tr-xl"><ImagePh :src="gallery[1]?.url" :alt="business.name" /></div>
                    <div class="relative overflow-hidden rounded-br-xl">
                        <ImagePh :src="gallery[2]?.url" :alt="business.name" />
                        <button v-if="gallery.length > 3" class="absolute bottom-3 right-3 cursor-pointer rounded-[7px] bg-ink/80 px-3 py-1.5 text-[11.5px] font-semibold text-white hover:bg-ink" @click="tab = 'photos'">+{{ gallery.length - 3 }} зураг</button>
                    </div>
                </div>
            </div>

            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-9 px-5 pb-11 pt-7 sm:px-10 lg:grid-cols-[1fr_340px]">
                <div>
                    <!-- Толгой -->
                    <div class="flex flex-wrap items-start gap-3.5">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <BizLogo :business="business" size="h-11 w-11 rounded-[11px] text-lg" />
                                <h1 class="text-[26px] font-extrabold leading-tight tracking-[-.02em] text-ink sm:text-[30px]">{{ business.name }}</h1>
                                <VerifiedBadge v-if="business.is_verified" :size="22" />
                            </div>
                            <div class="mt-2.5 flex flex-wrap items-center gap-2.5 text-[13.5px] font-medium text-soft">
                                <span class="font-bold text-ink">{{ (business.rating_avg || 0).toFixed(1) }}</span>
                                <span class="tracking-tight"><span class="text-amberdot">{{ '★'.repeat(Math.round(business.rating_avg || 0)) }}</span><span class="text-searchline">{{ '☆'.repeat(5 - Math.round(business.rating_avg || 0)) }}</span></span>
                                <span>{{ business.reviews_total }} сэтгэгдэл</span>
                                <template v-if="hasMultipleBranches"><span class="text-[#c9ccd1]">·</span><span>{{ business.branches_count }} салбар</span></template>
                                <span class="text-[#c9ccd1]">·</span>
                                <span>{{ business.subcategory || business.category?.name }}</span>
                                <template v-if="business.price_level"><span class="text-[#c9ccd1]">·</span><span>{{ business.price_level }}</span></template>
                                <span class="text-[#c9ccd1]">·</span>
                                <span class="font-semibold" :class="branch?.is_open ? 'text-green' : 'text-amberdark'">{{ branch?.open_label }}</span>
                            </div>
                        </div>
                        <div class="flex gap-2 text-[12.5px] font-semibold">
                            <button class="btn-outline !px-3.5 !py-2.5" :class="{ '!border-redline !bg-redtint !text-red': business.is_favorited }" :disabled="favoriteBusy" @click="toggleFavorite">
                                {{ business.is_favorited ? '♥ Хадгалсан' : 'Хадгалах' }}
                            </button>
                        </div>
                    </div>

                    <!-- Ангиллууд: нэг бизнес олон ангилалд харагдана -->
                    <div v-if="business.categories?.length" class="mt-3 flex flex-wrap gap-1.5">
                        <router-link
                            v-for="c in categoryChips"
                            :key="c.id"
                            :to="{ name: 'category', params: { slug: c.slug } }"
                            class="rounded-full border px-2.5 py-1 text-[12px] font-semibold transition"
                            :class="c.id === business.category?.id ? 'border-blueline bg-bluetint text-brand' : 'border-searchline bg-white text-body hover:border-brand hover:text-brand'"
                        >{{ c.name }}</router-link>
                    </div>

                    <!-- Салбар сонгогч (5b) -->
                    <div v-if="hasMultipleBranches" class="mt-6">
                        <div class="flex items-center gap-2">
                            <div class="text-[13px] font-bold text-ink">Салбар сонгох</div>
                            <div class="text-[12.5px] font-medium text-mute">· Сэтгэгдэл, цагийн хуваарь салбар тус бүрт тусдаа</div>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                            <button
                                v-for="(b, i) in business.branches"
                                :key="b.id"
                                class="cursor-pointer rounded-[11px] border-[1.5px] p-3.5 text-left transition"
                                :class="b.id === branch?.id ? 'border-brand bg-bluecard' : 'border-line bg-white hover:border-blueline'"
                                @click="selectedBranchId = b.id"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="flex h-[18px] w-[18px] items-center justify-center rounded-full text-[10px] font-bold" :class="b.id === branch?.id ? 'bg-brand text-white' : 'bg-bluetint text-brand'">{{ i + 1 }}</span>
                                    <span class="text-[13.5px] font-bold text-ink">{{ b.district }}</span>
                                </div>
                                <div class="mt-2 flex items-center text-[12px] font-medium">
                                    <span :class="b.is_open ? 'text-green' : 'text-amberdark'" class="font-semibold">{{ b.is_open ? 'Нээлттэй' : b.open_label }}</span>
                                </div>
                                <div class="mt-1.5 text-[11.5px] leading-snug text-mute">{{ b.address }}</div>
                                <div class="mt-2 text-[11.5px] font-medium text-mute"><b class="text-ink">{{ b.rating_avg.toFixed(1) }}</b> {{ b.reviews_count }} сэтгэгдэл</div>
                            </button>
                        </div>
                    </div>

                    <!-- Tabs (2a) -->
                    <div class="mt-6 flex gap-6 border-b border-line text-[13.5px] font-semibold">
                        <button v-for="t in [['overview', 'Тойм'], ['reviews', `Сэтгэгдэл (${branch?.reviews_count || 0})`], ['photos', 'Зураг']]" :key="t[0]"
                            class="cursor-pointer pb-2.5"
                            :class="tab === t[0] ? 'border-b-2 border-brand text-ink' : 'text-mute'"
                            @click="tab = t[0]"
                        >{{ t[1] }}</button>
                    </div>

                    <!-- Тойм -->
                    <div v-show="tab === 'overview'">
                        <p v-if="business.description" class="mt-5 max-w-[600px] text-[14.5px] leading-[1.75] text-body">{{ business.description }}</p>

                        <div v-if="branch?.amenities?.length" class="mt-4 flex flex-wrap gap-2">
                            <span v-for="a in branch.amenities" :key="a" class="rounded-full border border-line bg-panel px-3 py-2 text-[12.5px] font-medium text-body">{{ a }}</span>
                        </div>

                        <h2 class="mb-3.5 mt-8 text-[17px] font-bold text-ink">Цагийн хуваарь</h2>
                        <div class="overflow-hidden rounded-[11px] border border-line">
                            <div v-for="(label, key) in weekdays" :key="key" class="flex justify-between border-b border-hairline px-4 py-2.5 last:border-0" :class="{ 'bg-bluetint': key === todayKey }">
                                <span class="text-[13.5px] text-ink" :class="key === todayKey ? 'font-bold' : 'font-medium'">{{ label }}</span>
                                <span class="text-[13.5px] font-medium text-soft">
                                    {{ dayLabel(branch?.hours?.[key]) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Сэтгэгдэл -->
                    <div v-show="tab === 'overview' || tab === 'reviews'">
                        <div class="mb-3.5 mt-8 flex items-baseline justify-between">
                            <h2 class="text-[17px] font-bold text-ink">Сэтгэгдэл <span v-if="hasMultipleBranches" class="text-[13px] font-medium text-mute">· {{ branch?.name }}</span></h2>
                        </div>

                        <div v-if="!auth.isLoggedIn" class="mb-4 flex items-center justify-between rounded-[11px] border border-line bg-panel p-4">
                            <span class="text-[13px] font-medium text-body">Сэтгэгдэл бичихийн тулд нэвтэрнэ үү.</span>
                            <router-link :to="{ name: 'login', query: { redirect: route.fullPath } }" class="btn-primary !px-4 !py-2 !text-[12.5px]">Нэвтрэх</router-link>
                        </div>
                        <form v-else class="mb-4 space-y-3 rounded-[11px] border border-line bg-panel p-4" @submit.prevent="submitReview">
                            <p v-if="myReview" class="text-[12px] font-medium text-mute">Та энэ салбарт сэтгэгдэл бичсэн — доорх форм илгээвэл хуучныг шинэчилнэ. <button type="button" class="cursor-pointer font-semibold text-red" @click="deleteMyReview">Сэтгэгдлээ устгах</button></p>
                            <div class="flex items-center gap-3">
                                <span class="text-[13px] font-semibold text-body">Таны үнэлгээ:</span>
                                <div class="flex gap-0.5">
                                    <button
                                        v-for="s in 5"
                                        :key="s"
                                        type="button"
                                        class="cursor-pointer text-[20px] leading-none transition hover:scale-110"
                                        :class="s <= reviewForm.rating ? 'text-amberdot' : 'text-searchline'"
                                        :aria-label="`${s} од`"
                                        :aria-pressed="s === reviewForm.rating"
                                        @click="reviewForm.rating = s"
                                    >★</button>
                                </div>
                                <span class="text-[12px] font-medium" :class="reviewForm.rating ? 'text-mute' : 'text-amberdark'">
                                    {{ reviewForm.rating ? `${reviewForm.rating} / 5` : 'Од дарж үнэлгээгээ өгнө үү' }}
                                </span>
                            </div>
                            <textarea v-model="reviewForm.comment" rows="2" placeholder="Туршлагаа хуваалцаарай..." class="input resize-none"></textarea>
                            <p v-if="reviewError" class="text-[12.5px] font-medium text-red">{{ reviewError }}</p>
                            <button type="submit" class="btn-primary !py-2.5" :disabled="reviewBusy || !reviewForm.rating">
                                {{ auth.isLoggedIn ? 'Сэтгэгдэл үлдээх' : 'Нэвтэрч сэтгэгдэл бичих' }}
                            </button>
                        </form>

                        <div v-if="branch?.reviews?.length" class="flex flex-col gap-3">
                            <div v-for="review in branch.reviews" :key="review.id" class="rounded-[11px] border border-line p-4">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-[34px] w-[34px] items-center justify-center rounded-full border border-blueline bg-bluetint text-[13px] font-bold text-brand">{{ review.user?.name?.charAt(0) }}</span>
                                    <div>
                                        <div class="text-[13.5px] font-bold text-ink">{{ review.user?.name }}</div>
                                        <div class="mt-0.5 text-[11.5px] text-mute">{{ shortDate(review.created_at) }}</div>
                                    </div>
                                    <div class="ml-auto text-[13px] font-semibold text-ink">{{ stars(review.rating) }}</div>
                                </div>
                                <p v-if="review.comment" class="mt-2.5 text-[13.5px] leading-[1.7] text-body">{{ review.comment }}</p>
                                <div v-if="review.reply" class="mt-2.5 rounded-lg bg-greentint px-3 py-2 text-[12px] font-medium leading-relaxed text-green">
                                    Бизнесийн хариу: {{ review.reply }}
                                </div>
                                <div class="mt-2.5 flex items-center gap-3 text-[11.5px] font-semibold">
                                    <button class="cursor-pointer" :class="review._helpful ? 'text-brand' : 'text-mute hover:text-brand'" @click="markHelpful(review)">👍 Хэрэгтэй{{ review.helpful_count ? ` (${review.helpful_count})` : '' }}</button>
                                    <button v-if="review.user?.id !== auth.user?.id" class="cursor-pointer text-mute hover:text-red" @click="reportReview(review)">Мэдэгдэх</button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-[13px] text-mute">Энэ салбарт одоогоор сэтгэгдэл алга. Та анхны сэтгэгдлийг үлдээгээрэй!</p>
                    </div>

                    <!-- Зураг -->
                    <div v-show="tab === 'photos'" class="mt-5">
                        <div v-if="gallery.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <a v-for="img in gallery" :key="img.id" :href="img.url" target="_blank" rel="noopener" class="h-36 overflow-hidden rounded-xl">
                                <img :src="img.url" class="h-full w-full object-cover transition hover:opacity-90" loading="lazy" />
                            </a>
                        </div>
                        <p v-else class="text-[13px] text-mute">Зураг оруулаагүй байна.</p>
                    </div>
                </div>

                <!-- Sidebar -->
                <aside class="flex flex-col gap-3">
                    <div class="rounded-xl border border-line bg-panel p-[18px]">
                        <a :href="`tel:${branch?.phone}`" class="block rounded-[9px] bg-brand py-3 text-center text-[13.5px] font-bold text-white hover:bg-brand-dark" @click="track('call')">
                            Залгах · {{ branch?.phone }}
                        </a>

                        <div class="my-[18px] h-px bg-divider"></div>
                        <div class="flex flex-col gap-3.5 text-[13.5px]">
                            <div><div class="kicker !text-[11px]">ХАЯГ</div><div class="mt-1 font-medium leading-normal text-ink">{{ branch?.city && branch.city !== 'Улаанбаатар' ? branch.city + ', ' : '' }}{{ branch?.district }}{{ branch?.khoroo ? ', ' + branch.khoroo : '' }}, {{ branch?.address }}</div></div>
                            <div><div class="kicker !text-[11px]">УТАС</div><div class="mt-1 font-medium text-ink">{{ branch?.phone }}</div></div>
                            <div v-if="business.website"><div class="kicker !text-[11px]">ВЭБ</div><a :href="'https://' + business.website.replace(/^https?:\/\//, '')" target="_blank" rel="noopener" class="mt-1 block font-medium text-brand">{{ business.website }}</a></div>
                            <div v-if="business.email"><div class="kicker !text-[11px]">И-МЭЙЛ</div><a :href="`mailto:${business.email}`" class="mt-1 block font-medium text-brand">{{ business.email }}</a></div>
                            <div v-if="business.facebook || business.instagram"><div class="kicker !text-[11px]">СОШИАЛ</div><div class="mt-1 flex gap-2 font-medium text-brand">
                                <a v-if="business.facebook" :href="'https://' + business.facebook.replace(/^https?:\/\//, '')" target="_blank" rel="noopener">Facebook</a>
                                <a v-if="business.instagram" :href="'https://instagram.com/' + business.instagram.replace('@', '')" target="_blank" rel="noopener">Instagram</a>
                            </div></div>
                        </div>
                    </div>

                    <!-- Зураглал (Leaflet + OpenStreetMap) -->
                    <div class="overflow-hidden rounded-xl border border-line">
                        <MapView
                            v-if="mapMarkers().length"
                            :markers="mapMarkers()"
                            :selected-id="branch?.id"
                            height="170px"
                            @select="(id) => (selectedBranchId = id)"
                        />
                        <div v-else class="map-ph relative h-[170px]">
                            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-[11.5px] font-medium text-mute">Байршил тэмдэглээгүй</div>
                        </div>
                        <div class="flex items-center justify-between px-4 py-3.5">
                            <span class="text-[12.5px] font-medium text-soft">{{ branch?.address }}</span>
                            <a
                                v-if="branch?.lat"
                                :href="`https://www.google.com/maps/dir/?api=1&destination=${branch.lat},${branch.lng}`"
                                target="_blank" rel="noopener"
                                class="text-[12.5px] font-semibold text-brand"
                                @click="track('direction')"
                            >Зам заах →</a>
                        </div>
                    </div>

                    <!-- Ижил төрлийн -->
                    <div v-if="similar.length" class="rounded-xl border border-line p-[18px]">
                        <div class="text-[14px] font-bold text-ink">Ижил төрлийн бизнес</div>
                        <div class="mt-3.5 flex flex-col gap-3.5">
                            <router-link v-for="s in similar" :key="s.id" :to="{ name: 'business', params: { slug: s.slug } }" class="flex items-center gap-3">
                                <div class="h-11 w-11 shrink-0 overflow-hidden rounded-lg"><ImagePh :src="s.branches?.[0]?.cover_url" /></div>
                                <div class="min-w-0">
                                    <div class="truncate text-[13px] font-semibold text-ink">{{ s.name }}</div>
                                    <div class="mt-0.5 text-[11.5px] text-mute">{{ (s.rating_avg || 0).toFixed(1) }} · {{ s.category?.name }} · {{ s.branches?.[0]?.district }}</div>
                                </div>
                            </router-link>
                        </div>
                    </div>
                </aside>
            </div>

            <!-- Залруулга (2a footer) -->
            <div class="bg-dark">
                <div class="mx-auto max-w-7xl px-5 py-6 sm:px-10">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-[13px] text-darkmute">Мэдээлэл буруу байна уу? Хаана редакцад мэдэгдэх</span>
                        <button class="cursor-pointer rounded-lg bg-chip px-4 py-2.5 text-[12.5px] font-semibold text-ink hover:bg-white" @click="correctionOpen = !correctionOpen">Залруулга хүсэх</button>
                    </div>
                    <div v-if="correctionOpen" class="mt-4 max-w-xl">
                        <p v-if="correctionMsg" class="mb-2 rounded-lg bg-white/10 px-3 py-2 text-[12.5px] font-medium text-white">{{ correctionMsg }}</p>
                        <form class="flex gap-2" @submit.prevent="sendCorrection">
                            <input v-model="correctionText" type="text" maxlength="1000" placeholder="Жш: Утасны дугаар солигдсон — 7011 2233 боллоо" class="w-full rounded-[9px] border border-white/20 bg-white/10 px-3.5 py-2.5 text-[13px] text-white outline-none placeholder:text-darkmute" required />
                            <button type="submit" class="btn-primary !px-4 !py-2.5 !text-[12.5px]">Илгээх</button>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
