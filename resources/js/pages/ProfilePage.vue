<script setup>
import { ref } from 'vue';
import { api, ApiError } from '../api';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

const profileForm = ref({ name: auth.user?.name || '', email: auth.user?.email || '' });
const passwordForm = ref({ current_password: '', password: '' });
const profileMsg = ref({ type: '', text: '' });
const passwordMsg = ref({ type: '', text: '' });

async function saveProfile() {
    profileMsg.value = { type: '', text: '' };
    try {
        const data = await api.put('/me', profileForm.value);
        auth.user = data.data ?? data;
        profileMsg.value = { type: 'ok', text: 'Мэдээлэл хадгалагдлаа.' };
    } catch (e) {
        profileMsg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    }
}

async function changePassword() {
    passwordMsg.value = { type: '', text: '' };
    try {
        await api.put('/me/password', passwordForm.value);
        passwordForm.value = { current_password: '', password: '' };
        passwordMsg.value = { type: 'ok', text: 'Нууц үг солигдлоо.' };
    } catch (e) {
        passwordMsg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    }
}
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-6 px-4 py-8 sm:px-6">
        <h1 class="text-2xl font-bold text-slate-900">Профайл</h1>

        <div class="card p-6">
            <h2 class="font-bold text-slate-900">Хувийн мэдээлэл</h2>
            <p class="mt-1 text-sm text-slate-500">Утасны дугаар: <span class="font-semibold text-slate-700">{{ auth.user?.phone }}</span> (баталгаажсан ✅)</p>

            <form class="mt-5 space-y-4" @submit.prevent="saveProfile">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Нэр</label>
                    <input v-model="profileForm.name" type="text" class="input" required maxlength="100" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">И-мэйл (заавал биш)</label>
                    <input v-model="profileForm.email" type="email" class="input" />
                </div>
                <p v-if="profileMsg.text" class="rounded-lg px-3 py-2 text-sm" :class="profileMsg.type === 'ok' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'">
                    {{ profileMsg.text }}
                </p>
                <button type="submit" class="btn-primary">Хадгалах</button>
            </form>
        </div>

        <div class="card p-6">
            <h2 class="font-bold text-slate-900">Нууц үг солих</h2>
            <form class="mt-5 space-y-4" @submit.prevent="changePassword">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Одоогийн нууц үг</label>
                    <input v-model="passwordForm.current_password" type="password" class="input" required />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Шинэ нууц үг</label>
                    <input v-model="passwordForm.password" type="password" class="input" required minlength="8" />
                </div>
                <p v-if="passwordMsg.text" class="rounded-lg px-3 py-2 text-sm" :class="passwordMsg.type === 'ok' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'">
                    {{ passwordMsg.text }}
                </p>
                <button type="submit" class="btn-primary">Нууц үг солих</button>
            </form>
        </div>
    </div>
</template>
