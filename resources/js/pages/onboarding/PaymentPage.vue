<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import QRCode from 'qrcode';
import { api, ApiError } from '../../api';

/**
 * byl.mn төлбөрийн хуудас (10b): QR + банкны аппууд + захиалгын тойм.
 * Төлөв 4 секунд тутам автоматаар шалгагдана; төлөгдмөгц амжилтын
 * хуудас руу шилжинэ. QR нь byl.mn-ийн нэхэмжлэхийн хуудас руу хөтөлнө.
 */
const route = useRoute();
const router = useRouter();

const order = ref(null);
const loadError = ref('');
const qrCanvas = ref(null);
const checking = ref(false);
const canceling = ref(false);
const justChecked = ref(false);
const copied = ref(false);
const elapsed = ref(0);
let pollTimer = null;
let clockTimer = null;

const banks = [
    ['Хаанбанк', '#1f5fa8'], ['Голомт банк', '#16181c'], ['Хас банк', '#1a8a5a'], ['ХХБанк', '#3d454e'],
    ['Ариг банк', '#a8621d'], ['Төрийн банк', '#2f5f8a'], ['Most Money', '#c04a30'], ['Monpay', '#6b4ea8'],
];

const steps = [
    'Банкны апп нээж, QR уншуулах хэсгийг сонгоно (төлбөрийг byl.mn боловсруулна).',
    'QR-ыг уншуулж, дүн зөв болохыг шалгаад баталгаажуулна.',
    'Батлах дарсны дараа энэ хуудас автоматаар «Төлөгдсөн» болно.',
];

const fmt = (n) => '₮' + Number(n).toLocaleString();

const isPending = computed(() => order.value?.status === 'pending');
const isClosed = computed(() => ['void', 'expired'].includes(order.value?.status));

const elapsedLabel = computed(() => {
    const m = String(Math.floor(elapsed.value / 60)).padStart(2, '0');
    const s = String(elapsed.value % 60).padStart(2, '0');
    return `${m}:${s}`;
});

const statusChip = computed(() => ({
    pending: { text: 'Хүлээгдэж байна', cls: 'border-amberline bg-ambertint text-amber', dot: 'bg-amberdot' },
    void: { text: 'Цуцлагдсан', cls: 'border-line bg-panel text-mute', dot: 'bg-inputline' },
    expired: { text: 'Хугацаа дууссан', cls: 'border-line bg-panel text-mute', dot: 'bg-inputline' },
    paid: { text: 'Төлөгдсөн', cls: 'border-greenline bg-greentint text-green', dot: 'bg-green' },
}[order.value?.status] || { text: '…', cls: 'border-line bg-panel text-mute', dot: 'bg-inputline' }));

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

        if (isClosed.value) stop();

        await nextTick();
        if (order.value.invoice_url && qrCanvas.value) {
            QRCode.toCanvas(qrCanvas.value, order.value.invoice_url, { width: 188, margin: 0, color: { dark: '#16181c' } });
        }
    } catch (e) {
        if (e instanceof ApiError && (e.status === 404 || e.status === 403)) {
            stop();
            loadError.value = 'Захиалга олдсонгүй эсвэл таных биш байна.';
        }
        // сүлжээний түр алдаа — дараагийн poll дахин оролдоно
    } finally {
        if (manual) {
            checking.value = false;
            justChecked.value = true;
            setTimeout(() => (justChecked.value = false), 2000);
        }
    }
}

async function cancelOrder() {
    if (!confirm('Энэ захиалгыг цуцлах уу? Нэхэмжлэх хүчингүй болно.')) return;
    canceling.value = true;
    try {
        const data = await api.delete(`/orders/${order.value.id}`);
        order.value = data.data;
        stop();
    } catch (e) {
        // цуцлах явцад төлөгдчихсөн байж болно — төлөвөө дахин татна
        await fetchOrder();
    } finally {
        canceling.value = false;
    }
}

function copyLink() {
    if (!order.value?.invoice_url) return;
    navigator.clipboard?.writeText(order.value.invoice_url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function stop() {
    clearInterval(pollTimer);
    clearInterval(clockTimer);
}

onMounted(() => {
    fetchOrder();
    pollTimer = setInterval(fetchOrder, 4000);
    clockTimer = setInterval(() => elapsed.value++, 1000);
});

onBeforeUnmount(stop);
</script>

<template>
    <div class="min-h-screen bg-page">
        <!-- Толгой (10b) -->
        <div class="flex items-center justify-between border-b border-line bg-white px-5 py-3.5 sm:px-8">
            <div class="flex items-center gap-2.5">
                <span class="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] bg-brand text-[13px] font-extrabold text-white">Х</span>
                <span class="text-[15px] font-extrabold text-ink">Хаана<span class="text-brand">.mn</span> <span class="text-[12.5px] font-medium text-mute">· Төлбөр</span></span>
            </div>
            <div class="flex items-center gap-3 text-[12.5px]">
                <span v-if="order" class="hidden font-medium text-mute sm:inline">Захиалга <span class="font-mono text-soft">{{ order.number }}</span></span>
                <button
                    v-if="isPending"
                    class="cursor-pointer font-semibold text-soft hover:text-red"
                    :disabled="canceling"
                    @click="cancelOrder"
                >{{ canceling ? 'Цуцалж байна…' : 'Цуцлах' }}</button>
                <router-link v-else :to="{ name: 'console-invoices' }" class="font-semibold text-soft">Хаах</router-link>
            </div>
        </div>

        <!-- Алдаа -->
        <div v-if="loadError" class="mx-auto max-w-md px-5 py-20 text-center">
            <p class="text-[15px] font-bold text-ink">{{ loadError }}</p>
            <router-link :to="{ name: 'console-invoices' }" class="btn-primary mt-5">Нэхэмжлэхүүд рүү буцах</router-link>
        </div>

        <!-- Ачаалж байна -->
        <div v-else-if="!order" class="mx-auto grid max-w-5xl grid-cols-1 gap-4 p-6 lg:grid-cols-[1fr_320px]">
            <div class="card h-[420px] animate-pulse bg-white"></div>
            <div class="card h-[420px] animate-pulse bg-white"></div>
        </div>

        <!-- Цуцлагдсан / хугацаа дууссан -->
        <div v-else-if="isClosed" class="mx-auto max-w-md px-5 py-16">
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

        <!-- Төлбөр хүлээгдэж буй (10b) -->
        <div v-else class="mx-auto grid max-w-5xl grid-cols-1 lg:grid-cols-[1fr_320px]">
            <!-- Зүүн тал -->
            <div class="border-line px-5 py-7 sm:px-8 lg:border-r">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-[22px] font-extrabold tracking-[-.02em] text-ink">byl.mn-ээр төлөх</h1>
                    <div class="ml-auto flex items-center gap-2 rounded-lg border px-3 py-2" :class="statusChip.cls">
                        <span class="relative flex h-[7px] w-[7px]">
                            <span v-if="isPending" class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-60" :class="statusChip.dot"></span>
                            <span class="relative inline-flex h-[7px] w-[7px] rounded-full" :class="statusChip.dot"></span>
                        </span>
                        <span class="text-[11.5px] font-semibold">{{ statusChip.text }}</span>
                        <span class="font-mono text-[11.5px] font-semibold text-ambertext">{{ elapsedLabel }}</span>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-6 sm:flex-row">
                    <!-- QR -->
                    <div class="w-[212px] shrink-0">
                        <div class="flex h-[212px] w-[212px] items-center justify-center rounded-xl border border-line bg-white p-3">
                            <canvas v-if="order.invoice_url" ref="qrCanvas"></canvas>
                            <div v-else class="px-4 text-center text-[12px] font-medium leading-relaxed text-mute">
                                Туршилтын (dev) горим — byl.mn-ийн түлхүүр тохируулаагүй тул QR үүсэхгүй.
                                «Төлбөрөө шалгах» дарахад төлөв шинэчлэгдэнэ.
                            </div>
                        </div>
                        <div class="mt-2.5 text-center text-[11.5px] leading-normal text-mute">Банкны аппаараа QR уншуулна</div>
                        <div v-if="order.invoice_url" class="mt-2 flex items-center justify-center gap-3 text-[12px] font-semibold">
                            <a :href="order.invoice_url" target="_blank" rel="noopener" class="text-brand">byl.mn дээр нээх</a>
                            <span class="text-[#c9ccd1]">·</span>
                            <button class="cursor-pointer text-brand" @click="copyLink">{{ copied ? '✓ Хуулагдлаа' : 'Линк хуулах' }}</button>
                        </div>
                    </div>

                    <!-- Банкууд -->
                    <div class="min-w-0 flex-1">
                        <div class="text-[13.5px] font-bold text-ink">Эсвэл банкны аппаа сонгоно</div>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <component
                                :is="order.invoice_url ? 'a' : 'div'"
                                v-for="[name, color] in banks"
                                :key="name"
                                :href="order.invoice_url || undefined"
                                target="_blank" rel="noopener"
                                class="flex items-center gap-2.5 rounded-[9px] border border-line bg-white px-3 py-2.5"
                                :class="order.invoice_url ? 'transition hover:border-blueline hover:bg-bluepale' : 'opacity-50'"
                            >
                                <span class="h-[26px] w-[26px] shrink-0 rounded-[7px]" :style="{ background: color }"></span>
                                <span class="text-[12px] font-semibold text-ink">{{ name }}</span>
                                <span class="ml-auto text-[11px] font-semibold text-brand">→</span>
                            </component>
                        </div>

                        <div class="mt-3.5 flex items-center gap-3 rounded-[10px] border border-line bg-white p-3.5">
                            <span class="h-[18px] w-[18px] shrink-0 animate-spin rounded-full border-2 border-brand border-t-transparent"></span>
                            <div>
                                <div class="text-[12.5px] font-bold text-ink">Төлбөрийг хүлээж байна…</div>
                                <div class="mt-0.5 text-[11.5px] text-mute">Төлсний дараа энэ хуудас автоматаар шинэчлэгдэнэ</div>
                            </div>
                            <button
                                class="ml-auto shrink-0 cursor-pointer rounded-lg border border-blueline bg-bluetint px-3 py-2 text-[11.5px] font-bold text-brand transition hover:bg-blueline/40 disabled:opacity-60"
                                :disabled="checking"
                                @click="fetchOrder({ manual: true })"
                            >{{ checking ? 'Шалгаж байна…' : justChecked ? '✓ Шалгалаа' : 'Төлбөрөө шалгах' }}</button>
                        </div>
                    </div>
                </div>

                <!-- Хэрхэн төлөх -->
                <div class="mt-6 border-t border-divider pt-4">
                    <div class="text-[13px] font-bold text-ink">Хэрхэн төлөх</div>
                    <div class="mt-2.5 flex flex-col gap-2.5">
                        <div v-for="(s, i) in steps" :key="i" class="flex gap-2.5">
                            <span class="mt-px flex h-[17px] w-[17px] shrink-0 items-center justify-center rounded-full border border-blueline bg-bluetint text-[9.5px] font-bold text-brand">{{ i + 1 }}</span>
                            <span class="text-[12.5px] leading-relaxed text-body">{{ s }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Захиалгын тойм -->
            <div class="bg-white px-5 py-7 sm:px-7">
                <div class="kicker">ЗАХИАЛГА <span class="font-mono normal-case tracking-normal text-soft">{{ order.number }}</span></div>
                <div class="mt-3.5 flex flex-col gap-3">
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
                <div class="my-4 h-px bg-divider"></div>
                <div class="flex items-baseline justify-between">
                    <span class="text-[13px] font-bold text-ink">Нийт</span>
                    <span class="text-[22px] font-extrabold tracking-[-.02em] text-ink">{{ fmt(order.total) }}</span>
                </div>
                <div class="mt-1.5 text-[11.5px] font-medium text-mute">НӨАТ орсон · и-баримт автоматаар</div>

                <div class="mt-4 rounded-[11px] border border-blueline bg-bluetint p-3.5">
                    <div class="text-[12.5px] font-bold text-ink">Төлбөр батлагдмагц</div>
                    <div class="mt-1.5 text-[12px] leading-relaxed text-body">Эрх шууд, онцлох байршил 10 минутын дотор нээгдэнэ. И-баримт SMS-ээр илгээгдэнэ.</div>
                </div>

                <button
                    class="mt-4 w-full cursor-pointer rounded-[10px] border border-inputline bg-white py-2.5 text-[12.5px] font-bold text-soft transition hover:border-redline hover:bg-redtint hover:text-red disabled:opacity-60"
                    :disabled="canceling"
                    @click="cancelOrder"
                >{{ canceling ? 'Цуцалж байна…' : 'Захиалга цуцлах' }}</button>
                <div class="mt-3.5 text-center text-[11.5px] leading-relaxed text-mute">Асуудал гарвал 7011 1414 · тусламжийн чат.</div>
            </div>
        </div>
    </div>
</template>
