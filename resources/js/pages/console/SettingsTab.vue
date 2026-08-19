<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api, ApiError } from '../../api';
import { useConsoleStore } from '../../stores/console';
import BizLogo from '../../components/BizLogo.vue';

// Байгууллагын нийтлэг мэдээлэл (нэр, регистр, лого, ангилал, танилцуулга)
const store = useConsoleStore();

const orgForm = ref({ name: '', registration_number: '' });
const bizForm = ref({});
const msg = ref({ type: '', text: '' });
const categories = ref([]);
const selectedBusinessId = ref(null);
const logoFile = ref(null);
const logoUploading = ref(false);

const business = computed(() => store.businesses.find((b) => b.id === selectedBusinessId.value) || store.businesses[0]);

function syncForms() {
    if (store.organization) {
        orgForm.value = {
            name: store.organization.name,
            registration_number: store.organization.registration_number || '',
        };
    }
    if (business.value) {
        bizForm.value = {
            name: business.value.name,
            category_id: business.value.category?.id || '',
            description: business.value.description || '',
            website: business.value.website || '',
            facebook: business.value.facebook || '',
            instagram: business.value.instagram || '',
            price_level: business.value.price_level || '₮₮',
        };
    }
}

async function saveOrg() {
    msg.value = { type: '', text: '' };
    try {
        await api.put(`/console/organizations/${store.organization.id}`, orgForm.value);
        await store.load(true);
        msg.value = { type: 'ok', text: 'Байгууллагын мэдээлэл хадгалагдлаа.' };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    }
}

async function saveBusiness() {
    msg.value = { type: '', text: '' };
    try {
        // Лого файлтай тул multipart-аар илгээнэ
        const fd = new FormData();
        // Хоосон болгосон талбарыг ч илгээнэ — эс бөгөөс устгасан вэб сайт,
        // тайлбар зэрэг хадгалагдахгүй хуучнаараа үлддэг (backend-д nullable)
        Object.entries(bizForm.value).forEach(([k, v]) => fd.append(k, v ?? ''));
        if (logoFile.value) fd.append('logo', logoFile.value);

        await api.postForm(`/console/businesses/${business.value.id}`, fd);
        logoFile.value = null;
        await store.load(true);
        msg.value = { type: 'ok', text: 'Бизнесийн мэдээлэл хадгалагдлаа.' };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    }
}

async function deleteBusiness() {
    const check = prompt(`Бизнесийг бүх салбар, сэтгэгдэлтэй нь БҮРМӨСӨН устгана. Баталгаажуулахын тулд «${business.value.name}» гэж бичнэ үү:`);
    if (check !== business.value.name) return;

    try {
        await api.delete(`/console/businesses/${business.value.id}`);
        selectedBusinessId.value = null;
        await store.load(true);
        msg.value = { type: 'ok', text: 'Бизнес устгагдлаа.' };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Устгахад алдаа гарлаа' };
    }
}

watch(() => store.organization?.id, syncForms, { immediate: true });
watch(selectedBusinessId, syncForms);

onMounted(async () => {
    const cats = await api.get('/categories');
    categories.value = cats.data;
});
</script>

<template>
    <div class="max-w-2xl p-5 sm:p-7">
        <h1 class="text-xl font-extrabold tracking-[-.02em] text-ink">Тохиргоо</h1>
        <p class="mt-1 text-[12.5px] text-mute">Нэр, лого, ангилал, тайлбар — байгууллагын хэмжээнд. Хаяг, утас, цаг — салбар тус бүрт.</p>

        <p v-if="msg.text" class="mt-4 rounded-lg px-4 py-2.5 text-[13px] font-medium" :class="msg.type === 'ok' ? 'bg-greentint text-green' : 'bg-redtint text-red'">{{ msg.text }}</p>

        <div class="card mt-4 p-5">
            <div class="text-[15px] font-bold text-ink">Байгууллага</div>
            <form class="mt-4 grid grid-cols-1 gap-3.5 sm:grid-cols-2" @submit.prevent="saveOrg">
                <div>
                    <label class="field-label">Байгууллагын нэр</label>
                    <input v-model="orgForm.name" type="text" class="input" required />
                </div>
                <div>
                    <label class="field-label">Улсын бүртгэлийн дугаар</label>
                    <input v-model="orgForm.registration_number" type="text" class="input" />
                </div>
                <div class="sm:col-span-2"><button type="submit" class="btn-primary !px-5 !py-2.5 !text-[12.5px]">Хадгалах</button></div>
            </form>
        </div>

        <div v-if="business" class="card mt-3.5 p-5">
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-[15px] font-bold text-ink">Бизнес</div>
                <select v-if="store.businesses.length > 1" v-model="selectedBusinessId" class="cursor-pointer rounded-[8px] border border-inputline bg-white px-2.5 py-1.5 text-[12.5px] font-semibold text-ink outline-none">
                    <option :value="null">{{ store.businesses[0]?.name }}</option>
                    <option v-for="b in store.businesses.slice(1)" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
                <span v-else class="text-[14px] font-semibold text-soft">{{ business.name }}</span>
            </div>

            <form class="mt-4 grid grid-cols-1 gap-3.5 sm:grid-cols-2" @submit.prevent="saveBusiness">
                <!-- Лого -->
                <div class="flex items-center gap-3.5 sm:col-span-2">
                    <BizLogo :business="business" size="h-14 w-14 rounded-[12px] text-xl" />
                    <div>
                        <label class="field-label !mb-1">Лого</label>
                        <label class="inline-block cursor-pointer rounded-[8px] border border-inputline bg-white px-3 py-2 text-[12px] font-semibold text-brand hover:border-brand">
                            {{ logoFile ? logoFile.name : 'Зураг сонгох (PNG/JPG, 2MB хүртэл)' }}
                            <input type="file" accept="image/*" class="hidden" @change="logoFile = $event.target.files[0] || null" />
                        </label>
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label class="field-label">Бизнесийн нэр</label>
                    <input v-model="bizForm.name" type="text" class="input" required />
                </div>
                <div>
                    <label class="field-label">Ангилал</label>
                    <select v-model="bizForm.category_id" class="input cursor-pointer">
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Үнийн зэрэглэл</label>
                    <div class="flex gap-1.5">
                        <button v-for="p in ['₮', '₮₮', '₮₮₮']" :key="p" type="button" class="cursor-pointer rounded-[7px] px-4 py-2 text-[13px] font-semibold" :class="bizForm.price_level === p ? 'bg-brand text-white' : 'bg-chip text-chiptext'" @click="bizForm.price_level = p">{{ p }}</button>
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label">Тайлбар</label>
                    <textarea v-model="bizForm.description" rows="3" maxlength="400" class="input resize-none"></textarea>
                </div>
                <div><label class="field-label">Вэб сайт</label><input v-model="bizForm.website" type="text" class="input" /></div>
                <div><label class="field-label">Facebook</label><input v-model="bizForm.facebook" type="text" class="input" /></div>
                <div><label class="field-label">Instagram</label><input v-model="bizForm.instagram" type="text" class="input" /></div>
                <div class="flex items-center justify-between sm:col-span-2">
                    <button type="submit" class="btn-primary !px-5 !py-2.5 !text-[12.5px]" :disabled="logoUploading">Хадгалах</button>
                    <button type="button" class="cursor-pointer text-[12px] font-semibold text-red hover:underline" @click="deleteBusiness">Бизнес устгах</button>
                </div>
            </form>
        </div>
    </div>
</template>
