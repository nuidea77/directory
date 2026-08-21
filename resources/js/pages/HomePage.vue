<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api';
import BusinessCard from '../components/BusinessCard.vue';
import CategoryIcon from '../components/CategoryIcon.vue';
import HomeSection from '../components/HomeSection.vue';
import SectionHeader from '../components/SectionHeader.vue';
import { LayoutGrid, Sparkle } from 'lucide-vue-next';

const router = useRouter();
const categories = ref([]);
const featured = ref([]);
const sections = ref([]);
const stats = ref({ businesses: 0 });
const query = ref('');
const city = ref('Улаанбаатар');
const loading = ref(true);
const loadError = ref('');
const featuredFilter = ref('all');

const popular = ['хоолны газар', 'шүдний эмнэлэг', 'нотариат', 'барилгын материал'];
const cities = ref([]);

async function fetchHome() {
    loading.value = true;
    loadError.value = '';
    try {
        const data = await api.get('/home', { city: city.value });
        categories.value = data.categories;
        featured.value = data.featured;
        sections.value = data.sections || [];
        stats.value = data.stats;
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    api.get('/locations').then((locs) => (cities.value = locs.data.map((l) => l.city))).catch(() => {});
    await fetchHome();
});

function search(term) {
    const params = {};
    const q = term || query.value;
    if (q) params.q = q;
    if (city.value && city.value !== 'Улаанбаатар') params.city = city.value;
    router.push({ name: 'search', query: params });
}

function filteredFeatured() {
    return featured.value.filter((b) => {
        if (featuredFilter.value === 'open') return b.branches?.some((br) => br.is_open);
        if (featuredFilter.value === 'rating') return (b.rating_avg || 0) >= 4.5;
        if (featuredFilter.value === 'verified') return b.is_verified;
        return true;
    });
}
</script>

<template>
    <div>
        <!-- Hero -->
        <section class="relative overflow-hidden border-b border-line bg-hero">
            <!-- Зөөлөн өнгөний толбо — хоосон талбайг дүүргэнэ -->
            <div class="pointer-events-none absolute -right-24 -top-24 h-[420px] w-[420px] rounded-full bg-bluetint/70 blur-3xl"></div>

            <div class="relative mx-auto grid max-w-7xl grid-cols-1 gap-8 px-5 py-12 sm:px-10 sm:py-14 lg:grid-cols-[1fr_300px] lg:items-center">
                <div>
                    <h1 class="max-w-[720px] text-[32px] font-extrabold leading-[1.15] tracking-[-.025em] text-ink sm:text-[40px]">
                        Монгол дахь {{ stats.businesses.toLocaleString() }} бизнесийг нэг дороос
                    </h1>
                    <p class="mt-3 max-w-[640px] text-[15px] leading-relaxed text-soft">
                        Ресторан, эмнэлэг, авто засвар, хууль зүйн үйлчилгээ — хаяг, цагийн хуваарь, үнэлгээ бүхий баталгаажсан лавлах.
                    </p>

                    <!-- Хайлт: нэг мөр болгон нэгтгэсэн -->
                    <form class="mt-6 flex max-w-[760px] flex-col gap-2.5 rounded-[14px] border border-inputline bg-white p-2 shadow-sm sm:flex-row sm:items-center sm:gap-0" @submit.prevent="search()">
                        <div class="flex flex-[1.5] items-center gap-2.5 px-3 py-2.5">
                            <svg class="h-4 w-4 shrink-0 text-ph" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4-4"/></svg>
                            <input v-model="query" type="search" placeholder="Бизнес, үйлчилгээ, брэнд хайх" class="w-full bg-transparent text-[14px] font-medium text-ink outline-none placeholder:text-ph" />
                        </div>
                        <div class="hidden h-7 w-px bg-line sm:block"></div>
                        <div class="flex flex-1 items-center px-3">
                            <select v-model="city" class="w-full cursor-pointer bg-transparent py-2.5 text-[14px] font-medium text-ink outline-none" @change="fetchHome()">
                                <option value="Улаанбаатар">Улаанбаатар</option>
                                <option v-for="c in cities.filter((c) => c !== 'Улаанбаатар')" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary !rounded-[10px] !px-7 !py-3 !text-[14px]">Хайх</button>
                    </form>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="text-[12.5px] font-medium text-soft">Их хайсан:</span>
                        <button
                            v-for="term in popular"
                            :key="term"
                            class="cursor-pointer rounded-full border border-inputline bg-white px-3 py-1.5 text-[12.5px] font-semibold text-body transition hover:border-brand hover:text-brand"
                            @click="search(term)"
                        >{{ term }}</button>
                    </div>
                </div>

                <!-- Тоон тойм -->
                <div class="rounded-2xl border border-line bg-white/80 p-5 backdrop-blur">
                    <div class="text-[11px] font-semibold tracking-[.08em] text-mute">ХААНА.MN-Д</div>
                    <div class="mt-3 flex flex-col gap-3">
                        <div v-for="stat in [[stats.businesses, 'бүртгэлтэй бизнес'], [stats.branches, 'салбар, хаяг'], [categories.length, 'үндсэн ангилал']]" :key="stat[1]" class="flex items-baseline gap-2">
                            <span class="text-[22px] font-extrabold tracking-[-.02em] text-brand">{{ Number(stat[0] || 0).toLocaleString() }}</span>
                            <span class="text-[12.5px] font-medium text-mute">{{ stat[1] }}</span>
                        </div>
                    </div>
                    <router-link :to="{ name: 'add-business' }" class="mt-4 block rounded-[9px] border border-blueline bg-bluetint py-2.5 text-center text-[12.5px] font-bold text-brand transition hover:bg-white">
                        Бизнесээ нэмэх
                    </router-link>
                </div>
            </div>
        </section>

        <!-- Ангиллаар үзэх -->
        <section class="mx-auto max-w-7xl px-5 pt-10 sm:px-10">
            <SectionHeader
                title="Ангиллаар үзэх"
                subtitle="Хэрэгтэй үйлчилгээгээ ангиллаар нь олоорой"
                :icon="LayoutGrid"
                :to="{ name: 'categories' }"
                :link-label="`Бүх ${categories.length} ангилал →`"
            />
            <div class="mt-3 grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4">
                <router-link
                    v-for="category in categories.slice(0, 12)"
                    :key="category.id"
                    :to="{ name: 'category', params: { slug: category.slug }, query: city !== 'Улаанбаатар' ? { city } : {} }"
                    class="group flex items-center gap-3 rounded-[11px] border border-line bg-white px-3.5 py-3 transition hover:-translate-y-0.5 hover:border-blueline hover:shadow-sm"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] border border-blueline bg-bluetint text-brand transition group-hover:bg-brand group-hover:text-white">
                        <CategoryIcon :name="category.icon" :size="17" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-[13.5px] font-semibold text-ink group-hover:text-brand">{{ category.name }}</span>
                        <span class="mt-0.5 block text-[11.5px] text-mute">
                            {{ category.businesses_count ? `${category.businesses_count.toLocaleString()} бизнес` : `${category.children_count || 0} дэд ангилал` }}
                        </span>
                    </span>
                </router-link>
            </div>
        </section>

        <!-- Онцлох бизнесүүд -->
        <section class="mx-auto max-w-7xl px-5 pt-10 sm:px-10">
            <SectionHeader :title="`Онцлох бизнесүүд — ${city}`" subtitle="Санал болгож буй, идэвхтэй бизнесүүд" :icon="Sparkle">
                <template #actions>
                    <div class="flex flex-wrap gap-1.5 text-[12.5px] font-semibold">
                        <button class="chip !px-3 !py-1.5" :class="{ 'chip-active': featuredFilter === 'all' }" @click="featuredFilter = 'all'">Бүгд</button>
                        <button class="chip !px-3 !py-1.5" :class="{ 'chip-active': featuredFilter === 'open' }" @click="featuredFilter = 'open'">Нээлттэй</button>
                        <button class="chip !px-3 !py-1.5" :class="{ 'chip-active': featuredFilter === 'rating' }" @click="featuredFilter = 'rating'">4.5+</button>
                        <button class="chip !px-3 !py-1.5" :class="{ 'chip-active': featuredFilter === 'verified' }" @click="featuredFilter = 'verified'">Баталгаажсан</button>
                    </div>
                </template>
            </SectionHeader>

            <div v-if="loading" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="i in 6" :key="i" class="card h-72 animate-pulse bg-panel"></div>
            </div>
            <div v-else-if="loadError" class="card mt-4 p-10 text-center">
                <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
                <button class="btn-primary mt-4" @click="fetchHome()">Дахин оролдох</button>
            </div>
            <div v-else-if="filteredFeatured().length" class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <BusinessCard v-for="business in filteredFeatured()" :key="business.id" :business="business" />
            </div>
            <!-- Тухайн хотод огт бизнес байхгүй бол шүүлтүүрийн хоосноос ялгаж хэлнэ -->
            <div v-else-if="!featured.length" class="card mt-4 p-10 text-center">
                <p class="text-[15px] font-bold text-ink">{{ city }}-д бүртгэлтэй бизнес одоогоор алга</p>
                <p class="mx-auto mt-1.5 max-w-[420px] text-[13px] leading-relaxed text-mute">
                    Энд бизнесээ анхны байдлаар бүртгүүлж, {{ city }}-ийн нүүр хуудсанд эхэлж харагдаарай.
                </p>
                <router-link :to="{ name: 'add-business' }" class="btn-primary mt-4">Бизнес нэмэх</router-link>
            </div>
            <div v-else class="card mt-4 p-10 text-center text-[13px] text-mute">Энэ шүүлтүүрт тохирох бизнес алга</div>
        </section>

        <!-- Сэдэвчилсэн блокууд: хаана хооллох, болзох, 24 цаг, шинэ -->
        <section v-if="loading" class="mx-auto max-w-7xl px-5 pt-10 sm:px-10">
            <div class="h-9 w-56 animate-pulse rounded-lg bg-panel"></div>
            <div class="mt-4 grid grid-cols-1 gap-x-7 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="i in 6" :key="i" class="flex items-center gap-3 py-2.5">
                    <div class="h-[52px] w-[52px] shrink-0 animate-pulse rounded-[10px] bg-panel"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-3 w-3/4 animate-pulse rounded bg-panel"></div>
                        <div class="h-2.5 w-1/2 animate-pulse rounded bg-panel"></div>
                    </div>
                </div>
            </div>
        </section>
        <template v-else-if="!loadError">
            <HomeSection v-for="section in sections" :key="section.key" :section="section" />
        </template>

        <!-- Бизнесээ бүртгүүлэх урилга -->
        <section class="mx-auto mt-11 max-w-7xl px-5 pb-12 sm:px-10">
            <div class="overflow-hidden rounded-2xl border border-blueline bg-bluetint px-6 py-8 sm:px-10 sm:py-10">
                <div class="flex flex-wrap items-center justify-between gap-6">
                    <div class="max-w-[520px]">
                        <h2 class="text-[24px] font-extrabold tracking-[-.02em] text-ink sm:text-[28px]">Бизнесээ Хаана.mn дээр нэмээрэй</h2>
                        <p class="mt-2 text-[14px] leading-relaxed text-body">
                            Хаяг, цагийн хуваарь, зураг, сэтгэгдэл — хэрэглэгчид хайж олоход хэрэгтэй бүхнийг нэг дороос.
                            Бүртгэл 1 жил үнэгүй.
                        </p>
                        <div class="mt-5 flex flex-wrap gap-2.5">
                            <router-link :to="{ name: 'add-business' }" class="btn-primary !px-6">Бизнес нэмэх</router-link>
                            <router-link :to="{ name: 'pricing' }" class="btn-outline !bg-white !px-5">Үнийн санал үзэх</router-link>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-6">
                        <div>
                            <div class="text-[28px] font-extrabold tracking-[-.02em] text-brand">{{ (stats.businesses || 0).toLocaleString() }}</div>
                            <div class="mt-0.5 text-[12px] font-medium text-mute">бүртгэлтэй бизнес</div>
                        </div>
                        <div>
                            <div class="text-[28px] font-extrabold tracking-[-.02em] text-brand">{{ (stats.branches || 0).toLocaleString() }}</div>
                            <div class="mt-0.5 text-[12px] font-medium text-mute">салбар</div>
                        </div>
                        <div>
                            <div class="text-[28px] font-extrabold tracking-[-.02em] text-brand">{{ categories.length }}</div>
                            <div class="mt-0.5 text-[12px] font-medium text-mute">ангилал</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
