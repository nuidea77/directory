<script setup>
import { onMounted, ref } from 'vue';
import { Flag, Clock, CheckCircle2, XCircle, Store } from 'lucide-vue-next';
import { api } from '../../api';
import AdminPageHeader from '../../components/admin/AdminPageHeader.vue';
import AdminStat from '../../components/admin/AdminStat.vue';
import AdminBadge from '../../components/admin/AdminBadge.vue';

// Модерацын тойм (4a): KPI, хүлээгдэж буй бүртгэл, дата чанар
// Огноог YYYY.MM.DD — browser бүрд mn-MN locale байдаггүй
const today = (() => {
    const d = new Date();
    return `${d.getFullYear()}.${String(d.getMonth() + 1).padStart(2, '0')}.${String(d.getDate()).padStart(2, '0')}`;
})();

const data = ref(null);
const loadError = ref('');
const busyId = ref(null);

async function fetchData() {
    loadError.value = '';
    try {
        data.value = await api.get('/admin/moderation');
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    }
}

async function decide(branch, action) {
    let reason;

    if (action === 'reject') {
        // Шалтгаан эзэнд харагдана
        reason = prompt('Татгалзах шалтгаан (эзэнд харагдана):');
        if (reason === null) return;
    }

    busyId.value = branch.id;
    try {
        await api.post(`/admin/branches/${branch.id}/${action}`, reason ? { reason } : undefined);
        await fetchData();
    } catch {
        alert('Алдаа гарлаа, дахин оролдоно уу.');
    } finally {
        busyId.value = null;
    }
}

onMounted(fetchData);
</script>

<template>
    <div class="p-5 sm:p-7">
        <AdminPageHeader
            title="Модерацын тойм"
            description="Шинээр бүртгүүлсэн салбаруудыг батлах, татгалзах. Татгалзсан шалтгаан эзэнд харагдана."
        >
            <template #actions>
                <span class="text-[12.5px] font-medium text-mute">{{ today }}</span>
                <button class="btn-outline !px-3.5 !py-2 !text-[12.5px]" @click="fetchData">Шинэчлэх</button>
            </template>
        </AdminPageHeader>

        <div v-if="loadError" class="card p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="fetchData">Дахин оролдох</button>
        </div>

        <div v-else-if="!data" class="card h-64 animate-pulse"></div>

        <template v-else>
            <!-- KPI (4a) -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <AdminStat
                    label="Гомдолтой сэтгэгдэл"
                    :value="Number(data.kpis.flagged_reviews)"
                    :tone="data.kpis.flagged_reviews ? 'bad' : 'default'"
                    :icon="Flag"
                    :to="{ name: 'admin-reviews' }"
                    hint="Шалгах шаардлагатай"
                />
                <AdminStat label="Хүлээгдэж байгаа" :value="Number(data.kpis.pending)" :tone="data.kpis.pending ? 'warn' : 'good'" :icon="Clock" hint="Редакцын хяналтад" />
                <AdminStat label="Өнөөдөр батлагдсан" :value="Number(data.kpis.approved_today)" tone="good" :icon="CheckCircle2" />
                <AdminStat label="Татгалзсан" :value="Number(data.kpis.rejected)" :icon="XCircle" />
                <AdminStat label="Бүртгэлтэй бизнес" :value="Number(data.kpis.total_businesses)" :icon="Store" />
            </div>

            <div class="mt-3.5 grid grid-cols-1 gap-3.5 lg:grid-cols-[1fr_272px]">
                <!-- Хүлээгдэж буй бүртгэл -->
                <div class="card overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-divider px-4 py-3.5">
                        <span class="text-[14px] font-bold text-ink">Хүлээгдэж байгаа бүртгэл</span>
                        <AdminBadge :tone="data.queue_total ? 'warn' : 'neutral'" mono>{{ data.queue_total }}</AdminBadge>
                    </div>
                    <div v-for="branch in data.queue" :key="branch.id" class="flex flex-wrap items-center gap-3 border-b border-hairline px-4 py-3 last:border-0">
                        <div class="img-ph h-[26px] w-[26px] shrink-0 rounded-md"></div>
                        <div class="min-w-[170px] flex-1">
                            <div class="flex items-center gap-2">
                                <span class="whitespace-nowrap text-[13px] font-bold text-ink">{{ branch.business?.name }} — {{ branch.name }}</span>
                                <AdminBadge tone="brand">ШИНЭ</AdminBadge>
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
