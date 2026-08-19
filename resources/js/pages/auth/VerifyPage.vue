<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, ApiError } from '../../api';
import { useAuthStore } from '../../stores/auth';
import VerifyPanel from '../../components/VerifyPanel.vue';

// Нэвтэрсэн ч дугаараа баталгаажуулаагүй хэрэглэгчийн хуудас
const auth = useAuthStore();
const router = useRouter();

const verification = ref(null);
const error = ref('');

async function start() {
    error.value = '';
    try {
        const data = await api.post('/auth/verify/start');
        if (data.verification) {
            verification.value = data.verification;
        } else {
            await auth.refresh();
            router.push({ name: 'home' });
        }
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    }
}

async function onVerified() {
    await auth.refresh();
    router.push({ name: 'home' });
}

onMounted(() => {
    if (auth.user?.phone_verified) {
        router.push({ name: 'home' });
        return;
    }
    start();
});
</script>

<template>
    <div class="bg-hero px-4 py-14">
        <div class="card mx-auto max-w-[460px] bg-white p-8">
            <h1 class="text-[25px] font-extrabold leading-tight tracking-[-.025em] text-ink">Дугаараа баталгаажуулна уу</h1>
            <p class="mt-2 text-[13px] leading-[1.65] text-soft">
                Сэтгэгдэл бичих, зурвас илгээх, бизнес нэмэхэд утасны дугаар баталгаажсан байх шаардлагатай.
            </p>
            <p v-if="error" class="mt-3 rounded-lg bg-redtint px-3 py-2 text-[12.5px] font-medium text-red">{{ error }}</p>
            <div v-if="verification" class="mt-4">
                <VerifyPanel :verification="verification" @verified="onVerified" @expired="start" />
            </div>
            <div class="my-5 h-px bg-divider"></div>
            <div class="flex items-center justify-between text-[12.5px] font-semibold">
                <button class="cursor-pointer text-brand" @click="start">Шинэ код авах</button>
                <router-link :to="{ name: 'home' }" class="text-soft">Дараа баталгаажуулах</router-link>
            </div>
        </div>
    </div>
</template>
