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

const error = ref('');

async function fetchOrders() {
    loading.value = true;
    error.value = '';
    try {
        const data = await api.get('/orders');
        orders.value = data.data;
    } catch {
        error.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    } finally {
        loading.value = false;
    }
}

async function cancelOrder(order) {
    if (!confirm(`${order.number} захиалгыг цуцлах уу?`)) return;
    try {
        await api.delete(`/orders/${order.id}`);
        await fetchOrders();
    } catch {
        alert('Цуцлахад алдаа гарлаа.');
    }
}

onMounted(fetchOrders);
</script>

<template>
    <div class="p-5 sm:p-7">
        <h1 class="text-xl font-extrabold tracking-[-.02em] text-ink">Нэхэмжлэх, төлбөрийн түүх</h1>

        <div v-if="loading" class="card mt-4 h-48 animate-pulse"></div>

        <div v-else-if="error" class="card mt-4 p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ error }}</p>
            <button class="btn-primary mt-4" @click="fetchOrders">Дахин оролдох</button>
        </div>

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
                    <template v-if="order.status === 'pending'">
                        <a v-if="order.invoice_url" :href="order.invoice_url" class="rounded-lg bg-brand px-3.5 py-2 text-[12px] font-bold text-white">byl.mn-ээр төлөх →</a>
                        <router-link v-else :to="{ name: 'order-pay', params: { id: order.id } }" class="rounded-lg bg-brand px-3.5 py-2 text-[12px] font-bold text-white">Төлөх →</router-link>
                        <button class="cursor-pointer text-[12px] font-semibold text-mute hover:text-red" @click="cancelOrder(order)">Цуцлах</button>
                    </template>
                </div>
            </div>
        </div>

        <div v-else class="card mt-4 p-12 text-center text-[13px] text-mute">Төлбөрийн түүх алга</div>
    </div>
</template>
