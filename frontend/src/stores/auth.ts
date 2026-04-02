import { defineStore } from 'pinia'
import api from '../plugins/axios'

interface User {
    id: number;
    name: string;
    email: string;
    role: {
        name: string;
    };
}

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null as User | null,
        token: localStorage.getItem('token') || null,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        role: (state) => state.user?.role?.name || null,
        isAdmin: (state) => state.user?.role?.name === 'Admin' || state.user?.role?.name === 'Gérant',
        isCommercial: (state) => state.user?.role?.name === 'Commercial',
        isPurchasing: (state) => state.user?.role?.name === "Responsable d'Achat",
        isAccountant: (state) => state.user?.role?.name === 'Comptable',
        isMarketing: (state) => state.user?.role?.name === 'Responsable Marketing',
        isLogistician: (state) => state.user?.role?.name === 'Logisticien',
    },

    actions: {
        setToken(token: string) {
            this.token = token
            localStorage.setItem('token', token)
            // Attach to all future axios requests
            api.defaults.headers.common['Authorization'] = `Bearer ${token}`
        },

        setUser(user: User) {
            this.user = user
        },

        async login(credentials: any) {
            try {
                // Laravel Sanctum CSRF Initialization (Not needed for simple token auth but good for cross-subdomain sessions)
                // If needed in production, it should use the actual API URL.
                // For now, we remove it as it was hardcoded to localhost.

                const response = await api.post('/login', credentials)

                if (response.data.token) {
                    this.setToken(response.data.token)
                    this.setUser(response.data.user)
                    return true
                }
                return false
            } catch (error) {
                throw error
            }
        },

        async fetchProfile() {
            if (!this.token) return false

            // Always ensure token is in headers on reload
            api.defaults.headers.common['Authorization'] = `Bearer ${this.token}`

            try {
                const response = await api.get('/profile')
                this.setUser(response.data)
                return true
            } catch (error) {
                this.logout()
                return false
            }
        },

        async logout() {
            try {
                await api.post('/logout')
            } catch (e) {
                // even if it fails, clear local state
            } finally {
                this.user = null
                this.token = null
                localStorage.removeItem('token')
                delete api.defaults.headers.common['Authorization']
            }
        }
    }
})
