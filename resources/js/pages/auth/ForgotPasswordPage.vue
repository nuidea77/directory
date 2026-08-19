<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, ApiError } from '../../api';
import { useAuthStore } from '../../stores/auth';
import VerifyPanel from '../../components/VerifyPanel.vue';

const auth = useAuthStore();
const router = useRouter();

// 7d дизайн: 3 төлөвт урсгал (хүсэлт → шинэ нууц үг → амжилттай)
const step = ref(1);
const phone = ref('');
const password = ref('');
const passwordConfirm = ref('');
const showPassword = ref(false);
const verification = ref(null);
const error = ref('');
const busy = ref(false);

const rules = [
    { text: '8+ тэмдэгт', check: () => password.value.length >= 8 },
    { text: 'Том ба жижиг үсэг', check: () => /[a-zа-яөү]/.test(password.value) && /[A-ZА-ЯӨҮ]/.test(password.value) },
    { text: 'Тоо эсвэл тусгай тэмдэгт', check: () => /[0-9]/.test(password.value) || /[^a-zA-Z0-9а-яА-ЯөүӨҮ]/.test(password.value) },
];

async function start() {
    error.value = '';
    busy.value = true;
    try {
        const data = await api.post('/auth/reset', { phone: phone.value.replace(/\s/g, '') });
        verification.value = data.verification;
        if (verification.value.status === 'verified') step.value = 2;
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    } finally {
        busy.value = false;
    }
}

async function confirm() {
    error.value = '';
    if (password.value !== passwordConfirm.value) {
        error.value = 'Нууц үг таарахгүй байна.';
        return;
    }
    busy.value = true;
    try {
        const data = await api.post('/auth/reset/confirm', {
            verification_uuid: verification.value.uuid,
            password: password.value,
        });
        auth.setSession(data.token, data.user.data ?? data.user);
        step.value = 3;
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="bg-hero px-4 py-14">
        <div class="card mx-auto max-w-[460px] bg-white p-7">
            <!-- 1 · Хүсэлт -->
            <template v-if="step === 1 && !verification">
                <div class="kicker !text-[10.5px]">1 · ХҮСЭЛТ</div>
                <h1 class="mt-3 text-[21px] font-extrabold tracking-[-.02em] text-ink">Нууц үгээ мартсан уу?</h1>
                <p class="mt-2 text-[12.5px] leading-[1.65] text-soft">
                    Бүртгэлтэй утасны дугаараа бичнэ үү. Та verify.mn-ийн дугаарт код илгээснээр шинэ нууц үг тохируулах боломж нээгдэнэ.
                </p>
                <form class="mt-4 space-y-3.5" @submit.prevent="start">
                    <div>
                        <label class="field-label">Утасны дугаар</label>
                        <input v-model="phone" type="tel" inputmode="numeric" maxlength="9" placeholder="9911 2233" class="input" required />
                    </div>
                    <p v-if="error" class="rounded-lg bg-redtint px-3 py-2 text-[12.5px] font-medium text-red">{{ error }}</p>
                    <button type="submit" class="btn-primary w-full !py-3" :disabled="busy">Мессежээр сэргээх</button>
                </form>
                <router-link :to="{ name: 'login' }" class="mt-4 block text-[12.5px] font-medium text-brand">← Нэвтрэх хуудас руу</router-link>
            </template>

            <!-- Баталгаажуулалт хүлээх -->
            <template v-else-if="step === 1">
                <div class="kicker !text-[10.5px]">1 · БАТАЛГААЖУУЛАЛТ</div>
                <h1 class="mt-3 text-[21px] font-extrabold tracking-[-.02em] text-ink">Дугаараа баталгаажуулна уу</h1>
                <div class="mt-4">
                    <VerifyPanel :verification="verification" @verified="step = 2" @expired="verification = null; error = 'Хугацаа дууслаа.'" />
                </div>
                <button class="mt-4 cursor-pointer text-[12.5px] font-medium text-brand" @click="verification = null">← Дугаараа өөрчлөх</button>
            </template>

            <!-- 2 · Шинэ нууц үг -->
            <template v-else-if="step === 2">
                <div class="text-[10.5px] font-semibold tracking-[.1em] text-brand">2 · ШИНЭ НУУЦ ҮГ</div>
                <h1 class="mt-3 text-[21px] font-extrabold tracking-[-.02em] text-ink">Шинэ нууц үг тохируулах</h1>
                <p class="mt-2 text-[12.5px] leading-[1.65] text-soft">{{ phone }} дугаар баталгаажлаа. Шинэ нууц үгээ хоёр удаа бичнэ үү.</p>
                <form class="mt-4 space-y-3.5" @submit.prevent="confirm">
                    <div>
                        <label class="field-label">Шинэ нууц үг</label>
                        <div class="flex items-center rounded-[9px] border border-inputline px-3.5 py-3 focus-within:border-brand focus-within:ring-2 focus-within:ring-bluetint">
                            <input v-model="password" :type="showPassword ? 'text' : 'password'" class="w-full bg-transparent text-[13px] font-medium tracking-widest text-ink outline-none" required minlength="8" />
                            <button type="button" class="cursor-pointer text-[12px] font-semibold text-brand" @click="showPassword = !showPassword">{{ showPassword ? 'Нуух' : 'Харах' }}</button>
                        </div>
                    </div>
                    <div>
                        <label class="field-label">Дахин бичих</label>
                        <div class="flex items-center rounded-[9px] border px-3.5 py-3" :class="passwordConfirm && password === passwordConfirm ? 'border-[1.5px] border-green' : 'border-inputline'">
                            <input v-model="passwordConfirm" :type="showPassword ? 'text' : 'password'" class="w-full bg-transparent text-[13px] font-medium tracking-widest text-ink outline-none" required />
                            <span v-if="passwordConfirm && password === passwordConfirm" class="text-[12px] font-semibold text-green">✓ Таарлаа</span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <div v-for="rule in rules" :key="rule.text" class="flex items-center gap-2.5">
                            <span class="flex h-[15px] w-[15px] items-center justify-center rounded-full border-[1.5px] text-[9px] font-bold text-white" :class="rule.check() ? 'border-green bg-green' : 'border-inputline bg-white'">{{ rule.check() ? '✓' : '' }}</span>
                            <span class="text-[12px] font-medium" :class="rule.check() ? 'text-body' : 'text-mute'">{{ rule.text }}</span>
                        </div>
                    </div>
                    <p v-if="error" class="rounded-lg bg-redtint px-3 py-2 text-[12.5px] font-medium text-red">{{ error }}</p>
                    <button type="submit" class="btn-primary w-full !py-3" :disabled="busy">Нууц үг хадгалах</button>
                </form>
            </template>

            <!-- 3 · Амжилттай -->
            <template v-else>
                <div class="kicker !text-[10.5px]">3 · АМЖИЛТТАЙ</div>
                <div class="mt-4 flex h-[46px] w-[46px] items-center justify-center rounded-full border border-[#cfe6da] bg-greentint text-xl font-bold text-green">✓</div>
                <h1 class="mt-4 text-[21px] font-extrabold tracking-[-.02em] text-ink">Нууц үг шинэчлэгдлээ</h1>
                <p class="mt-2 text-[12.5px] leading-[1.65] text-soft">Бүх төхөөрөмжөөс гарсан. Та одоо шинэ нууц үгтэйгээ нэвтэрсэн байна.</p>
                <div class="mt-4 rounded-[10px] border border-line bg-panel p-3.5">
                    <div class="text-[10.5px] font-semibold tracking-[.08em] text-mute">ХАМГААЛАЛТЫН МЭДЭЭЛЭЛ</div>
                    <div class="mt-2.5 flex flex-col gap-2 text-[12px] font-medium text-body">
                        <div class="flex justify-between"><span>Сүүлд солисон</span><b class="text-ink">{{ new Date().toLocaleString() }}</b></div>
                        <div class="flex justify-between"><span>Баталгаажуулалт</span><b class="text-ink">Мессеж · verify.mn</b></div>
                    </div>
                </div>
                <p class="mt-3.5 text-[11.5px] leading-relaxed text-mute">Та биш байсан бол дугаараа хамгаалахын тулд бидэнтэй холбогдоно уу.</p>
                <button class="btn-primary mt-4 w-full !py-3" @click="router.push({ name: 'home' })">Үргэлжлүүлэх</button>
            </template>
        </div>
    </div>
</template>
