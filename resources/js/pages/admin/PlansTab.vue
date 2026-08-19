<script setup>
import { onMounted, ref } from 'vue';
import { api, ApiError } from '../../api';

// Эрхийн бичгүүд: үнэ, хугацаа, лимитийг админаас удирдана
const plans = ref(null);
const msg = ref({ type: '', text: '' });
const busyId = ref(null);
const createOpen = ref(false);

const emptyPlan = () => ({
    key: '',
    name: '',
    price: 0,
    term_years: 1,
    limits: { businesses: 1, branches: 1, images_per_branch: 1 },
    analytics: false,
    top_list: false,
    verified_badge: false,
    is_active: true,
});

const newPlan = ref(emptyPlan());

async function fetchPlans() {
    const data = await api.get('/admin/plans');
    plans.value = data.data;
}

async function savePlan(plan) {
    msg.value = { type: '', text: '' };
    busyId.value = plan.id;
    try {
        await api.put(`/admin/plans/${plan.id}`, plan);
        msg.value = { type: 'ok', text: `«${plan.name}» хадгалагдлаа — үнэ, лимит шууд хүчинтэй.` };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    } finally {
        busyId.value = null;
    }
}

async function createPlan() {
    msg.value = { type: '', text: '' };
    try {
        await api.post('/admin/plans', newPlan.value);
        newPlan.value = emptyPlan();
        createOpen.value = false;
        await fetchPlans();
        msg.value = { type: 'ok', text: 'Шинэ эрхийн бичиг үүслээ.' };
    } catch (e) {
        msg.value = { type: 'error', text: e instanceof ApiError ? e.firstError() : 'Алдаа гарлаа' };
    }
}

onMounted(fetchPlans);
</script>

<template>
    <div class="p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-[15px] font-bold text-ink">Эрхийн бичгүүд</h1>
            <button class="btn-primary cursor-pointer !px-4 !py-2 !text-[12.5px]" @click="createOpen = !createOpen">Шинэ эрх үүсгэх</button>
        </div>
        <p class="mt-1 text-[12px] text-mute">Өөрчлөлт хадгалмагц үнийн хуудас, худалдан авалт, лимит шалгалтад шууд хүчинтэй болно. Идэвхгүй болгосон эрх шинээр зарагдахгүй, байгаа эзэмшигчид хэвээр.</p>

        <p v-if="msg.text" class="mt-3 rounded-lg px-4 py-2.5 text-[13px] font-medium" :class="msg.type === 'ok' ? 'bg-greentint text-green' : 'bg-redtint text-red'">{{ msg.text }}</p>

        <!-- Шинэ эрх -->
        <div v-if="createOpen" class="card mt-4 p-5">
            <div class="text-[14px] font-bold text-ink">Шинэ эрхийн бичиг</div>
            <form class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4" @submit.prevent="createPlan">
                <div><label class="field-label !text-[11px]">Түлхүүр (латин)</label><input v-model="newPlan.key" type="text" class="input" placeholder="premium" required maxlength="30" pattern="[a-z0-9\-]+" /></div>
                <div><label class="field-label !text-[11px]">Нэр</label><input v-model="newPlan.name" type="text" class="input" required maxlength="60" /></div>
                <div><label class="field-label !text-[11px]">Үнэ (₮)</label><input v-model.number="newPlan.price" type="number" min="0" class="input" required /></div>
                <div><label class="field-label !text-[11px]">Хугацаа (жил)</label><input v-model.number="newPlan.term_years" type="number" min="1" max="5" class="input" required /></div>
                <div><label class="field-label !text-[11px]">Бизнес</label><input v-model.number="newPlan.limits.businesses" type="number" min="1" class="input" required /></div>
                <div><label class="field-label !text-[11px]">Салбар (0=хязгааргүй)</label><input v-model.number="newPlan.limits.branches" type="number" min="0" class="input" required /></div>
                <div><label class="field-label !text-[11px]">Зураг/салбар</label><input v-model.number="newPlan.limits.images_per_branch" type="number" min="1" class="input" required /></div>
                <div class="col-span-2 flex items-end gap-3 sm:col-span-1">
                    <label class="flex cursor-pointer items-center gap-1.5 text-[11.5px] font-semibold text-body"><input v-model="newPlan.analytics" type="checkbox" />Аналитик</label>
                    <label class="flex cursor-pointer items-center gap-1.5 text-[11.5px] font-semibold text-body"><input v-model="newPlan.top_list" type="checkbox" />ТОП</label>
                    <label class="flex cursor-pointer items-center gap-1.5 text-[11.5px] font-semibold text-body"><input v-model="newPlan.verified_badge" type="checkbox" />✓</label>
                </div>
                <div class="col-span-2 sm:col-span-4"><button type="submit" class="btn-primary !px-5 !py-2.5 !text-[12.5px]">Үүсгэх</button></div>
            </form>
        </div>

        <div v-if="!plans" class="card mt-4 h-64 animate-pulse"></div>

        <div v-else class="mt-4 grid grid-cols-1 gap-3.5 lg:grid-cols-3">
            <div v-for="plan in plans" :key="plan.id" class="card p-5" :class="{ 'opacity-60': !plan.is_active }">
                <div class="flex items-center gap-2">
                    <span class="text-[15px] font-bold text-ink">{{ plan.name }}</span>
                    <span class="rounded-[4px] bg-chip px-1.5 py-0.5 font-mono text-[10px] font-semibold text-chiptext">{{ plan.key }}</span>
                    <label class="ml-auto flex cursor-pointer items-center gap-1.5 text-[11px] font-semibold" :class="plan.is_active ? 'text-green' : 'text-mute'">
                        <input v-model="plan.is_active" type="checkbox" />{{ plan.is_active ? 'Идэвхтэй' : 'Идэвхгүй' }}
                    </label>
                </div>

                <div class="mt-3.5 grid grid-cols-2 gap-2.5">
                    <div><label class="field-label !text-[11px]">Нэр</label><input v-model="plan.name" type="text" class="input !py-2 !text-[12.5px]" /></div>
                    <div><label class="field-label !text-[11px]">Үнэ (₮)</label><input v-model.number="plan.price" type="number" min="0" class="input !py-2 !text-[12.5px]" /></div>
                    <div><label class="field-label !text-[11px]">Хугацаа (жил)</label><input v-model.number="plan.term_years" type="number" min="1" max="5" class="input !py-2 !text-[12.5px]" /></div>
                    <div><label class="field-label !text-[11px]">Бизнес</label><input v-model.number="plan.limits.businesses" type="number" min="1" class="input !py-2 !text-[12.5px]" /></div>
                    <div><label class="field-label !text-[11px]">Салбар (0=∞)</label><input v-model.number="plan.limits.branches" type="number" min="0" class="input !py-2 !text-[12.5px]" /></div>
                    <div><label class="field-label !text-[11px]">Зураг/салбар</label><input v-model.number="plan.limits.images_per_branch" type="number" min="1" class="input !py-2 !text-[12.5px]" /></div>
                </div>

                <div class="mt-3 flex flex-wrap gap-3 text-[11.5px] font-semibold text-body">
                    <label class="flex cursor-pointer items-center gap-1.5"><input v-model="plan.analytics" type="checkbox" />Аналитик</label>
                    <label class="flex cursor-pointer items-center gap-1.5"><input v-model="plan.top_list" type="checkbox" />ТОП жагсаалт</label>
                    <label class="flex cursor-pointer items-center gap-1.5"><input v-model="plan.verified_badge" type="checkbox" />✓ Баталгаажсан</label>
                </div>

                <button class="btn-primary mt-4 w-full cursor-pointer !py-2.5 !text-[12.5px]" :disabled="busyId === plan.id" @click="savePlan(plan)">
                    {{ busyId === plan.id ? 'Хадгалж байна…' : 'Хадгалах' }}
                </button>
            </div>
        </div>
    </div>
</template>
