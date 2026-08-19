import { defineStore } from 'pinia';
import { api } from '../api';

// Бизнес зөвлөлийн нийтлэг төлөв: байгууллагууд + сонгосон байгууллага
export const useConsoleStore = defineStore('console', {
    state: () => ({
        organizations: [],
        selectedId: null,
        loaded: false,
        loading: false,
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
            try {
                const data = await api.get('/console/organizations');
                this.organizations = data.data;
                if (!this.selectedId && this.organizations.length) {
                    this.selectedId = this.organizations[0].id;
                }
                this.loaded = true;
            } finally {
                this.loading = false;
            }
        },
    },
});
