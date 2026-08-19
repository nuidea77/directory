<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

// Дугаар амжилттай баталгаажсаны дараах дэлгэц —
// хэдэн секундын дараа нүүр хуудас руу автоматаар шилжинэ
const props = defineProps({
    name: { type: String, default: '' },
    phone: { type: String, default: '' },
});

const router = useRouter();
const seconds = ref(8);
let timer = null;

function go(target) {
    clearInterval(timer);
    router.push(target);
}

onMounted(() => {
    timer = setInterval(() => {
        seconds.value--;
        if (seconds.value <= 0) go({ name: 'home' });
    }, 1000);
});

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <div class="card mx-auto max-w-[460px] bg-white p-8 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-greentint">
            <svg class="h-8 w-8 text-green" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1 class="mt-5 text-[24px] font-extrabold leading-tight tracking-[-.025em] text-ink">Таны мэдээлэл амжилттай баталгаажлаа</h1>
        <p class="mt-2.5 text-[13.5px] leading-relaxed text-soft">
            {{ name ? name + ', тавтай' : 'Тавтай' }} морил! <b class="text-ink">{{ phone }}</b> дугаар баталгаажиж,
            сэтгэгдэл бичих, бизнесээ бүртгэх бүрэн эрхтэй боллоо.
        </p>

        <div class="mt-6 flex flex-col gap-2">
            <button class="btn-primary w-full cursor-pointer !rounded-[10px] !py-3.5" @click="go({ name: 'home' })">Нүүр хуудас руу</button>
            <button class="btn-outline w-full cursor-pointer !rounded-[10px] !py-3.5" @click="go({ name: 'add-business' })">Бизнесээ бүртгүүлэх</button>
        </div>

        <p class="mt-4 text-[11.5px] font-medium text-mute">{{ seconds }} секундын дараа нүүр хуудас руу шилжинэ</p>
    </div>
</template>
