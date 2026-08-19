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
    await auth.logout();
    router.push({ name: 'home' });
}
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
            <router-link :to="{ name: 'home' }" class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-lg font-extrabold text-white">Л</span>
                <span class="text-lg font-extrabold tracking-tight text-slate-900">Лавлах<span class="text-brand-600">.мн</span></span>
            </router-link>

            <nav class="hidden items-center gap-1 md:flex">
                <router-link :to="{ name: 'home' }" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900">Нүүр</router-link>
                <router-link :to="{ name: 'search' }" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900">Байгууллагууд</router-link>
                <router-link v-if="auth.isLoggedIn" :to="{ name: 'favorites' }" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900">Хадгалсан</router-link>
            </nav>

            <div class="hidden items-center gap-3 md:flex">
                <template v-if="auth.isLoggedIn">
                    <router-link :to="{ name: 'listing-create' }" class="btn-primary !py-2">+ Бизнес нэмэх</router-link>
                    <div class="relative">
                        <button
                            class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">
                                {{ auth.user.name?.charAt(0)?.toUpperCase() }}
                            </span>
                            {{ auth.user.name }}
                        </button>
                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 mt-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                            @click="userMenuOpen = false"
                        >
                            <router-link :to="{ name: 'dashboard' }" class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">Миний бизнесүүд</router-link>
                            <router-link :to="{ name: 'payments' }" class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">Төлбөрийн түүх</router-link>
                            <router-link :to="{ name: 'profile' }" class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">Профайл</router-link>
                            <button class="block w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50" @click="logout">Гарах</button>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <router-link :to="{ name: 'login' }" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Нэвтрэх</router-link>
                    <router-link :to="{ name: 'register' }" class="btn-primary !py-2">Бүртгүүлэх</router-link>
                </template>
            </div>

            <button class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden" aria-label="Цэс" @click="mobileOpen = !mobileOpen">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path v-if="!mobileOpen" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                    <path v-else stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>

        <div v-if="mobileOpen" class="border-t border-slate-200 bg-white px-4 py-3 md:hidden" @click="mobileOpen = false">
            <nav class="flex flex-col gap-1">
                <router-link :to="{ name: 'home' }" class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Нүүр</router-link>
                <router-link :to="{ name: 'search' }" class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Байгууллагууд</router-link>
                <template v-if="auth.isLoggedIn">
                    <router-link :to="{ name: 'favorites' }" class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Хадгалсан</router-link>
                    <router-link :to="{ name: 'dashboard' }" class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Миний бизнесүүд</router-link>
                    <router-link :to="{ name: 'listing-create' }" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50">+ Бизнес нэмэх</router-link>
                    <button class="rounded-lg px-3 py-2.5 text-left text-sm font-medium text-red-600 hover:bg-red-50" @click="logout">Гарах</button>
                </template>
                <template v-else>
                    <router-link :to="{ name: 'login' }" class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Нэвтрэх</router-link>
                    <router-link :to="{ name: 'register' }" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50">Бүртгүүлэх</router-link>
                </template>
            </nav>
        </div>
    </header>
</template>
