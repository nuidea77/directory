<script setup>
import { RouterLink } from 'vue-router';
import { Search } from 'lucide-vue-next';

/**
 * Гүн ногоон дэвсгэртэй том уриалга — хуудсуудын төгсгөлд давтагдана.
 */
defineProps({
    title: { type: String, default: 'Хэрэгтэй бизнесээ хормын дотор олоорой' },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <section class="mx-auto max-w-7xl px-5 pt-12 sm:px-10">
        <div class="relative overflow-hidden rounded-[26px] bg-dark px-6 py-14 text-center sm:px-10">
            <!-- Чимэглэлийн нуман шугамууд -->
            <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 1200 320" preserveAspectRatio="none" aria-hidden="true">
                <g fill="none" stroke-linecap="round">
                    <path d="M-60 360 C 120 210, 240 60, 380 -60" stroke="#2fbf6f" stroke-width="16" opacity=".7" />
                    <path d="M1260 -40 C 1120 90, 1040 190, 900 360" stroke="#2fbf6f" stroke-width="14" opacity=".45" />
                    <path d="M1210 60 C 1130 140, 1090 210, 1030 360" stroke="#6fd79b" stroke-width="6" opacity=".35" />
                </g>
            </svg>

            <div class="relative">
                <span class="mx-auto flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-brandlite">
                    <Search :size="17" :stroke-width="2.2" aria-hidden="true" />
                </span>
                <h2 class="mx-auto mt-4 max-w-[620px] text-[26px] font-extrabold leading-[1.2] tracking-[-.02em] text-white sm:text-[32px]">{{ title }}</h2>
                <p v-if="subtitle" class="mx-auto mt-3 max-w-[540px] text-[13.5px] leading-relaxed text-darktext">{{ subtitle }}</p>

                <div class="mt-6 flex flex-wrap items-center justify-center gap-2.5">
                    <RouterLink :to="{ name: 'search' }" class="inline-flex items-center gap-2 rounded-[10px] bg-brandlite px-6 py-3 text-[13.5px] font-bold text-[#062a1c] transition hover:bg-white">
                        Бизнес хайх →
                    </RouterLink>
                    <RouterLink :to="{ name: 'add-business' }" class="inline-flex items-center gap-2 rounded-[10px] border border-white/25 px-6 py-3 text-[13.5px] font-bold text-white transition hover:bg-white/10">
                        Бизнесээ нэмэх
                    </RouterLink>
                </div>
            </div>
        </div>
    </section>
</template>
