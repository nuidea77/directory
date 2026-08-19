<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../../api';

/**
 * Төлбөрийн шилжүүлэгч хуудас: захиалга үүссэн бол ШУУД byl.mn-ийн
 * төлбөрийн хуудас руу үсэрнэ (QR, банкны сонголтууд byl.mn дээрээ бий).
 * byl.mn-ээс буцаж ирэхэд төлөв автоматаар шалгагдаж, төлөгдсөн бол
 * амжилтын хуудас руу шилжинэ.
 */
const route = useRoute();
const router = useRouter();

const order = ref(null);
const loadError = ref('');
// byl.mn-ээс буцаж ирсэн үед (?return=success|cancel) дахин тийш нь үсэргэхгүй
const returnState = ref(route.query.return || '');
const redirected = ref(!!returnState.value);
const checking = ref(false);
const canceling = ref(false);
let pollTimer = null;

const fmt = (n) => '₮' + Number(n).toLocaleString();

const isPending = computed(() => order.value?.status === 'pending');
const isClosed = computed(() => ['void', 'expired'].includes(order.value?.status));

async function fetchOrder({ manual = false } = {}) {
    if (manual) checking.value = true;
    try {
        const data = await api.get(`/orders/${route.params.id}`);
        order.value = data.data;
        loadError.value = '';

        if (order.value.status === 'paid') {
            stop();
            router.push({ name: 'order-success', params: { id: order.value.id } });
            return;
        }

        if (isClosed.value) {
            stop();
            return;
        }

        // Хүлээгдэж буй + нэхэмжлэхтэй → шууд byl.mn руу үсэрнэ (нэг л удаа)
        if (isPending.value && order.value.invoice_url && !redirected.value) {
            redirected.value = true;
            window.location.href = order.value.invoice_url;
        }
    } catch (e) {
        if (e instanceof ApiError && (e.status === 404 || e.status === 403)) {
            stop();
            loadError.value = 'Захиалга олдсонгүй эсвэл таных биш байна.';
        }
    } finally {
        if (manual) checking.value = false;
    }
}

async function cancelOrder() {
    if (!confirm('Энэ захиалгыг цуцлах уу? Нэхэмжлэх хүчингүй болно.')) return;
    canceling.value = true;
    try {
        const data = await api.delete(`/orders/${order.value.id}`);
        order.value = data.data;
        stop();
    } catch {
        await fetchOrder();
    } finally {
        canceling.value = false;
    }
}

function stop() {
    clearInterval(pollTimer);
}

onMounted(() => {
    fetchOrder();
    // byl.mn-ээс буцаж ирэх юм уу өөр таб дээр төлөхөд төлөв автоматаар шинэчлэгдэнэ
    pollTimer = setInterval(fetchOrder, 4000);
});

onBeforeUnmount(stop);
</script>

<template>
    <div class="min-h-screen bg-page">
        <!-- Толгой -->
        <div class="flex items-center justify-between border-b border-line bg-white px-5 py-3.5 sm:px-8">
            <div class="flex items-center gap-2.5">
                <span class="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] bg-brand text-[13px] font-extrabold text-white">Х</span>
                <span class="text-[15px] font-extrabold text-ink">Хаана<span class="text-brand">.mn</span> <span class="text-[12.5px] font-medium text-mute">· Төлбөр</span></span>
            </div>
            <span v-if="order" class="font-mono text-[12.5px] font-medium text-soft">{{ order.number }}</span>
        </div>

        <!-- Алдаа -->
        <div v-if="loadError" class="mx-auto max-w-md px-5 py-20 text-center">
            <p class="text-[15px] font-bold text-ink">{{ loadError }}</p>
            <router-link :to="{ name: 'console-invoices' }" class="btn-primary mt-5">Нэхэмжлэхүүд рүү буцах</router-link>
        </div>

        <!-- Цуцлагдсан / хугацаа дууссан -->
        <div v-else-if="order && isClosed" class="mx-auto max-w-md px-5 py-16">
            <div class="card bg-white p-8 text-center">
                <div class="mx-auto flex h-[52px] w-[52px] items-center justify-center rounded-full bg-chip text-2xl text-mute">✕</div>
                <h1 class="mt-4 text-[22px] font-extrabold tracking-[-.02em] text-ink">Захиалга {{ order.status === 'void' ? 'цуцлагдлаа' : 'хугацаа нь дууслаа' }}</h1>
                <p class="mt-2 text-[13px] leading-relaxed text-soft">
                    <span class="font-mono">{{ order.number }}</span> · {{ fmt(order.total) }} — төлбөр хийгдээгүй тул эрх, онцлох байршил идэвхжээгүй.
                </p>
                <div class="mt-5 flex flex-col gap-2">
                    <router-link :to="{ name: 'console-plan' }" class="btn-primary w-full">Дахин захиалах</router-link>
                    <router-link :to="{ name: 'console' }" class="btn-outline w-full">Бизнес зөвлөл рүү</router-link>
                </div>
            </div>
        </div>

        <!-- Шилжүүлж байна / төлбөр хүлээж байна -->
        <div v-else class="mx-auto max-w-md px-5 py-16">
            <div class="card bg-white p-8 text-center">
                <div class="mx-auto flex h-[52px] w-[52px] items-center justify-center rounded-full border border-blueline bg-bluetint">
                    <span class="h-6 w-6 animate-spin rounded-full border-[3px] border-brand border-t-transparent"></span>
                </div>

                <template v-if="!order">
                    <h1 class="mt-4 text-[20px] font-extrabold tracking-[-.02em] text-ink">Захиалга ачаалж байна…</h1>
                </template>

                <template v-else-if="returnState === 'success'">
                    <h1 class="mt-4 text-[20px] font-extrabold tracking-[-.02em] text-ink">Төлбөрийг баталгаажуулж байна…</h1>
                    <p class="mt-2 text-[13px] leading-relaxed text-soft">
                        Та byl.mn-ээс буцаж ирлээ. Төлбөр батлагдмагц амжилтын хуудас руу автоматаар шилжинэ — хэдхэн секунд хүлээнэ үү.
                    </p>
                </template>

                <template v-else-if="returnState === 'cancel'">
                    <h1 class="mt-4 text-[20px] font-extrabold tracking-[-.02em] text-ink">Төлбөр хийгдээгүй байна</h1>
                    <p class="mt-2 text-[13px] leading-relaxed text-soft">
                        Та төлбөрөө дуусгалгүй буцаж ирсэн байна. Захиалга хадгалагдсан — хүссэн үедээ дахин төлж болно.
                    </p>
                    <a v-if="order.invoice_url" :href="order.invoice_url" class="btn-primary mt-5 w-full !py-3.5">{{ fmt(order.total) }} төлөх — byl.mn</a>
                </template>

                <template v-else-if="order.invoice_url">
                    <h1 class="mt-4 text-[20px] font-extrabold tracking-[-.02em] text-ink">byl.mn руу шилжүүлж байна…</h1>
                    <p class="mt-2 text-[13px] leading-relaxed text-soft">
                        Төлбөрийг byl.mn-ийн найдвартай хуудсаар (QR, банкны апп, карт) хийнэ.
                        Төлсний дараа автоматаар энэ сайт руу буцаж ирнэ.
                        Автоматаар шилжихгүй бол доорх товчийг дарна уу.
                    </p>
                    <a :href="order.invoice_url" class="btn-primary mt-5 w-full !py-3.5">{{ fmt(order.total) }} төлөх — byl.mn</a>
                    <p class="mt-3 text-[11.5px] leading-relaxed text-mute">Төлбөр батлагдмагц эрх шууд, онцлох байршил 10 минутын дотор нээгдэнэ.</p>
                </template>

                <template v-else>
                    <h1 class="mt-4 text-[20px] font-extrabold tracking-[-.02em] text-ink">Нэхэмжлэх бэлдэж байна…</h1>
                    <p class="mt-2 text-[13px] leading-relaxed text-soft">
                        byl.mn-ийн нэхэмжлэх үүсээгүй байна. Хэрэв энэ нь туршилтын (dev) орчин бол
                        <code class="rounded bg-chip px-1.5 py-0.5 font-mono text-[11.5px]">BYL_API_TOKEN</code> тохируулаагүй тул
                        төлбөр автоматаар батлагдана — «Төлбөрөө шалгах» дарна уу.
                    </p>
                    <button class="btn-primary mt-5 w-full" :disabled="checking" @click="fetchOrder({ manual: true })">
                        {{ checking ? 'Шалгаж байна…' : 'Төлбөрөө шалгах' }}
                    </button>
                </template>

                <div v-if="order && isPending" class="mt-4 border-t border-divider pt-4">
                    <div class="flex items-center justify-center gap-4 text-[12.5px] font-semibold">
                        <button class="cursor-pointer text-brand disabled:opacity-60" :disabled="checking" @click="fetchOrder({ manual: true })">
                            {{ checking ? 'Шалгаж байна…' : 'Төлсөн, шалгах' }}
                        </button>
                        <span class="text-[#c9ccd1]">·</span>
                        <button class="cursor-pointer text-soft hover:text-red disabled:opacity-60" :disabled="canceling" @click="cancelOrder">
                            {{ canceling ? 'Цуцалж байна…' : 'Захиалга цуцлах' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Захиалгын тойм -->
            <div v-if="order?.items?.length" class="card mt-3.5 bg-white p-5">
                <div class="kicker">ЗАХИАЛГА</div>
                <div class="mt-3 flex flex-col gap-2.5">
                    <div v-for="item in order.items" :key="item.id" class="flex items-start gap-2.5">
                        <div class="min-w-0">
                            <div class="text-[12.5px] font-semibold text-ink">{{ item.name }}</div>
                            <div class="mt-0.5 text-[11px] text-mute">{{ item.meta }}</div>
                        </div>
                        <div class="ml-auto whitespace-nowrap text-right">
                            <div class="text-[12.5px] font-semibold text-ink">{{ fmt(item.amount) }}</div>
                            <div v-if="item.discount" class="text-[11px] font-semibold text-green">−{{ fmt(item.discount) }}</div>
                        </div>
                    </div>
                </div>
                <div class="my-3.5 h-px bg-divider"></div>
                <div class="flex items-baseline justify-between">
                    <span class="text-[13px] font-bold text-ink">Нийт (НӨАТ орсон)</span>
                    <span class="text-[20px] font-extrabold tracking-[-.02em] text-ink">{{ fmt(order.total) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
