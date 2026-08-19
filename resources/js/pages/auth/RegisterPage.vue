<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, ApiError } from '../../api';
import { useAuthStore } from '../../stores/auth';
import VerifyPanel from '../../components/VerifyPanel.vue';

const auth = useAuthStore();
const router = useRouter();

const step = ref('form'); // form | verify | expired
const form = ref({ name: '', phone: '', email: '', password: '' });
const showPassword = ref(false);
const verification = ref(null);
const error = ref('');
const busy = ref(false);

const perks = [
    { n: '1', title: 'Дуртай газраа хадгалах', text: 'Жагсаалт үүсгэж, цагийн хуваарь өөрчлөгдөхөд мэдэгдэл авна.' },
    { n: '2', title: 'Сэтгэгдэл, зураг нэмэх', text: 'Баталгаажсан дугаартай хэрэглэгчийн сэтгэгдэл дээгүүр гарна.' },
    { n: '3', title: 'Бизнесээ бүртгэх', text: 'Нэг аккаунтаар олон салбар, менежерийн эрхийг удирдана.' },
    { n: '4', title: 'Залруулга мэдэгдэх', text: 'Буруу хаяг, хуучин дугаарыг зассан хувь нэмэр профайлд бүртгэгдэнэ.' },
];

function passwordScore() {
    const p = form.value.password;
    let s = 0;
    if (p.length >= 8) s++;
    if (/[a-zа-яөү]/i.test(p) && /[A-ZА-ЯӨҮ]/.test(p)) s++;
    if (/[0-9]/.test(p) || /[^a-zA-Z0-9а-яА-ЯөүӨҮ]/.test(p)) s++;
    if (p.length >= 12) s++;
    return s;
}

async function register() {
    error.value = '';
    busy.value = true;
    try {
        const data = await api.post('/auth/register', {
            name: form.value.name,
            phone: form.value.phone.replace(/\s/g, ''),
            email: form.value.email || undefined,
            password: form.value.password,
        });

        auth.setSession(data.token, data.user.data ?? data.user);

        if (data.verification.status === 'verified') {
            await auth.refresh();
            router.push({ name: 'home' });
            return;
        }

        verification.value = data.verification;
        step.value = 'verify';
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа. Дахин оролдоно уу.';
    } finally {
        busy.value = false;
    }
}

async function onVerified() {
    await auth.refresh();
    router.push({ name: 'home' });
}
</script>

<template>
    <div class="bg-hero px-4 py-10">
        <!-- Хоёр талт зохион байгуулалт (7a) -->
        <div v-if="step === 'form'" class="card mx-auto grid max-w-[940px] grid-cols-1 overflow-hidden md:grid-cols-[1fr_400px]">
            <div class="bg-white px-7 py-9 sm:px-10">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand text-[14px] font-extrabold text-white">Х</span>
                    <span class="text-[17px] font-extrabold text-ink">Хаана<span class="text-brand">.mn</span></span>
                    <span class="ml-auto text-[12.5px] font-medium text-soft">Аккаунт байгаа? <router-link :to="{ name: 'login' }" class="font-semibold text-brand">Нэвтрэх</router-link></span>
                </div>

                <h1 class="mt-7 text-[28px] font-extrabold tracking-[-.025em] text-ink">Бүртгүүлэх</h1>
                <p class="mt-2 max-w-[400px] text-[13.5px] leading-relaxed text-soft">Бизнесээ бүртгэх, дуртай газраа хадгалах, сэтгэгдэл бичихэд нэг аккаунт хүрэлцэнэ.</p>

                <form class="mt-6 grid max-w-[420px] grid-cols-1 gap-3" @submit.prevent="register">
                    <div>
                        <label class="field-label">Нэр</label>
                        <input v-model="form.name" type="text" placeholder="Энхжин" class="input" required maxlength="100" />
                    </div>
                    <div>
                        <label class="field-label">Утасны дугаар</label>
                        <div class="flex items-center gap-2.5 rounded-[9px] border border-inputline px-3.5 py-3 focus-within:border-brand focus-within:ring-2 focus-within:ring-bluetint">
                            <span class="font-mono text-[13px] text-mute">+976</span>
                            <span class="h-4 w-px bg-divider"></span>
                            <input v-model="form.phone" type="tel" inputmode="numeric" maxlength="9" placeholder="9911 2233" class="w-full bg-transparent text-[13px] font-medium text-ink outline-none placeholder:text-ph" required />
                        </div>
                        <p class="mt-1.5 text-[11.5px] text-mute">Дараагийн шатанд та энэ дугаараас код илгээж баталгаажуулна</p>
                    </div>
                    <div>
                        <label class="field-label">И-мэйл (сонголт)</label>
                        <input v-model="form.email" type="email" placeholder="enkhjin@example.mn" class="input" />
                    </div>
                    <div>
                        <label class="field-label">Нууц үг</label>
                        <div class="flex items-center rounded-[9px] border border-inputline px-3.5 py-3 focus-within:border-brand focus-within:ring-2 focus-within:ring-bluetint">
                            <input v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="••••••••••" class="w-full bg-transparent text-[13px] font-medium tracking-widest text-ink outline-none placeholder:text-ph" required minlength="8" />
                            <button type="button" class="cursor-pointer text-[12px] font-semibold text-brand" @click="showPassword = !showPassword">{{ showPassword ? 'Нуух' : 'Харах' }}</button>
                        </div>
                        <div class="mt-2 flex gap-1.5">
                            <div v-for="i in 4" :key="i" class="h-1 flex-1 rounded-full" :class="i <= passwordScore() ? 'bg-green' : 'bg-divider'"></div>
                        </div>
                        <p class="mt-1.5 text-[11.5px] text-mute">{{ passwordScore() >= 3 ? 'Сайн нууц үг' : '8+ тэмдэгт, том/жижиг үсэг, тоо' }}</p>
                    </div>

                    <p v-if="error" class="rounded-lg bg-redtint px-3 py-2 text-[12.5px] font-medium text-red">{{ error }}</p>

                    <div class="mt-1 flex items-start gap-2.5">
                        <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded bg-brand text-[10px] font-bold text-white">✓</span>
                        <span class="text-[12px] leading-relaxed text-soft"><span class="text-brand">Үйлчилгээний нөхцөл</span> ба <span class="text-brand">нууцлалын журам</span>-ыг зөвшөөрч байна</span>
                    </div>
                    <button type="submit" class="btn-primary mt-1 !rounded-[10px] !py-3.5" :disabled="busy">
                        {{ busy ? 'Илгээж байна…' : 'Бүртгүүлэх' }}
                    </button>
                </form>
            </div>

            <!-- Хар баганын танилцуулга -->
            <div class="hidden bg-dark px-8 py-9 md:block">
                <div class="text-[10.5px] font-semibold tracking-[.12em] text-bluelight">42,180 БИЗНЕС · 21 АЙМАГ</div>
                <div class="mt-3.5 text-[22px] font-extrabold leading-snug text-white">Хаана аккаунт танд юу өгөх вэ</div>
                <div class="mt-5 flex flex-col gap-4">
                    <div v-for="p in perks" :key="p.n" class="flex gap-3">
                        <span class="flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-lg bg-bluelight/15 text-[11px] font-bold text-bluelight">{{ p.n }}</span>
                        <div>
                            <div class="text-[13.5px] font-bold text-white">{{ p.title }}</div>
                            <div class="mt-1 text-[12.5px] leading-relaxed text-darkmute">{{ p.text }}</div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 border-t border-white/10 pt-4 text-[12.5px] leading-[1.7] text-darkmute">
                    Бизнес эзэн бол мөн адил энэ аккаунтаар бүртгэлээ удирдана — тусдаа хаяг шаардахгүй.
                </div>
            </div>
        </div>

        <!-- Баталгаажуулах (7c) -->
        <div v-else-if="step === 'verify'" class="card mx-auto max-w-[460px] bg-white p-8">
            <div class="flex items-center gap-2.5">
                <button class="cursor-pointer text-lg text-mute" @click="step = 'form'">←</button>
                <span class="text-[12.5px] font-semibold text-soft">Бүртгэл · 2 / 2</span>
                <div class="ml-auto flex gap-1.5">
                    <div class="h-1 w-[22px] rounded-full bg-brand"></div>
                    <div class="h-1 w-[22px] rounded-full bg-brand"></div>
                </div>
            </div>
            <h1 class="mt-6 text-[25px] font-extrabold leading-tight tracking-[-.025em] text-ink">Дугаараа баталгаажуулна уу</h1>
            <p class="mt-2 text-[13px] leading-[1.65] text-soft">
                Бид танд мессеж илгээхгүй. Та <b>{{ form.phone }}</b> дугаараасаа доорх текстийг илгээнэ. Баталгаажуулалтыг verify.mn гүйцэтгэнэ.
            </p>
            <div class="mt-4">
                <VerifyPanel :verification="verification" @verified="onVerified" @expired="step = 'expired'" />
            </div>
            <div class="my-5 h-px bg-divider"></div>
            <div class="flex items-center justify-between text-[12.5px] font-semibold">
                <button class="cursor-pointer text-brand" @click="register">Шинэ код авах</button>
                <button class="cursor-pointer text-soft" @click="step = 'form'">Дугаараа өөрчлөх</button>
            </div>
            <p class="mt-3.5 text-[11.5px] leading-relaxed text-mute">
                Мессеж илгээх боломжгүй бол <button class="cursor-pointer font-semibold text-brand" @click="router.push({ name: 'home' })">дараа баталгаажуулж</button>, аккаунтаа хязгаартай (сэтгэгдэл бичихгүй) хэрэглэж болно.
            </p>
        </div>

        <!-- Хугацаа дууссан -->
        <div v-else class="card mx-auto max-w-[460px] bg-white p-8 text-center">
            <h2 class="text-lg font-bold text-ink">Хугацаа дууслаа</h2>
            <p class="mt-2 text-[13px] text-soft">Баталгаажуулалтын хугацаа дууссан байна.</p>
            <button class="btn-primary mt-5 w-full" @click="register">Шинэ код авах</button>
            <button class="btn-outline mt-2 w-full" @click="router.push({ name: 'home' })">Дараа баталгаажуулах</button>
        </div>
    </div>
</template>
