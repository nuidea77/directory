<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../api';
import { useAuthStore } from '../stores/auth';
import ImagePh from '../components/ImagePh.vue';
import BizLogo from '../components/BizLogo.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const business = ref(null);
const similar = ref([]);
const selectedBranchId = ref(null);
const loading = ref(true);
const notFound = ref(false);
const tab = ref('overview');

const reviewForm = ref({ rating: 5, comment: '' });
const reviewError = ref('');
const reviewBusy = ref(false);
const favoriteBusy = ref(false);

const messageOpen = ref(false);
const messageBody = ref('');
const messageSent = ref(false);
const messageError = ref('');

const weekdays = { mon: 'Даваа', tue: 'Мягмар', wed: 'Лхагва', thu: 'Пүрэв', fri: 'Баасан', sat: 'Хагас', sun: 'Бүтэн' };
const todayKey = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'][(new Date().getDay() + 6) % 7];

const branch = computed(() => business.value?.branches?.find((b) => b.id === selectedBranchId.value) || business.value?.branches?.[0]);
const hasMultipleBranches = computed(() => (business.value?.branches?.length || 0) > 1);
const gallery = computed(() => branch.value?.images || []);

async function fetchBusiness() {
    loading.value = true;
    notFound.value = false;
    try {
        const data = await api.get(`/businesses/${route.params.slug}`);
        business.value = data.data;
        similar.value = data.similar;
        selectedBranchId.value = business.value.branches?.[0]?.id || null;

        if (branch.value) {
            // Аппын доторх шилжилтэд эх сурвалжийг жагсаалтын хуудас аль хэдийн
            // бүртгэсэн тул давхардуулахгүй; шууд орж ирсэн үед л direct гэж тоолно
            const cameFromApp = !!window.history.state?.back;
            api.post(`/branches/${branch.value.id}/event`, cameFromApp ? { type: 'view' } : { type: 'view', source: 'direct' }).catch(() => {});
        }
    } catch (e) {
        if (e instanceof ApiError && e.status === 404) notFound.value = true;
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
        reviewForm.value = { rating: 5, comment: '' };
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

async function sendMessage() {
    if (!auth.isLoggedIn) return router.push({ name: 'login', query: { redirect: route.fullPath } });
    messageError.value = '';
    try {
        await api.post(`/businesses/${business.value.id}/messages`, { body: messageBody.value });
        messageBody.value = '';
        messageSent.value = true;
    } catch (e) {
        if (e instanceof ApiError && e.data?.code === 'phone_unverified') {
            router.push({ name: 'verify' });
            return;
        }
        messageError.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
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
                        <div v-if="gallery.length > 3" class="absolute bottom-3 right-3 rounded-[7px] bg-ink/80 px-3 py-1.5 text-[11.5px] font-semibold text-white">+{{ gallery.length - 3 }} зураг</div>
                    </div>
                </div>
            </div>

            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-9 px-5 pb-11 pt-7 sm:px-10 lg:grid-cols-[1fr_340px]">
                <div>
                    <!-- Толгой -->
                    <div class="flex flex-wrap items-start gap-3.5">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <BizLogo v-if="business.logo_url" :business="business" size="h-11 w-11 rounded-[11px] text-lg" />
                                <h1 class="text-[26px] font-extrabold leading-tight tracking-[-.02em] text-ink sm:text-[30px]">{{ business.name }}</h1>
                                <span v-if="business.is_verified" class="badge-verified !text-[11px]">✓ Баталгаажсан</span>
                            </div>
                            <div class="mt-2.5 flex flex-wrap items-center gap-2.5 text-[13.5px] font-medium text-soft">
                                <span class="font-bold text-ink">{{ (business.rating_avg || 0).toFixed(1) }}</span>
                                <span class="tracking-tight text-amberdot">★★★★★</span>
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
                            <button class="btn-outline !px-3.5 !py-2.5" @click="messageOpen = !messageOpen">Зурвас</button>
                        </div>
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
                                    {{ branch?.hours?.[key]?.closed ? 'Амарна' : (branch?.hours?.[key]?.from ? `${branch.hours[key].from} – ${branch.hours[key].to}` : '—') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Сэтгэгдэл -->
                    <div v-show="tab === 'overview' || tab === 'reviews'">
                        <div class="mb-3.5 mt-8 flex items-baseline justify-between">
                            <h2 class="text-[17px] font-bold text-ink">Сэтгэгдэл <span v-if="hasMultipleBranches" class="text-[13px] font-medium text-mute">· {{ branch?.name }}</span></h2>
                        </div>

                        <form class="mb-4 space-y-3 rounded-[11px] border border-line bg-panel p-4" @submit.prevent="submitReview">
                            <div class="flex items-center gap-3">
                                <span class="text-[13px] font-semibold text-body">Таны үнэлгээ:</span>
                                <div class="flex gap-0.5">
                                    <button v-for="s in 5" :key="s" type="button" class="cursor-pointer text-[20px] leading-none transition hover:scale-110" :class="s <= reviewForm.rating ? 'text-amberdot' : 'text-searchline'" @click="reviewForm.rating = s">★</button>
                                </div>
                            </div>
                            <textarea v-model="reviewForm.comment" rows="2" placeholder="Туршлагаа хуваалцаарай..." class="input resize-none"></textarea>
                            <p v-if="reviewError" class="text-[12.5px] font-medium text-red">{{ reviewError }}</p>
                            <button type="submit" class="btn-primary !py-2.5" :disabled="reviewBusy">
                                {{ auth.isLoggedIn ? 'Сэтгэгдэл үлдээх' : 'Нэвтэрч сэтгэгдэл бичих' }}
                            </button>
                        </form>

                        <div v-if="branch?.reviews?.length" class="flex flex-col gap-3">
                            <div v-for="review in branch.reviews" :key="review.id" class="rounded-[11px] border border-line p-4">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-[34px] w-[34px] items-center justify-center rounded-full border border-blueline bg-bluetint text-[13px] font-bold text-brand">{{ review.user?.name?.charAt(0) }}</span>
                                    <div>
                                        <div class="text-[13.5px] font-bold text-ink">{{ review.user?.name }}</div>
                                        <div class="mt-0.5 text-[11.5px] text-mute">{{ new Date(review.created_at).toLocaleDateString() }}</div>
                                    </div>
                                    <div class="ml-auto text-[13px] font-semibold text-ink">{{ stars(review.rating) }}</div>
                                </div>
                                <p v-if="review.comment" class="mt-2.5 text-[13.5px] leading-[1.7] text-body">{{ review.comment }}</p>
                                <div v-if="review.reply" class="mt-2.5 rounded-lg bg-greentint px-3 py-2 text-[12px] font-medium leading-relaxed text-green">
                                    Бизнесийн хариу: {{ review.reply }}
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
                        <button class="mt-2.5 w-full cursor-pointer rounded-[9px] border border-inputline bg-white py-3 text-[13.5px] font-bold text-ink hover:bg-panel" @click="messageOpen = true">Зурвас бичих</button>

                        <div v-if="messageOpen" class="mt-3 rounded-[10px] border border-blueline bg-bluecard p-3">
                            <div v-if="messageSent" class="text-[12.5px] font-semibold text-green">Зурвас илгээгдлээ. Хариуг «Миний булан»-гаас харна.</div>
                            <template v-else>
                                <textarea v-model="messageBody" rows="3" placeholder="Асуултаа бичнэ үү..." class="input resize-none !bg-white"></textarea>
                                <p v-if="messageError" class="mt-1.5 text-[12px] text-red">{{ messageError }}</p>
                                <button class="btn-primary mt-2 w-full !py-2" :disabled="!messageBody.trim()" @click="sendMessage">Илгээх</button>
                            </template>
                        </div>

                        <div class="my-[18px] h-px bg-divider"></div>
                        <div class="flex flex-col gap-3.5 text-[13.5px]">
                            <div><div class="kicker !text-[11px]">ХАЯГ</div><div class="mt-1 font-medium leading-normal text-ink">{{ branch?.district }} дүүрэг{{ branch?.khoroo ? ', ' + branch.khoroo : '' }}, {{ branch?.address }}</div></div>
                            <div><div class="kicker !text-[11px]">УТАС</div><div class="mt-1 font-medium text-ink">{{ branch?.phone }}</div></div>
                            <div v-if="business.website"><div class="kicker !text-[11px]">ВЭБ</div><a :href="'https://' + business.website.replace(/^https?:\/\//, '')" target="_blank" rel="noopener" class="mt-1 block font-medium text-brand">{{ business.website }}</a></div>
                            <div v-if="business.facebook || business.instagram"><div class="kicker !text-[11px]">СОШИАЛ</div><div class="mt-1 flex gap-2 font-medium text-brand">
                                <a v-if="business.facebook" :href="'https://' + business.facebook.replace(/^https?:\/\//, '')" target="_blank" rel="noopener">Facebook</a>
                                <a v-if="business.instagram" :href="'https://instagram.com/' + business.instagram.replace('@', '')" target="_blank" rel="noopener">Instagram</a>
                            </div></div>
                        </div>
                    </div>

                    <!-- Зураглал -->
                    <div class="overflow-hidden rounded-xl border border-line">
                        <div class="map-ph relative h-[170px]">
                            <div v-if="branch?.lat" class="absolute left-1/2 top-1/2 h-[22px] w-[22px] -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-white bg-brand shadow-lg"></div>
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
                <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-6 sm:px-10">
                    <span class="text-[13px] text-darkmute">Мэдээлэл буруу байна уу? Хаана редакцад мэдэгдэх</span>
                    <button class="cursor-pointer rounded-lg bg-chip px-4 py-2.5 text-[12.5px] font-semibold text-ink hover:bg-white">Залруулга хүсэх</button>
                </div>
            </div>
        </template>
    </div>
</template>
