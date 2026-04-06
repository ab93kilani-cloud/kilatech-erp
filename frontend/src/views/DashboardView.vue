<script setup lang="ts">
import { useAuthStore } from '../stores/auth'
import { computed, ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const authStore = useAuthStore()
const user = computed(() => authStore.user)

const stats = ref({
    sales: { today: 0, month: 0, year: 0 },
    purchases: { today: 0, month: 0, year: 0 },
    stock_alerts: 0,
    pending_quotes: 0,
    qa_receptions: 0,
    marketing_pending: 0
})

const fetchStats = async () => {
    try {
        const res = await api.get('/dashboard-stats')
        stats.value = res.data
    } catch (e) {
        console.error('Failed to fetch dashboard stats')
    }
}

onMounted(fetchStats)
</script>

<template>
  <div>
    <!-- Page Header Premium -->
    <div class="mb-10 bg-gradient-to-r from-brand-700 to-brand-900 rounded-3xl p-8 shadow-xl shadow-brand-100 text-white relative overflow-hidden">
      <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <h1 class="text-3xl font-black tracking-tight">Bonjour, {{ user?.name || 'Admin' }} 👋</h1>
            <p class="text-brand-100 mt-2 font-medium">Tableau de bord de pilotage Kilatech - Données consolidées en temps réel.</p>
          </div>
          <div class="flex gap-4">
              <div class="bg-white/10 backdrop-blur-md px-6 py-3 rounded-2xl border border-white/20">
                  <span class="block text-[10px] font-bold uppercase tracking-widest text-brand-200">Session</span>
                  <span class="text-sm font-black">{{ user?.role?.name }}</span>
              </div>
          </div>
      </div>
      <!-- Decorative circle -->
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>
    </div>

    <!-- MAIN STATS SECTION: PERFORMANCE & ACHATS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
        
        <!-- COLUMN: PERFORMANCE VENTES -->
        <div class="space-y-6">
            <div class="flex items-center gap-2 px-2">
                <div class="w-2 h-6 bg-green-500 rounded-full"></div>
                <h2 class="text-lg font-black text-gray-800 uppercase tracking-tighter">Performance Ventes (TTC)</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Aujourd'hui</span>
                    <span class="text-xl font-black text-green-600 block mb-1">{{ stats.sales.today.toLocaleString() }}</span>
                    <span class="text-[10px] font-medium text-gray-500">MAD Encaissés</span>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Ce Mois</span>
                    <span class="text-xl font-black text-green-700 block mb-1">{{ stats.sales.month.toLocaleString() }}</span>
                    <span class="text-[10px] font-medium text-gray-500">CA Mensuel</span>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-brand-100 bg-brand-50/10 hover:shadow-md transition-shadow">
                    <span class="text-[10px] font-bold text-brand-600 uppercase tracking-widest block mb-2">Annuel {{ new Date().getFullYear() }}</span>
                    <span class="text-xl font-black text-brand-900 block mb-1">{{ stats.sales.year.toLocaleString() }}</span>
                    <span class="text-[10px] font-medium text-brand-500">Cumul Annuel</span>
                </div>
            </div>
        </div>

        <!-- COLUMN: VOLUME ACHATS -->
        <div class="space-y-6">
            <div class="flex items-center gap-2 px-2">
                <div class="w-2 h-6 bg-amber-500 rounded-full"></div>
                <h2 class="text-lg font-black text-gray-800 uppercase tracking-tighter">Volume d'Achats (Dépenses)</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Aujourd'hui</span>
                    <span class="text-xl font-black text-amber-600 block mb-1">{{ stats.purchases.today.toLocaleString() }}</span>
                    <span class="text-[10px] font-medium text-gray-500">Commandé</span>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Ce Mois</span>
                    <span class="text-xl font-black text-amber-700 block mb-1">{{ stats.purchases.month.toLocaleString() }}</span>
                    <span class="text-[10px] font-medium text-gray-500">Engagé</span>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-amber-100 bg-amber-50/20 hover:shadow-md transition-shadow">
                    <span class="text-[10px] font-bold text-amber-600 uppercase tracking-widest block mb-2">Total Annuel</span>
                    <span class="text-xl font-black text-amber-900 block mb-1">{{ stats.purchases.year.toLocaleString() }}</span>
                    <span class="text-[10px] font-medium text-amber-500">Coûts d'acquisition</span>
                </div>
            </div>
        </div>

    </div>

    <!-- ALERTES & OPÉRATIONS SECTION -->
    <div class="space-y-6">
        <div class="flex items-center gap-2 px-2">
            <div class="w-2 h-6 bg-red-400 rounded-full"></div>
            <h2 class="text-lg font-black text-gray-800 uppercase tracking-tighter">Alertes & Flux Opérationnels</h2>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Alert Stock -->
            <div class="bg-white overflow-hidden shadow-sm rounded-3xl border border-red-100 hover:border-red-400 transition-colors">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-red-50 p-3 rounded-2xl text-red-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <span class="text-2xl font-black text-red-600">{{ stats.stock_alerts }}</span>
                    </div>
                    <dt class="text-sm font-bold text-gray-700">Alertes Stock / DLC</dt>
                    <dd class="text-[10px] font-medium text-gray-400 mt-1 uppercase tracking-wider">Lots périmés ou critiques</dd>
                    <div class="mt-4 pt-4 border-t border-gray-50">
                        <router-link :to="{ name: 'stock' }" class="text-xs font-black text-red-600 hover:underline px-2 py-1 bg-red-50 rounded-lg">Gérer le stock →</router-link>
                    </div>
                </div>
            </div>

            <!-- Devis Pending -->
            <div class="bg-white overflow-hidden shadow-sm rounded-3xl border border-blue-100 hover:border-blue-400 transition-colors">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-blue-50 p-3 rounded-2xl text-blue-600">
                           <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                           </svg>
                        </div>
                        <span class="text-2xl font-black text-blue-600">{{ stats.pending_quotes }}</span>
                    </div>
                    <dt class="text-sm font-bold text-gray-700">Devis à Relancer</dt>
                    <dd class="text-[10px] font-medium text-gray-400 mt-1 uppercase tracking-wider">Opportunités en attente</dd>
                    <div class="mt-4 pt-4 border-t border-gray-50">
                        <router-link :to="{ name: 'quotes' }" class="text-xs font-black text-blue-600 hover:underline px-2 py-1 bg-blue-50 rounded-lg">Ouvrir les Ventes →</router-link>
                    </div>
                </div>
            </div>

            <!-- QA Receptions -->
            <div class="bg-white overflow-hidden shadow-sm rounded-3xl border border-green-100 hover:border-green-400 transition-colors">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-green-50 p-3 rounded-2xl text-green-600">
                          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                          </svg>
                        </div>
                        <span class="text-2xl font-black text-green-600">{{ stats.qa_receptions }}</span>
                    </div>
                    <dt class="text-sm font-bold text-gray-700">Contrôles Qualité (QA)</dt>
                    <dd class="text-[10px] font-medium text-gray-400 mt-1 uppercase tracking-wider">Réceptions à valider</dd>
                    <div class="mt-4 pt-4 border-t border-gray-50">
                        <router-link :to="{ name: 'stock.qa' }" class="text-xs font-black text-green-600 hover:underline px-2 py-1 bg-green-50 rounded-lg">Lancer le contrôle →</router-link>
                    </div>
                </div>
            </div>

            <!-- Marketing Articles -->
            <div class="bg-white overflow-hidden shadow-sm rounded-3xl border border-indigo-100 hover:border-indigo-400 transition-colors">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-indigo-50 p-3 rounded-2xl text-indigo-600">
                          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                          </svg>
                        </div>
                        <span class="text-2xl font-black text-indigo-600">{{ stats.marketing_pending }}</span>
                    </div>
                    <dt class="text-sm font-bold text-gray-700">Contenus Web / SEO</dt>
                    <dd class="text-[10px] font-medium text-gray-400 mt-1 uppercase tracking-wider">Articles en brouillon</dd>
                    <div class="mt-4 pt-4 border-t border-gray-50">
                        <router-link :to="{ name: 'marketing' }" class="text-xs font-black text-indigo-600 hover:underline px-2 py-1 bg-indigo-50 rounded-lg">Éditer Blog →</router-link>
                    </div>
                </div>
            </div>

        </div>
    </div>
  </div>
>
</template>
