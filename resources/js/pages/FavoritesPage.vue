<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';
import ListingCard from '../components/ListingCard.vue';

const listings = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const data = await api.get('/favorites');
        listings.value = data.data;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <h1 class="text-2xl font-bold text-slate-900">Хадгалсан байгууллагууд</h1>

        <div v-if="loading" class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="i in 4" :key="i" class="card h-64 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else-if="listings.length" class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <ListingCard v-for="listing in listings" :key="listing.id" :listing="listing" />
        </div>

        <div v-else class="card mt-8 p-16 text-center">
            <p class="text-4xl">🤍</p>
            <p class="mt-4 font-semibold text-slate-700">Хадгалсан байгууллага алга</p>
            <router-link :to="{ name: 'search' }" class="btn-primary mt-6">Байгууллага хайх</router-link>
        </div>
    </div>
</template>
