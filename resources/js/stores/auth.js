import { defineStore } from 'pinia';
import { api, getToken, setToken } from '../api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loaded: false,
    }),

    getters: {
        isLoggedIn: (state) => state.user !== null,
    },

    actions: {
        async load() {
            if (this.loaded) return;
            this.loaded = true;

            if (!getToken()) return;

            try {
                const data = await api.get('/me');
                this.user = data.data ?? data;
            } catch {
                this.user = null;
            }
        },

        setSession(token, user) {
            setToken(token);
            this.user = user;
        },

        async logout() {
            try {
                await api.post('/auth/logout');
            } catch {
                // token may already be invalid; clear locally regardless
            }
            setToken(null);
            this.user = null;
        },
    },
});
