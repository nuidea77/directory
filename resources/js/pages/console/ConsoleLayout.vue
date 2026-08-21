<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useConsoleStore } from '../../stores/console';
import { useAuthStore } from '../../stores/auth';
import { BadgeCheck, ChartNoAxesColumn, MessageSquare, Receipt, Settings, Store } from 'lucide-vue-next';

// Бизнес зөвлөл: өөрийн толгой + sidebar (нийтийн header давхарлахгүй)
const store = useConsoleStore();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const mobileNavOpen = ref(false);

// Дугаар баталгаажаагүй бол баталгаажуулах хуудас руу
watch(
    () => store.error,
    (err) => {
        if (err === 'phone_unverified') router.replace({ name: 'verify' });
    },
    { immediate: true },
);

const nav = [
    { name: 'Салбарууд', route: 'console', icon: Store },
    { name: 'Статистик', route: 'console-stats', icon: ChartNoAxesColumn },
    { name: 'Сэтгэгдэл', route: 'console-reviews', icon: MessageSquare },
    { name: 'Эрх ба сурталчилгаа', route: 'console-plan', icon: BadgeCheck },
    { name: 'Нэхэмжлэх', route: 'console-invoices', icon: Receipt },
    { name: 'Тохиргоо', route: 'console-settings', icon: Settings },
];

const currentName = computed(() => nav.find((n) => n.route === route.name)?.name || 'Бизнес зөвлөл');

async function logout() {
    await auth.logout();
    router.push({ name: 'home' });
}

onMounted(() => store.load());
</script>

<template>
    <div class="flex min-h-screen bg-page">
        <!-- Sidebar — дэлгэцэнд наалттай -->
        <aside class="sticky top-0 hidden h-screen w-[212px] shrink-0 flex-col overflow-y-auto border-r border-line bg-white px-3.5 py-4 md:flex">
            <router-link :to="{ name: 'console' }" class="flex items-center gap-2 px-1.5 pb-4">
                <span class="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] bg-brand text-[13px] font-extrabold text-white">Х</span>
                <span class="text-[15px] font-extrabold text-ink">Хаана</span>
                <span class="rounded-[4px] bg-bluetint px-1.5 py-0.5 text-[10px] font-semibold text-brand">БИЗНЕС</span>
            </router-link>

            <div v-if="store.organization" class="rounded-[10px] border border-line bg-panel p-3">
                <div class="text-[10px] font-semibold tracking-[.08em] text-mute">БАЙГУУЛЛАГА</div>
                <div class="mt-1.5 flex items-center gap-2">
                    <select
                        v-if="store.organizations.length > 1"
                        v-model="store.selectedId"
                        class="w-full cursor-pointer bg-transparent text-[12.5px] font-bold text-ink outline-none"
                    >
                        <option v-for="o in store.organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                    <span v-else class="text-[12.5px] font-bold text-ink">{{ store.organization.name }}</span>
                </div>
                <div class="mt-1.5 flex items-center gap-1.5">
                    <span class="badge-verified !border-blueline !px-1.5 !py-0.5 !text-[9.5px] uppercase">{{ store.organization.plan_name }}</span>
                    <span class="text-[11px] font-medium text-mute">{{ store.branches.length }} салбар</span>
                </div>
                <router-link
                    v-if="store.organization.plan !== 'free' && store.organization.plan_days_left !== null && store.organization.plan_days_left <= 14"
                    :to="{ name: 'console-plan' }"
                    class="mt-1.5 block rounded-md px-1.5 py-1 text-[10.5px] font-bold"
                    :class="store.organization.plan_days_left === 0 ? 'bg-redtint text-red' : 'bg-ambertint text-amberdark'"
                >{{ store.organization.plan_days_left === 0 ? 'Эрх дууссан — сунгах' : `Эрх ${store.organization.plan_days_left} хоногт дуусна` }}</router-link>
            </div>

            <nav class="mt-3.5 flex flex-col gap-0.5">
                <router-link
                    v-for="item in nav"
                    :key="item.route"
                    :to="{ name: item.route }"
                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[12.5px] font-semibold"
                    :class="$route.name === item.route ? 'bg-bluetint text-brand' : 'text-body hover:bg-panel'"
                >
                    <component :is="item.icon" :size="16" :stroke-width="1.9" class="shrink-0" aria-hidden="true" />
                    {{ item.name }}
                </router-link>
            </nav>

            <div v-if="store.organization?.effective_plan === 'free'" class="mt-4 rounded-[10px] border border-blueline bg-bluetint p-3">
                <div class="text-[12px] font-bold text-ink">Үнэгүй эрх · 1 жил</div>
                <div class="mt-1 text-[11.5px] leading-normal text-body">Салбар нэмэх, аналитик Стандарт эрхээс нээгдэнэ.</div>
                <router-link :to="{ name: 'console-plan' }" class="mt-2.5 block rounded-[7px] bg-brand py-2 text-center text-[11.5px] font-bold text-white">Эрх авах</router-link>
            </div>

            <!-- Доод хэсэг: сайт руу, гарах -->
            <div class="mt-auto border-t border-line pt-3">
                <router-link :to="{ name: 'home' }" class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-[12px] font-semibold text-mute hover:bg-panel hover:text-ink">
                    ← Сайт руу буцах
                </router-link>
                <div class="mt-1 flex items-center gap-2 px-2.5 py-2">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-bluetint text-[11px] font-bold text-brand">
                        {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
                    </span>
                    <span class="min-w-0 flex-1 truncate text-[11.5px] font-semibold text-ink">{{ auth.user?.name }}</span>
                    <button class="cursor-pointer text-[11px] font-semibold text-red hover:underline" @click="logout">Гарах</button>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Зөвлөлийн толгой -->
            <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-line bg-white px-4 py-3 sm:px-6">
                <button class="cursor-pointer text-[18px] leading-none text-ink md:hidden" aria-label="Цэс" @click="mobileNavOpen = !mobileNavOpen">☰</button>

                <router-link :to="{ name: 'console' }" class="flex items-center gap-2 md:hidden">
                    <span class="flex h-[24px] w-[24px] items-center justify-center rounded-[6px] bg-brand text-[12px] font-extrabold text-white">Х</span>
                    <span class="rounded-[4px] bg-bluetint px-1.5 py-0.5 text-[10px] font-semibold text-brand">БИЗНЕС</span>
                </router-link>

                <h1 class="hidden text-[15px] font-bold text-ink md:block">{{ currentName }}</h1>
                <span v-if="store.organization" class="hidden text-[12.5px] font-medium text-mute lg:block">· {{ store.organization.name }}</span>

                <div class="ml-auto flex items-center gap-3">
                    <router-link :to="{ name: 'add-business' }" class="btn-primary !px-3.5 !py-2 !text-[12px]">Бизнес нэмэх</router-link>
                    <router-link :to="{ name: 'home' }" class="hidden text-[12.5px] font-semibold text-brand sm:block">Сайт руу</router-link>
                    <router-link :to="{ name: 'account' }" class="hidden text-[12.5px] font-medium text-soft hover:text-ink md:block">{{ auth.user?.name }}</router-link>
                    <button class="cursor-pointer rounded-lg border border-inputline px-3 py-1.5 text-[12px] font-semibold text-red hover:bg-redtint md:hidden" @click="logout">Гарах</button>
                </div>
            </header>

            <!-- Мобайл цэс -->
            <nav v-if="mobileNavOpen" class="flex flex-col border-b border-line bg-white md:hidden">
                <router-link
                    v-for="item in nav"
                    :key="item.route"
                    :to="{ name: item.route }"
                    class="flex items-center gap-2.5 border-b border-hairline px-4 py-2.5 text-[13px] font-semibold last:border-0"
                    :class="$route.name === item.route ? 'bg-bluetint text-brand' : 'text-body'"
                    @click="mobileNavOpen = false"
                >
                    <component :is="item.icon" :size="16" :stroke-width="1.9" aria-hidden="true" />
                    {{ item.name }}
                </router-link>
                <router-link :to="{ name: 'home' }" class="px-4 py-2.5 text-[13px] font-semibold text-mute" @click="mobileNavOpen = false">← Сайт руу буцах</router-link>
            </nav>

            <div class="min-w-0 flex-1">
                <div class="mx-auto max-w-[1400px]">
                    <div v-if="store.error === 'failed'" class="p-10 text-center">
                        <p class="text-[15px] font-bold text-ink">Ачаалахад алдаа гарлаа</p>
                        <p class="mt-1.5 text-[13px] text-mute">Интернэт холболтоо шалгаад дахин оролдоно уу.</p>
                        <button class="btn-primary mt-5" :disabled="store.loading" @click="store.load(true)">{{ store.loading ? 'Ачаалж байна…' : 'Дахин оролдох' }}</button>
                    </div>
                    <div v-else-if="store.loaded && !store.organization" class="p-10 text-center">
                        <p class="text-[15px] font-bold text-ink">Танд бүртгэлтэй бизнес алга</p>
                        <p class="mt-1.5 text-[13px] text-mute">Эхний бизнесээ бүртгүүлээд хэрэглэгчдэд хүрээрэй.</p>
                        <router-link :to="{ name: 'add-business' }" class="btn-primary mt-5">Бизнес нэмэх</router-link>
                    </div>
                    <router-view v-else-if="store.loaded" />
                    <div v-else class="space-y-4 p-7">
                        <div class="card h-24 animate-pulse bg-white"></div>
                        <div class="card h-48 animate-pulse bg-white"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
