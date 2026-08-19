<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import QRCode from 'qrcode';
import { api } from '../../api';

/**
 * byl.mn төлбөрийн хуудас (10b): QR + захиалгын тойм + автомат төлөв шалгалт.
 * QR нь byl.mn-ийн нэхэмжлэхийн хуудас руу хөтөлнө — банкны апп/утсаараа
 * уншуулж byl.mn дээр QPay, SocialPay, Pocket-оор төлнө.
 */
const route = useRoute();
const router = useRouter();

const order = ref(null);
const qrCanvas = ref(null);
let pollTimer = null;

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

async function fetchOrder() {
    const data = await api.get(`/orders/${route.params.id}`);
    order.value = data.data;

    if (order.value.status === 'paid') {
        clearInterval(pollTimer);
        router.push({ name: 'order-success', params: { id: order.value.id } });
        return;
    }

    if (order.value.status === 'void') {
        clearInterval(pollTimer);
    }

    await nextTick();
    if (order.value.invoice_url && qrCanvas.value) {
        QRCode.toCanvas(qrCanvas.value, order.value.invoice_url, { width: 188, margin: 0, color: { dark: '#16181c' } });
    }
}

onMounted(() => {
    fetchOrder();
    pollTimer = setInterval(fetchOrder, 4000);
});

onBeforeUnmount(() => clearInterval(pollTimer));
</script>

<template>
    <div class="min-h-screen bg-page">
        <!-- Толгой (10b) -->
        <div class="flex items-center justify-between border-b border-line bg-white px-5 py-3.5 sm:px-8">
            <div class="flex items-center gap-2.5">
                <span class="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] bg-brand text-[13px] font-extrabold text-white">Х</span>
                <span class="text-[15px] font-extrabold text-ink">Хаана<span class="text-brand">.mn</span> <span class="text-[12.5px] font-medium text-mute">· Төлбөр</span></span>
            </div>
            <div class="flex items-center gap-2.5 text-[12.5px]">
                <span class="font-medium text-mute">Захиалга <span class="font-mono text-soft">{{ order?.number }}</span></span>
                <router-link :to="{ name: 'console' }" class="font-semibold text-soft">Цуцлах</router-link>
            </div>
        </div>

        <div v-if="order" class="mx-auto grid max-w-5xl grid-cols-1 lg:grid-cols-[1fr_320px]">
            <!-- Зүүн тал -->
            <div class="border-line px-5 py-7 sm:px-8 lg:border-r">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-[22px] font-extrabold tracking-[-.02em] text-ink">byl.mn-ээр төлөх</h1>
                    <div class="ml-auto flex items-center gap-2 rounded-lg border border-amberline bg-ambertint px-3 py-2">
                        <span class="h-[7px] w-[7px] rounded-full bg-amberdot"></span>
                        <span class="text-[11.5px] font-semibold text-amber">{{ order.status === 'void' ? 'Цуцлагдсан' : 'Хүлээгдэж байна' }}</span>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-6 sm:flex-row">
                    <!-- QR -->
                    <div class="w-[212px] shrink-0">
                        <div class="flex h-[212px] w-[212px] items-center justify-center rounded-xl border border-line bg-white p-3">
                            <canvas v-if="order.invoice_url" ref="qrCanvas"></canvas>
                            <div v-else class="text-center text-[12px] font-medium text-mute">Нэхэмжлэх үүсээгүй байна</div>
                        </div>
                        <div class="mt-2.5 text-center text-[11.5px] leading-normal text-mute">Банкны аппаараа QR уншуулна</div>
                        <a v-if="order.invoice_url" :href="order.invoice_url" target="_blank" rel="noopener" class="mt-2 block text-center text-[12px] font-semibold text-brand">byl.mn дээр нээх</a>
                    </div>

                    <!-- Банкууд -->
                    <div class="min-w-0 flex-1">
                        <div class="text-[13.5px] font-bold text-ink">Эсвэл банкны аппаа сонгоно</div>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <a
                                v-for="[name, color] in banks"
                                :key="name"
                                :href="order.invoice_url || '#'"
                                target="_blank" rel="noopener"
                                class="flex items-center gap-2.5 rounded-[9px] border border-line bg-white px-3 py-2.5"
                            >
                                <span class="h-[26px] w-[26px] shrink-0 rounded-[7px]" :style="{ background: color }"></span>
                                <span class="text-[12px] font-semibold text-ink">{{ name }}</span>
                                <span class="ml-auto text-[11px] font-semibold text-brand">→</span>
                            </a>
                        </div>

                        <div class="mt-3.5 flex items-center gap-3 rounded-[10px] border border-line bg-white p-3.5">
                            <span class="h-[18px] w-[18px] shrink-0 animate-spin rounded-full border-2 border-brand border-t-transparent"></span>
                            <div>
                                <div class="text-[12.5px] font-bold text-ink">Төлбөрийг хүлээж байна…</div>
                                <div class="mt-0.5 text-[11.5px] text-mute">Төлсний дараа энэ хуудас автоматаар шинэчлэгдэнэ</div>
                            </div>
                        </div>
                        <button class="mt-3.5 cursor-pointer text-[12.5px] font-semibold text-brand" @click="fetchOrder">Төлбөрөө шалгах</button>
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
                <div class="kicker">ЗАХИАЛГА</div>
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
                <div class="mt-3.5 text-[11.5px] leading-relaxed text-mute">Асуудал гарвал 7011 1414 · тусламжийн чат.</div>
            </div>
        </div>
    </div>
</template>
