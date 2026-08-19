<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../../api';
import { useConsoleStore } from '../../stores/console';

// Зурвасын inbox (4b): thread жагсаалт + харилцан яриа + хариу
const store = useConsoleStore();

const selectedBusinessId = ref(null);
const threads = ref([]);
const activeUser = ref(null);
const messages = ref([]);
const reply = ref('');
const busy = ref(false);

const business = computed(() => store.businesses.find((b) => b.id === selectedBusinessId.value) || store.businesses[0]);

async function fetchThreads() {
    if (!business.value) return;
    const data = await api.get(`/console/businesses/${business.value.id}/messages`);
    threads.value = data.data;
}

async function openThread(thread) {
    activeUser.value = thread.user;
    const data = await api.get(`/console/businesses/${business.value.id}/messages/${thread.user.id}`);
    messages.value = data.data;
    thread.unread = 0;
}

async function send() {
    if (!reply.value.trim()) return;
    busy.value = true;
    try {
        const data = await api.post(`/console/businesses/${business.value.id}/messages/${activeUser.value.id}`, { body: reply.value });
        messages.value.push(data.data);
        reply.value = '';
    } finally {
        busy.value = false;
    }
}

watch(() => store.organization?.id, () => { selectedBusinessId.value = null; activeUser.value = null; fetchThreads(); });
onMounted(fetchThreads);
</script>

<template>
    <div class="p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-extrabold tracking-[-.02em] text-ink">Хэрэглэгчийн зурвас</h1>
            <select v-if="store.businesses.length > 1" v-model="selectedBusinessId" class="cursor-pointer rounded-lg border border-inputline bg-white px-3 py-2 text-[12.5px] font-medium text-soft outline-none" @change="activeUser = null; fetchThreads()">
                <option v-for="b in store.businesses" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
        </div>

        <div class="card mt-4 grid min-h-[440px] grid-cols-1 overflow-hidden md:grid-cols-[300px_1fr]">
            <!-- Thread жагсаалт -->
            <div class="border-line md:border-r">
                <div v-if="!threads.length" class="p-8 text-center text-[13px] text-mute">Одоогоор зурвас алга</div>
                <button
                    v-for="thread in threads"
                    :key="thread.user.id"
                    class="flex w-full cursor-pointer gap-2.5 border-b border-hairline px-4 py-3.5 text-left"
                    :class="{ 'bg-bluepale': activeUser?.id === thread.user.id }"
                    @click="openThread(thread)"
                >
                    <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-full border border-blueline bg-bluetint text-[12px] font-bold text-brand">{{ thread.user.name?.charAt(0) }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span class="text-[13px] font-bold text-ink">{{ thread.user.name }}</span>
                            <span v-if="thread.unread" class="ml-auto rounded-full bg-amberbadge px-1.5 py-0.5 font-mono text-[10px] font-bold text-amber">{{ thread.unread }}</span>
                        </span>
                        <span class="mt-1 block truncate text-[12px] text-mute">{{ thread.last_message }}</span>
                    </span>
                </button>
            </div>

            <!-- Харилцан яриа -->
            <div class="flex flex-col">
                <div v-if="!activeUser" class="flex flex-1 items-center justify-center p-8 text-[13px] text-mute">Зурвас сонгоно уу</div>
                <template v-else>
                    <div class="border-b border-divider px-4 py-3 text-[13.5px] font-bold text-ink">{{ activeUser.name }}</div>
                    <div class="flex flex-1 flex-col gap-2.5 overflow-y-auto p-4">
                        <div v-for="m in messages" :key="m.id" class="max-w-[75%] rounded-[11px] px-3.5 py-2.5 text-[13px] leading-relaxed" :class="m.sender === 'business' ? 'self-end bg-brand text-white' : 'self-start border border-line bg-panel text-body'">
                            {{ m.body }}
                            <div class="mt-1 text-[10px] opacity-60">{{ new Date(m.created_at).toLocaleString() }}</div>
                        </div>
                    </div>
                    <div class="flex gap-2 border-t border-divider p-3.5">
                        <input v-model="reply" type="text" placeholder="Хариу бичих..." class="input !py-2.5" @keyup.enter="send" />
                        <button class="btn-primary shrink-0 !px-4 !py-2.5" :disabled="busy || !reply.trim()" @click="send">Илгээх</button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
