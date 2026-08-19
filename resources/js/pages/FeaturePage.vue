<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api, ApiError } from '../api';

const route = useRoute();

const listing = ref(null);
const plans = ref([]);
const selectedPlan = ref('');
const payment = ref(null);
const error = ref('');
const submitting = ref(false);
let pollTimer = null;

onMounted(async () => {
    const [l, p] = await Promise.all([
        api.get(`/my/listings/${route.params.id}`),
        api.get('/plans'),
    ]);
    listing.value = l.data;
    plans.value = p.data;
    selectedPlan.value = plans.value[0]?.key || '';
});

async function pay() {
    error.value = '';
    submitting.value = true;
    try {
        const data = await api.post(`/my/listings/${route.params.id}/feature`, { plan: selectedPlan.value });
        payment.value = data.data;

        if (payment.value.status === 'pending' && payment.value.invoice_url) {
            // Open the byl.mn hosted invoice page and start polling for payment.
            window.open(payment.value.invoice_url, '_blank', 'noopener');
            pollTimer = setInterval(checkPayment, 4000);
        }
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа. Дахин оролдоно уу.';
    } finally {
        submitting.value = false;
    }
}

async function checkPayment() {
    try {
        const data = await api.get(`/payments/${payment.value.id}`);
        payment.value = data.data;

        if (payment.value.status !== 'pending') {
            clearInterval(pollTimer);
            if (payment.value.status === 'paid') {
                const l = await api.get(`/my/listings/${route.params.id}`);
                listing.value = l.data;
            }
        }
    } catch {
        // transient error — keep polling
    }
}

onBeforeUnmount(() => clearInterval(pollTimer));

const formatAmount = (amount) => new Intl.NumberFormat('mn-MN').format(amount) + '₮';
</script>

<template>
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
        <h1 class="text-2xl font-bold text-slate-900">★ Онцлох байршуулалт</h1>
        <p v-if="listing" class="mt-1 text-sm text-slate-500">
            «{{ listing.name }}» бүртгэлийг нүүр хуудас болон хайлтын дээд хэсэгт онцолж харуулна.
        </p>

        <div v-if="listing?.is_featured" class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-700">
            Одоогоор {{ new Date(listing.featured_until).toLocaleDateString() }} хүртэл онцлох байршуулалттай.
            Шинэ төлбөр хийвэл хугацаа дээр нь нэмэгдэнэ.
        </div>

        <!-- Paid -->
        <div v-if="payment?.status === 'paid'" class="card mt-8 p-10 text-center">
            <p class="text-5xl">🎉</p>
            <h2 class="mt-4 text-xl font-bold text-slate-900">Төлбөр амжилттай!</h2>
            <p class="mt-2 text-sm text-slate-500">
                Таны бүртгэл {{ listing?.featured_until ? new Date(listing.featured_until).toLocaleDateString() : '' }} хүртэл онцлох байршуулалттай боллоо.
            </p>
            <router-link :to="{ name: 'dashboard' }" class="btn-primary mt-6">Буцах</router-link>
        </div>

        <!-- Waiting for payment -->
        <div v-else-if="payment?.status === 'pending'" class="card mt-8 p-10 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-3xl">💳</div>
            <h2 class="mt-4 text-xl font-bold text-slate-900">Төлбөр хүлээгдэж байна</h2>
            <p class="mt-2 text-sm text-slate-500">
                Нээгдсэн byl.mn хуудсаар QPay, SocialPay, Pocket эсвэл картаар төлнө үү.
                Төлбөр хийгдмэгц энэ хуудас автоматаар шинэчлэгдэнэ.
            </p>
            <a :href="payment.invoice_url" target="_blank" rel="noopener" class="btn-primary mt-6">Төлбөрийн хуудас нээх</a>
            <div class="mt-4 flex items-center justify-center gap-2 text-sm text-slate-400">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-brand-500"></span>
                </span>
                Төлбөрийг шалгаж байна...
            </div>
        </div>

        <!-- Plan selection -->
        <template v-else>
            <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <button
                    v-for="plan in plans"
                    :key="plan.key"
                    type="button"
                    class="card p-6 text-left transition"
                    :class="selectedPlan === plan.key ? 'border-brand-500 ring-2 ring-brand-200' : 'hover:border-brand-300'"
                    @click="selectedPlan = plan.key"
                >
                    <p class="text-2xl">★</p>
                    <p class="mt-2 font-bold text-slate-900">{{ plan.name }}</p>
                    <p class="mt-1 text-xl font-extrabold text-brand-600">{{ formatAmount(plan.amount) }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ plan.days }} хоногийн турш</p>
                </button>
            </div>

            <div class="card mt-6 p-6">
                <h3 class="font-bold text-slate-900">Онцлох байршуулалтад юу багтдаг вэ?</h3>
                <ul class="mt-3 space-y-2 text-sm text-slate-600">
                    <li>✅ Нүүр хуудасны «Онцлох байгууллагууд» хэсэгт харагдана</li>
                    <li>✅ Хайлтын үр дүнд бусдаас түрүүлж харагдана</li>
                    <li>✅ Тод «Онцлох» тэмдэгтэй харагдана</li>
                </ul>
                <p class="mt-4 text-xs text-slate-400">
                    Төлбөрийг byl.mn-ээр дамжуулан QPay, SocialPay, Pocket, Golomt банкны аль нэгээр төлнө.
                </p>
            </div>

            <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

            <button class="btn-primary mt-6 w-full !py-3.5 text-base" :disabled="submitting || !selectedPlan" @click="pay">
                {{ submitting ? 'Нэхэмжлэх үүсгэж байна...' : 'Төлбөр төлөх' }}
            </button>
        </template>
    </div>
</template>
