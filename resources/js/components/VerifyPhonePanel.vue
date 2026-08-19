<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '../api';

/**
 * verify.mn MO SMS verification panel.
 *
 * Shows the instruction ("144773 дугаарт ... гэж SMS илгээнэ үү") plus a
 * one-tap sms: link, and polls the backend every 3 seconds until the session
 * becomes verified or expires. Emits `verified` with the poll response
 * (which contains token + user for registrations).
 */
const props = defineProps({
    verification: { type: Object, required: true },
});

const emit = defineEmits(['verified', 'expired']);

const status = ref(props.verification.status);
const secondsLeft = ref(0);
let pollTimer = null;
let countdownTimer = null;

const expiresAt = computed(() => (props.verification.expires_at ? new Date(props.verification.expires_at) : null));

function updateCountdown() {
    if (!expiresAt.value) return;
    secondsLeft.value = Math.max(0, Math.floor((expiresAt.value - Date.now()) / 1000));
}

async function poll() {
    try {
        const data = await api.get(`/auth/verifications/${props.verification.uuid}`);
        status.value = data.verification.status;

        if (status.value === 'verified') {
            stop();
            emit('verified', data);
        } else if (status.value === 'expired' || status.value === 'failed') {
            stop();
            emit('expired');
        }
    } catch {
        // transient network error — keep polling
    }
}

function stop() {
    clearInterval(pollTimer);
    clearInterval(countdownTimer);
}

onMounted(() => {
    updateCountdown();
    countdownTimer = setInterval(updateCountdown, 1000);
    // verify.mn minimum polling interval is 3 seconds.
    pollTimer = setInterval(poll, 3000);
    poll();
});

onBeforeUnmount(stop);
</script>

<template>
    <div class="card space-y-5 p-6 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-3xl">💬</div>

        <div>
            <h3 class="text-lg font-bold text-slate-900">Утасны дугаараа баталгаажуулна уу</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                {{ verification.display_instruction || 'Доорх товчийг дарж SMS илгээнэ үү' }}
            </p>
        </div>

        <a
            v-if="verification.sms_uri"
            :href="verification.sms_uri"
            class="btn-primary w-full !py-3 text-base"
        >
            📱 SMS илгээх
        </a>

        <div class="flex items-center justify-center gap-2 text-sm text-slate-500">
            <span class="relative flex h-2.5 w-2.5">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-75"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-brand-500"></span>
            </span>
            Баталгаажуулалтыг хүлээж байна...
            <span v-if="secondsLeft > 0" class="font-semibold text-slate-700">
                {{ Math.floor(secondsLeft / 60) }}:{{ String(secondsLeft % 60).padStart(2, '0') }}
            </span>
        </div>

        <p class="text-xs text-slate-400">
            SMS илгээсний дараа систем автоматаар баталгаажуулна. Нэг SMS-ийн үнэ: операторын тарифаар.
        </p>
    </div>
</template>
