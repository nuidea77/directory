<script setup>
// Админ layout (4a/9b): хар sidebar
const nav = [
    { name: 'Модерац', route: 'admin' },
    { name: 'Бизнесүүд', route: 'admin-businesses' },
    { name: 'Сэтгэгдэл, залруулга', route: 'admin-reviews' },
    { name: 'Сурталчилгаа', route: 'admin-ads' },
    { name: 'Эрхийн бичиг', route: 'admin-plans' },
    { name: 'Ангилал', route: 'admin-categories' },
    { name: 'Эрх, орлого', route: 'admin-revenue' },
];
</script>

<template>
    <div class="flex min-h-[calc(100vh-62px)] bg-page">
        <aside class="hidden w-[212px] shrink-0 bg-dark px-3.5 py-4 md:block">
            <div class="flex items-center gap-2 px-1.5 pb-4">
                <span class="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] bg-brand text-[13px] font-extrabold text-white">Х</span>
                <span class="text-[15px] font-extrabold text-white">Хаана</span>
                <span class="rounded-[4px] bg-bluelight/15 px-1.5 py-0.5 text-[10px] font-semibold text-bluelight">АДМИН</span>
            </div>
            <nav class="flex flex-col gap-0.5">
                <router-link
                    v-for="item in nav"
                    :key="item.route"
                    :to="{ name: item.route }"
                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[12.5px] font-semibold"
                    :class="$route.name === item.route ? 'bg-white/10 text-white' : 'text-darktext hover:bg-white/5'"
                >
                    <span class="h-4 w-4 rounded-[5px]" :class="$route.name === item.route ? 'bg-brand' : 'bg-[#3d454e]'"></span>
                    {{ item.name }}
                </router-link>
            </nav>
        </aside>

        <div class="min-w-0 flex-1">
            <div class="flex gap-4 overflow-x-auto border-b border-line bg-white px-4 py-3 text-[13px] font-semibold text-mute md:hidden">
                <router-link v-for="item in nav" :key="item.route" :to="{ name: item.route }" :class="{ 'text-brand': $route.name === item.route }">{{ item.name }}</router-link>
            </div>
            <router-view />
        </div>
    </div>
</template>
