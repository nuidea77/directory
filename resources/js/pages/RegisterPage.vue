<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, ApiError } from '../api';
import { useAuthStore } from '../stores/auth';
import VerifyPhonePanel from '../components/VerifyPhonePanel.vue';

const auth = useAuthStore();
const router = useRouter();

const step = ref('form'); // form | verify | expired
const form = ref({ name: '', phone: '', password: '' });
const verification = ref(null);
const error = ref('');
const submitting = ref(false);

async function register() {
    error.value = '';
    submitting.value = true;
    try {
        const data = await api.post('/auth/register', form.value);

        // Dev mode can come back already verified with a token.
        if (data.token) {
            auth.setSession(data.token, data.user.data ?? data.user);
            router.push({ name: 'dashboard' });
            return;
        }

        verification.value = data.verification;
        step.value = 'verify';
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа. Дахин оролдоно уу.';
    } finally {
        submitting.value = false;
    }
}

function onVerified(data) {
    if (data.token) {
        auth.setSession(data.token, data.user.data ?? data.user);
        router.push({ name: 'dashboard' });
    }
}
</script>

<template>
    <div class="mx-auto flex max-w-md flex-col px-4 py-16">
        <div v-if="step === 'form'" class="card p-8">
            <h1 class="text-2xl font-extrabold text-slate-900">Бүртгүүлэх</h1>
            <p class="mt-1 text-sm text-slate-500">Утасны дугаараа SMS-ээр баталгаажуулж бүртгэл үүсгэнэ.</p>

            <form class="mt-6 space-y-4" @submit.prevent="register">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Нэр</label>
                    <input v-model="form.name" type="text" placeholder="Таны нэр" class="input" required maxlength="100" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Утасны дугаар</label>
                    <input v-model="form.phone" type="tel" inputmode="numeric" maxlength="8" pattern="[0-9]{8}" placeholder="99112233" class="input" required />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Нууц үг</label>
                    <input v-model="form.password" type="password" placeholder="Доод тал нь 8 тэмдэгт" class="input" required minlength="8" />
                </div>

                <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

                <button type="submit" class="btn-primary w-full !py-3" :disabled="submitting">
                    {{ submitting ? 'Илгээж байна...' : 'Үргэлжлүүлэх' }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">
                Бүртгэлтэй юу?
                <router-link :to="{ name: 'login' }" class="font-medium text-brand-600 hover:text-brand-700">Нэвтрэх</router-link>
            </p>
        </div>

        <VerifyPhonePanel
            v-else-if="step === 'verify'"
            :verification="verification"
            @verified="onVerified"
            @expired="step = 'expired'"
        />

        <div v-else class="card p-8 text-center">
            <p class="text-4xl">⏰</p>
            <h2 class="mt-4 text-lg font-bold text-slate-900">Хугацаа дууслаа</h2>
            <p class="mt-2 text-sm text-slate-500">Баталгаажуулалтын хугацаа дууссан байна. Дахин оролдоно уу.</p>
            <button class="btn-primary mt-6 w-full" @click="step = 'form'">Дахин эхлэх</button>
        </div>
    </div>
</template>
