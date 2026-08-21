<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, ApiError } from '../../api';
import { useConsoleStore } from '../../stores/console';
import ImagePh from '../../components/ImagePh.vue';
import BizLogo from '../../components/BizLogo.vue';
import PanelPageHeader from '../../components/panel/PanelPageHeader.vue';
import PanelStat from '../../components/panel/PanelStat.vue';
import { Eye, MapPin, Navigation, Phone, Plus, Store } from 'lucide-vue-next';

// Миний салбарууд (4b): салбар бүр үзэлт/залгалт/үнэлгээ/бүрэн байдалтай
const store = useConsoleStore();
const router = useRouter();

// Байгаа бизнест шинэ салбар нэмэх (шинэ байгууллага үүсгэхгүй)
const addOpen = ref(false);
const limitOpen = ref(false);
const addBusy = ref(false);
const addError = ref('');
const locations = ref([]);
const addForm = ref({ business_id: null, city: 'Улаанбаатар', district: '', address: '', phone: '' });

const addDistricts = computed(() => locations.value.find((l) => l.city === addForm.value.city)?.districts || []);

// Эрхийн бичгийн салбарын лимит (0 = хязгааргүй)
const branchLimit = computed(() => Number(store.organization?.limits?.branches || 0));
const atLimit = computed(() => branchLimit.value > 0 && store.branches.length >= branchLimit.value);

// Лимит дүүрсэн бол формыг нээхийн оронд эрх ахиулах санамж харуулна
async function openAdd() {
    if (atLimit.value) {
        addOpen.value = false;
        limitOpen.value = !limitOpen.value;
        return;
    }
    limitOpen.value = false;
    addOpen.value = !addOpen.value;
    addForm.value.business_id = store.businesses[0]?.id || null;
    if (!locations.value.length) {
        const locs = await api.get('/locations');
        locations.value = locs.data;
    }
}

async function submitAdd() {
    addError.value = '';
    addBusy.value = true;
    try {
        const data = await api.post(`/console/businesses/${addForm.value.business_id}/branches`, {
            ...addForm.value,
            name: addForm.value.district + ' салбар',
            phone: addForm.value.phone.replace(/\s/g, ''),
        });
        await store.load(true);
        addOpen.value = false;
        // Зураг, цаг зэргийг гүйцээхээр засварын хуудас руу
        router.push({ name: 'branch-edit', params: { id: data.data.id } });
    } catch (e) {
        addError.value = e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа';
    } finally {
        addBusy.value = false;
    }
}

const statusLabel = {
    active: ['НЭЭЛТТЭЙ', 'bg-greentint text-green'],
    pending: ['ХЯНАЛТАД', 'bg-amberbadge text-amber'],
    draft: ['НООРОГ', 'bg-chip text-chiptext'],
    rejected: ['ТАТГАЛЗСАН', 'bg-redtint text-red'],
    hidden: ['ТҮР ХААЛТТАЙ', 'bg-chip text-chiptext'],
};

const totals = computed(() => ({
    views: store.branches.reduce((s, b) => s + (b.views_count || 0), 0),
    calls: store.branches.reduce((s, b) => s + (b.calls_count || 0), 0),
    directions: store.branches.reduce((s, b) => s + (b.directions_count || 0), 0),
}));
</script>

<template>
    <div class="p-5 sm:p-7">
        <PanelPageHeader
            title="Салбарууд"
            description="Салбар бүр тусдаа бүртгэл — хаяг, цагийн хуваарь, статистик нь салангид."
            :meta="[
                { label: branchLimit ? `${store.branches.length} / ${branchLimit} салбар` : `${store.branches.length} салбар` },
                { label: `${store.businesses.length} бизнес` },
            ]"
        >
            <template #actions>
                <router-link :to="{ name: 'add-business' }" class="btn-outline !px-4 !py-2.5 !text-[12.5px]">Шинэ бизнес</router-link>
                <button class="btn-primary flex cursor-pointer items-center gap-1.5 !px-4 !py-2.5 !text-[12.5px]" @click="openAdd">
                    <Plus :size="15" :stroke-width="2.2" /> Салбар нэмэх
                </button>
            </template>
        </PanelPageHeader>

        <!-- Лимит дүүрсэн үеийн санамж -->
        <div v-if="limitOpen" class="card mt-4 !border-blueline !bg-bluetint p-5">
            <div class="text-[14px] font-bold text-ink">Салбар нэмэхэд эрхээ ахиулна</div>
            <p class="mt-1.5 max-w-[560px] text-[12.5px] leading-relaxed text-body">
                {{ store.organization?.plan_name }} эрхэд {{ branchLimit }} хаяг багтана (одоо {{ store.branches.length }}).
                Стандарт эрхээс эхлэн салбарын тоо хязгааргүй — шинэ салбар бүр өөрийн хаяг, цагийн хуваарь,
                статистиктай тусдаа бүртгэл болно.
            </p>
            <div class="mt-3.5 flex flex-wrap gap-2">
                <router-link :to="{ name: 'console-plan' }" class="btn-primary !px-5 !py-2.5 !text-[12.5px]">Эрх ахиулах</router-link>
                <button type="button" class="btn-outline !px-4 !py-2.5 !text-[12.5px]" @click="limitOpen = false">Болих</button>
            </div>
        </div>

        <!-- Салбар нэмэх форм -->
        <div v-if="addOpen" class="card mt-4 p-5">
            <div class="text-[14px] font-bold text-ink">Шинэ салбар</div>
            <p v-if="addError" class="mt-2 rounded-lg bg-redtint px-3 py-2 text-[12.5px] font-medium text-red">{{ addError }}</p>
            <form class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2" @submit.prevent="submitAdd">
                <div v-if="store.businesses.length > 1" class="sm:col-span-2">
                    <label class="field-label !text-[12px]">Бизнес</label>
                    <select v-model="addForm.business_id" class="input cursor-pointer" required>
                        <option v-for="b in store.businesses" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label !text-[12px]">Аймаг / Нийслэл</label>
                    <select v-model="addForm.city" class="input cursor-pointer" required @change="addForm.district = ''">
                        <option v-for="l in locations" :key="l.city" :value="l.city">{{ l.city }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label !text-[12px]">{{ addForm.city === 'Улаанбаатар' ? 'Дүүрэг' : 'Сум' }}</label>
                    <select v-model="addForm.district" class="input cursor-pointer" required>
                        <option value="" disabled>Сонгоно уу</option>
                        <option v-for="d in addDistricts" :key="d" :value="d">{{ d }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label !text-[12px]">Хаяг</label>
                    <input v-model="addForm.address" type="text" class="input" required />
                </div>
                <div>
                    <label class="field-label !text-[12px]">Салбарын утас</label>
                    <input v-model="addForm.phone" type="tel" inputmode="numeric" class="input" required />
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" class="btn-primary !px-5 !py-2.5 !text-[12.5px]" :disabled="addBusy">{{ addBusy ? 'Нэмж байна…' : 'Салбар үүсгэх' }}</button>
                    <button type="button" class="btn-outline !px-4 !py-2.5 !text-[12.5px]" @click="addOpen = false">Болих</button>
                </div>
            </form>
        </div>

        <!-- KPI -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <PanelStat label="Бүртгэл үзсэн" :value="totals.views" :icon="Eye" />
            <PanelStat label="Залгасан" :value="totals.calls" :icon="Phone" tone="good" />
            <PanelStat label="Зам заалт" :value="totals.directions" :icon="Navigation" />
            <PanelStat label="Салбарууд" :value="store.branches.length" :icon="Store" :to="{ name: 'console-stats' }" />
        </div>

        <!-- Салбарын мөрүүд -->
        <div class="card mt-4 overflow-hidden">
            <div class="flex items-center border-b border-divider px-4 py-3.5">
                <span class="text-[14px] font-bold text-ink">Миний салбарууд</span>
                <span class="ml-auto hidden text-[12px] font-medium text-mute sm:block">Хаяг, нэр өөрчлөхөд редакцын хяналт шаардана</span>
            </div>
            <div v-for="branch in store.branches" :key="branch.id" class="flex flex-wrap items-center gap-4 border-b border-hairline px-4 py-4 last:border-0">
                <div class="hidden h-[62px] w-[84px] shrink-0 overflow-hidden rounded-lg sm:block">
                    <ImagePh :src="branch.cover_url" />
                </div>
                <div class="w-full min-w-0 sm:w-[290px]">
                    <div class="flex items-center gap-2">
                        <BizLogo :business="branch.business" size="h-6 w-6 rounded-[6px] text-[10px]" />
                        <span class="truncate text-[14px] font-bold text-ink">{{ branch.business.name }} — {{ branch.district }}</span>
                        <span class="rounded-[4px] px-1.5 py-0.5 text-[9.5px] font-semibold" :class="statusLabel[branch.status]?.[1]">{{ statusLabel[branch.status]?.[0] }}</span>
                    </div>
                    <div class="mt-1 flex items-center gap-1 truncate text-[11.5px] text-mute">
                        <MapPin :size="12" :stroke-width="1.9" class="shrink-0" /> {{ branch.address }}
                    </div>
                    <div v-if="branch.status === 'rejected' && branch.rejection_reason" class="mt-1 text-[11.5px] font-medium text-red">Шалтгаан: {{ branch.rejection_reason }}</div>
                </div>
                <div class="w-20"><div class="text-[10.5px] font-semibold text-mute">ҮЗСЭН</div><div class="mt-1 text-[15px] font-bold text-ink">{{ (branch.views_count || 0).toLocaleString() }}</div></div>
                <div class="w-20"><div class="text-[10.5px] font-semibold text-mute">ЗАЛГАСАН</div><div class="mt-1 text-[15px] font-bold text-ink">{{ (branch.calls_count || 0).toLocaleString() }}</div></div>
                <div class="w-20"><div class="text-[10.5px] font-semibold text-mute">ҮНЭЛГЭЭ</div><div class="mt-1 text-[15px] font-bold text-ink">{{ branch.reviews_count ? branch.rating_avg.toFixed(1) : '—' }}</div></div>
                <div class="min-w-[120px] flex-1">
                    <div class="flex justify-between text-[10.5px] font-semibold text-mute"><span>БҮРЭН БАЙДАЛ</span><span>{{ branch.completeness }}%</span></div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-chip">
                        <div class="h-full rounded-full" :class="branch.completeness >= 80 ? 'bg-green' : branch.completeness >= 60 ? 'bg-brand' : 'bg-amberdot'" :style="{ width: branch.completeness + '%' }"></div>
                    </div>
                </div>
                <router-link :to="{ name: 'branch-edit', params: { id: branch.id } }" class="text-[12.5px] font-semibold text-brand">Засах</router-link>
            </div>
        </div>
    </div>
</template>
