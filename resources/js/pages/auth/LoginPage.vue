<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../../api';
import { useAuthStore } from '../../stores/auth';
import VerifyPanel from '../../components/VerifyPanel.vue';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const mode = ref('password'); // password | sms
const form = ref({ phone: '', password: '' });
const showPassword = ref(false);
const verification = ref(null);
const error = ref('');
const busy = ref(false);

function done() {
    // redirect query-г шалгаж (нээлттэй redirect-ээс сэргийлнэ) зөвхөн дотоод зам зөвшөөрнө
    const target = Array.isArray(route.query.redirect) ? route.query.redirect[0] : route.query.redirect;
    router.push(target && target.startsWith('/') && !target.startsWith('//') ? target : { name: 'home' });
}

async function login() {
    error.value = '';
    busy.value = true;
    try {
        const data = await api.post('/auth/login', {
            phone: form.value.phone.replace(/\s/g, ''),
            password: form.value.password,
        });
        auth.setSession(data.token, data.user.data ?? data.user);

        // Дугаараа баталгаажуулаагүй хэрэглэгчийг заавал баталгаажуулах хуудас руу
        if (!auth.user?.phone_verified) {
            router.push({ name: 'verify' });
            return;
        }

        done();
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа. Дахин оролдоно уу.';
    } finally {
        busy.value = false;
    }
}

async function loginBySms() {
    error.value = '';
    busy.value = true;
    try {
        const data = await api.post('/auth/login-sms', { phone: form.value.phone.replace(/\s/g, '') });
        verification.value = data.verification;
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    } finally {
        busy.value = false;
    }
}

async function onVerified(data) {
    if (data.token) {
        auth.setSession(data.token, data.user.data ?? data.user);

        // Мессежээр нэвтэрсэн ч мөн адил баталгаажилтын шаардлага үйлчилнэ
        if (!auth.user?.phone_verified) {
            router.push({ name: 'verify' });
            return;
        }

        done();
    }
}
</script>

<template>
    <div class="bg-hero px-4 py-14">
        <div class="card mx-auto max-w-[460px] bg-white p-8">
            <div class="flex items-center gap-2.5">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand text-[14px] font-extrabold text-white">Х</span>
                <span class="text-[17px] font-extrabold text-ink">Хаана<span class="text-brand">.mn</span></span>
            </div>
            <h1 class="mt-6 text-[26px] font-extrabold tracking-[-.025em] text-ink">Нэвтрэх</h1>
            <p class="mt-1.5 text-[13px] text-soft">Утасны дугаар эсвэл и-мэйлээрээ.</p>

            <!-- Горим сонгогч (7b) -->
            <div class="mt-5 flex gap-1.5 rounded-[9px] border border-searchline bg-search p-1">
                <button class="flex-1 cursor-pointer rounded-[7px] py-2 text-[12.5px] font-semibold" :class="mode === 'password' ? 'bg-white text-ink shadow-sm' : 'text-soft'" @click="mode = 'password'; verification = null">Нууц үгээр</button>
                <button class="flex-1 cursor-pointer rounded-[7px] py-2 text-[12.5px] font-semibold" :class="mode === 'sms' ? 'bg-white text-ink shadow-sm' : 'text-soft'" @click="mode = 'sms'">Мессежээр</button>
            </div>

            <!-- Нууц үгээр -->
            <form v-if="mode === 'password'" class="mt-4 space-y-3.5" @submit.prevent="login">
                <div>
                    <label class="field-label">Утас эсвэл и-мэйл</label>
                    <input v-model="form.phone" type="text" placeholder="9911 2233" class="input" required />
                </div>
                <div>
                    <div class="flex items-baseline justify-between">
                        <label class="field-label !mb-0">Нууц үг</label>
                        <router-link :to="{ name: 'forgot' }" class="text-[11.5px] font-semibold text-brand">Мартсан?</router-link>
                    </div>
                    <div class="mt-1.5 flex items-center rounded-[9px] border px-3.5 py-3 focus-within:ring-2 focus-within:ring-bluetint" :class="error ? 'border-[1.5px] border-red' : 'border-inputline focus-within:border-brand'">
                        <input v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="•••••••" class="w-full bg-transparent text-[13px] font-medium tracking-widest text-ink outline-none placeholder:text-ph" required />
                        <button type="button" class="cursor-pointer text-[12px] font-semibold text-brand" @click="showPassword = !showPassword">{{ showPassword ? 'Нуух' : 'Харах' }}</button>
                    </div>
                    <p v-if="error" class="mt-1.5 text-[11.5px] font-medium text-red">{{ error }}</p>
                </div>
                <button type="submit" class="btn-primary w-full !rounded-[10px] !py-3.5" :disabled="busy">{{ busy ? 'Нэвтэрч байна…' : 'Нэвтрэх' }}</button>
            </form>

            <!-- Мессежээр -->
            <div v-else class="mt-4">
                <template v-if="!verification">
                    <form class="space-y-3.5" @submit.prevent="loginBySms">
                        <div>
                            <label class="field-label">Утасны дугаар</label>
                            <input v-model="form.phone" type="tel" inputmode="numeric" maxlength="9" placeholder="9911 2233" class="input" required />
                        </div>
                        <p v-if="error" class="rounded-lg bg-redtint px-3 py-2 text-[12.5px] font-medium text-red">{{ error }}</p>
                        <button type="submit" class="btn-primary w-full !rounded-[10px] !py-3.5" :disabled="busy">Код авах</button>
                    </form>
                    <p class="mt-3 text-[11.5px] leading-relaxed text-mute">Та бүртгэлтэй дугаараасаа verify.mn-ийн дугаарт мессеж илгээснээр нэвтэрнэ — нууц үг шаардлагагүй.</p>
                </template>
                <VerifyPanel v-else :verification="verification" @verified="onVerified" @expired="verification = null; error = 'Хугацаа дууслаа, дахин оролдоно уу.'" />
            </div>

            <p class="mt-6 text-center text-[12.5px] font-medium text-soft">
                Аккаунт байхгүй? <router-link :to="{ name: 'register' }" class="font-semibold text-brand">Бүртгүүлэх</router-link>
            </p>
        </div>
    </div>
</template>
