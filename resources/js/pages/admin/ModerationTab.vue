<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../../api';

// Модерацын тойм (4a): KPI, хүлээгдэж буй бүртгэл, дата чанар
const data = ref(null);
const busyId = ref(null);

async function fetchData() {
    data.value = await api.get('/admin/moderation');
}

async function decide(branch, action) {
    busyId.value = branch.id;
    try {
        await api.post(`/admin/branches/${branch.id}/${action}`);
        await fetchData();
    } finally {
        busyId.value = null;
    }
}

onMounted(fetchData);
</script>

<template>
    <div class="p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3.5">
                <h1 class="text-[15px] font-bold text-ink">Модерацын тойм</h1>
                <span class="text-[12.5px] font-medium text-mute">{{ new Date().toLocaleDateString() }}</span>
            </div>
        </div>

        <div v-if="!data" class="card mt-5 h-64 animate-pulse"></div>

        <template v-else>
            <!-- KPI (4a) -->
            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div v-for="k in [
                    ['Хүлээгдэж байгаа', data.kpis.pending, 'text-amber'],
                    ['Өнөөдөр батлагдсан', data.kpis.approved_today, 'text-ink'],
                    ['Татгалзсан', data.kpis.rejected, 'text-ink'],
                    ['Гомдолтой сэтгэгдэл', data.kpis.flagged_reviews, data.kpis.flagged_reviews ? 'text-red' : 'text-ink'],
                    ['Бүртгэлтэй бизнес', data.kpis.total_businesses, 'text-ink'],
                ]" :key="k[0]" class="card p-4">
                    <div class="text-[11px] font-semibold text-mute">{{ k[0] }}</div>
                    <div class="mt-2 text-2xl font-extrabold tracking-[-.02em]" :class="k[2]">{{ Number(k[1]).toLocaleString() }}</div>
                </div>
            </div>

            <div class="mt-3.5 grid grid-cols-1 gap-3.5 lg:grid-cols-[1fr_272px]">
                <!-- Хүлээгдэж буй бүртгэл -->
                <div class="card overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-divider px-4 py-3.5">
                        <span class="text-[14px] font-bold text-ink">Хүлээгдэж байгаа бүртгэл</span>
                        <span class="rounded-full bg-amberbadge px-2 py-0.5 font-mono text-[10.5px] font-bold text-amber">{{ data.queue_total }}</span>
                    </div>
                    <div v-for="branch in data.queue" :key="branch.id" class="flex flex-wrap items-center gap-3 border-b border-hairline px-4 py-3 last:border-0">
                        <div class="img-ph h-[26px] w-[26px] shrink-0 rounded-md"></div>
                        <div class="min-w-[170px] flex-1">
                            <div class="flex items-center gap-2">
                                <span class="whitespace-nowrap text-[13px] font-bold text-ink">{{ branch.business?.name }} — {{ branch.name }}</span>
                                <span class="rounded-[4px] bg-bluetint px-1.5 py-0.5 text-[9.5px] font-semibold text-brand">ШИНЭ</span>
                            </div>
                            <div class="mt-0.5 text-[11.5px] text-mute">{{ branch.business?.category?.name }} · {{ branch.district }} · {{ branch.phone }}</div>
                        </div>
                        <div class="flex gap-1.5 text-[11.5px] font-semibold">
                            <button class="cursor-pointer rounded-[7px] bg-brand px-2.5 py-1.5 text-white disabled:opacity-50" :disabled="busyId === branch.id" @click="decide(branch, 'approve')">Батлах</button>
                            <button class="cursor-pointer rounded-[7px] border border-inputline bg-white px-2.5 py-1.5 text-ink disabled:opacity-50" :disabled="busyId === branch.id" @click="decide(branch, 'reject')">Татгалзах</button>
                        </div>
                    </div>
                    <div v-if="!data.queue.length" class="p-10 text-center text-[13px] text-mute">Хүлээгдэж буй бүртгэл алга 🎉</div>
                </div>

                <!-- Дата чанар -->
                <div class="card h-fit p-[18px]">
                    <div class="text-[14px] font-bold text-ink">Дата чанар</div>
                    <div class="mt-3.5 flex flex-col gap-3">
                        <div v-for="d in data.data_quality" :key="d.name">
                            <div class="flex justify-between text-[12px] font-semibold text-ink"><span>{{ d.name }}</span><span class="text-soft">{{ d.pct }}%</span></div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-chip">
                                <div class="h-full rounded-full" :class="d.pct >= 80 ? 'bg-green' : d.pct >= 50 ? 'bg-brand' : 'bg-amberdot'" :style="{ width: d.pct + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
