<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../../api';

// Гомдолтой сэтгэгдэл + хэрэглэгчдийн залруулгын модерац
const reviews = ref(null);
const corrections = ref(null);
const busyId = ref(null);

async function fetchAll() {
    const [r, c] = await Promise.all([api.get('/admin/reviews'), api.get('/admin/corrections')]);
    reviews.value = r.data;
    corrections.value = c.data;
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
        <h1 class="text-[15px] font-bold text-ink">Сэтгэгдэл ба залруулга</h1>

        <div v-if="!reviews" class="card mt-5 h-64 animate-pulse"></div>

        <template v-else>
            <!-- Гомдолтой сэтгэгдлүүд -->
            <div class="card mt-4 overflow-hidden">
                <div class="flex items-center gap-2 border-b border-divider px-4 py-3.5">
                    <span class="text-[14px] font-bold text-ink">Гомдолтой сэтгэгдэл</span>
                    <span class="rounded-full bg-redtint px-2 py-0.5 font-mono text-[10.5px] font-bold text-red">{{ reviews.length }}</span>
                </div>
                <div v-for="r in reviews" :key="r.id" class="border-b border-hairline px-4 py-3 last:border-0">
                    <div class="flex flex-wrap items-center gap-2 text-[12.5px]">
                        <span class="font-bold text-ink">{{ r.user?.name }}</span>
                        <span class="text-amberdot">{{ '★'.repeat(Math.round(r.rating)) }}</span>
                        <span class="text-mute">→ {{ r.branch?.business?.name }} — {{ r.branch?.name }}</span>
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
                    <span class="rounded-full bg-amberbadge px-2 py-0.5 font-mono text-[10.5px] font-bold text-amber">{{ corrections?.length || 0 }}</span>
                </div>
                <div v-for="c in corrections" :key="c.id" class="border-b border-hairline px-4 py-3 last:border-0">
                    <div class="flex flex-wrap items-center gap-2 text-[12.5px]">
                        <span class="font-bold text-ink">{{ c.branch }}</span>
                        <span class="text-mute">· {{ c.user }} · {{ new Date(c.created_at).toLocaleDateString() }}</span>
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
