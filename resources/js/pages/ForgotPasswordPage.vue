<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, ApiError } from '../api';
import { useAuthStore } from '../stores/auth';
import VerifyPhonePanel from '../components/VerifyPhonePanel.vue';

const auth = useAuthStore();
const router = useRouter();

const step = ref('phone'); // phone | verify | password | expired
const phone = ref('');
const password = ref('');
const verification = ref(null);
const error = ref('');
const submitting = ref(false);

async function start() {
    error.value = '';
    submitting.value = true;
    try {
        const data = await api.post('/auth/reset', { phone: phone.value });
        verification.value = data.verification;
        step.value = verification.value.status === 'verified' ? 'password' : 'verify';
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    } finally {
        submitting.value = false;
    }
}

async function confirm() {
    error.value = '';
    submitting.value = true;
    try {
        const data = await api.post('/auth/reset/confirm', {
            verification_uuid: verification.value.uuid,
            password: password.value,
        });
        auth.setSession(data.token, data.user.data ?? data.user);
        router.push({ name: 'dashboard' });
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="mx-auto flex max-w-md flex-col px-4 py-16">
        <div v-if="step === 'phone'" class="card p-8">
            <h1 class="text-2xl font-extrabold text-slate-900">Нууц үг сэргээх</h1>
            <p class="mt-1 text-sm text-slate-500">Бүртгэлтэй утасны дугаараа оруулна уу.</p>

            <form class="mt-6 space-y-4" @submit.prevent="start">
                <input v-model="phone" type="tel" inputmode="numeric" maxlength="8" pattern="[0-9]{8}" placeholder="99112233" class="input" required />
                <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>
                <button type="submit" class="btn-primary w-full !py-3" :disabled="submitting">Үргэлжлүүлэх</button>
            </form>

            <p class="mt-6 text-center text-sm">
                <router-link :to="{ name: 'login' }" class="font-medium text-brand-600 hover:text-brand-700">← Нэвтрэх рүү буцах</router-link>
            </p>
        </div>

        <VerifyPhonePanel
            v-else-if="step === 'verify'"
            :verification="verification"
            @verified="step = 'password'"
            @expired="step = 'expired'"
        />

        <div v-else-if="step === 'password'" class="card p-8">
            <h1 class="text-2xl font-extrabold text-slate-900">Шинэ нууц үг</h1>
            <p class="mt-1 text-sm text-slate-500">Дугаар баталгаажлаа. Шинэ нууц үгээ оруулна уу.</p>

            <form class="mt-6 space-y-4" @submit.prevent="confirm">
                <input v-model="password" type="password" placeholder="Доод тал нь 8 тэмдэгт" class="input" required minlength="8" />
                <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>
                <button type="submit" class="btn-primary w-full !py-3" :disabled="submitting">Нууц үг шинэчлэх</button>
            </form>
        </div>

        <div v-else class="card p-8 text-center">
            <p class="text-4xl">⏰</p>
            <h2 class="mt-4 text-lg font-bold text-slate-900">Хугацаа дууслаа</h2>
            <button class="btn-primary mt-6 w-full" @click="step = 'phone'">Дахин эхлэх</button>
        </div>
    </div>
</template>
