<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import AppNavbar from './components/AppNavbar.vue';
import AppFooter from './components/AppFooter.vue';
import MobileTabBar from './components/MobileTabBar.vue';
import { useAuthStore } from './stores/auth';

const router = useRouter();
const auth = useAuthStore();

// Токен хүчингүй болоход (401) session-ийг цэвэрлэж login руу чиглүүлнэ
function onAuthExpired() {
    auth.user = null;
    if (router.currentRoute.value.meta.auth) {
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
    }
}

onMounted(() => window.addEventListener('auth:expired', onAuthExpired));
onBeforeUnmount(() => window.removeEventListener('auth:expired', onAuthExpired));
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <!-- Өөрийн толгойтой хуудсуудад (wizard, төлбөр, салбарын засвар) давхарлахгүй -->
        <AppNavbar v-if="$route.meta.chrome !== false" />
        <main class="flex-1">
            <router-view />
        </main>
        <AppFooter v-if="$route.meta.chrome !== false" />

        <!-- Гар утсан дээрх доод таб цэс — доод зайг нөхөж, агуулга халхлагдахгүй -->
        <template v-if="$route.meta.chrome !== false">
            <div class="h-[62px] md:hidden" aria-hidden="true"></div>
            <MobileTabBar />
        </template>
    </div>
</template>
