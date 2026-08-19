<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { api } from '../api';

/**
 * verify.mn MO SMS баталгаажуулалт (7c дизайн):
 * хэрэглэгч кодоо verify.mn-ийн богино дугаарт өөрөө илгээнэ,
 * бид 3 секунд тутам төлөв poll хийнэ.
 */
const props = defineProps({
    verification: { type: Object, required: true },
});

const emit = defineEmits(['verified', 'expired']);

const secondsLeft = ref(0);
let pollTimer = null;
let countdownTimer = null;
let deadline = null;

// Серверийн өгсөн үлдсэн секундээс deadline тооцно — хэрэглэгчийн
// компьютерийн цаг зөрүүтэй байсан ч countdown зөв явна
function computeDeadline() {
    if (props.verification.expires_in !== null && props.verification.expires_in !== undefined) {
        return Date.now() + props.verification.expires_in * 1000;
    }
    return props.verification.expires_at ? new Date(props.verification.expires_at).getTime() : null;
}

// smsUri: "sms:144773?body=482916" → дугаар ба текст задлах
const smsParts = computed(() => {
    const uri = props.verification.sms_uri || '';
    const m = uri.match(/^sms:([^?]+)\?body=(.+)$/);
    return m ? { number: m[1], text: decodeURIComponent(m[2]) } : null;
});

function updateCountdown() {
    if (deadline === null) return;
    secondsLeft.value = Math.max(0, Math.floor((deadline - Date.now()) / 1000));

    // 00:00 болмогц серверээс төлөв асууж, дуусвал expired event өгнө
    if (secondsLeft.value === 0) {
        clearInterval(countdownTimer);
        poll();
    }
}

const countdown = computed(() => {
    const m = Math.floor(secondsLeft.value / 60);
    const s = String(secondsLeft.value % 60).padStart(2, '0');
    return `${String(m).padStart(2, '0')}:${s}`;
});

async function poll() {
    try {
        const data = await api.get(`/auth/verifications/${props.verification.uuid}`);
        const status = data.verification.status;

        if (status === 'verified') {
            stop();
            emit('verified', data);
        } else if (status === 'expired' || status === 'failed') {
            stop();
            emit('expired');
        }
    } catch {
        // түр зуурын алдаа — үргэлжлүүлэн poll хийнэ
    }
}

function stop() {
    clearInterval(pollTimer);
    clearInterval(countdownTimer);
}

function copyText() {
    if (smsParts.value) navigator.clipboard?.writeText(smsParts.value.text);
}

function startTimers() {
    stop();
    deadline = computeDeadline();
    updateCountdown();
    countdownTimer = setInterval(updateCountdown, 1000);
    pollTimer = setInterval(poll, 3000); // verify.mn доод хязгаар 3с
    poll();
}

// «Шинэ код авах» гэх мэтээр шинэ verification ирэхэд таймерууд шинээр эхэлнэ
watch(() => props.verification.uuid, startTimers);

onMounted(startTimers);

onBeforeUnmount(stop);
</script>

<template>
    <div>
        <div class="rounded-xl border-[1.5px] border-brand bg-bluecard p-[18px]">
            <div v-if="smsParts" class="flex items-center gap-3.5">
                <div>
                    <div class="text-[10px] font-semibold tracking-[.1em] text-brand">ТЕКСТ</div>
                    <div class="mt-1.5 font-mono text-[22px] font-bold text-ink">{{ smsParts.text }}</div>
                </div>
                <div class="w-px self-stretch bg-blueline"></div>
                <div>
                    <div class="text-[10px] font-semibold tracking-[.1em] text-brand">ХҮЛЭЭН АВАГЧ · VERIFY.MN</div>
                    <div class="mt-1.5 font-mono text-[22px] font-bold text-ink">{{ smsParts.number }}</div>
                </div>
                <div class="ml-auto flex flex-col gap-1.5">
                    <a :href="verification.sms_uri" class="cursor-pointer rounded-lg bg-brand px-3.5 py-2.5 text-center text-[12px] font-bold leading-tight text-white hover:bg-brand-dark">Мессеж<br>апп нээх</a>
                    <button class="cursor-pointer rounded-lg border border-blueline bg-white px-3 py-1.5 text-[11.5px] font-semibold text-brand" @click="copyText">Хуулах</button>
                </div>
            </div>
            <div v-else class="text-[13px] font-medium text-body">
                {{ verification.display_instruction || 'DEV горим: баталгаажуулалт автоматаар хийгдэнэ' }}
            </div>
        </div>

        <div class="mt-3.5 flex items-center gap-3 rounded-[11px] border border-line bg-panel p-3.5">
            <div class="h-[18px] w-[18px] shrink-0 animate-spin rounded-full border-2 border-brand border-t-transparent"></div>
            <div>
                <div class="text-[12.5px] font-bold text-ink">Мессежийг хүлээж байна…</div>
                <div class="mt-0.5 text-[11.5px] text-mute">Ирсэн даруйд автоматаар үргэлжилнэ</div>
            </div>
            <div class="ml-auto font-mono text-[12px] font-medium text-soft">{{ countdown }}</div>
        </div>

        <div class="mt-5 flex flex-col gap-2.5">
            <div class="flex gap-2.5">
                <div class="mt-px flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full border border-blueline bg-bluetint text-[10px] font-bold text-brand">1</div>
                <div class="text-[12.5px] leading-relaxed text-body">Мессеж апп нээж, хүлээн авагчийг <b class="font-mono">{{ smsParts?.number || 'verify.mn дугаар' }}</b> болгоно.</div>
            </div>
            <div class="flex gap-2.5">
                <div class="mt-px flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full border border-blueline bg-bluetint text-[10px] font-bold text-brand">2</div>
                <div class="text-[12.5px] leading-relaxed text-body"><b class="font-mono">{{ smsParts?.text || 'кодоо' }}</b> гэж бичээд илгээнэ — нэмэлт тэмдэгт шаардлагагүй.</div>
            </div>
            <div class="flex gap-2.5">
                <div class="mt-px flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full border border-blueline bg-bluetint text-[10px] font-bold text-brand">3</div>
                <div class="text-[12.5px] leading-relaxed text-body">verify.mn мессежийг автоматаар танина, кодыг гараар оруулах шаардлагагүй.</div>
            </div>
        </div>
    </div>
</template>
