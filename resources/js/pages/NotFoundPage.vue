<template>
    <div class="mx-auto max-w-md px-4 py-24 text-center">
        <p class="text-6xl font-extrabold text-brand-600">404</p>
        <h1 class="mt-4 text-xl font-bold text-slate-900">Хуудас олдсонгүй</h1>
        <p class="mt-2 text-sm text-slate-500">Таны хайсан хуудас байхгүй эсвэл зөөгдсөн байна.</p>
        <router-link :to="{ name: 'home' }" class="btn-primary mt-8">Нүүр хуудас руу буцах</router-link>
    </div>
</template>
