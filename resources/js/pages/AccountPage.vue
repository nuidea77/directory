<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../api';
import { useAuthStore } from '../stores/auth';
import ImagePh from '../components/ImagePh.vue';

// Хэрэглэгчийн дашбоард (4c): хадгалсан, миний сэтгэгдэл, тохиргоо
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

// Таб URL-д хадгалагдана (refresh/back-д алдагдахгүй)
const tab = ref(['saved', 'reviews', 'settings'].includes(route.query.tab) ? route.query.tab : 'saved');
watch(tab, (t) => router.replace({ query: { ...route.query, tab: t === 'saved' ? undefined : t } }));

async function removeFavorite(b) {
    try {
        await api.post(`/businesses/${b.id}/favorite`);
        favorites.value = favorites.value.filter((f) => f.id !== b.id);
    } catch {
        // алдаа гарвал жагсаалт хэвээр
    }
}

async function deleteReview(r) {
    if (!confirm('Энэ сэтгэгдлээ устгах уу?')) return;
    try {
        await api.delete(`/branches/${r.branch.id}/reviews`);
        reviews.value = reviews.value.filter((x) => x.id !== r.id);
    } catch {
        alert('Устгахад алдаа гарлаа.');
    }
}
const favorites = ref([]);
const reviews = ref([]);
const loading = ref(true);

const profileForm = ref({ name: auth.user?.name || '', email: auth.user?.email || '' });
watch(() => auth.user, (u) => { if (u) profileForm.value = { name: u.name || '', email: u.email || '' }; });
const passwordForm = ref({ current_password: '', password: '' });
const msg = ref({ type: '', text: '' });

const nav = [
    ['saved', 'Хадгалсан'],
    ['reviews', 'Миний сэтгэгдэл'],
    ['settings', 'Тохиргоо'],
];

const contributions = computed(() => ({
    reviews: reviews.value.length,
    saved: favorites.value.length,
}));

async function fetchAll() {
    loading.value = true;
    try {
        const [f, r] = await Promise.all([
            api.get('/favorites'),
            api.get('/my/reviews'),
        ]);
        favorites.value = f.data;
        reviews.value = r.data;

    } finally {
        loading.value = false;
    }
}

async function saveProfile() {
    msg.value = { type: '', text: '' };
    try {
        const data = await api.put('/me', profileForm.value);
        auth.user = data.data ?? data;
        msg.value = { type: 'ok', text: 'Мэдээлэл хадгалагдлаа.' };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    }
}

async function changePassword() {
    msg.value = { type: '', text: '' };
    try {
        await api.put('/me/password', passwordForm.value);
        passwordForm.value = { current_password: '', password: '' };
        msg.value = { type: 'ok', text: 'Нууц үг солигдлоо.' };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    }
}

function coverOf(business) {
    return (business.branches || []).find((b) => b.cover_url)?.cover_url || null;
}

onMounted(fetchAll);
</script>

<template>
    <div class="bg-white">
        <div class="mx-auto grid max-w-7xl grid-cols-1 md:grid-cols-[250px_1fr]">
            <!-- Sidebar (4c) -->
            <aside class="border-line bg-panel px-5 py-6 md:min-h-[calc(100vh-62px)] md:border-r sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-[46px] w-[46px] items-center justify-center rounded-full border border-blueline bg-bluetint text-lg font-bold text-brand">{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
                    <div>
                        <div class="text-[14.5px] font-bold text-ink">{{ auth.user?.name }}</div>
                        <div class="mt-0.5 text-[11.5px] text-mute">{{ auth.user?.phone }}</div>
                    </div>
                </div>

                <div class="mt-3.5 flex items-center gap-2 rounded-[9px] border border-blueline bg-bluetint px-3 py-2.5">
                    <span class="text-[12px] font-bold text-brand">{{ auth.user?.level_name }}</span>
                    <span class="ml-auto font-mono text-[11px] font-semibold text-brand">Lv.{{ auth.user?.level }}</span>
                </div>

                <div v-if="!auth.user?.phone_verified" class="mt-3 rounded-[9px] border border-amberline bg-ambertint p-3">
                    <div class="text-[12px] font-bold text-ink">Дугаар баталгаажаагүй</div>
                    <div class="mt-1 text-[11.5px] leading-normal text-ambertext">Сэтгэгдэл бичих, бизнес нэмэхэд шаардлагатай.</div>
                    <router-link :to="{ name: 'verify' }" class="mt-2 block rounded-[7px] bg-brand py-1.5 text-center text-[11.5px] font-bold text-white">Баталгаажуулах</router-link>
                </div>

                <nav class="mt-4 flex flex-col gap-0.5">
                    <button
                        v-for="[key, label] in nav"
                        :key="key"
                        class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[12.5px] font-semibold"
                        :class="tab === key ? 'bg-bluetint text-brand' : 'text-body hover:bg-white'"
                        @click="tab = key"
                    >
                        <span class="h-4 w-4 rounded-[5px]" :class="tab === key ? 'bg-brand' : 'bg-searchline'"></span>
                        {{ label }}
                        <span class="ml-auto font-mono text-[11px] font-medium text-mute">{{ key === 'saved' ? favorites.length || '' : key === 'reviews' ? reviews.length || '' : '' }}</span>
                    </button>
                </nav>

                <div class="my-5 h-px bg-divider"></div>
                <div class="kicker !text-[10.5px]">МИНИЙ ХУВЬ НЭМЭР</div>
                <div class="mt-3 flex flex-col gap-2.5 text-[12.5px] font-medium text-body">
                    <div class="flex justify-between"><span>Сэтгэгдэл</span><b class="text-ink">{{ contributions.reviews }}</b></div>
                    <div class="flex justify-between"><span>Хадгалсан</span><b class="text-ink">{{ contributions.saved }}</b></div>
                </div>
            </aside>

            <!-- Агуулга -->
            <main class="px-5 py-6 sm:px-8">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <div>
                        <h1 class="text-[26px] font-extrabold tracking-[-.02em] text-ink">Сайн уу, {{ auth.user?.name }}</h1>
                        <p class="mt-1.5 text-[13.5px] text-soft">Хадгалсан {{ favorites.length }} бизнес · {{ reviews.length }} сэтгэгдэл</p>
                    </div>
                    <router-link :to="{ name: 'add-business' }" class="btn-primary !px-4 !py-2.5 !text-[12.5px]">Бизнес нэмэх</router-link>
                </div>

                <div v-if="loading" class="mt-6 grid grid-cols-1 gap-3.5 sm:grid-cols-3">
                    <div v-for="i in 3" :key="i" class="card h-56 animate-pulse bg-panel"></div>
                </div>

                <!-- Хадгалсан -->
                <template v-else-if="tab === 'saved'">
                    <div v-if="favorites.length" class="mt-5 grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                        <router-link v-for="b in favorites" :key="b.id" :to="{ name: 'business', params: { slug: b.slug } }" class="card overflow-hidden transition hover:-translate-y-0.5 hover:shadow-md">
                            <div class="relative h-[104px]">
                                <ImagePh :src="coverOf(b)" :alt="b.name" />
                                <button class="absolute right-2.5 top-2.5 flex h-[26px] w-[26px] cursor-pointer items-center justify-center rounded-full bg-white text-[12px] text-brand shadow hover:text-red" title="Хадгалснаас хасах" @click.prevent="removeFavorite(b)">♥</button>
                                <span v-if="b.list_name" class="absolute bottom-2.5 left-2.5 rounded-[5px] bg-white/95 px-2 py-1 text-[10.5px] font-semibold text-ink">{{ b.list_name }}</span>
                            </div>
                            <div class="p-3.5">
                                <div class="text-[14px] font-bold text-ink">{{ b.name }}</div>
                                <div class="mt-1 text-[12px] text-mute">{{ b.category?.name }} · {{ b.branches?.[0]?.district }}</div>
                                <div class="mt-2 flex items-center gap-2 text-[12px] font-medium text-mute">
                                    <b class="text-ink">{{ (b.rating_avg || 0).toFixed(1) }}</b>
                                    <span class="font-semibold" :class="b.branches?.[0]?.is_open ? 'text-green' : 'text-amberdark'">{{ b.branches?.[0]?.is_open ? 'Нээлттэй' : b.branches?.[0]?.open_label }}</span>
                                </div>
                            </div>
                        </router-link>
                    </div>
                    <div v-else class="card mt-5 p-14 text-center">
                        <p class="text-[15px] font-bold text-ink">Хадгалсан бизнес алга</p>
                        <router-link :to="{ name: 'search' }" class="btn-primary mt-4">Бизнес хайх</router-link>
                    </div>
                </template>

                <!-- Миний сэтгэгдэл -->
                <template v-else-if="tab === 'reviews'">
                    <div v-if="reviews.length" class="mt-5 flex max-w-2xl flex-col gap-3">
                        <div v-for="r in reviews" :key="r.id" class="rounded-[11px] border border-line p-4">
                            <div class="flex items-center gap-2.5">
                                <span class="text-[13px] font-bold text-ink">{{ r.branch?.business_name }}</span>
                                <span class="text-[11.5px] font-medium text-mute">{{ r.branch?.name }}</span>
                                <span class="ml-auto text-[12.5px] font-bold text-ink">★ {{ r.rating }}.0</span>
                            </div>
                            <p class="mt-2 text-[12.5px] leading-relaxed text-body">{{ r.comment }}</p>
                            <div v-if="r.reply" class="mt-2.5 rounded-lg bg-greentint px-3 py-2 text-[11.5px] font-medium leading-normal text-green">Бизнесийн хариу: {{ r.reply }}</div>
                            <button class="mt-2 cursor-pointer text-[11.5px] font-semibold text-red" @click="deleteReview(r)">Устгах</button>
                        </div>
                    </div>
                    <div v-else class="card mt-5 max-w-2xl p-14 text-center text-[13px] text-mute">Сэтгэгдэл бичээгүй байна</div>
                </template>

                <!-- Тохиргоо -->
                <template v-else>
                    <p v-if="msg.text" class="mt-4 max-w-xl rounded-lg px-4 py-2.5 text-[13px] font-medium" :class="msg.type === 'ok' ? 'bg-greentint text-green' : 'bg-redtint text-red'">{{ msg.text }}</p>
                    <div class="card mt-4 max-w-xl p-5">
                        <div class="text-[15px] font-bold text-ink">Хувийн мэдээлэл</div>
                        <p class="mt-1 text-[12.5px] text-mute">Утас: <b class="text-ink">{{ auth.user?.phone }}</b> {{ auth.user?.phone_verified ? '· баталгаажсан ✓' : '· баталгаажаагүй' }}</p>
                        <form class="mt-4 space-y-3.5" @submit.prevent="saveProfile">
                            <div><label class="field-label">Нэр</label><input v-model="profileForm.name" type="text" class="input" required /></div>
                            <div><label class="field-label">И-мэйл</label><input v-model="profileForm.email" type="email" class="input" /></div>
                            <button type="submit" class="btn-primary !px-5 !py-2.5 !text-[12.5px]">Хадгалах</button>
                        </form>
                    </div>
                    <div class="card mt-3.5 max-w-xl p-5">
                        <div class="text-[15px] font-bold text-ink">Нууц үг солих</div>
                        <form class="mt-4 space-y-3.5" @submit.prevent="changePassword">
                            <div><label class="field-label">Одоогийн нууц үг</label><input v-model="passwordForm.current_password" type="password" class="input" required /></div>
                            <div><label class="field-label">Шинэ нууц үг</label><input v-model="passwordForm.password" type="password" class="input" required minlength="8" /></div>
                            <button type="submit" class="btn-primary !px-5 !py-2.5 !text-[12.5px]">Солих</button>
                        </form>
                    </div>
                </template>
            </main>
        </div>
    </div>
</template>
