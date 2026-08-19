<template>
    <div class="mx-auto max-w-md px-5 py-24 text-center">
        <p class="font-mono text-[52px] font-bold text-brand">404</p>
        <h1 class="mt-3 text-xl font-extrabold text-ink">Хуудас олдсонгүй</h1>
        <p class="mt-2 text-[13px] text-mute">Таны хайсан хуудас байхгүй эсвэл зөөгдсөн байна.</p>
        <router-link :to="{ name: 'home' }" class="btn-primary mt-7">Нүүр хуудас руу буцах</router-link>
    </div>
</template>
