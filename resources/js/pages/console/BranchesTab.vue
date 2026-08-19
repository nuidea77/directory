<script setup>
import { computed } from 'vue';
import { useConsoleStore } from '../../stores/console';
import ImagePh from '../../components/ImagePh.vue';

// Миний салбарууд (4b): салбар бүр үзэлт/залгалт/үнэлгээ/бүрэн байдалтай
const store = useConsoleStore();

const statusLabel = {
    active: ['НЭЭЛТТЭЙ', 'bg-greentint text-green'],
    pending: ['ХЯНАЛТАД', 'bg-amberbadge text-amber'],
    draft: ['НООРОГ', 'bg-chip text-chiptext'],
    rejected: ['ТАТГАЛЗСАН', 'bg-redtint text-red'],
    hidden: ['ТҮР ХААЛТТАЙ', 'bg-chip text-chiptext'],
};

const totals = computed(() => ({
    views: store.branches.reduce((s, b) => s + (b.views_count || 0), 0),
    calls: store.branches.reduce((s, b) => s + (b.calls_count || 0), 0),
    directions: store.branches.reduce((s, b) => s + (b.directions_count || 0), 0),
}));
</script>

<template>
    <div class="p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-extrabold tracking-[-.02em] text-ink">Салбарууд</h1>
                <p class="mt-1 text-[12.5px] text-mute">Бүртгэл бүр тусад нь эрэмбэлэгддэг</p>
            </div>
            <router-link :to="{ name: 'add-business' }" class="btn-primary !px-4 !py-2.5 !text-[12.5px]">Салбар нэмэх</router-link>
        </div>

        <!-- KPI (4b) -->
        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="k in [['Бүртгэл үзсэн', totals.views], ['Залгасан', totals.calls], ['Зам заалт', totals.directions], ['Салбарууд', store.branches.length]]" :key="k[0]" class="card p-4">
                <div class="text-[11px] font-semibold text-mute">{{ k[0] }}</div>
                <div class="mt-2 text-2xl font-extrabold tracking-[-.02em] text-ink">{{ Number(k[1]).toLocaleString() }}</div>
            </div>
        </div>

        <!-- Салбарын мөрүүд -->
        <div class="card mt-4 overflow-hidden">
            <div class="flex items-center border-b border-divider px-4 py-3.5">
                <span class="text-[14px] font-bold text-ink">Миний салбарууд</span>
                <span class="ml-auto hidden text-[12px] font-medium text-mute sm:block">Хаяг, нэр өөрчлөхөд редакцын хяналт шаардана</span>
            </div>
            <div v-for="branch in store.branches" :key="branch.id" class="flex flex-wrap items-center gap-4 border-b border-hairline px-4 py-4 last:border-0">
                <div class="hidden h-[62px] w-[84px] shrink-0 overflow-hidden rounded-lg sm:block">
                    <ImagePh :src="branch.cover_url" />
                </div>
                <div class="w-full min-w-0 sm:w-[238px]">
                    <div class="flex items-center gap-2">
                        <span class="truncate text-[14px] font-bold text-ink">{{ branch.business.name }} — {{ branch.district }}</span>
                        <span class="rounded-[4px] px-1.5 py-0.5 text-[9.5px] font-semibold" :class="statusLabel[branch.status]?.[1]">{{ statusLabel[branch.status]?.[0] }}</span>
                    </div>
                    <div class="mt-1 truncate text-[11.5px] text-mute">{{ branch.address }}</div>
                </div>
                <div class="w-20"><div class="text-[10.5px] font-semibold text-mute">ҮЗСЭН</div><div class="mt-1 text-[15px] font-bold text-ink">{{ (branch.views_count || 0).toLocaleString() }}</div></div>
                <div class="w-20"><div class="text-[10.5px] font-semibold text-mute">ЗАЛГАСАН</div><div class="mt-1 text-[15px] font-bold text-ink">{{ (branch.calls_count || 0).toLocaleString() }}</div></div>
                <div class="w-20"><div class="text-[10.5px] font-semibold text-mute">ҮНЭЛГЭЭ</div><div class="mt-1 text-[15px] font-bold text-ink">{{ branch.reviews_count ? branch.rating_avg.toFixed(1) : '—' }}</div></div>
                <div class="min-w-[120px] flex-1">
                    <div class="flex justify-between text-[10.5px] font-semibold text-mute"><span>БҮРЭН БАЙДАЛ</span><span>{{ branch.completeness }}%</span></div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-chip">
                        <div class="h-full rounded-full" :class="branch.completeness >= 80 ? 'bg-green' : branch.completeness >= 60 ? 'bg-brand' : 'bg-amberdot'" :style="{ width: branch.completeness + '%' }"></div>
                    </div>
                </div>
                <router-link :to="{ name: 'branch-edit', params: { id: branch.id } }" class="text-[12.5px] font-semibold text-brand">Засах</router-link>
            </div>
        </div>
    </div>
</template>
