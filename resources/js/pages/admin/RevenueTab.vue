<script setup>
import { onMounted, ref } from 'vue';
import { Building2, FileClock, Megaphone, TicketPercent, Wallet } from 'lucide-vue-next';
import { api } from '../../api';
import PanelPageHeader from '../../components/panel/PanelPageHeader.vue';
import PanelStat from '../../components/panel/PanelStat.vue';
import PanelBadge from '../../components/panel/PanelBadge.vue';

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
        <PanelPageHeader
            title="Эрх, орлого"
            description="Эрхийн бичиг болон онцлох зайн орлого, төлбөртэй байгууллагын тоо, эрхийн бичгийн тархалт, онцлох зайн ашиглалтыг харуулна."
            :meta="[{ label: 'Сүүлийн 30 хоног' }]"
        />

        <div v-if="loadError" class="card mt-5 p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="fetchData">Дахин оролдох</button>
        </div>

        <div v-else-if="!data" class="card mt-5 h-64 animate-pulse"></div>

        <template v-else>
            <!-- KPI -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <PanelStat
                    label="30 хоногийн орлого"
                    :value="fmt(data.kpis.revenue_30d)"
                    :hint="data.kpis.revenue_delta !== null ? (data.kpis.revenue_delta >= 0 ? '+' : '') + data.kpis.revenue_delta + '%' : ''"
                    :tone="data.kpis.revenue_delta === null ? 'default' : data.kpis.revenue_delta >= 0 ? 'good' : 'bad'"
                    :icon="Wallet"
                />
                <PanelStat label="Төлбөртэй байгууллага" :value="data.kpis.paid_organizations" :icon="Building2" />
                <PanelStat label="Эрхийн орлого" :value="fmt(data.kpis.plan_revenue)" :icon="TicketPercent" />
                <PanelStat label="Онцлох зайн орлого" :value="fmt(data.kpis.ad_revenue)" :icon="Megaphone" />
                <PanelStat
                    label="Хүлээгдэж буй нэхэмжлэх"
                    :value="data.kpis.pending_orders"
                    :tone="data.kpis.pending_orders ? 'warn' : 'default'"
                    :icon="FileClock"
                />
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
                        <div class="w-16">
                            <PanelBadge mono>{{ v.occupied }} / {{ v.slots }}</PanelBadge>
                        </div>
                        <div class="w-16">
                            <PanelBadge v-if="v.queued" tone="warn">{{ v.queued }} хүлээж</PanelBadge>
                            <span v-else class="text-[12px] font-semibold text-mute">—</span>
                        </div>
                        <div class="w-24 text-right text-[12.5px] font-bold text-ink">{{ fmt(v.monthly_revenue) }}</div>
                    </div>
                    <div v-if="!data.inventory.length" class="p-8 text-center text-[13px] text-mute">Идэвхтэй онцлох зай алга</div>
                </div>
            </div>
        </template>
    </div>
</template>
