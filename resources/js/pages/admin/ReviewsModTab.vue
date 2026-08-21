<script setup>
import { onMounted, ref } from 'vue';
import { shortDate } from '../../utils/date';
import { api } from '../../api';
import PanelPageHeader from '../../components/panel/PanelPageHeader.vue';
import PanelBadge from '../../components/panel/PanelBadge.vue';

// Гомдолтой сэтгэгдэл + хэрэглэгчдийн залруулгын модерац
const reviews = ref(null);
const corrections = ref(null);
const reviewsTotal = ref(0);
const loadError = ref('');
const busyId = ref(null);

async function fetchAll() {
    loadError.value = '';
    try {
        const [r, c] = await Promise.all([api.get('/admin/reviews'), api.get('/admin/corrections')]);
        reviews.value = r.data;
        // Гомдлын тоог meta-аас — жагсаалт нь 20-оор хуудаслагддаг
        reviewsTotal.value = r.meta?.total ?? r.data.length;
        corrections.value = c.data;
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    }
}

async function moderateReview(review, action) {
    busyId.value = `r${review.id}`;
    try {
        await api.post(`/admin/reviews/${review.id}/moderate`, { action });
        await fetchAll();
    } catch {
        alert('Алдаа гарлаа, дахин оролдоно уу.');
    } finally {
        busyId.value = null;
    }
}

async function moderateCorrection(correction, action) {
    busyId.value = `c${correction.id}`;
    try {
        await api.post(`/admin/corrections/${correction.id}/moderate`, { action });
        await fetchAll();
    } catch {
        alert('Алдаа гарлаа, дахин оролдоно уу.');
    } finally {
        busyId.value = null;
    }
}

onMounted(fetchAll);
</script>

<template>
    <div class="p-5 sm:p-7">
        <PanelPageHeader
            title="Сэтгэгдэл ба залруулга"
            description="Гомдол ирсэн сэтгэгдлийг сэргээх эсвэл нуух, хэрэглэгчдээс ирсэн бүртгэлийн залруулгын хүсэлтийг хүлээн авах эсвэл татгалзах модерацын хуудас."
        />

        <div v-if="loadError" class="card p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="fetchAll">Дахин оролдох</button>
        </div>

        <div v-else-if="!reviews" class="card h-64 animate-pulse"></div>

        <template v-else>
            <!-- Гомдолтой сэтгэгдлүүд -->
            <div class="card overflow-hidden">
                <div class="flex items-center gap-2 border-b border-divider px-4 py-3.5">
                    <span class="text-[14px] font-bold text-ink">Гомдолтой сэтгэгдэл</span>
                    <PanelBadge tone="bad" mono>{{ reviewsTotal }}</PanelBadge>
                </div>
                <div v-for="r in reviews" :key="r.id" class="border-b border-hairline px-4 py-3 last:border-0">
                    <div class="flex flex-wrap items-center gap-2 text-[12.5px]">
                        <span class="font-bold text-ink">{{ r.user?.name }}</span>
                        <span class="text-amberdot">{{ '★'.repeat(Math.round(r.rating)) }}</span>
                        <span class="text-mute">→ {{ r.branch?.business_name }} — {{ r.branch?.name }}</span>
                    </div>
                    <p class="mt-1.5 text-[13px] leading-relaxed text-body">{{ r.comment || '(сэтгэгдэлгүй, зөвхөн үнэлгээ)' }}</p>
                    <div class="mt-2 flex gap-1.5 text-[11.5px] font-semibold">
                        <button class="cursor-pointer rounded-[7px] bg-brand px-2.5 py-1.5 text-white disabled:opacity-50" :disabled="busyId === `r${r.id}`" @click="moderateReview(r, 'restore')">Сэргээх</button>
                        <button class="cursor-pointer rounded-[7px] border border-inputline bg-white px-2.5 py-1.5 text-red disabled:opacity-50" :disabled="busyId === `r${r.id}`" @click="moderateReview(r, 'hide')">Нуух</button>
                    </div>
                </div>
                <div v-if="!reviews.length" class="p-10 text-center text-[13px] text-mute">Гомдолтой сэтгэгдэл алга 🎉</div>
            </div>

            <!-- Залруулгын хүсэлтүүд -->
            <div class="card mt-4 overflow-hidden">
                <div class="flex items-center gap-2 border-b border-divider px-4 py-3.5">
                    <span class="text-[14px] font-bold text-ink">Залруулгын хүсэлт</span>
                    <PanelBadge tone="warn" mono>{{ corrections?.length || 0 }}</PanelBadge>
                </div>
                <div v-for="c in corrections" :key="c.id" class="border-b border-hairline px-4 py-3 last:border-0">
                    <div class="flex flex-wrap items-center gap-2 text-[12.5px]">
                        <span class="font-bold text-ink">{{ c.branch }}</span>
                        <span class="text-mute">· {{ c.user }} · {{ shortDate(c.created_at) }}</span>
                    </div>
                    <p class="mt-1.5 text-[13px] leading-relaxed text-body">{{ c.text }}</p>
                    <div class="mt-2 flex items-center gap-1.5 text-[11.5px] font-semibold">
                        <button class="cursor-pointer rounded-[7px] bg-brand px-2.5 py-1.5 text-white disabled:opacity-50" :disabled="busyId === `c${c.id}`" @click="moderateCorrection(c, 'accept')">Хүлээн авах</button>
                        <button class="cursor-pointer rounded-[7px] border border-inputline bg-white px-2.5 py-1.5 text-ink disabled:opacity-50" :disabled="busyId === `c${c.id}`" @click="moderateCorrection(c, 'reject')">Татгалзах</button>
                        <span class="ml-2 text-[11px] text-mute">Хүлээн авбал бүртгэлийг гараар засна</span>
                    </div>
                </div>
                <div v-if="!corrections?.length" class="p-10 text-center text-[13px] text-mute">Залруулгын хүсэлт алга</div>
            </div>
        </template>
    </div>
</template>
