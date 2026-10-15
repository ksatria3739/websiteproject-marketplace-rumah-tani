import { defineStore } from 'pinia';
import { api } from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: JSON.parse(localStorage.getItem('rt_user') || 'null'),
        token: localStorage.getItem('rt_token') || null,
        loading: false,
    }),
    getters: {
        isAuthenticated: (state) => !!state.token && !!state.user,
        role: (state) => state.user ? state.user.role : 'guest',
        isSuperAdmin: (state) => state.user?.role === 'super_admin',
        isAdmin: (state) => state.user?.role === 'admin',
        isStaffAdmin: (state) => ['super_admin', 'admin'].includes(state.user?.role),
        isTengkulak: (state) => state.user?.role === 'admin_tengkulak',
        isPetani: (state) => state.user?.role === 'petani',
        isPembeli: (state) => state.user?.role === 'pembeli',
        isB2B: (state) => state.user?.role === 'pembeli' && state.user?.buyer_type === 'b2b',
        roleDashboardRoute: (state) => {
            switch (state.user?.role) {
                case 'super_admin':
                case 'admin': return '/super';
                case 'admin_tengkulak': return '/tengkulak';
                case 'petani': return '/petani';
                case 'pembeli': return '/buyer';
                default: return '/';
            }
        }
    },
    actions: {
        async login(email, password) {
            this.loading = true;
            try {
                const res = await api.post('/auth/login', { email, password });
                this.token = res.access_token;
                this.user = res.user;
                localStorage.setItem('rt_token', res.access_token);
                localStorage.setItem('rt_user', JSON.stringify(res.user));
                return res;
            } finally {
                this.loading = false;
            }
        },
        async register(formData) {
            this.loading = true;
            try {
                const res = await api.post('/auth/register', formData);
                this.token = res.access_token;
                this.user = res.user;
                localStorage.setItem('rt_token', res.access_token);
                localStorage.setItem('rt_user', JSON.stringify(res.user));
                return res;
            } finally {
                this.loading = false;
            }
        },
        async fetchMe() {
            if (!this.token) return null;
            try {
                const me = await api.get('/auth/me');
                this.user = { ...this.user, ...me };
                localStorage.setItem('rt_user', JSON.stringify(this.user));
                return this.user;
            } catch (err) {
                if (err.status === 401) {
                    this.logout();
                }
            }
        },
        logout() {
            if (this.token) {
                api.post('/auth/logout').catch(() => {});
            }
            this.user = null;
            this.token = null;
            localStorage.removeItem('rt_token');
            localStorage.removeItem('rt_user');
        }
    }
});
