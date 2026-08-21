<script setup>
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { api, ApiError } from '../../api';
import { useAuthStore } from '../../stores/auth';
import HoursEditor from '../../components/HoursEditor.vue';
import { cityCenters } from '../../data/cityCenters';
import { flattenCategories, optionLabel } from '../../utils/categories';

/**
 * Бизнес нэмэх шаталсан форм (2c → 3a/5a):
 * 1. Бизнесийн үндсэн мэдээлэл  2. Салбарууд (хаяг, цаг) → эрх сонгох
 */
const router = useRouter();
const auth = useAuthStore();

const step = ref(1);
const categories = ref([]);
const error = ref('');
const busy = ref(false);

const organization = ref(null);
const business = ref(null);

const info = ref({
    business_name: '',
    category_id: '',
    subcategory: '',
    description: '',
    website: '',
    email: '',
    facebook: '',
    instagram: '',
    price_level: '₮₮',
});

// Аймаг/нийслэл + сум/дүүрэг, үйлчилгээний жагсаалт API-аас (config/locations, config/amenities)
const locations = ref([]);
const amenityOptions = ref([]);

function districtsFor(city) {
    return locations.value.find((l) => l.city === city)?.districts || [];
}

const defaultHours = () => ({
    mon: { from: '09:00', to: '19:00', closed: false }, tue: { from: '09:00', to: '19:00', closed: false },
    wed: { from: '09:00', to: '19:00', closed: false }, thu: { from: '09:00', to: '19:00', closed: false },
    fri: { from: '09:00', to: '19:00', closed: false }, sat: { from: '10:00', to: '16:00', closed: false },
    sun: { from: '09:00', to: '19:00', closed: true },
});

// Leaflet-тэй тул lazy-load — үндсэн bundle томрохгүй
const MapView = defineAsyncComponent(() => import('../../components/MapView.vue'));

const structure = ref('single'); // single | multi

// Ангилал 3 түвшинтэй: үндсэн → дэд → дэд дэд (ж. Боловсрол → Хэлний сургалт → Англи хэл).
// Хамгийн гүн сонголт нь бизнесийн category_id болно.
const mainCategoryId = ref('');
const mainCategory = computed(() => categories.value.find((c) => c.id === Number(mainCategoryId.value)));
const subOptions = computed(() => flattenCategories(mainCategory.value?.children || [], 2));

watch(mainCategoryId, (id) => {
    info.value.category_id = id;
    info.value.subcategory = '';
});

function pickSubcategory(id) {
    const sub = subOptions.value.find((c) => c.id === Number(id));
    info.value.category_id = sub ? sub.id : mainCategoryId.value;
    info.value.subcategory = sub ? sub.name : '';
}
const branchForms = ref([newBranchForm()]);

const selectedCategory = computed(() => mainCategory.value);

function newBranchForm() {
    return {
        city: 'Улаанбаатар', district: '', khoroo: '', address: '', landmark: '',
        lat: null, lng: null,
        phone: '', email: '', hours: defaultHours(), amenities: [],
    };
}

// Салбарын байршил: зураг дээр дарж/чирэхэд координат хадгалагдана
// (хэрэглэгчид тоо харагдахгүй, зөвхөн цэг)
function pickLocation(form, { lat, lng }) {
    form.lat = lat;
    form.lng = lng;
}

const locatingIndex = ref(null);

function useMyLocation(form, i) {
    if (!navigator.geolocation) return;
    locatingIndex.value = i;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            pickLocation(form, { lat: +pos.coords.latitude.toFixed(6), lng: +pos.coords.longitude.toFixed(6) });
            locatingIndex.value = null;
        },
        () => { locatingIndex.value = null; },
        { timeout: 8000 },
    );
}

// Аймаг солиход зураг тухайн төв рүү шилжинэ (цэг тавиагүй үед)
function centerFor(form) {
    if (form.lat) return { lat: Number(form.lat), lng: Number(form.lng) };
    const c = cityCenters[form.city] || cityCenters['Улаанбаатар'];
    return { lat: c.lat, lng: c.lng };
}

function toggleAmenity(form, amenity) {
    const i = form.amenities.indexOf(amenity);
    if (i >= 0) form.amenities.splice(i, 1);
    else form.amenities.push(amenity);
}

async function submitInfo() {
    error.value = '';
    busy.value = true;
    try {
        // Байгууллагын нэрийг тусад нь асуухаа больсон — бизнесийн нэрээр үүснэ
        const data = await api.post('/console/organizations', {
            ...info.value,
            organization_name: info.value.business_name,
        });
        organization.value = data.organization.data ?? data.organization;
        business.value = data.business.data ?? data.business;
        step.value = 2;
        window.scrollTo({ top: 0 });
    } catch (e) {
        if (e instanceof ApiError && e.data?.code === 'phone_unverified') {
            router.push({ name: 'verify' });
            return;
        }
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    } finally {
        busy.value = false;
    }
}

async function submitBranches() {
    error.value = '';
    busy.value = true;
    try {
        const forms = structure.value === 'single' ? branchForms.value.slice(0, 1) : branchForms.value;

        for (const form of forms) {
            if (!form.district || !form.address || !form.phone) {
                error.value = 'Салбар бүрийн дүүрэг, хаяг, утсыг бөглөнө үү.';
                busy.value = false;
                return;
            }
        }

        // Эрхийн лимитээс хэтэрсэн салбаруудыг түр хадгалж, төлбөр төлсний
        // дараа үүсгэнэ — өмнө нь 2 дахь салбар дээр 422 өгч, хэрэглэгч
        // цаашаа гарах аргагүй гацдаг байсан
        const deferred = [];

        for (const [i, form] of forms.entries()) {
            // Алдаа гарч дахин илгээхэд өмнө нь үүссэн салбарыг давхардуулахгүй
            if (form._created) continue;

            const payload = {
                ...form,
                name: form.district + ' салбар',
                phone: form.phone.replace(/\s/g, ''),
            };

            // Лимит дүүрсэн нь тодорхой болмогц үлдсэнийг шалгалгүй хойшлуулна
            if (deferred.length) {
                deferred.push(payload);
                continue;
            }

            try {
                await api.post(`/console/businesses/${business.value.id}/branches`, payload);
                form._created = true;
            } catch (e) {
                const limitReached = e instanceof ApiError && e.status === 422;

                // Эхний салбар үүсэхгүй бол жинхэнэ алдаа — хэрэглэгчид харуулна
                if (i === 0 || !limitReached) throw e;

                deferred.push(payload);
            }
        }

        if (deferred.length) {
            sessionStorage.setItem(`pending_branches:${business.value.id}`, JSON.stringify(deferred));
        } else {
            sessionStorage.removeItem(`pending_branches:${business.value.id}`);
        }

        // Салбарууд хадгалагдмагц шууд эрх сонгох руу
        router.push({ name: 'plan-select', params: { orgId: organization.value.id } });
    } catch (e) {
        error.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    } finally {
        busy.value = false;
    }
}

onMounted(async () => {
    if (!auth.user?.phone_verified) {
        router.push({ name: 'verify' });
        return;
    }
    const [cats, locs] = await Promise.all([api.get('/categories'), api.get('/locations')]);
    categories.value = cats.data;
    locations.value = locs.data;
    amenityOptions.value = locs.amenities || [];
});
</script>

<template>
    <div class="min-h-screen bg-white">
        <!-- Толгой -->
        <div class="flex items-center justify-between border-b border-line px-5 py-3.5 sm:px-10">
            <div class="flex items-center gap-2.5">
                <span class="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] bg-brand text-[13px] font-extrabold text-white">Х</span>
                <span class="text-base font-extrabold text-ink">Хаана<span class="text-brand">.mn</span> <span class="text-[13px] font-medium text-mute">· Бизнес нэмэх</span></span>
            </div>
            <router-link :to="{ name: 'home' }" class="text-[13px] font-medium text-soft">Гарах</router-link>
        </div>

        <div class="mx-auto grid max-w-7xl grid-cols-1 lg:grid-cols-[1fr_380px]">
            <div class="border-line px-5 py-8 sm:px-10 lg:border-r">
                <!-- Шатны заалт -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <template v-for="(s, i) in ['Мэдээлэл', 'Салбарууд']" :key="s">
                        <div class="flex items-center gap-2">
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-full text-[11.5px] font-bold"
                                :class="step > i + 1 ? 'bg-brand text-white' : step === i + 1 ? 'border-[1.5px] border-brand bg-bluetint text-brand' : 'border-[1.5px] border-inputline bg-white text-ph'"
                            >{{ step > i + 1 ? '✓' : i + 1 }}</span>
                            <span class="text-[13px] font-semibold" :class="step >= i + 1 ? 'text-ink' : 'text-ph'">{{ s }}</span>
                        </div>
                        <div v-if="i < 1" class="h-[1.5px] w-9" :class="step > i + 1 ? 'bg-brand' : 'bg-searchline'"></div>
                    </template>
                </div>

                <p v-if="error" class="mt-5 max-w-[620px] rounded-lg bg-redtint px-4 py-2.5 text-[13px] font-medium text-red">{{ error }}</p>

                <!-- ШАТ 1: Үндсэн мэдээлэл (2c) -->
                <template v-if="step === 1">
                    <h1 class="mt-7 text-[26px] font-extrabold tracking-[-.02em] text-ink">Бизнесийн үндсэн мэдээлэл</h1>
                    <p class="mt-2 max-w-[520px] text-[14px] leading-relaxed text-soft">Нэр, ангилал, холбоо барих мэдээллээ бөглөнө үү. Редакц хянасны дараа бизнес «Баталгаажсан» тэмдэг авч, хайлтад дээгүүр эрэмбэлэгдэнэ.</p>

                    <form class="mt-6 grid max-w-[620px] grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="submitInfo">
                        <div class="sm:col-span-2">
                            <label class="field-label !text-[12px]">Бизнесийн нэр</label>
                            <input v-model="info.business_name" type="text" placeholder="Хангай Авто Сервис" class="input" required maxlength="150" />
                        </div>
                        <div>
                            <label class="field-label !text-[12px]">Ангилал</label>
                            <select v-model="mainCategoryId" class="input cursor-pointer" required>
                                <option value="" disabled>Сонгоно уу</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label !text-[12px]">Дэд ангилал</label>
                            <select :value="info.subcategory ? String(info.category_id) : ''" class="input cursor-pointer" :disabled="!subOptions.length" @change="pickSubcategory($event.target.value)">
                                <option value="">{{ subOptions.length ? 'Сонгоно уу (сонголттой)' : 'Дэд ангилал алга' }}</option>
                                <option v-for="sub in subOptions" :key="sub.id" :value="sub.id">{{ optionLabel(sub) }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="field-label !text-[12px]">Тайлбар</label>
                            <textarea v-model="info.description" rows="3" maxlength="400" placeholder="Танай бизнес юугаараа онцлог вэ?" class="input resize-none"></textarea>
                            <p class="mt-1 text-[11.5px] text-mute">{{ info.description.length }} / 400 тэмдэгт</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="field-label !text-[12px]">Вэб сайт</label>
                            <input v-model="info.website" type="text" placeholder="hangaiauto.mn" class="input" />
                            <p class="mt-1 text-[11.5px] text-mute">https:// шаардлагагүй</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="field-label !text-[12px]">И-мэйл хаяг</label>
                            <input v-model="info.email" type="email" placeholder="info@hangaiauto.mn" class="input" />
                            <p class="mt-1 text-[11.5px] text-mute">Захиалга, лавлагаа хүлээн авах хаяг — бизнесийн хуудсанд харагдана</p>
                        </div>
                        <div>
                            <label class="field-label !text-[12px]">Facebook хуудас</label>
                            <input v-model="info.facebook" type="text" placeholder="facebook.com/hangaiauto" class="input" />
                        </div>
                        <div>
                            <label class="field-label !text-[12px]">Instagram</label>
                            <input v-model="info.instagram" type="text" placeholder="@hangai.auto" class="input" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="field-label !text-[12px]">Үнийн зэрэглэл</label>
                            <div class="flex gap-1.5">
                                <button v-for="p in ['₮', '₮₮', '₮₮₮']" :key="p" type="button" class="cursor-pointer rounded-[7px] px-4 py-2 text-[13px] font-semibold" :class="info.price_level === p ? 'bg-brand text-white' : 'bg-chip text-chiptext'" @click="info.price_level = p">{{ p }}</button>
                            </div>
                        </div>
                        <div class="mt-2 flex gap-2.5 sm:col-span-2">
                            <button type="submit" class="btn-primary !px-6" :disabled="busy">{{ busy ? 'Хадгалж байна…' : 'Хаяг, цагийн хуваарь →' }}</button>
                        </div>
                    </form>
                </template>

                <!-- ШАТ 2: Салбарууд (5a/3a) -->
                <template v-else-if="step === 2">
                    <div class="mt-7 text-[11px] font-semibold tracking-[.12em] text-brand">2-Р ШАТ · БҮТЭЦ</div>
                    <h1 class="mt-2.5 text-[26px] font-extrabold tracking-[-.02em] text-ink">Бизнес хэдэн салбартай вэ?</h1>
                    <p class="mt-2 max-w-[540px] text-[14px] leading-relaxed text-soft">
                        Салбар тус бүр өөрийн хаяг, утас, цагийн хуваарьтай тусдаа бүртгэл болно — ингэснээр «миний ойролцоо» хайлтад зөв салбар олдоно.
                    </p>

                    <div class="mt-5 grid max-w-[600px] grid-cols-1 gap-3 sm:grid-cols-2">
                        <button class="cursor-pointer rounded-[11px] border-[1.5px] p-4 text-left" :class="structure === 'single' ? 'border-brand bg-bluecard' : 'border-inputline'" @click="structure = 'single'; branchForms = branchForms.slice(0, 1)">
                            <div class="flex items-center gap-2.5">
                                <span class="h-4 w-4 rounded-full border-[1.5px]" :class="structure === 'single' ? 'border-[5px] border-brand bg-white' : 'border-[#cfccc5]'"></span>
                                <span class="text-[13.5px] font-bold text-ink">Нэг хаяг</span>
                            </div>
                            <div class="mt-2 text-[12.5px] leading-normal text-mute">Зөвхөн нэг байршилтай бизнес.</div>
                        </button>
                        <button class="cursor-pointer rounded-[11px] border-[1.5px] p-4 text-left" :class="structure === 'multi' ? 'border-brand bg-bluecard' : 'border-inputline'" @click="structure = 'multi'">
                            <div class="flex items-center gap-2.5">
                                <span class="h-4 w-4 rounded-full border-[1.5px]" :class="structure === 'multi' ? 'border-[5px] border-brand bg-white' : 'border-[#cfccc5]'"></span>
                                <span class="text-[13.5px] font-bold text-ink">Олон салбар</span>
                                <span v-if="structure === 'multi'" class="ml-auto rounded-[4px] bg-bluetint px-1.5 py-0.5 text-[10px] font-semibold text-brand">{{ branchForms.length }} САЛБАР</span>
                            </div>
                            <div class="mt-2 text-[12.5px] leading-normal text-body">Нэг байгууллага, дүүрэг тус бүрт салбар. Статистик салбараар тусдаа.</div>
                        </button>
                    </div>
                    <p v-if="structure === 'multi' && organization?.effective_plan === 'free'" class="mt-3 max-w-[600px] rounded-[10px] border border-amberline bg-ambertint px-4 py-2.5 text-[12.5px] font-medium leading-relaxed text-ambertext">
                        Үнэгүй эрхэд 1 хаяг л бүртгэнэ. Олон салбар нэмэхийн тулд дараагийн шатанд Стандарт эсвэл Бизнес эрх сонгоно — салбаруудаа одоо бөглөөд хамт төлж болно.
                    </p>

                    <div class="mt-7 flex max-w-[600px] items-baseline justify-between">
                        <h2 class="text-[17px] font-bold text-ink">Салбарууд</h2>
                        <span class="text-[12.5px] font-medium text-mute">Ерөнхий мэдээллийг бүх салбар хуваалцана</span>
                    </div>

                    <div class="mt-3 flex max-w-[600px] flex-col gap-3">
                        <div v-for="(form, i) in branchForms" :key="i" class="overflow-hidden rounded-xl border-[1.5px] border-line">
                            <div class="flex items-center gap-2.5 border-b border-hairline px-4 py-3">
                                <span class="flex h-[22px] w-[22px] items-center justify-center rounded-full bg-bluetint text-[11px] font-bold text-brand">{{ i + 1 }}</span>
                                <span class="text-[14px] font-bold text-ink">{{ form.district ? form.district + ' салбар' : 'Шинэ салбар' }}</span>
                                <span v-if="i === 0" class="rounded-[4px] bg-bluetint px-1.5 py-0.5 text-[10px] font-semibold text-brand">ТӨВ</span>
                                <button v-if="branchForms.length > 1" class="ml-auto cursor-pointer text-[12px] font-semibold text-red" @click="branchForms.splice(i, 1)">Устгах</button>
                            </div>
                            <div class="grid grid-cols-1 gap-3.5 p-4 sm:grid-cols-2">
                                <div>
                                    <label class="field-label !text-[12px]">Аймаг / Нийслэл</label>
                                    <select v-model="form.city" class="input cursor-pointer" required @change="form.district = ''">
                                        <option v-for="l in locations" :key="l.city" :value="l.city">{{ l.city }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="field-label !text-[12px]">{{ form.city === 'Улаанбаатар' ? 'Дүүрэг' : 'Сум' }}</label>
                                    <select v-model="form.district" class="input cursor-pointer" required>
                                        <option value="" disabled>Сонгоно уу</option>
                                        <option v-for="d in districtsFor(form.city)" :key="d" :value="d">{{ d }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="field-label !text-[12px]">Хороо / баг (сонголт)</label>
                                    <input v-model="form.khoroo" type="text" placeholder="13-р хороо" class="input" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="field-label !text-[12px]">Гудамж, тоот</label>
                                    <input v-model="form.address" type="text" placeholder="Энхтайваны өргөн чөлөө 68" class="input" required />
                                </div>
                                <div>
                                    <label class="field-label !text-[12px]">Ориентир (сонголт)</label>
                                    <input v-model="form.landmark" type="text" placeholder="жш: 13-р хорооллын эсрэг талд" class="input" />
                                </div>
                                <div>
                                    <label class="field-label !text-[12px]">Салбарын утас</label>
                                    <input v-model="form.phone" type="tel" inputmode="numeric" placeholder="9500 1122" class="input" required />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="field-label !text-[12px]">Газрын зураг дээрх байршил <span class="font-normal text-mute">— «Ойролцоо» хайлтад хэрэгтэй</span></label>
                                    <div class="overflow-hidden rounded-[11px] border border-line">
                                        <MapView :center="centerFor(form)" :zoom="form.lat ? 15 : 12" picker height="210px" @pick="pickLocation(form, $event)" />
                                        <div class="flex flex-wrap items-center gap-2 border-t border-line bg-panel px-3 py-2">
                                            <span class="text-[11.5px] font-medium" :class="form.lat ? 'text-green' : 'text-mute'">
                                                {{ form.lat ? '✓ Байршил тэмдэглэгдлээ' : 'Зураг дээр дарж эсвэл цэгийг чирж байршлаа тавина' }}
                                            </span>
                                            <button type="button" class="ml-auto cursor-pointer rounded-[7px] border border-inputline bg-white px-2.5 py-1.5 text-[11.5px] font-semibold text-brand" :disabled="locatingIndex === i" @click="useMyLocation(form, i)">
                                                {{ locatingIndex === i ? 'Тогтоож байна…' : '📍 Одоогийн байршлаа ашиглах' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="sm:col-span-2">
                                    <HoursEditor v-model="form.hours">
                                        <template #title><span class="field-label !mb-0 !text-[12px]">Цагийн хуваарь</span></template>
                                    </HoursEditor>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="field-label !text-[12px]">Үйлчилгээ, онцлог <span class="font-normal text-mute">— шүүлтүүрт харагдана</span></label>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="a in amenityOptions"
                                            :key="a"
                                            type="button"
                                            class="cursor-pointer rounded-full border px-3 py-1.5 text-[12.5px] font-semibold"
                                            :class="form.amenities.includes(a) ? 'border-brand bg-brand text-white' : 'border-inputline bg-white text-body'"
                                            @click="toggleAmenity(form, a)"
                                        >{{ a }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button v-if="structure === 'multi'" class="flex cursor-pointer items-center gap-3 rounded-xl border-[1.5px] border-dashed border-inputline p-4 text-left hover:border-brand" @click="branchForms.push(newBranchForm())">
                            <span class="flex h-[30px] w-[30px] items-center justify-center rounded-lg border border-blueline bg-bluetint text-[15px] font-bold text-brand">+</span>
                            <span>
                                <span class="block text-[13.5px] font-bold text-ink">Салбар нэмэх</span>
                                <span class="mt-0.5 block text-[12px] text-mute">Хаяг, утас, цагийн хуваарь л бөглөнө — бусад мэдээлэл автоматаар хуулагдана</span>
                            </span>
                        </button>
                    </div>

                    <div class="mt-7 flex max-w-[600px] items-center gap-2.5">
                        <button class="btn-outline !px-5" @click="step = 1">← Буцах</button>
                        <button class="btn-primary !px-6" :disabled="busy" @click="submitBranches">
                            {{ busy ? 'Хадгалж байна…' : branchForms.length > 1 ? `${branchForms.length} салбарыг батлах →` : 'Эрх сонгох →' }}
                        </button>
                        <span class="ml-auto hidden text-[12.5px] font-medium text-mute sm:block">Салбар тус бүр тусад нь хянагдана</span>
                    </div>
                </template>

            </div>

            <!-- Баруун sidebar -->
            <aside class="bg-panel px-6 py-8 sm:px-8">
                <template v-if="true">
                    <div class="kicker">ХЭРХЭН ХАРАГДАХ</div>
                    <div class="card mt-3 overflow-hidden bg-white">
                        <div class="img-ph h-[120px]"></div>
                        <div class="p-4">
                            <div class="flex items-center gap-2">
                                <span class="text-[15px] font-bold text-ink">{{ info.business_name || 'Бизнесийн нэр' }}</span>
                            </div>
                            <div class="mt-1 text-[12.5px] text-mute">{{ selectedCategory?.name || 'Ангилал' }}{{ branchForms[0]?.district ? ' · ' + branchForms[0].district : '' }}</div>
                            <div class="mt-2.5 flex items-center gap-2 text-[12px] font-medium text-mute"><b class="text-ink">Шинэ</b> сэтгэгдэл хараахан үгүй</div>
                            <div class="mt-3 flex gap-2 text-[12.5px] font-semibold">
                                <span class="flex-1 rounded-lg border border-inputline py-2 text-center text-ink">{{ branchForms[0]?.phone || 'Утас' }}</span>
                                <span class="flex-1 rounded-lg bg-brand py-2 text-center text-white">Дэлгэрэнгүй</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-blueline bg-bluetint p-4">
                        <div class="text-[13.5px] font-bold text-ink">Баталгаажуулалт хэрхэн явагддаг</div>
                        <div class="mt-3 flex flex-col gap-2.5">
                            <div v-for="(v, i) in ['Утасны дугаараа баталгаажуулснаар бүртгэл идэвхжинэ.', 'Хаана редакц 1–2 ажлын өдрийн дотор хаяг, зургийг хянана — энэ хугацаанд ч бүртгэл хайлтад харагдана.']" :key="i" class="flex gap-2.5">
                                <span class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full bg-brand text-[10px] font-bold text-white">{{ i + 1 }}</span>
                                <span class="text-[12.5px] leading-normal text-body">{{ v }}</span>
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 text-[12px] leading-relaxed text-mute">Бүртгэл үнэгүй (1 жил). Салбар, аналитик, онцлох байршил нь төлбөртэй.</p>
                </template>


            </aside>
        </div>
    </div>
</template>
