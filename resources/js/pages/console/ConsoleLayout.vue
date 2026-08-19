<script setup>
import { onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useConsoleStore } from '../../stores/console';

const store = useConsoleStore();
const router = useRouter();

// Дугаар баталгаажаагүй бол баталгаажуулах хуудас руу
watch(
    () => store.error,
    (err) => {
        if (err === 'phone_unverified') router.replace({ name: 'verify' });
    },
    { immediate: true },
);

const nav = [
    { name: 'Салбарууд', route: 'console' },
    { name: 'Статистик', route: 'console-stats' },
    { name: 'Сэтгэгдэл', route: 'console-reviews' },
    { name: 'Эрх ба сурталчилгаа', route: 'console-plan' },
    { name: 'Нэхэмжлэх', route: 'console-invoices' },
    { name: 'Тохиргоо', route: 'console-settings' },
];

onMounted(() => store.load());
</script>

<template>
    <div class="flex min-h-[calc(100vh-62px)] bg-page">
        <!-- Sidebar (4b) -->
        <aside class="hidden w-[206px] shrink-0 border-r border-line bg-white px-3.5 py-4 md:block">
            <div class="flex items-center gap-2 px-1.5 pb-4">
                <span class="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] bg-brand text-[13px] font-extrabold text-white">Х</span>
                <span class="text-[15px] font-extrabold text-ink">Бизнес зөвлөл</span>
            </div>

            <div v-if="store.organization" class="rounded-[10px] border border-line bg-panel p-3">
                <div class="text-[10px] font-semibold tracking-[.08em] text-mute">БАЙГУУЛЛАГА</div>
                <div class="mt-1.5 flex items-center gap-2">
                    <select
                        v-if="store.organizations.length > 1"
                        v-model="store.selectedId"
                        class="w-full cursor-pointer bg-transparent text-[12.5px] font-bold text-ink outline-none"
                    >
                        <option v-for="o in store.organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                    <span v-else class="text-[12.5px] font-bold text-ink">{{ store.organization.name }}</span>
                </div>
                <div class="mt-1.5 flex items-center gap-1.5">
                    <span class="badge-verified !border-blueline !px-1.5 !py-0.5 !text-[9.5px] uppercase">{{ store.organization.plan_name }}</span>
                    <span class="text-[11px] font-medium text-mute">{{ store.branches.length }} салбар</span>
                </div>
            </div>

            <nav class="mt-3.5 flex flex-col gap-0.5">
                <router-link
                    v-for="item in nav"
                    :key="item.route"
                    :to="{ name: item.route }"
                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[12.5px] font-semibold"
                    :class="$route.name === item.route ? 'bg-bluetint text-brand' : 'text-body hover:bg-panel'"
                >
                    <span class="h-4 w-4 rounded-[5px]" :class="$route.name === item.route ? 'bg-brand' : 'bg-searchline'"></span>
                    {{ item.name }}
                </router-link>
            </nav>

            <div v-if="store.organization?.effective_plan === 'free'" class="mt-4 rounded-[10px] border border-blueline bg-bluetint p-3">
                <div class="text-[12px] font-bold text-ink">Үнэгүй эрх · 1 жил</div>
                <div class="mt-1 text-[11.5px] leading-normal text-body">Салбар нэмэх, аналитик Стандарт эрхээс нээгдэнэ.</div>
                <router-link :to="{ name: 'console-plan' }" class="mt-2.5 block rounded-[7px] bg-brand py-2 text-center text-[11.5px] font-bold text-white">Эрх авах</router-link>
            </div>
        </aside>

        <!-- Мобайл таб -->
        <div class="min-w-0 flex-1">
            <div class="flex gap-4 overflow-x-auto border-b border-line bg-white px-4 py-3 text-[13px] font-semibold text-mute md:hidden">
                <router-link v-for="item in nav" :key="item.route" :to="{ name: item.route }" class="whitespace-nowrap" :class="{ 'text-brand': $route.name === item.route }">{{ item.name }}</router-link>
            </div>

            <div v-if="store.error === 'failed'" class="p-10 text-center">
                <p class="text-[15px] font-bold text-ink">Ачаалахад алдаа гарлаа</p>
                <p class="mt-1.5 text-[13px] text-mute">Интернэт холболтоо шалгаад дахин оролдоно уу.</p>
                <button class="btn-primary mt-5" :disabled="store.loading" @click="store.load(true)">{{ store.loading ? 'Ачаалж байна…' : 'Дахин оролдох' }}</button>
            </div>
            <div v-else-if="store.loaded && !store.organization" class="p-10 text-center">
                <p class="text-[15px] font-bold text-ink">Танд бүртгэлтэй бизнес алга</p>
                <p class="mt-1.5 text-[13px] text-mute">Эхний бизнесээ бүртгүүлээд хэрэглэгчдэд хүрээрэй.</p>
                <router-link :to="{ name: 'add-business' }" class="btn-primary mt-5">Бизнес нэмэх</router-link>
            </div>
            <router-view v-else-if="store.loaded" />
            <div v-else class="space-y-4 p-7">
                <div class="card h-24 animate-pulse bg-white"></div>
                <div class="card h-48 animate-pulse bg-white"></div>
            </div>
        </div>
    </div>
</template>
