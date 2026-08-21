<script setup>
/**
 * Админы хуудасны нэгдсэн толгой: гарчиг, тайлбар, тоон тойм, үйлдлүүд.
 * Таб бүр өөр өөрийн хэлбэрээр бичдэг байсныг нэгтгэв.
 */
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    // [{ label: '22 үндсэн' }, ...] — гарчгийн хажуугийн жижиг тоонууд
    meta: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-4 pb-5">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-[19px] font-extrabold tracking-[-.02em] text-ink">{{ title }}</h1>
                <span
                    v-for="m in meta"
                    :key="m.label"
                    class="rounded-full bg-chip px-2.5 py-0.5 text-[11.5px] font-semibold text-chiptext"
                >{{ m.label }}</span>
            </div>
            <p v-if="description" class="mt-1.5 max-w-[640px] text-[13px] leading-relaxed text-soft">{{ description }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <slot name="actions" />
        </div>
    </div>
</template>
