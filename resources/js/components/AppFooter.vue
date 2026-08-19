<template>
    <footer class="bg-dark">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-5 py-7 sm:flex-row sm:px-10">
            <p class="text-[13px] text-darkmute">© {{ new Date().getFullYear() }} Хаана.mn — Монголын бизнес лавлах</p>
            <div class="flex gap-5 text-[13px] font-medium text-[#e9e6df]">
                <router-link :to="{ name: 'add-business' }" class="hover:text-white">Бизнесээ нэмэх</router-link>
                <router-link :to="{ name: 'pricing' }" class="hover:text-white">Зар</router-link>
                <a href="tel:70111414" class="hover:text-white">Холбоо барих</a>
            </div>
        </div>
    </footer>
</template>
