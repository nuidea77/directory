<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ShieldCheck, Store, MessageSquare, Megaphone, BadgeCheck, TicketPercent, LayoutGrid, Search, TrendingUp } from 'lucide-vue-next';
import { useAuthStore } from '../../stores/auth';

// Админ layout (4a/9b): хар sidebar + өөрийн толгой
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const mobileNavOpen = ref(false);

const nav = [
    { name: 'Модерац', route: 'admin', icon: ShieldCheck },
    { name: 'Бизнесүүд', route: 'admin-businesses', icon: Store },
    { name: 'Сэтгэгдэл, залруулга', route: 'admin-reviews', icon: MessageSquare },
    { name: 'Сурталчилгаа', route: 'admin-ads', icon: Megaphone },
    { name: 'Эрхийн бичиг', route: 'admin-plans', icon: BadgeCheck },
    { name: 'Промо код', route: 'admin-promo', icon: TicketPercent },
    { name: 'Ангилал', route: 'admin-categories', icon: LayoutGrid },
    { name: 'Хайлтын синоним', route: 'admin-search-aliases', icon: Search },
    { name: 'Эрх, орлого', route: 'admin-revenue', icon: TrendingUp },
];

const currentName = computed(() => nav.find((n) => n.route === route.name)?.name || 'Админ');

async function logout() {
    await auth.logout();
    router.push({ name: 'home' });
}
</script>

<template>
    <div class="flex min-h-screen bg-page">
        <!-- Sidebar -->
        <!-- Sidebar дэлгэцэнд наалттай: урт жагсаалт гүйлгэхэд ч цэс, гарах товч хэвээр -->
        <aside class="sticky top-0 hidden h-screen w-[212px] shrink-0 flex-col overflow-y-auto bg-dark px-3.5 py-4 md:flex">
            <router-link :to="{ name: 'admin' }" class="flex items-center gap-2 px-1.5 pb-4">
                <span class="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] bg-brand text-[13px] font-extrabold text-white">О</span>
                <span class="text-[15px] font-extrabold text-white">Ойрхон</span>
                <span class="rounded-[4px] bg-bluelight/15 px-1.5 py-0.5 text-[10px] font-semibold text-bluelight">АДМИН</span>
            </router-link>

            <nav class="flex flex-col gap-0.5">
                <router-link
                    v-for="item in nav"
                    :key="item.route"
                    :to="{ name: item.route }"
                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[12.5px] font-semibold transition"
                    :class="$route.name === item.route ? 'bg-white/10 text-white' : 'text-darktext hover:bg-white/5 hover:text-white'"
                >
                    <component :is="item.icon" :size="16" :stroke-width="1.9" :class="$route.name === item.route ? 'text-bluelight' : 'text-[#7c848e]'" aria-hidden="true" />
                    {{ item.name }}
                </router-link>
            </nav>

            <!-- Доод хэсэг: сайт руу буцах, гарах -->
            <div class="mt-auto border-t border-white/10 pt-3">
                <router-link :to="{ name: 'home' }" class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-[12px] font-semibold text-darktext hover:bg-white/5">
                    ← Сайт руу буцах
                </router-link>
                <div class="mt-1 flex items-center gap-2 px-2.5 py-2">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/10 text-[11px] font-bold text-white">
                        {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
                    </span>
                    <span class="min-w-0 flex-1 truncate text-[11.5px] font-semibold text-white">{{ auth.user?.name }}</span>
                    <button class="cursor-pointer text-[11px] font-semibold text-red hover:underline" @click="logout">Гарах</button>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Админы толгой -->
            <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-line bg-white px-4 py-3 sm:px-6">
                <button class="cursor-pointer text-[18px] leading-none text-ink md:hidden" @click="mobileNavOpen = !mobileNavOpen">☰</button>

                <div class="flex items-center gap-2 md:hidden">
                    <span class="flex h-[24px] w-[24px] items-center justify-center rounded-[6px] bg-brand text-[12px] font-extrabold text-white">О</span>
                    <span class="rounded-[4px] bg-bluetint px-1.5 py-0.5 text-[10px] font-semibold text-brand">АДМИН</span>
                </div>

                <h1 class="hidden text-[15px] font-bold text-ink md:block">{{ currentName }}</h1>

                <div class="ml-auto flex items-center gap-3">
                    <router-link :to="{ name: 'home' }" class="hidden text-[12.5px] font-semibold text-brand sm:block">Сайт руу</router-link>
                    <span class="hidden text-[12.5px] font-medium text-soft md:block">{{ auth.user?.name }}</span>
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

            <!-- Өргөн дэлгэцэнд контент хэт сунахгүй -->
            <div class="min-w-0 flex-1">
                <div class="mx-auto max-w-[1400px]">
                    <router-view />
                </div>
            </div>
        </div>
    </div>
</template>
