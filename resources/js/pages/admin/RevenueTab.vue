<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../../api';

// Эрх, сурталчилгаа, орлого (9b)
const data = ref(null);
const loadError = ref('');

const fmt = (n) => '₮' + Number(n).toLocaleString();

async function fetchData() {
    loadError.value = '';
    try {
        data.value = await api.get('/admin/revenue');
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    }
}

onMounted(fetchData);
</script>

<template>
    <div class="p-5 sm:p-7">
        <div class="flex items-center gap-3.5">
            <h1 class="text-[15px] font-bold text-ink">Эрх, сурталчилгаа</h1>
            <span class="text-[12.5px] font-medium text-mute">Сүүлийн 30 хоног</span>
        </div>

        <div v-if="loadError" class="card mt-5 p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="fetchData">Дахин оролдох</button>
        </div>

        <div v-else-if="!data" class="card mt-5 h-64 animate-pulse"></div>

        <template v-else>
            <!-- KPI -->
            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div class="card p-4">
                    <div class="text-[11px] font-semibold text-mute">30 хоногийн орлого</div>
                    <div class="mt-2 text-[20px] font-extrabold tracking-[-.02em] text-ink">{{ fmt(data.kpis.revenue_30d) }}</div>
                    <div v-if="data.kpis.revenue_delta !== null" class="mt-1 text-[11px] font-semibold" :class="data.kpis.revenue_delta >= 0 ? 'text-green' : 'text-red'">{{ (data.kpis.revenue_delta >= 0 ? '+' : '') + data.kpis.revenue_delta }}%</div>
                </div>
                <div class="card p-4">
                    <div class="text-[11px] font-semibold text-mute">Төлбөртэй байгууллага</div>
                    <div class="mt-2 text-[20px] font-extrabold text-ink">{{ data.kpis.paid_organizations }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-[11px] font-semibold text-mute">Эрхийн орлого</div>
                    <div class="mt-2 text-[20px] font-extrabold text-ink">{{ fmt(data.kpis.plan_revenue) }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-[11px] font-semibold text-mute">Онцлох зайн орлого</div>
                    <div class="mt-2 text-[20px] font-extrabold text-ink">{{ fmt(data.kpis.ad_revenue) }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-[11px] font-semibold text-mute">Хүлээгдэж буй нэхэмжлэх</div>
                    <div class="mt-2 text-[20px] font-extrabold" :class="data.kpis.pending_orders ? 'text-amber' : 'text-ink'">{{ data.kpis.pending_orders }}</div>
                </div>
            </div>

            <div class="mt-3.5 grid grid-cols-1 gap-3.5 lg:grid-cols-[1fr_1fr]">
                <!-- Эрхийн бичгийн тархалт -->
                <div class="card p-5">
                    <div class="text-[15px] font-bold text-ink">Эрхийн бичгийн тархалт</div>
                    <div class="mt-4 flex flex-col gap-3.5">
                        <div v-for="p in data.plan_mix" :key="p.plan">
                            <div class="flex justify-between text-[12.5px] font-semibold text-ink"><span>{{ p.name }}</span><span class="text-soft">{{ p.count.toLocaleString() }}</span></div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-chip">
                                <div class="h-full" :class="p.plan === 'free' ? 'bg-blueline' : 'bg-brand'" :style="{ width: Math.min(100, (p.count / Math.max(1, data.plan_mix.reduce((s, x) => s + x.count, 0))) * 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Онцлох зайн бүртгэл -->
                <div class="card overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-divider px-4 py-3.5">
                        <span class="text-[14px] font-bold text-ink">Онцлох зайн бүртгэл</span>
                    </div>
                    <div v-for="v in data.inventory" :key="v.label + v.type" class="flex items-center gap-3 border-b border-hairline px-4 py-3 last:border-0">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[12.5px] font-bold text-ink">{{ v.label || v.type_name }}</div>
                            <div class="mt-0.5 text-[11px] text-mute">{{ v.type_name }}</div>
                        </div>
                        <div class="w-16 font-mono text-[12px] text-soft">{{ v.occupied }} / {{ v.slots }}</div>
                        <div class="w-16 text-[12px] font-semibold" :class="v.queued ? 'text-amber' : 'text-mute'">{{ v.queued ? v.queued + ' хүлээж' : '—' }}</div>
                        <div class="w-24 text-right text-[12.5px] font-bold text-ink">{{ fmt(v.monthly_revenue) }}</div>
                    </div>
                    <div v-if="!data.inventory.length" class="p-8 text-center text-[13px] text-mute">Идэвхтэй онцлох зай алга</div>
                </div>
            </div>
        </template>
    </div>
</template>
