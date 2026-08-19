<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../api';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = ref({ phone: '', password: '' });
const error = ref('');
const submitting = ref(false);

async function login() {
    error.value = '';
    submitting.value = true;
    try {
        const data = await api.post('/auth/login', form.value);
        auth.setSession(data.token, data.user);
        router.push(route.query.redirect || { name: 'dashboard' });
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа. Дахин оролдоно уу.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="mx-auto flex max-w-md flex-col px-4 py-16">
        <div class="card p-8">
            <h1 class="text-2xl font-extrabold text-slate-900">Нэвтрэх</h1>
            <p class="mt-1 text-sm text-slate-500">Утасны дугаар, нууц үгээрээ нэвтэрнэ үү.</p>

            <form class="mt-6 space-y-4" @submit.prevent="login">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Утасны дугаар</label>
                    <input v-model="form.phone" type="tel" inputmode="numeric" maxlength="8" placeholder="99112233" class="input" required />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Нууц үг</label>
                    <input v-model="form.password" type="password" placeholder="••••••••" class="input" required />
                </div>

                <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

                <button type="submit" class="btn-primary w-full !py-3" :disabled="submitting">
                    {{ submitting ? 'Нэвтэрч байна...' : 'Нэвтрэх' }}
                </button>
            </form>

            <div class="mt-6 flex items-center justify-between text-sm">
                <router-link :to="{ name: 'forgot' }" class="font-medium text-brand-600 hover:text-brand-700">Нууц үг мартсан?</router-link>
                <router-link :to="{ name: 'register' }" class="font-medium text-brand-600 hover:text-brand-700">Бүртгүүлэх →</router-link>
            </div>
        </div>
    </div>
</template>
