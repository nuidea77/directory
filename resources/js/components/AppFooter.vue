<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api';

// Хөлийн ангиллын холбоос — SEO болон навигацид хэрэгтэй
const categories = ref([]);

onMounted(async () => {
    try {
        const data = await api.get('/categories');
        categories.value = (data.data || []).slice(0, 8);
    } catch {
        /* хөл ангиллын холбоосгүйгээр ч ажиллана */
    }
});
</script>

<template>
    <footer class="mt-12 bg-hero">
        <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-5 py-10 sm:px-10 lg:grid-cols-[1.4fr_1fr_1fr]">
            <div>
                <div class="flex items-center gap-2">
                    <span class="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] bg-brand text-[13px] font-extrabold text-white">Х</span>
                    <span class="text-[15px] font-extrabold text-ink">Хаана<span class="text-brand">.mn</span></span>
                </div>
                <p class="mt-3 max-w-[380px] text-[12.5px] leading-relaxed text-soft">
                    Монголын бизнесийн лавлах — хаяг, цагийн хуваарь, үнэлгээ, зураг бүхий баталгаажсан мэдээлэл.
                    Бизнесээ үнэгүй бүртгүүлээд хэрэглэгчдэд хүрээрэй.
                </p>
                <router-link :to="{ name: 'add-business' }" class="mt-4 inline-block rounded-[10px] bg-brand px-4 py-2.5 text-[12.5px] font-bold text-white transition hover:bg-brand-dark">
                    Бизнесээ нэмэх
                </router-link>
            </div>

            <div>
                <div class="text-[11px] font-semibold tracking-[.08em] text-mute">АНГИЛАЛ</div>
                <div class="mt-3 flex flex-col gap-2">
                    <router-link
                        v-for="c in categories"
                        :key="c.id"
                        :to="{ name: 'category', params: { slug: c.slug } }"
                        class="text-[12.5px] font-medium text-body hover:text-brand"
                    >{{ c.name }}</router-link>
                    <router-link :to="{ name: 'categories' }" class="text-[12.5px] font-semibold text-brand hover:text-brand-dark">Бүх ангилал →</router-link>
                </div>
            </div>

            <div>
                <div class="text-[11px] font-semibold tracking-[.08em] text-mute">ХААНА.MN</div>
                <div class="mt-3 flex flex-col gap-2 text-[12.5px] font-medium text-body">
                    <router-link :to="{ name: 'search' }" class="hover:text-brand">Бүх бизнес</router-link>
                    <router-link :to="{ name: 'pricing' }" class="hover:text-brand">Зар, эрхийн бичиг</router-link>
                    <router-link :to="{ name: 'terms' }" class="hover:text-brand">Үйлчилгээний нөхцөл</router-link>
                    <router-link :to="{ name: 'privacy' }" class="hover:text-brand">Нууцлалын бодлого</router-link>
                    <a href="tel:70111414" class="hover:text-brand">Холбоо барих · 7011-1414</a>
                </div>
            </div>
        </div>

        <div class="border-t border-line">
            <div class="mx-auto max-w-7xl px-5 py-4 text-[12px] text-mute sm:px-10">
                © {{ new Date().getFullYear() }} Хаана.mn — Монголын бизнес лавлах
            </div>
        </div>
    </footer>
</template>
