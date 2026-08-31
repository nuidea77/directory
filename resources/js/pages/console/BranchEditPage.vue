<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, ApiError } from '../../api';
import HoursEditor from '../../components/HoursEditor.vue';
import VerifiedBadge from '../../components/VerifiedBadge.vue';
import { cityCenters } from '../../data/cityCenters';
import { hoursAre247 } from '../../utils/hours';
import { defineAsyncComponent } from 'vue';

// Leaflet газрын зураг — дарж/чирж байршил сонгоно
const MapView = defineAsyncComponent(() => import('../../components/MapView.vue'));

// Салбар засах editor (6a): хаяг/байршил, холбоо/цаг, зураг, үйлчилгээ
const route = useRoute();

const branch = ref(null);
const form = ref(null);
const loadError = ref('');
const msg = ref({ type: '', text: '' });
const busy = ref(false);
const uploading = ref(false);

// Байршил, үйлчилгээний жагсаалт API-аас (нэг эх сурвалж)
const locations = ref([]);
const amenityOptions = ref([]);

const districtOptions = computed(() => locations.value.find((l) => l.city === form.value?.city)?.districts || []);

const todo = computed(() => {
    if (!branch.value) return [];
    return [
        { name: 'Хаяг, байршил', done: !!(branch.value.address && branch.value.lat), gain: '' },
        { name: 'Цагийн хуваарь', done: !!branch.value.hours, gain: '' },
        { name: '3+ зураг нэмэх', done: (branch.value.images || []).length >= 3, gain: '+18% хандалт' },
        { name: 'Үйлчилгээ, онцлог', done: (branch.value.amenities || []).length > 0, gain: '+9% залгалт' },
    ];
});

// 24/7 нь тусдаа тохиргоо биш — оруулсан цагийн хуваариас өөрөө тооцогдоно
// (сервер ч мөн `hours`-оос is_24_7-г яг ижил дүрмээр бодно)
const is247 = computed(() => hoursAre247(form.value?.hours));

async function fetchBranch() {
    loadError.value = '';
    try {
        const data = await api.get(`/console/branches/${route.params.id}`);
        branch.value = data.data;
        form.value = {
            name: branch.value.name,
            city: branch.value.city || 'Улаанбаатар',
            district: branch.value.district || '',
            khoroo: branch.value.khoroo || '',
            address: branch.value.address || '',
            landmark: branch.value.landmark || '',
            lat: branch.value.lat,
            lng: branch.value.lng,
            phone: branch.value.phone,
            email: branch.value.email || '',
            hours: branch.value.hours || {},
            amenities: branch.value.amenities || [],
        };
    } catch {
        loadError.value = 'Ачаалахад алдаа гарлаа. Дахин оролдоно уу.';
    }
}

function toggleAmenity(a) {
    const i = form.value.amenities.indexOf(a);
    if (i >= 0) form.value.amenities.splice(i, 1);
    else form.value.amenities.push(a);
}

async function save() {
    msg.value = { type: '', text: '' };
    busy.value = true;
    try {
        const data = await api.put(`/console/branches/${branch.value.id}`, {
            ...form.value,
            phone: String(form.value.phone).replace(/\s/g, ''),
        });
        branch.value = data.data;
        msg.value = { type: 'ok', text: branch.value.status === 'pending' ? 'Хадгалагдлаа — хаягийн өөрчлөлт редакцын хяналтад илгээгдлээ.' : 'Хадгалагдлаа.' };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    } finally {
        busy.value = false;
    }
}

async function uploadImages(event) {
    const files = Array.from(event.target.files || []);
    if (!files.length) return;
    uploading.value = true;
    msg.value = { type: '', text: '' };
    try {
        const fd = new FormData();
        files.forEach((f) => fd.append('images[]', f));
        const data = await api.postForm(`/console/branches/${branch.value.id}/images`, fd);
        branch.value = data.data;
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Зураг хуулахад алдаа гарлаа' };
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
}

async function deleteImage(image) {
    if (!confirm('Энэ зургийг устгах уу?')) return;
    try {
        await api.delete(`/console/branches/${branch.value.id}/images/${image.id}`);
        branch.value.images = branch.value.images.filter((i) => i.id !== image.id);
    } catch {
        msg.value = { type: 'error', text: 'Зураг устгахад алдаа гарлаа' };
    }
}

async function setCover(image) {
    try {
        const data = await api.post(`/console/branches/${branch.value.id}/images/${image.id}/cover`);
        branch.value = data.data;
    } catch {
        msg.value = { type: 'error', text: 'Нүүр зураг солиход алдаа гарлаа' };
    }
}

const router2 = useRouter();

async function deleteBranch() {
    if (!confirm(`«${branch.value.name}» салбарыг бүрмөсөн устгах уу? Сэтгэгдэл, зураг хамт устна.`)) return;
    try {
        await api.delete(`/console/branches/${branch.value.id}`);
        router2.push({ name: 'console' });
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Устгахад алдаа гарлаа' };
    }
}

// Зураг дээр дарж/чирэхэд координат шинэчлэгдэнэ
function onPick({ lat, lng }) {
    form.value.lat = lat;
    form.value.lng = lng;
}

// GPS-ээр одоогийн байршлаа авах
const locating = ref(false);

function useMyLocation() {
    if (!navigator.geolocation) return;
    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            onPick({ lat: +pos.coords.latitude.toFixed(6), lng: +pos.coords.longitude.toFixed(6) });
            locating.value = false;
        },
        () => { locating.value = false; },
        { timeout: 8000 },
    );
}

// Аймаг солиход зураг тухайн төв рүү шилжинэ (координат сонгоогүй үед)
const mapCenter = computed(() => {
    if (form.value?.lat) return { lat: Number(form.value.lat), lng: Number(form.value.lng) };
    const c = cityCenters[form.value?.city] || cityCenters['Улаанбаатар'];
    return { lat: c.lat, lng: c.lng };
});

onMounted(async () => {
    await fetchBranch();
    try {
        const locs = await api.get('/locations');
        locations.value = locs.data;
        amenityOptions.value = locs.amenities || [];
    } catch {
        /* байршлын жагсаалтгүйгээр үргэлжилнэ */
    }
});
</script>

<template>
    <div class="min-h-screen bg-page">
        <!-- Толгой (6a) -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-white px-5 py-3 sm:px-7">
            <div class="flex items-center gap-3.5">
                <router-link :to="{ name: 'console' }" class="flex items-center gap-2.5">
                    <span class="flex h-[26px] w-[26px] items-center justify-center rounded-[7px] bg-brand text-[13px] font-extrabold text-white">О</span>
                    <span class="text-[15px] font-extrabold text-ink">Бизнес зөвлөл</span>
                </router-link>
                <div v-if="branch" class="hidden items-center gap-1.5 text-[12.5px] font-medium text-mute sm:flex">
                    <router-link :to="{ name: 'console' }" class="text-brand">Салбарууд</router-link>
                    <span>/</span>
                    <span class="font-semibold text-ink">{{ branch.name }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <router-link v-if="branch?.business" :to="{ name: 'business', params: { slug: branch.business.slug } }" class="btn-outline !px-3.5 !py-2 !text-[12.5px]">Хэрэглэгчийн хуудсаар үзэх</router-link>
                <button class="btn-primary !px-4 !py-2 !text-[12.5px]" :disabled="busy" @click="save">{{ busy ? 'Хадгалж байна…' : 'Хадгалах' }}</button>
            </div>
        </div>

        <div v-if="branch && form" class="mx-auto grid max-w-7xl grid-cols-1 lg:grid-cols-[1fr_320px]">
            <div class="px-5 py-6 sm:px-7">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-[22px] font-extrabold tracking-[-.02em] text-ink">{{ branch.name }}</h1>
                    <VerifiedBadge v-if="branch.business?.is_verified" :size="18" />
                    <span class="ml-auto text-[12px] font-medium text-mute">Төлөв: <b class="text-ink">{{ { active: 'Нээлттэй, хайлтад байна', pending: 'Редакцын хяналтад', draft: 'Ноорог', rejected: 'Татгалзсан', hidden: 'Түр хаалттай' }[branch.status] }}</b></span>
                </div>

                <p v-if="msg.text" class="mt-3.5 rounded-lg px-4 py-2.5 text-[13px] font-medium" :class="msg.type === 'ok' ? 'bg-greentint text-green' : 'bg-redtint text-red'">{{ msg.text }}</p>

                <p v-if="branch.status === 'rejected' && branch.rejection_reason" class="mt-3.5 rounded-lg bg-redtint px-4 py-2.5 text-[13px] font-medium text-red">
                    Татгалзсан шалтгаан: {{ branch.rejection_reason }} — засаад дахин хяналтад илгээнэ үү.
                </p>

                <!-- Хаяг ба байршил -->
                <div class="card mt-4 p-5">
                    <div class="text-[15px] font-bold text-ink">Хаяг ба байршил</div>
                    <div class="mt-3.5 grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="field-label">Гудамж, тоот</label>
                            <input v-model="form.address" type="text" class="input" />
                        </div>
                        <div>
                            <label class="field-label">Аймаг / Нийслэл</label>
                            <select v-model="form.city" class="input cursor-pointer" @change="form.district = ''">
                                <option v-for="l in locations" :key="l.city" :value="l.city">{{ l.city }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">{{ form.city === 'Улаанбаатар' ? 'Дүүрэг' : 'Сум' }}</label>
                            <select v-model="form.district" class="input cursor-pointer">
                                <option value="" disabled>Сонгоно уу</option>
                                <option v-for="d in districtOptions" :key="d" :value="d">{{ d }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Хороо</label>
                            <input v-model="form.khoroo" type="text" class="input" />
                        </div>
                        <div>
                            <label class="field-label">Ориентир</label>
                            <input v-model="form.landmark" type="text" class="input" />
                        </div>
                    </div>
                    <div class="mt-3.5 overflow-hidden rounded-[11px] border border-line">
                        <MapView :center="mapCenter" :zoom="form.lat ? 15 : 12" picker height="230px" @pick="onPick" />
                        <div class="flex flex-wrap items-center gap-2 border-t border-line bg-panel px-3 py-2">
                            <span class="text-[11.5px] font-medium text-mute">Зураг дээр дарж эсвэл цэгийг чирж байршлаа тавина</span>
                            <button type="button" class="ml-auto cursor-pointer rounded-[7px] border border-inputline bg-white px-2.5 py-1.5 text-[11.5px] font-semibold text-brand" :disabled="locating" @click="useMyLocation">
                                {{ locating ? 'Тогтоож байна…' : '📍 Одоогийн байршлаа ашиглах' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Холбоо ба цаг -->
                <div class="card mt-3.5 p-5">
                    <div class="text-[15px] font-bold text-ink">Холбоо барих ба цагийн хуваарь</div>
                    <div class="mt-3.5 grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Салбарын утас</label>
                            <input v-model="form.phone" type="tel" inputmode="numeric" class="input" />
                        </div>
                        <div>
                            <label class="field-label">И-мэйл (сонголт)</label>
                            <input v-model="form.email" type="email" class="input" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <HoursEditor v-model="form.hours">
                            <template #title><span class="field-label !mb-0">Цагийн хуваарь</span></template>
                        </HoursEditor>
                        <div v-if="is247" class="mt-2.5 flex items-center gap-2 rounded-xl border border-greenline bg-greentint px-3.5 py-2.5">
                            <span class="rounded-full bg-green px-2 py-0.5 text-[11px] font-bold text-white">24/7</span>
                            <p class="text-[12px] font-medium text-green">Долоо хоногийн өдөр бүр бүтэн хоног нээлттэй тул хайлтад «24/7» тэмдэгтэй харагдаж, шүүлтүүрт орно.</p>
                        </div>
                        <p v-else class="mt-2.5 text-[12px] font-medium text-mute">Өдөр бүрийг «24 цаг» болговол салбар автоматаар 24/7 гэж тэмдэглэгдэнэ.</p>
                    </div>
                </div>

                <!-- Зураг -->
                <div class="card mt-3.5 p-5">
                    <div class="flex items-baseline justify-between">
                        <span class="text-[15px] font-bold text-ink">Зураг <span class="font-medium text-mute">· {{ (branch.images || []).length }} / 3 доод шаардлага</span></span>
                        <span v-if="(branch.images || []).length < 3" class="text-[12px] font-semibold text-red">{{ 3 - (branch.images || []).length }} зураг нэмэх шаардлагатай</span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2.5">
                        <div v-for="(img, i) in branch.images" :key="img.id" class="group relative h-24 w-[132px] overflow-hidden rounded-[9px]">
                            <img :src="img.url" class="h-full w-full object-cover" />
                            <span v-if="img.is_cover || i === 0" class="absolute bottom-2 left-2 rounded-[4px] bg-white/95 px-1.5 py-0.5 text-[10px] font-semibold text-ink">Нүүр зураг</span>
                            <button v-else class="absolute bottom-2 left-2 hidden cursor-pointer rounded-[4px] bg-white/95 px-1.5 py-0.5 text-[10px] font-semibold text-brand group-hover:block" @click="setCover(img)">Нүүр болгох</button>
                            <button class="absolute right-1.5 top-1.5 hidden h-6 w-6 cursor-pointer items-center justify-center rounded-full bg-red text-[11px] text-white group-hover:flex" @click="deleteImage(img)">✕</button>
                        </div>
                        <label class="flex h-24 min-w-[180px] flex-1 cursor-pointer flex-col items-center justify-center gap-1 rounded-[9px] border-[1.5px] border-dashed border-inputline hover:border-brand">
                            <span class="text-[15px] font-bold text-brand">+</span>
                            <span class="px-3 text-center text-[12px] font-medium text-soft">{{ uploading ? 'Хуулж байна…' : 'Гадна тал, ажлын талбай, хүлээх танхимын зураг' }}</span>
                            <input type="file" accept="image/*" multiple class="hidden" :disabled="uploading" @change="uploadImages" />
                        </label>
                    </div>
                </div>

                <!-- Үйлчилгээ -->
                <div class="card mt-3.5 p-5">
                    <div class="text-[15px] font-bold text-ink">Энэ салбарын үйлчилгээ</div>
                    <p class="mt-1.5 text-[12.5px] text-mute">Зөвхөн энэ салбарт хамаарна. Шүүлтүүрт харагдана.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="a in amenityOptions"
                            :key="a"
                            class="cursor-pointer rounded-full border px-3 py-2 text-[12.5px] font-semibold"
                            :class="form.amenities.includes(a) ? 'border-brand bg-brand text-white' : 'border-inputline bg-white text-body'"
                            @click="toggleAmenity(a)"
                        >{{ a }}</button>
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-2.5">
                    <button class="btn-primary !px-5" :disabled="busy" @click="save">Хадгалж хяналтад илгээх</button>
                    <router-link :to="{ name: 'console' }" class="btn-outline">Буцах</router-link>
                    <button class="cursor-pointer text-[12px] font-semibold text-red hover:underline" @click="deleteBranch">Салбар устгах</button>
                    <span class="ml-auto hidden text-[12px] font-medium text-mute lg:block">Хаяг, нэр өөрчлөхөд редакцын хяналт шаардана · бусад засвар шууд гарна</span>
                </div>
            </div>

            <!-- Баруун sidebar (6a): бүрэн байдал -->
            <aside class="border-line bg-white px-5 py-6 sm:px-6 lg:border-l">
                <div class="kicker">ЭНЭ САЛБАРЫН БҮРЭН БАЙДАЛ</div>
                <div class="mt-2.5 flex items-baseline gap-2">
                    <span class="text-[28px] font-extrabold text-ink">{{ branch.completeness }}%</span>
                    <span class="text-[12px] font-medium text-soft">дүүрэн бүртгэл 3 дахин их хандалт авдаг</span>
                </div>
                <div class="mt-2.5 h-[7px] overflow-hidden rounded-full bg-chip"><div class="h-full bg-brand" :style="{ width: branch.completeness + '%' }"></div></div>
                <div class="mt-3.5 flex flex-col gap-2.5">
                    <div v-for="t in todo" :key="t.name" class="flex items-center gap-2.5">
                        <span class="flex h-[17px] w-[17px] shrink-0 items-center justify-center rounded-full border-[1.5px] text-[9.5px] font-bold text-white" :class="t.done ? 'border-green bg-green' : 'border-inputline bg-white'">{{ t.done ? '✓' : '' }}</span>
                        <span class="text-[12.5px] font-medium" :class="t.done ? 'text-ink' : 'text-mute'">{{ t.name }}</span>
                        <span class="ml-auto text-[11px] font-semibold text-mute">{{ t.gain }}</span>
                    </div>
                </div>

                <div class="my-5 h-px bg-divider"></div>
                <div class="kicker">ХЭРЭГЛЭГЧИЙН ХУУДАС</div>
                <div class="card mt-2.5 overflow-hidden">
                    <div class="h-[86px] overflow-hidden">
                        <img v-if="branch.images?.[0]" :src="branch.images[0].url" class="h-full w-full object-cover" />
                        <div v-else class="img-ph h-full"></div>
                    </div>
                    <div class="p-3.5">
                        <div class="flex items-center gap-2">
                            <span class="text-[13.5px] font-bold text-ink">{{ branch.business?.name }}</span>
                            <span class="rounded-[4px] bg-bluetint px-1.5 py-0.5 text-[9.5px] font-semibold text-brand">{{ branch.district?.slice(0, 3).toUpperCase() }} САЛБАР</span>
                        </div>
                        <div class="mt-1 text-[11.5px] text-mute">{{ branch.business?.category?.name }}</div>
                        <div class="mt-2 flex items-center gap-2 text-[11.5px] font-medium">
                            <b class="text-ink">{{ branch.reviews_count ? branch.rating_avg.toFixed(1) : 'Шинэ' }}</b>
                            <span class="font-semibold" :class="branch.is_open ? 'text-green' : 'text-amberdark'">{{ branch.open_label }}</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <div v-else-if="loadError" class="p-5 sm:p-7">
            <div class="card p-10 text-center">
                <p class="text-[13px] font-medium text-red">{{ loadError }}</p>
                <button class="btn-primary mt-4" @click="fetchBranch">Дахин оролдох</button>
            </div>
        </div>

        <div v-else class="space-y-4 p-7">
            <div class="card h-40 animate-pulse bg-white"></div>
        </div>
    </div>
</template>
