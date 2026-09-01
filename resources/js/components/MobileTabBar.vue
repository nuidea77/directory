<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { Grid2x2, Heart, House, Search, User } from 'lucide-vue-next';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const auth = useAuthStore();

// Доод таб — гар утсанд үндсэн шилжилтийн цэс болно
const tabs = computed(() => [
    { key: 'home', label: 'Нүүр', icon: House, to: { name: 'home' } },
    { key: 'categories', label: 'Ангилал', icon: Grid2x2, to: { name: 'categories' } },
    { key: 'search', label: 'Хайх', icon: Search, to: { name: 'search' } },
    { key: 'saved', label: 'Хадгалсан', icon: Heart, to: auth.isLoggedIn ? { name: 'account', query: { tab: undefined } } : { name: 'login' } },
    {
        key: 'profile',
        label: auth.isLoggedIn ? 'Профайл' : 'Нэвтрэх',
        icon: User,
        to: auth.isLoggedIn ? { name: 'account', query: { tab: 'settings' } } : { name: 'login' },
    },
]);

function isActive(tab) {
    if (tab.key === 'home') return route.name === 'home';
    if (tab.key === 'categories') return route.name === 'categories' || route.name === 'category';
    if (tab.key === 'search') return route.name === 'search';
    if (tab.key === 'saved') return route.name === 'account' && route.query.tab !== 'settings';
    if (tab.key === 'profile') return route.name === 'account' && route.query.tab === 'settings';
    return false;
}
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white/95 backdrop-blur md:hidden"
        style="padding-bottom: env(safe-area-inset-bottom)"
        aria-label="Үндсэн цэс"
    >
        <div class="flex items-stretch">
            <router-link
                v-for="t in tabs"
                :key="t.key"
                :to="t.to"
                class="flex flex-1 flex-col items-center gap-1 py-2"
                :class="isActive(t) ? 'text-brand' : 'text-mute'"
            >
                <!-- Идэвхтэй табын дээд цэг -->
                <span class="h-1 w-1 rounded-full" :class="isActive(t) ? 'bg-brand' : 'bg-transparent'"></span>
                <component :is="t.icon" :size="21" :stroke-width="isActive(t) ? 2 : 1.6" aria-hidden="true" />
                <span class="text-[10.5px] font-semibold leading-none">{{ t.label }}</span>
            </router-link>
        </div>
    </nav>
</template>
