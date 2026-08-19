<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const mobileOpen = ref(false);
const userMenuOpen = ref(false);

async function logout() {
    userMenuOpen.value = false;
    mobileOpen.value = false;
    await auth.logout();
    router.push({ name: 'home' });
}
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-line bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-[62px] max-w-7xl items-center justify-between gap-6 px-5 sm:px-10">
            <div class="flex items-center gap-8">
                <router-link :to="{ name: 'home' }" class="flex items-center gap-2.5">
                    <span class="flex h-[30px] w-[30px] items-center justify-center rounded-lg bg-brand text-[15px] font-extrabold text-white">Х</span>
                    <span class="text-lg font-extrabold tracking-[-.01em] text-ink">Хаана<span class="text-brand">.mn</span></span>
                </router-link>
                <nav class="hidden items-center gap-6 text-[13.5px] font-medium text-chiptext lg:flex">
                    <router-link :to="{ name: 'categories' }" class="hover:text-ink">Ангилал</router-link>
                    <router-link :to="{ name: 'nearby' }" class="hover:text-ink">Миний ойролцоо</router-link>
                    <router-link :to="{ name: 'pricing' }" class="hover:text-ink">Бизнест</router-link>
                </nav>
            </div>

            <div class="hidden items-center gap-3 md:flex">
                <span class="text-[13px] font-medium text-chiptext">MN / EN</span>

                <template v-if="auth.isLoggedIn">
                    <router-link :to="{ name: 'add-business' }" class="btn-primary !px-4 !py-2.5 !text-[13px]">Бизнес нэмэх</router-link>
                    <div class="relative">
                        <button
                            class="flex cursor-pointer items-center gap-2 rounded-lg border border-inputline px-3 py-2 text-[13px] font-semibold text-ink hover:bg-panel"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            <span class="flex h-6 w-6 items-center justify-center rounded-full border border-blueline bg-bluetint text-[11px] font-bold text-brand">
                                {{ auth.user.name?.charAt(0)?.toUpperCase() }}
                            </span>
                            {{ auth.user.name }}
                        </button>
                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-lg"
                            @click="userMenuOpen = false"
                        >
                            <router-link :to="{ name: 'account' }" class="block px-4 py-2.5 text-[13px] font-medium text-body hover:bg-panel">Миний булан</router-link>
                            <router-link :to="{ name: 'console' }" class="block px-4 py-2.5 text-[13px] font-medium text-body hover:bg-panel">Бизнес зөвлөл</router-link>
                            <router-link v-if="auth.user.is_admin" :to="{ name: 'admin' }" class="block px-4 py-2.5 text-[13px] font-medium text-body hover:bg-panel">Админ</router-link>
                            <button class="block w-full cursor-pointer px-4 py-2.5 text-left text-[13px] font-medium text-red hover:bg-redtint" @click="logout">Гарах</button>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <router-link :to="{ name: 'login' }" class="btn-outline !px-3.5 !py-2.5 !text-[13px]">Нэвтрэх</router-link>
                    <router-link :to="{ name: 'register' }" class="btn-primary !px-4 !py-2.5 !text-[13px]">Бизнес нэмэх</router-link>
                </template>
            </div>

            <button class="cursor-pointer rounded-lg p-2 text-soft hover:bg-panel md:hidden" aria-label="Цэс" @click="mobileOpen = !mobileOpen">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path v-if="!mobileOpen" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                    <path v-else stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>

        <div v-if="mobileOpen" class="border-t border-line bg-white px-5 py-3 md:hidden" @click="mobileOpen = false">
            <nav class="flex flex-col gap-1 text-[13.5px] font-medium">
                <router-link :to="{ name: 'categories' }" class="rounded-lg px-3 py-2.5 text-body hover:bg-panel">Ангилал</router-link>
                <router-link :to="{ name: 'nearby' }" class="rounded-lg px-3 py-2.5 text-body hover:bg-panel">Миний ойролцоо</router-link>
                <router-link :to="{ name: 'pricing' }" class="rounded-lg px-3 py-2.5 text-body hover:bg-panel">Бизнест</router-link>
                <template v-if="auth.isLoggedIn">
                    <router-link :to="{ name: 'account' }" class="rounded-lg px-3 py-2.5 text-body hover:bg-panel">Миний булан</router-link>
                    <router-link :to="{ name: 'console' }" class="rounded-lg px-3 py-2.5 text-body hover:bg-panel">Бизнес зөвлөл</router-link>
                    <router-link :to="{ name: 'add-business' }" class="rounded-lg px-3 py-2.5 font-bold text-brand hover:bg-bluetint">Бизнес нэмэх</router-link>
                    <button class="cursor-pointer rounded-lg px-3 py-2.5 text-left text-red hover:bg-redtint" @click="logout">Гарах</button>
                </template>
                <template v-else>
                    <router-link :to="{ name: 'login' }" class="rounded-lg px-3 py-2.5 text-body hover:bg-panel">Нэвтрэх</router-link>
                    <router-link :to="{ name: 'register' }" class="rounded-lg px-3 py-2.5 font-bold text-brand hover:bg-bluetint">Бүртгүүлэх</router-link>
                </template>
            </nav>
        </div>
    </header>
</template>
