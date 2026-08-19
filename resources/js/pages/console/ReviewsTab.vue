<script setup>
import { onMounted, ref, watch } from 'vue';
import { api } from '../../api';
import { useConsoleStore } from '../../stores/console';

// Сэтгэгдэл (3c): жагсаалт + хариу бичих
const store = useConsoleStore();
const reviews = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const unrepliedOnly = ref(false);
const replyingId = ref(null);
const replyText = ref('');
const busy = ref(false);
const loading = ref(true);
const loadError = ref('');

async function fetchReviews(page = 1) {
    if (!store.organization) return;
    loading.value = true;
    loadError.value = '';
    try {
        const data = await api.get(`/console/organizations/${store.organization.id}/reviews`, {
            unreplied: unrepliedOnly.value ? 1 : undefined,
            page: page > 1 ? page : undefined,
        });
        reviews.value = data.data;
        // Хуудаслалт: өмнө нь зөвхөн эхний 20 сэтгэгдэл харагдаж, үлдсэнд нь
        // хүрэх арга байхгүй байсан
        meta.value = data.meta || { current_page: 1, last_page: 1, total: data.data.length };
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    } finally {
        loading.value = false;
    }
}

async function submitReply(review) {
    busy.value = true;
    try {
        const data = await api.post(`/console/reviews/${review.id}/reply`, { reply: replyText.value });
        Object.assign(review, data.data ?? data);
        replyingId.value = null;
        replyText.value = '';
    } finally {
        busy.value = false;
    }
}

// Дугаартай аргумент дамжуулахгүй — watch/onMounted нь өөрийн утгаа
// page параметрт өгвөл буруу хуудас дуудагдана
watch(() => store.organization?.id, () => fetchReviews());
onMounted(() => fetchReviews());
</script>

<template>
    <div class="p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-extrabold tracking-[-.02em] text-ink">Сэтгэгдэл</h1>
            <button class="chip" :class="{ 'chip-active': unrepliedOnly }" @click="unrepliedOnly = !unrepliedOnly; fetchReviews()">Хариугүй</button>
        </div>

        <div v-if="loadError" class="card mt-4 p-10 text-center">
            <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
            <button class="btn-primary mt-4" @click="fetchReviews()">Дахин оролдох</button>
        </div>

        <div v-else-if="loading" class="card mt-4 h-40 animate-pulse"></div>

        <div v-else class="mt-4 flex flex-col gap-2.5">
            <div v-for="review in reviews" :key="review.id" class="rounded-[11px] border p-4" :class="review.reply ? 'border-line bg-white' : 'border-blueline bg-bluepale'">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-[30px] w-[30px] items-center justify-center rounded-full border border-blueline bg-bluetint text-[12px] font-bold text-brand">{{ review.user?.name?.charAt(0) }}</span>
                    <span class="text-[13px] font-bold text-ink">{{ review.user?.name }}</span>
                    <span class="text-[11.5px] font-medium text-mute">{{ review.branch?.name }} · {{ new Date(review.created_at).toLocaleDateString() }}</span>
                    <span class="ml-auto text-[12.5px] font-bold text-ink">★ {{ review.rating }}.0</span>
                </div>
                <p class="mt-2.5 text-[13px] leading-[1.65] text-body">{{ review.comment }}</p>

                <div v-if="review.reply" class="mt-2.5 rounded-lg bg-greentint px-3 py-2 text-[12px] font-medium leading-relaxed text-green">
                    Таны хариу: {{ review.reply }}
                </div>
                <template v-else>
                    <div v-if="replyingId === review.id" class="mt-3">
                        <textarea v-model="replyText" rows="2" placeholder="Хариугаа бичнэ үү..." class="input resize-none"></textarea>
                        <div class="mt-2 flex gap-2">
                            <button class="btn-primary !px-3.5 !py-2 !text-[12px]" :disabled="busy || !replyText.trim()" @click="submitReply(review)">Илгээх</button>
                            <button class="btn-outline !px-3.5 !py-2 !text-[12px]" @click="replyingId = null">Болих</button>
                        </div>
                    </div>
                    <div v-else class="mt-3 flex gap-2 text-[12px] font-semibold">
                        <button class="cursor-pointer rounded-lg bg-brand px-3.5 py-2 text-white" @click="replyingId = review.id; replyText = ''">Хариу бичих</button>
                    </div>
                </template>
            </div>

            <div v-if="!reviews.length" class="card p-12 text-center text-[13px] text-mute">Одоогоор сэтгэгдэл алга</div>

            <div v-if="meta.last_page > 1" class="mt-4 flex items-center justify-between">
                <div class="flex gap-1.5 text-[12.5px] font-semibold">
                    <button class="cursor-pointer rounded-lg border border-inputline px-3 py-2 text-mute disabled:opacity-40" :disabled="meta.current_page <= 1" @click="fetchReviews(meta.current_page - 1)">←</button>
                    <button class="cursor-pointer rounded-lg border border-inputline px-3 py-2 text-ink disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="fetchReviews(meta.current_page + 1)">→</button>
                </div>
                <div class="text-[12.5px] font-medium text-mute">{{ meta.current_page }} / {{ meta.last_page }} хуудас · нийт {{ meta.total }} сэтгэгдэл</div>
            </div>
        </div>
    </div>
</template>
