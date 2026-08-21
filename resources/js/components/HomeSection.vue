<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { Clock, Heart, Sparkles, Store, Utensils } from 'lucide-vue-next';
import BizLogo from './BizLogo.vue';
import VerifiedBadge from './VerifiedBadge.vue';
import SectionHeader from './SectionHeader.vue';

/**
 * Нүүрийн сэдэвчилсэн блок: «Хаана хооллох вэ?», «Болзоход тохиромжтой» гэх мэт.
 * Мөр карт — эрэмбийн дугаар, лого/зураг, нэр, үнэлгээ, үнэ, байршил, нээлттэй эсэх.
 */
const props = defineProps({
    section: { type: Object, required: true },
    loading: { type: Boolean, default: false },
});

const ICONS = { utensils: Utensils, heart: Heart, clock: Clock, sparkles: Sparkles };

const icon = computed(() => ICONS[props.section.icon] || Store);

const to = computed(() => {
    const l = props.section.link || {};
    return l.name === 'category'
        ? { name: 'category', params: { slug: l.slug } }
        : { name: 'search', query: l.query || {} };
});
</script>

<template>
    <section class="mx-auto max-w-7xl px-5 pt-10 sm:px-10">
        <SectionHeader :title="section.title" :subtitle="section.subtitle" :icon="icon" :to="to" />

        <div class="mt-3 grid grid-cols-1 gap-x-7 sm:grid-cols-2 lg:grid-cols-3">
            <RouterLink
                v-for="(item, i) in section.items"
                :key="item.id"
                :to="{ name: 'business', params: { slug: item.slug } }"
                class="group flex items-center gap-3 border-b border-hairline py-2.5 transition last:border-0"
            >
                <span class="w-4 shrink-0 text-right font-mono text-[11.5px] text-faint">{{ i + 1 }}</span>

                <div class="h-[52px] w-[52px] shrink-0 overflow-hidden rounded-[10px] border border-line bg-white">
                    <img v-if="item.cover_url" :src="item.cover_url" :alt="item.name" class="h-full w-full object-cover" loading="lazy" />
                    <BizLogo v-else :business="item" size="h-full w-full rounded-none text-[15px]" />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="truncate text-[13.5px] font-bold text-ink group-hover:text-brand">{{ item.name }}</span>
                        <VerifiedBadge v-if="item.is_verified" :size="13" />
                        <span v-if="item.is_featured" class="badge-featured shrink-0">ОНЦЛОХ</span>
                    </div>

                    <div class="mt-1 flex items-center gap-1.5 text-[12px]">
                        <template v-if="item.reviews_count">
                            <span class="text-[11px] text-amberdot">★</span>
                            <span class="font-bold text-ink">{{ item.rating_avg.toFixed(1) }}</span>
                            <span class="text-mute">({{ item.reviews_count }})</span>
                        </template>
                        <span v-else class="text-mute">Шинэ</span>

                        <span class="text-[#c9ccd1]">·</span>
                        <span v-if="item.is_24_7" class="font-semibold text-green">24 цаг</span>
                        <span v-else class="font-semibold" :class="item.is_open ? 'text-green' : 'text-amberdark'">{{ item.is_open ? 'Нээлттэй' : 'Хаалттай' }}</span>
                    </div>

                    <div class="mt-0.5 flex items-center gap-1.5 truncate text-[11.5px] text-mute">
                        <span v-if="item.price_level" class="font-semibold text-body">{{ item.price_level }}</span>
                        <span v-if="item.price_level" class="text-[#c9ccd1]">·</span>
                        <span class="truncate">{{ item.district }}{{ item.category ? ' · ' + item.category : '' }}</span>
                    </div>
                </div>
            </RouterLink>
        </div>
    </section>
</template>
