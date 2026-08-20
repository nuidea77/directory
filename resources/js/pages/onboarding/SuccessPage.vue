<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { shortDate } from '../../utils/date';
import { api } from '../../api';

// Төлбөр амжилттай (10c): баримт + нээгдсэн эрх + дараагийн 3 үйлдэл
const route = useRoute();
const order = ref(null);
const organization = ref(null);
const loadError = ref('');
const pendingBranchesNote = ref('');

const fmt = (n) => '₮' + Number(n).toLocaleString();

const planItem = computed(() => order.value?.items?.find((i) => i.type === 'plan'));
const campaignItems = computed(() => order.value?.items?.filter((i) => i.type === 'campaign') || []);

const nextThree = [
    { n: '1', title: 'Зураг нэмэх', text: '3+ зурагтай бүртгэл 18% илүү үзэгддэг.', cta: 'Зураг нэмэх →', to: { name: 'console' } },
    { n: '2', title: 'Салбар нэмэх', text: 'Салбаруудаа нэг байгууллага дор нэмнэ.', cta: 'Салбар нэмэх →', to: { name: 'console' } },
    { n: '3', title: 'Статистикаа харах', text: 'Хандалт, залгалт, хайлтын үгсийг хянана.', cta: 'Статистик →', to: { name: 'console-stats' } },
];

/**
 * Бүртгэлийн мастерт бөглөсөн ч эрхийн лимитээс болж үүсээгүй салбаруудыг
 * төлбөр батлагдсаны дараа үүсгэнэ.
 */
async function createPendingBranches() {
    const business = organization.value?.businesses?.[organization.value.businesses.length - 1];
    if (!business) return;

    const key = `pending_branches:${business.id}`;
    let pending = [];

    try {
        pending = JSON.parse(sessionStorage.getItem(key) || '[]');
    } catch {
        sessionStorage.removeItem(key);
        return;
    }

    if (!pending.length) return;

    const failed = [];

    for (const payload of pending) {
        try {
            await api.post(`/console/businesses/${business.id}/branches`, payload);
        } catch {
            failed.push(payload);
        }
    }

    if (failed.length) {
        sessionStorage.setItem(key, JSON.stringify(failed));
        pendingBranchesNote.value = `${failed.length} салбарыг үүсгэж чадсангүй — Бизнес зөвлөлөөс гараар нэмнэ үү.`;
    } else {
        sessionStorage.removeItem(key);
        pendingBranchesNote.value = `${pending.length} нэмэлт салбар үүслээ.`;
    }
}

async function load() {
    loadError.value = '';
    try {
        const [orderData, orgs] = await Promise.all([
            api.get(`/orders/${route.params.id}`),
            api.get('/console/organizations'),
        ]);
        order.value = orderData.data;
        organization.value = orgs.data.find((o) => o.id === order.value.organization_id) || orgs.data[0];

        if (order.value.status === 'paid') await createPendingBranches();
    } catch {
        loadError.value = 'Захиалга олдсонгүй эсвэл таных биш байна.';
    }
}

onMounted(load);
</script>

<template>
    <div class="bg-page py-8">
        <div v-if="order" class="card mx-auto max-w-4xl overflow-hidden bg-white">
            <!-- Амжилтын толгой -->
            <div class="border-b border-greenline bg-greentint px-6 pb-7 pt-10 text-center">
                <div class="mx-auto flex h-[52px] w-[52px] items-center justify-center rounded-full bg-green text-2xl font-bold text-white">✓</div>
                <h1 class="mt-4 text-[27px] font-extrabold tracking-[-.02em] text-ink">Төлбөр хийгдлээ</h1>
                <p class="mx-auto mt-2 max-w-[480px] text-[13.5px] leading-[1.65] text-body">
                    {{ planItem ? planItem.name + ' нээгдлээ. ' : '' }}{{ campaignItems.length ? 'Онцлох байршил 10 минутын дотор ажиллаж эхэлнэ.' : '' }}
                </p>
                <p v-if="pendingBranchesNote" class="mx-auto mt-2 max-w-[480px] text-[12.5px] font-semibold text-green">{{ pendingBranchesNote }}</p>
                <div class="mt-4 inline-flex gap-2">
                    <router-link :to="{ name: 'console' }" class="btn-primary !px-4 !py-2.5 !text-[12.5px]">Бизнес зөвлөл рүү</router-link>
                    <a v-if="order.invoice_url" :href="order.invoice_url" target="_blank" rel="noopener" class="rounded-lg border border-[#cfe6da] bg-white px-4 py-2.5 text-[12.5px] font-bold text-ink">И-баримт татах</a>
                </div>
            </div>

            <div class="grid grid-cols-1 border-b border-line md:grid-cols-2">
                <!-- Баримт -->
                <div class="border-line px-6 py-6 md:border-r">
                    <div class="kicker">БАРИМТ</div>
                    <div class="mt-3.5 flex flex-col gap-2.5 text-[12.5px]">
                        <div class="flex justify-between gap-3"><span class="font-medium text-mute">Захиалгын дугаар</span><span class="font-mono font-semibold text-ink">{{ order.number }}</span></div>
                        <div class="flex justify-between gap-3"><span class="font-medium text-mute">Төлсөн огноо</span><span class="font-semibold text-ink">{{ order.paid_at ? new Date(order.paid_at).toLocaleString() : '—' }}</span></div>
                        <div class="flex justify-between gap-3"><span class="font-medium text-mute">Төлбөрийн арга</span><span class="font-semibold text-ink">byl.mn</span></div>
                        <div class="flex justify-between gap-3"><span class="font-medium text-mute">Байгууллага</span><span class="font-semibold text-ink">{{ organization?.name }}</span></div>
                        <div v-if="organization?.registration_number" class="flex justify-between gap-3"><span class="font-medium text-mute">Регистр</span><span class="font-mono font-semibold text-ink">{{ organization.registration_number }}</span></div>
                    </div>
                    <div class="my-4 h-px bg-divider"></div>
                    <div class="flex items-baseline justify-between">
                        <span class="text-[13px] font-bold text-ink">Төлсөн дүн</span>
                        <span class="text-xl font-extrabold text-ink">{{ fmt(order.total) }}</span>
                    </div>
                </div>

                <!-- Эрх нээгдсэн -->
                <div class="px-6 py-6">
                    <div class="kicker">ЭРХ НЭЭГДСЭН</div>
                    <div class="mt-3.5 flex flex-col gap-2.5">
                        <div v-for="item in order.items" :key="item.id" class="flex items-center gap-2.5">
                            <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-[1.5px] text-[9px] font-bold text-white" :class="item.type === 'campaign' ? 'border-amberdot bg-amberdot' : 'border-green bg-green'">{{ item.type === 'campaign' ? '' : '✓' }}</span>
                            <span class="text-[12.5px] font-medium text-body">{{ item.name }}</span>
                            <span class="ml-auto whitespace-nowrap text-[11px] font-semibold" :class="item.type === 'campaign' ? 'text-amber' : 'text-green'">
                                {{ item.type === 'campaign' ? '~10 минутын дараа' : 'Нээгдсэн' }}
                            </span>
                        </div>
                    </div>
                    <div v-if="organization?.plan_expires_at" class="mt-4 rounded-[10px] border border-amberline bg-ambertint p-3.5">
                        <div class="text-[12px] font-bold text-ink">{{ organization.plan_name }} эрх</div>
                        <div class="mt-1 text-[11.5px] leading-normal text-ambertext">
                            {{ shortDate(organization.plan_started_at) }} – {{ shortDate(organization.plan_expires_at) }} · дуусахаас өмнө сунгах сануулга илгээнэ.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Дараагийн 3 үйлдэл -->
            <div class="px-6 py-6">
                <div class="text-[15px] font-bold text-ink">Дараагийн 3 үйлдэл</div>
                <div class="mt-3.5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <router-link v-for="n in nextThree" :key="n.n" :to="n.to" class="rounded-[11px] border border-line p-4 transition hover:border-blueline">
                        <div class="flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-[7px] border border-blueline bg-bluetint text-[11px] font-bold text-brand">{{ n.n }}</span>
                            <span class="text-[13px] font-bold text-ink">{{ n.title }}</span>
                        </div>
                        <div class="mt-2 text-[12px] leading-relaxed text-soft">{{ n.text }}</div>
                        <div class="mt-2.5 text-[12px] font-semibold text-brand">{{ n.cta }}</div>
                    </router-link>
                </div>
                <p class="mt-4 text-[11.5px] leading-relaxed text-mute">Эрхээ 14 хоногийн дотор цуцлавал ашиглаагүй хугацааны төлбөрийг буцаана. И-баримт, нэхэмжлэх «Нэхэмжлэх» хэсэгт хадгалагдана.</p>
            </div>
        </div>

        <template v-else>
            <div v-if="loadError" class="card mx-auto max-w-4xl p-10 text-center">
                <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
                <button class="btn-primary mt-4" @click="load">Дахин оролдох</button>
            </div>
            <div v-else class="card mx-auto max-w-4xl h-64 animate-pulse"></div>
        </template>
    </div>
</template>
