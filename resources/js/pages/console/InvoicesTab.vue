<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../../api';

// Нэхэмжлэх, төлбөрийн түүх (9a-ийн хэсэг)
const orders = ref([]);
const loading = ref(true);

const fmt = (n) => '₮' + Number(n).toLocaleString();

const statusLabel = {
    paid: ['Төлөгдсөн', 'text-green'],
    pending: ['Хүлээгдэж байна', 'text-amberdark'],
    void: ['Цуцлагдсан', 'text-mute'],
    expired: ['Хугацаа дууссан', 'text-mute'],
};

onMounted(async () => {
    try {
        const data = await api.get('/orders');
        orders.value = data.data;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="p-5 sm:p-7">
        <h1 class="text-xl font-extrabold tracking-[-.02em] text-ink">Нэхэмжлэх, төлбөрийн түүх</h1>

        <div v-if="loading" class="card mt-4 h-48 animate-pulse"></div>

        <div v-else-if="orders.length" class="card mt-4 overflow-hidden">
            <div v-for="order in orders" :key="order.id" class="border-b border-hairline px-4 py-3.5 last:border-0">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="font-mono text-[12.5px] font-semibold text-ink">{{ order.number }}</div>
                        <div class="mt-1 text-[11.5px] text-mute">{{ (order.items || []).map(i => i.name).join(' · ') }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-[13px] font-bold text-ink">{{ fmt(order.total) }}</div>
                        <div class="mt-0.5 text-[11.5px] font-semibold" :class="statusLabel[order.status]?.[1]">{{ statusLabel[order.status]?.[0] }}</div>
                    </div>
                    <router-link v-if="order.status === 'pending'" :to="{ name: 'order-pay', params: { id: order.id } }" class="rounded-lg bg-brand px-3.5 py-2 text-[12px] font-bold text-white">Төлөх →</router-link>
                    <a v-else-if="order.invoice_url" :href="order.invoice_url" target="_blank" rel="noopener" class="text-[12px] font-semibold text-brand">И-баримт</a>
                </div>
            </div>
        </div>

        <div v-else class="card mt-4 p-12 text-center text-[13px] text-mute">Төлбөрийн түүх алга</div>
    </div>
</template>
