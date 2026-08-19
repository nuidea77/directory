<script setup>
import AppNavbar from './components/AppNavbar.vue';
import AppFooter from './components/AppFooter.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <AppNavbar />
        <main class="flex-1">
            <router-view />
        </main>
        <AppFooter />
    </div>
</template>
