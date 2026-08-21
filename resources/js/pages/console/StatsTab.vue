<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../../api';
import { useConsoleStore } from '../../stores/console';

// Статистик (3c): KPI, хандалт/залгалтын багана график, "Хэрхэн олсон", хариу хүлээх сэтгэгдэл
const store = useConsoleStore();
const stats = ref(null);
const days = ref(30);
const loadError = ref('');

async function fetchStats() {
    if (!store.organization) return;
    loadError.value = '';
    try {
        const data = await api.get(`/console/organizations/${store.organization.id}/stats`, { days: days.value });
        stats.value = data;
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    }
}

// 3 хоногийн bucket-ууд болгож нэгтгэнэ (график)
const buckets = computed(() => {
    const series = stats.value?.series || [];
    const out = [];
    for (let i = 0; i < series.length; i += 3) {
        const group = series.slice(i, i + 3);
        out.push({
            label: group[0].date.slice(5),
            views: group.reduce((s, d) => s + d.views, 0),
            calls: group.reduce((s, d) => s + d.calls, 0),
        });
    }
    const max = Math.max(1, ...out.map((b) => b.views + b.calls));
    return out.map((b) => ({ ...b, h1: Math.round((b.views / max) * 130) + 6, h2: Math.round((b.calls / max) * 130) + 4 }));
});

const deltaColor = (d) => (d === null ? 'text-mute' : d >= 0 ? 'text-green' : 'text-amberdark');
const deltaText = (d) => (d === null ? '—' : (d >= 0 ? '+' : '') + d + '%');

watch(() => store.organization?.id, fetchStats);
onMounted(fetchStats);
import PanelPageHeader from '../../components/panel/PanelPageHeader.vue';
import { Eye, Navigation, Phone } from 'lucide-vue-next';
</script>

<template>
    <div class="p-5 sm:p-7">
        <PanelPageHeader title="Статистик" description="Салбар бүрийн үзэлт, залгалт, зам заалт — сонгосон хугацаанд.">
            <template #actions>
            <select v-model="days" class="cursor-pointer rounded-lg border border-inputline bg-white px-3 py-2 text-[12.5px] font-medium text-soft outline-none" @change="fetchStats">
                <option :value="7">Сүүлийн 7 хоног</option>
                <option :value="30">Сүүлийн 30 хоног</option>
                <option :value="90">Сүүлийн 90 хоног</option>
            </select>
            </template>
        </PanelPageHeader>

        <div v-if="loadError" class="card mt-5 p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="fetchStats">Дахин оролдох</button>
        </div>

        <div v-else-if="!stats" class="card mt-5 h-64 animate-pulse"></div>

        <template v-else>
            <div v-if="!stats.analytics_enabled" class="mt-4 flex items-center gap-3 rounded-xl border border-blueline bg-bluetint px-4 py-3">
                <span class="text-[12.5px] font-medium leading-relaxed text-body">Дэлгэрэнгүй аналитик (хайлтын үгс, эх сурвалж) Стандарт эрхээс нээгдэнэ — үндсэн тоонууд үнэгүй эрхэд харагдана.</span>
                <router-link :to="{ name: 'console-plan' }" class="ml-auto whitespace-nowrap rounded-lg bg-brand px-3 py-2 text-[11.5px] font-bold text-white">Эрх авах</router-link>
            </div>

            <!-- KPI -->
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div v-for="k in [['Бүртгэл үзсэн', stats.totals.views, stats.totals.views_delta, Eye], ['Залгасан', stats.totals.calls, stats.totals.calls_delta, Phone], ['Зам заалт', stats.totals.directions, stats.totals.directions_delta, Navigation]]" :key="k[0]" class="card p-[18px]">
                    <div class="flex items-center gap-1.5 text-[11.5px] font-semibold text-mute">
                        <component :is="k[3]" :size="14" :stroke-width="1.9" class="text-brand" /> {{ k[0] }}
                    </div>
                    <div class="mt-2 text-[28px] font-extrabold tracking-[-.02em] text-ink">{{ Number(k[1]).toLocaleString() }}</div>
                    <div class="mt-1.5 flex items-center gap-1.5 text-[11.5px]">
                        <span class="font-bold" :class="deltaColor(k[2])">{{ deltaText(k[2]) }}</span>
                        <span class="font-medium text-mute">өмнөх үеэс</span>
                    </div>
                </div>
            </div>

            <div class="mt-3.5 grid grid-cols-1 gap-3.5 lg:grid-cols-[1.5fr_1fr]">
                <!-- Багана график (3c) -->
                <div class="card p-5">
                    <div class="flex items-baseline justify-between">
                        <span class="text-[15px] font-bold text-ink">Хандалт ба залгалт</span>
                        <div class="flex gap-3.5 text-[11.5px] font-medium text-soft">
                            <span class="flex items-center gap-1.5"><span class="h-[9px] w-[9px] rounded-[3px] bg-brand"></span>Үзсэн</span>
                            <span class="flex items-center gap-1.5"><span class="h-[9px] w-[9px] rounded-[3px] bg-[#bfd4ea]"></span>Залгасан</span>
                        </div>
                    </div>
                    <div v-if="!buckets.length" class="mt-5 flex h-[170px] items-center justify-center text-[13px] text-mute">Өгөгдөл хараахан алга</div>
                    <div v-else class="mt-5 flex h-[170px] items-end gap-2.5">
                        <div v-for="b in buckets" :key="b.label" class="flex flex-1 flex-col items-center gap-2">
                            <div class="flex h-[150px] w-full flex-col justify-end gap-[3px]">
                                <div class="rounded-t-[5px] bg-brand" :style="{ height: b.h1 + 'px' }" :title="`Үзсэн: ${b.views}`"></div>
                                <div class="rounded-b-[5px] bg-[#bfd4ea]" :style="{ height: b.h2 + 'px' }" :title="`Залгасан: ${b.calls}`"></div>
                            </div>
                            <span class="font-mono text-[10.5px] font-medium text-faint">{{ b.label }}</span>
                        </div>
                    </div>
                </div>

                <!-- Хэрхэн олсон -->
                <div class="card p-5">
                    <div class="text-[15px] font-bold text-ink">Хэрхэн олсон</div>
                    <div class="mt-4 flex flex-col gap-3.5" :class="{ 'opacity-40 blur-[2px]': !stats.analytics_enabled }">
                        <div v-for="s in stats.sources" :key="s.name">
                            <div class="flex justify-between text-[12.5px] font-semibold text-ink"><span>{{ s.name }}</span><span class="text-soft">{{ s.pct === null ? '🔒' : s.pct + '%' }}</span></div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-chip"><div class="h-full bg-brand" :style="{ width: (s.pct || 0) + '%' }"></div></div>
                        </div>
                    </div>
                    <p v-if="!stats.analytics_enabled" class="mt-3 text-[11.5px] font-medium text-mute">Стандарт эрхэд нээгдэнэ</p>
                </div>
            </div>

            <!-- Хариу хүлээж буй сэтгэгдэл -->
            <div v-if="stats.pending_reviews?.length" class="card mt-3.5 p-5">
                <div class="flex items-baseline justify-between">
                    <span class="text-[15px] font-bold text-ink">Хариу бичих сэтгэгдэл</span>
                    <router-link :to="{ name: 'console-reviews' }" class="text-[12.5px] font-semibold text-brand">Бүгдийг үзэх</router-link>
                </div>
                <div class="mt-3.5 flex flex-col gap-2.5">
                    <div v-for="r in stats.pending_reviews" :key="r.id" class="rounded-[11px] border border-blueline bg-bluepale p-3.5">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-[30px] w-[30px] items-center justify-center rounded-full border border-blueline bg-bluetint text-[12px] font-bold text-brand">{{ r.user?.name?.charAt(0) }}</span>
                            <span class="text-[13px] font-bold text-ink">{{ r.user?.name }}</span>
                            <span class="text-[11.5px] font-medium text-mute">{{ r.branch?.name }}</span>
                            <span class="ml-auto text-[12.5px] font-bold text-ink">★ {{ r.rating }}.0</span>
                        </div>
                        <p class="mt-2 text-[13px] leading-relaxed text-body">{{ r.comment }}</p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
