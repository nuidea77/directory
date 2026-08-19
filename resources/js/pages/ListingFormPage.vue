<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../api';

const route = useRoute();
const router = useRouter();

const isEdit = computed(() => Boolean(route.params.id));
const categories = ref([]);
const listing = ref(null);
const error = ref('');
const submitting = ref(false);
const uploadingImages = ref(false);

const form = ref({
    name: '',
    category_id: '',
    phone: '',
    description: '',
    email: '',
    website: '',
    address: '',
    district: '',
    opening_hours: { mon: '', tue: '', wed: '', thu: '', fri: '', sat: '', sun: '' },
    socials: { facebook: '', instagram: '' },
});

const logoFile = ref(null);
const coverFile = ref(null);

const districts = ['Сүхбаатар', 'Чингэлтэй', 'Баянгол', 'Баянзүрх', 'Хан-Уул', 'Сонгинохайрхан', 'Налайх', 'Багануур'];
const weekdays = { mon: 'Даваа', tue: 'Мягмар', wed: 'Лхагва', thu: 'Пүрэв', fri: 'Баасан', sat: 'Бямба', sun: 'Ням' };

onMounted(async () => {
    const cats = await api.get('/categories');
    categories.value = cats.data;

    if (isEdit.value) {
        const data = await api.get(`/my/listings/${route.params.id}`);
        listing.value = data.data;
        const l = listing.value;
        form.value = {
            name: l.name,
            category_id: l.category?.id ?? '',
            phone: l.phone,
            description: l.description || '',
            email: l.email || '',
            website: l.website || '',
            address: l.address || '',
            district: l.district || '',
            opening_hours: { mon: '', tue: '', wed: '', thu: '', fri: '', sat: '', sun: '', ...(l.opening_hours || {}) },
            socials: { facebook: '', instagram: '', ...(l.socials || {}) },
        };
    }
});

function buildFormData() {
    const fd = new FormData();
    const f = form.value;

    fd.append('name', f.name);
    fd.append('category_id', f.category_id);
    fd.append('phone', f.phone);
    if (f.description) fd.append('description', f.description);
    if (f.email) fd.append('email', f.email);
    if (f.website) fd.append('website', f.website);
    if (f.address) fd.append('address', f.address);
    if (f.district) fd.append('district', f.district);

    for (const [day, hours] of Object.entries(f.opening_hours)) {
        if (hours) fd.append(`opening_hours[${day}]`, hours);
    }
    for (const [key, url] of Object.entries(f.socials)) {
        if (url) fd.append(`socials[${key}]`, url);
    }

    if (logoFile.value) fd.append('logo', logoFile.value);
    if (coverFile.value) fd.append('cover', coverFile.value);

    return fd;
}

async function submit() {
    error.value = '';
    submitting.value = true;
    try {
        const url = isEdit.value ? `/my/listings/${route.params.id}` : '/my/listings';
        const data = await api.postForm(url, buildFormData());
        listing.value = data.data;

        if (!isEdit.value) {
            router.push({ name: 'listing-edit', params: { id: data.data.id } });
        }
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа. Дахин оролдоно уу.';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
        submitting.value = false;
    }
}

async function uploadGallery(event) {
    const files = Array.from(event.target.files || []);
    if (!files.length || !listing.value) return;

    uploadingImages.value = true;
    try {
        const fd = new FormData();
        files.forEach((f) => fd.append('images[]', f));
        const data = await api.postForm(`/my/listings/${listing.value.id}/images`, fd);
        listing.value = data.data;
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Зураг хуулахад алдаа гарлаа';
    } finally {
        uploadingImages.value = false;
        event.target.value = '';
    }
}

async function deleteImage(image) {
    await api.delete(`/my/listings/${listing.value.id}/images/${image.id}`);
    listing.value.images = listing.value.images.filter((i) => i.id !== image.id);
}
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <h1 class="text-2xl font-bold text-slate-900">{{ isEdit ? 'Бизнес засах' : 'Бизнес нэмэх' }}</h1>
        <p class="mt-1 text-sm text-slate-500">Мэдээллээ бүрэн бөглөх тусам илүү олон хэрэглэгчид хүрнэ.</p>

        <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

        <form class="mt-6 space-y-6" @submit.prevent="submit">
            <div class="card space-y-4 p-6">
                <h2 class="font-bold text-slate-900">Үндсэн мэдээлэл</h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Байгууллагын нэр *</label>
                        <input v-model="form.name" type="text" class="input" required maxlength="150" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Ангилал *</label>
                        <select v-model="form.category_id" class="input" required>
                            <option value="" disabled>Сонгоно уу</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.icon }} {{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Утас *</label>
                        <input v-model="form.phone" type="tel" inputmode="numeric" class="input" required pattern="[0-9]{6,11}" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">И-мэйл</label>
                        <input v-model="form.email" type="email" class="input" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Вэбсайт</label>
                        <input v-model="form.website" type="url" placeholder="https://" class="input" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Танилцуулга</label>
                        <textarea v-model="form.description" rows="4" class="input resize-none" maxlength="5000"></textarea>
                    </div>
                </div>
            </div>

            <div class="card space-y-4 p-6">
                <h2 class="font-bold text-slate-900">Байршил</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Дүүрэг</label>
                        <select v-model="form.district" class="input">
                            <option value="">Сонгоно уу</option>
                            <option v-for="d in districts" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Хаяг</label>
                        <input v-model="form.address" type="text" class="input" maxlength="255" />
                    </div>
                </div>
            </div>

            <div class="card space-y-4 p-6">
                <h2 class="font-bold text-slate-900">Цагийн хуваарь</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div v-for="(label, key) in weekdays" :key="key" class="flex items-center gap-3">
                        <span class="w-16 shrink-0 text-sm text-slate-600">{{ label }}</span>
                        <input v-model="form.opening_hours[key]" type="text" placeholder="09:00 - 18:00" class="input" />
                    </div>
                </div>
            </div>

            <div class="card space-y-4 p-6">
                <h2 class="font-bold text-slate-900">Сошиал хаягууд</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <input v-model="form.socials.facebook" type="url" placeholder="Facebook хаяг" class="input" />
                    <input v-model="form.socials.instagram" type="url" placeholder="Instagram хаяг" class="input" />
                </div>
            </div>

            <div class="card space-y-4 p-6">
                <h2 class="font-bold text-slate-900">Зургууд</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Лого (2MB хүртэл)</label>
                        <input type="file" accept="image/*" class="input !py-2" @change="logoFile = $event.target.files[0]" />
                        <img v-if="listing?.logo_url" :src="listing.logo_url" class="mt-2 h-16 w-16 rounded-xl border object-cover" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Ковер зураг (4MB хүртэл)</label>
                        <input type="file" accept="image/*" class="input !py-2" @change="coverFile = $event.target.files[0]" />
                        <img v-if="listing?.cover_url" :src="listing.cover_url" class="mt-2 h-16 w-28 rounded-xl border object-cover" />
                    </div>
                </div>

                <template v-if="isEdit && listing">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Галерей ({{ listing.images?.length || 0 }}/10)</label>
                        <input type="file" accept="image/*" multiple class="input !py-2" :disabled="uploadingImages" @change="uploadGallery" />
                    </div>
                    <div v-if="listing.images?.length" class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                        <div v-for="img in listing.images" :key="img.id" class="group relative">
                            <img :src="img.url" class="h-20 w-full rounded-lg object-cover" />
                            <button
                                type="button"
                                class="absolute -right-1.5 -top-1.5 hidden h-6 w-6 items-center justify-center rounded-full bg-red-500 text-xs text-white shadow group-hover:flex"
                                @click="deleteImage(img)"
                            >✕</button>
                        </div>
                    </div>
                </template>
                <p v-else class="text-xs text-slate-400">Галерейн зургуудыг бүртгэл үүссэний дараа нэмэх боломжтой.</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn-primary !px-8 !py-3" :disabled="submitting">
                    {{ submitting ? 'Хадгалж байна...' : isEdit ? 'Хадгалах' : 'Бүртгүүлэх' }}
                </button>
                <router-link :to="{ name: 'dashboard' }" class="btn-secondary !py-3">Буцах</router-link>
            </div>
        </form>
    </div>
</template>
