<script setup>
import CategoryIcon from '../components/CategoryIcon.vue';
import { computed, onMounted, ref } from 'vue';
import { api } from '../api';

// Бүх ангиллын жагсаалт: үндсэн ангилал бүр дэд ангиллуудтайгаа карт хэлбэрээр
const categories = ref([]);
const loading = ref(true);

const totalBusinesses = computed(() => categories.value.reduce((s, c) => s + (c.businesses_count || 0), 0));

onMounted(async () => {
    try {
        const data = await api.get('/categories');
        categories.value = data.data;
    } finally {
        loading.value = false;
    }
});
import CtaBand from '../components/CtaBand.vue';
</script>

<template>
    <div class="bg-white">
        <!-- Breadcrumb -->
        <div class="mx-auto flex max-w-7xl gap-2 px-5 pt-4 text-[12.5px] font-medium text-mute sm:px-10">
            <router-link :to="{ name: 'home' }" class="text-brand">Ойрхон</router-link>
            <span>/</span>
            <span>Ангилал</span>
        </div>

        <!-- Толгой -->
        <div class="border-b border-line">
            <div class="mx-auto max-w-7xl px-5 pb-6 pt-3.5 sm:px-10">
                <div class="flex flex-wrap items-end justify-between gap-6">
                    <div class="max-w-[600px]">
                        <h1 class="text-[28px] font-extrabold leading-tight tracking-[-.025em] text-ink sm:text-[34px]">Бүх ангилал</h1>
                        <p class="mt-2 text-[14px] leading-relaxed text-soft">Хэрэгтэй үйлчилгээгээ ангиллаар нь олоорой — дэд ангилал руу шууд үсэрч болно.</p>
                    </div>
                    <div v-if="!loading" class="flex gap-6">
                        <div><div class="text-[22px] font-extrabold text-ink">{{ categories.length }}</div><div class="mt-0.5 text-[11.5px] font-medium text-mute">үндсэн ангилал</div></div>
                        <div class="w-px bg-line"></div>
                        <div><div class="text-[22px] font-extrabold text-ink">{{ totalBusinesses.toLocaleString() }}</div><div class="mt-0.5 text-[11.5px] font-medium text-mute">бизнес</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ангиллын картууд -->
        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-10">
            <div v-if="loading" class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="i in 6" :key="i" class="card h-40 animate-pulse"></div>
            </div>

            <div v-else class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="category in categories" :key="category.id" class="card flex flex-col p-5 transition hover:-translate-y-0.5 hover:border-blueline hover:shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] border border-blueline bg-bluetint text-brand">
                            <CategoryIcon :name="category.icon" :size="19" />
                        </div>
                        <div class="min-w-0">
                            <router-link :to="{ name: 'category', params: { slug: category.slug } }" class="text-[15px] font-bold text-ink hover:text-brand">{{ category.name }}</router-link>
                            <div class="mt-0.5 text-[12px] text-mute">{{ (category.businesses_count || 0).toLocaleString() }} бизнес</div>
                        </div>
                    </div>

                    <p v-if="category.description" class="mt-3 line-clamp-2 text-[12.5px] leading-relaxed text-soft">{{ category.description }}</p>

                    <!-- Дэд ангиллууд -->
                    <div v-if="category.children?.length" class="mt-3 flex flex-wrap gap-1.5">
                        <router-link
                            v-for="child in category.children.slice(0, 8)"
                            :key="child.id"
                            :to="{ name: 'category', params: { slug: child.slug } }"
                            class="rounded-full border border-searchline bg-search px-2.5 py-1 text-[11.5px] font-semibold text-body hover:border-brand hover:text-brand"
                        >{{ child.name }}<span v-if="child.businesses_count" class="ml-1 font-mono text-[10px] text-mute">{{ child.businesses_count }}</span></router-link>
                        <span v-if="category.children.length > 8" class="px-1 py-1 text-[11.5px] font-medium text-mute">+{{ category.children.length - 8 }}</span>
                    </div>

                    <router-link :to="{ name: 'category', params: { slug: category.slug } }" class="mt-auto pt-3.5 text-[12.5px] font-semibold text-brand hover:text-brand-dark">Бүгдийг үзэх →</router-link>
                </div>
            </div>

            <div v-if="!loading && !categories.length" class="p-16 text-center text-[13px] text-mute">Ангилал бүртгэгдээгүй байна</div>
        </div>
    
        <CtaBand />
    </div>
</template>
