<script setup lang="ts">
import { useAuthStore } from '../stores/auth'
import { computed, ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const authStore = useAuthStore()
const user = computed(() => authStore.user)

const stats = ref({
    sales: { today: 0, month: 0, year: 0 },
    treasury: { paid: 0, unpaid: 0, warning: false },
    top_products: [] as any[],
    chart: [] as any[],
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

// SVG Chart Helper
const maxChartValue = computed(() => {
    if (!stats.value.chart.length) return 1000
    const maxValue = Math.max(...stats.value.chart.map(d => Math.max(d.invoiced, d.quotes, d.paid)))
    return maxValue === 0 ? 1000 : maxValue * 1.1 
})

const yAxisTicks = computed(() => {
    const max = maxChartValue.value
    // Round to nice numbers
    const roundTo = max > 10000 ? 5000 : 1000
    const roundedMax = Math.ceil(max / roundTo) * roundTo
    return [0, 0.25, 0.5, 0.75, 1].map(p => Math.round((roundedMax * p) / roundTo) * roundTo)
})

const chartPoints = computed(() => {
    if (!stats.value.chart.length) return []
    const width = 1000
    const height = 250 // Slightly taller
    const paddingLeft = 80 // Space for Y axis
    const paddingRight = 40
    const paddingTop = 40
    const paddingBottom = 40
    
    const innerWidth = width - paddingLeft - paddingRight
    const innerHeight = height - paddingTop - paddingBottom
    const step = innerWidth / stats.value.chart.length
    
    return stats.value.chart.map((d, i) => {
        const xBase = paddingLeft + (i * step) + (step / 2)
        const scale = (val: number) => (height - paddingBottom) - ((val / maxChartValue.value) * innerHeight)
        
        return {
            x: xBase,
            label: d.label,
            bars: [
                { value: d.quotes,   y: scale(d.quotes),   color: '#3b82f6', label: 'Devis' },       // Blue
                { value: d.invoiced, y: scale(d.invoiced), color: '#10b981', label: 'Facturé' },    // Green
                { value: d.paid,     y: scale(d.paid),     color: '#f59e0b', label: 'Encaissé' }     // Amber
            ]
        }
    })
})
</script>

<template>
  <div class="pb-10">
    <!-- Page Header Premium -->
    <div class="mb-8 bg-gradient-to-r from-brand-700 to-brand-900 rounded-3xl p-8 shadow-xl shadow-brand-100 text-white relative overflow-hidden">
      <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div>
            <h1 class="text-3xl font-black tracking-tight">Bonjour, {{ user?.name || 'Admin' }} 👋</h1>
            <p class="text-brand-100 mt-2 font-medium">Pilotage Stratégique Kilatech - Analyse Financière & Performance.</p>
          </div>
          <div class="bg-white/10 backdrop-blur-md px-6 py-3 rounded-2xl border border-white/20">
              <span class="block text-[10px] font-bold uppercase tracking-widest text-brand-200">Mode</span>
              <span class="text-sm font-black">{{ user?.role?.name || 'Gérant' }}</span>
          </div>
      </div>
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>
    </div>

    <!-- ROW 1: CHIFFRE D'AFFAIRES -->
    <div class="mb-8">
        <div class="flex items-center gap-2 px-2 mb-6">
            <div class="w-2 h-6 bg-green-500 rounded-full"></div>
            <h2 class="text-lg font-black text-gray-800 uppercase tracking-tighter">Chiffre d'Affaires Global (TTC)</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">Aujourd'hui</span>
                <span class="text-3xl font-black text-green-600">{{ stats.sales.today.toLocaleString() }} <span class="text-xs">MAD</span></span>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1">Actuel ({{ new Date().toLocaleString('fr-FR', { month: 'long' }) }})</span>
                <span class="text-3xl font-black text-green-700">{{ stats.sales.month.toLocaleString() }} <span class="text-xs">MAD</span></span>
            </div>
            <div class="bg-brand-50/30 p-6 rounded-3xl shadow-sm border border-brand-100">
                <span class="text-[10px] font-bold text-brand-600 uppercase tracking-widest block mb-1">Exercice {{ new Date().getFullYear() }}</span>
                <span class="text-3xl font-black text-brand-900">{{ stats.sales.year.toLocaleString() }} <span class="text-xs text-brand-600 font-bold">MAD</span></span>
            </div>
        </div>
    </div>

    <!-- ROW 2: GRAPHIQUE & TRÉSORERIE -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        
        <!-- CHART PANEL -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-8 shadow-sm border border-gray-100">
             <div class="flex items-center justify-between mb-8">
                <h3 class="font-black text-gray-800 uppercase text-sm tracking-widest">Analyse Mensuelle Précise</h3>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-4 rounded-sm bg-blue-500"></div>
                        <span class="text-[9px] font-black text-gray-400 uppercase">Devis</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-4 rounded-sm bg-emerald-500"></div>
                        <span class="text-[9px] font-black text-gray-400 uppercase">Facturé</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-4 rounded-sm bg-amber-500"></div>
                        <span class="text-[9px] font-black text-gray-400 uppercase">Encaissé</span>
                    </div>
                </div>
            </div>
            <div class="h-64 w-full relative">
                <!-- SVG Chart -->
                <svg v-if="stats.chart.length" viewBox="0 0 1000 250" preserveAspectRatio="none" class="w-full h-full overflow-visible">
                    <!-- Y-Axis Grid Lines -->
                    <g v-for="tick in yAxisTicks" :key="'grid-'+tick">
                        <line x1="80" :y1="210 - (tick / maxChartValue * 170)" x2="960" :y2="210 - (tick / maxChartValue * 170)" 
                            stroke="#f3f4f6" stroke-width="1" />
                        <text x="70" :y="210 - (tick / maxChartValue * 170)" text-anchor="end" 
                            class="text-[9px] font-bold fill-gray-300" alignment-baseline="middle">
                            {{ tick.toLocaleString() }}
                        </text>
                    </g>

                    <!-- Bars Groups -->
                    <g v-for="(group, i) in chartPoints" :key="'group-'+i">
                        <!-- Group of 3 bars -->
                        <rect v-for="(bar, bi) in group.bars" :key="'bar-'+bi"
                            :x="group.x - 22 + (bi * 15)" :y="bar.y" width="12" :height="210 - bar.y"
                            :fill="bar.color" rx="2" class="transition-all duration-500 hover:brightness-110 cursor-pointer" />
                        
                        <!-- Month Label in SVG -->
                        <text :x="group.x" y="235" text-anchor="middle" class="text-[8px] font-black fill-gray-400 uppercase">
                            {{ group.label.split(' ')[0] }}
                        </text>
                    </g>

                    <!-- Base Line -->
                    <line x1="80" y1="210" x2="960" y2="210" stroke="#e5e7eb" stroke-width="1" />
                </svg>

                <!-- Tooltips (Dynamic) -->
                <template v-for="(group, i) in chartPoints" :key="'tips-'+i">
                    <div v-for="(bar, bi) in group.bars" :key="'tip-'+bi"
                        class="absolute group/bar cursor-pointer"
                        :style="{ left: `${((group.x - 22 + (bi * 15) + 6) / 1000) * 100}%`, top: `${(bar.y / 250) * 100}%`, width: '12px', height: `${(210 - bar.y) / 250 * 100}%` }">
                        <div class="opacity-0 group-hover/bar:opacity-100 bg-gray-900 text-white text-[9px] font-black px-2 py-1 rounded-lg absolute -top-10 left-1/2 -translate-x-1/2 whitespace-nowrap shadow-xl z-50 pointer-events-none">
                            {{ bar.label }}: {{ bar.value.toLocaleString() }} MAD
                            <div class="absolute w-1.5 h-1.5 bg-gray-900 rotate-45 -bottom-1 left-1/2 -translate-x-1/2"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- TREASURY PANEL -->
        <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 flex flex-col">
            <h3 class="font-black text-gray-800 uppercase text-sm tracking-widest mb-8">Suivi Trésorerie</h3>
            <div class="flex-1 space-y-6">
                <div>
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Total Encaissé</span>
                    <span class="text-2xl font-black text-green-600">{{ stats.treasury.paid.toLocaleString() }} <span class="text-xs">MAD</span></span>
                    <div class="w-full h-2 bg-gray-100 rounded-full mt-2">
                        <div class="h-full bg-green-500 rounded-full" :style="{ width: `${(stats.treasury.paid / (stats.treasury.paid + stats.treasury.unpaid || 1)) * 100}%` }"></div>
                    </div>
                </div>
                <div class="pt-6 border-t border-gray-50">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Reste à Recouvrer (Impayés)</span>
                    <span class="text-2xl font-black" :class="stats.treasury.warning ? 'text-red-600' : 'text-gray-900'">{{ stats.treasury.unpaid.toLocaleString() }} <span class="text-xs">MAD</span></span>
                    <div v-if="stats.treasury.warning" class="mt-4 p-3 bg-red-50 rounded-xl flex items-center gap-3">
                        <div class="p-2 bg-red-600 text-white rounded-lg">
                             <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <span class="text-[10px] font-black text-red-700 leading-tight uppercase">Alerte Recouvrement Critique (>25%)</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ROW 3: TOP 10 PRODUCTS & ALERTS -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- TOP 10 PRODUCTS -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-8 shadow-sm border border-gray-100">
            <h3 class="font-black text-gray-800 uppercase text-sm tracking-widest mb-6">Top 10 Performance Articles (Kilatech)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-gray-50">
                            <th class="text-left py-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Produit</th>
                            <th class="text-right py-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Qté</th>
                            <th class="text-right py-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Marge Profit (MAD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(prod, idx) in stats.top_products" :key="idx" class="border-b border-gray-50 last:border-0 hover:bg-gray-50 transition-colors">
                            <td class="py-4">
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 flex items-center justify-center bg-brand-100 text-brand-700 text-[10px] font-black rounded-lg">{{ idx + 1 }}</span>
                                    <span class="text-sm font-bold text-gray-800">{{ prod.name }}</span>
                                </div>
                            </td>
                            <td class="text-right py-4 text-sm font-medium text-gray-500">{{ Number(prod.total_qty).toLocaleString() }}</td>
                            <td class="text-right py-4 text-sm font-black text-brand-600">+ {{ Number(prod.total_profit).toLocaleString() }}</td>
                        </tr>
                        <tr v-if="!stats.top_products.length">
                            <td colspan="3" class="py-10 text-center text-xs text-gray-400 italic">Aucune donnée de vente pour le moment</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- OPERATIONAL ALERTS SMALL -->
        <div class="space-y-6">
            <h3 class="font-black text-gray-800 uppercase text-sm tracking-widest px-2">Flux de Travail</h3>
            
            <div class="bg-indigo-50 border border-indigo-100 p-6 rounded-3xl flex items-center justify-between">
                <div>
                    <span class="block text-xl font-black text-indigo-700">{{ stats.pending_quotes }}</span>
                    <span class="text-[10px] font-bold text-indigo-400 uppercase">Devis à Relancer</span>
                </div>
                <router-link :to="{ name: 'quotes' }" class="p-3 bg-white rounded-2xl text-indigo-600 shadow-sm"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5l7 7-7 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></router-link>
            </div>

            <div class="bg-red-50 border border-red-100 p-6 rounded-3xl flex items-center justify-between">
                <div>
                    <span class="block text-xl font-black text-red-700">{{ stats.stock_alerts }}</span>
                    <span class="text-[10px] font-bold text-red-400 uppercase">Alertes Stock/DLC</span>
                </div>
                <router-link :to="{ name: 'stock' }" class="p-3 bg-white rounded-2xl text-red-600 shadow-sm"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5l7 7-7 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></router-link>
            </div>

            <div class="bg-green-50 border border-green-100 p-6 rounded-3xl flex items-center justify-between">
                <div>
                    <span class="block text-xl font-black text-green-700">{{ stats.qa_receptions }}</span>
                    <span class="text-[10px] font-bold text-green-400 uppercase">Contrôles QA</span>
                </div>
                <router-link :to="{ name: 'stock.qa' }" class="p-3 bg-white rounded-2xl text-green-600 shadow-sm"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5l7 7-7 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></router-link>
            </div>

             <div class="bg-brand-50 border border-brand-100 p-6 rounded-3xl flex flex-col justify-center items-center text-center">
                <span class="text-[10px] font-black text-brand-600 uppercase tracking-widest mb-2">Vision Marketing</span>
                <router-link :to="{ name: 'marketing' }" class="text-xs font-black text-brand-900 underline underline-offset-4 hover:text-brand-700">Gérer les {{ stats.marketing_pending }} articles →</router-link>
            </div>
        </div>
    </div>
  </div>
</template>
