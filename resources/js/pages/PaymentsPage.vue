<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';

const payments = ref([]);
const loading = ref(true);

const statusLabels = {
    pending: ['Хүлээгдэж буй', 'bg-amber-100 text-amber-700'],
    paid: ['Төлөгдсөн', 'bg-emerald-100 text-emerald-700'],
    void: ['Цуцлагдсан', 'bg-slate-100 text-slate-500'],
    expired: ['Хугацаа дууссан', 'bg-slate-100 text-slate-500'],
};

onMounted(async () => {
    try {
        const data = await api.get('/payments');
        payments.value = data.data;
    } finally {
        loading.value = false;
    }
});

const formatAmount = (amount) => new Intl.NumberFormat('mn-MN').format(amount) + '₮';
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <h1 class="text-2xl font-bold text-slate-900">Төлбөрийн түүх</h1>

        <div v-if="loading" class="mt-8 space-y-3">
            <div v-for="i in 3" :key="i" class="card h-20 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else-if="payments.length" class="card mt-8 divide-y divide-slate-100">
            <div v-for="p in payments" :key="p.id" class="flex items-center justify-between gap-4 p-5">
                <div>
                    <p class="font-semibold text-slate-900">{{ formatAmount(p.amount) }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ p.plan }} · {{ new Date(p.created_at).toLocaleString() }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a v-if="p.status === 'pending' && p.invoice_url" :href="p.invoice_url" target="_blank" rel="noopener" class="text-sm font-semibold text-brand-600 hover:text-brand-700">Төлөх →</a>
                    <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statusLabels[p.status]?.[1]">
                        {{ statusLabels[p.status]?.[0] || p.status }}
                    </span>
                </div>
            </div>
        </div>

        <div v-else class="card mt-8 p-16 text-center">
            <p class="text-4xl">💳</p>
            <p class="mt-4 font-semibold text-slate-700">Төлбөрийн түүх алга</p>
        </div>
    </div>
</template>
