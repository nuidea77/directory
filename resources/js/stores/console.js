import { defineStore } from 'pinia';
import { api, ApiError } from '../api';

// Бизнес зөвлөлийн нийтлэг төлөв: байгууллагууд + сонгосон байгууллага
export const useConsoleStore = defineStore('console', {
    state: () => ({
        organizations: [],
        selectedId: null,
        loaded: false,
        loading: false,
        error: '', // '' | 'phone_unverified' | 'failed'
    }),

    getters: {
        organization: (state) => state.organizations.find((o) => o.id === state.selectedId) || state.organizations[0] || null,
        businesses() {
            return this.organization?.businesses || [];
        },
        branches() {
            return this.businesses.flatMap((b) => (b.branches || []).map((br) => ({ ...br, business: b })));
        },
    },

    actions: {
        async load(force = false) {
            if (this.loaded && !force) return;
            this.loading = true;
            this.error = '';
            try {
                const data = await api.get('/console/organizations');
                this.organizations = data.data;
                if (!this.selectedId && this.organizations.length) {
                    this.selectedId = this.organizations[0].id;
                }
                this.loaded = true;
            } catch (e) {
                // 403 phone_unverified → баталгаажуулах хуудас руу, бусад → retry
                this.error = e instanceof ApiError && e.data?.code === 'phone_unverified' ? 'phone_unverified' : 'failed';
            } finally {
                this.loading = false;
            }
        },
    },
});
