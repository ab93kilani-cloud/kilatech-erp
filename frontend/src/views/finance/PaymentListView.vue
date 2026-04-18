<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

interface InvoicePayment {
  id: number;
  invoice: { 
    invoice_number: string, 
    order: { client: { company_name: string, type: string, contact_name: string } } 
  };
  amount: number;
  payment_date: string;
  payment_method: string;
  reference: string;
}

interface SupplierPayment {
  id: number;
  purchase_order: { 
    po_number: string, 
    supplier: { name: string } 
  };
  amount: number;
  payment_date: string;
  payment_method: string;
  reference: string;
}

const formatPrice = (val: number | string) => {
    return new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(val))
}

const activeTab = ref<'clients' | 'suppliers' | 'overdue'>('clients')
const invoicePayments = ref<InvoicePayment[]>([])
const supplierPayments = ref<SupplierPayment[]>([])
const financeOverview = ref<{ unpaid_invoices: any[], unpaid_purchases: any[] }>({
    unpaid_invoices: [],
    unpaid_purchases: []
})

const isLoading = ref(true)
const error = ref('')

const fetchPayments = async () => {
    isLoading.value = true
    error.value = ''
    try {
        const [invoiceRes, supplierRes, overviewRes] = await Promise.all([
            api.get('/invoice-payments'),
            api.get('/supplier-payments'),
            api.get('/accounting/finance-overview')
        ])
        invoicePayments.value = invoiceRes.data
        supplierPayments.value = supplierRes.data
        financeOverview.value = overviewRes.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des règlements.'
    } finally {
        isLoading.value = false
    }
}

const isOverdue = (date: string) => {
    if (!date) return false
    return new Date(date) < new Date()
}

const updateDueDate = async (id: number, type: 'invoice' | 'purchase', event: any) => {
    const newDate = event.target.value
    if (!newDate) return
    try {
        await api.post('/accounting/quick-update-due-date', { id, type, due_date: newDate })
        // Mettre à jour localement pour feedback immédiat
        if (type === 'invoice') {
            const inv = financeOverview.value.unpaid_invoices.find(i => i.id === id)
            if (inv) inv.due_date = newDate
        } else {
            const po = financeOverview.value.unpaid_purchases.find(p => p.id === id)
            if (po) po.due_date = newDate
        }
    } catch (e: any) {
        alert('Erreur lors de la mise à jour de la date.')
    }
}

onMounted(() => {
    fetchPayments()
})
</script>

<template>
  <div class="max-w-7xl mx-auto py-8">
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-3xl font-black text-gray-900 tracking-tight uppercase">Journal des <span class="text-blue-600">Règlements</span></h1>
        <p class="mt-2 text-sm text-gray-700">Consultez l'historique des encaissements clients et des décaissements fournisseurs.</p>
      </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="flex space-x-1 bg-white p-1 rounded-2xl shadow-sm border border-gray-100 mb-8 max-w-2xl">
        <button 
            @click="activeTab = 'clients'"
            :class="[
                'flex-1 py-3 px-6 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all',
                activeTab === 'clients' ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'text-gray-400 hover:bg-gray-50'
            ]"
        >
            Clients (Entrées)
        </button>
        <button 
            @click="activeTab = 'suppliers'"
            :class="[
                'flex-1 py-3 px-6 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all',
                activeTab === 'suppliers' ? 'bg-purple-600 text-white shadow-lg shadow-purple-200' : 'text-gray-400 hover:bg-gray-50'
            ]"
        >
            Fournisseurs (Sorties)
        </button>
        <button 
            @click="activeTab = 'overdue'"
            :class="[
                'flex-1 py-3 px-6 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all flex items-center justify-center gap-2',
                activeTab === 'overdue' ? 'bg-red-600 text-white shadow-lg shadow-red-200' : 'text-gray-400 hover:bg-gray-50'
            ]"
        >
            <span class="w-2 h-2 rounded-full bg-white animate-ping" v-if="financeOverview.unpaid_invoices.some(i => isOverdue(i.due_date))"></span>
            Échéancier & Relances
        </button>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 p-6 rounded-3xl border border-red-100 mb-8 text-center">
        <p class="text-sm font-bold text-red-700">{{ error }}</p>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
        <p class="text-xs font-black text-gray-400 uppercase tracking-widest">Chargement des données...</p>
    </div>

    <!-- Data Tables -->
    <div v-else class="space-y-10">
        
        <!-- HISTORY VIEW (CLIENTS / SUPPLIERS) -->
        <div v-if="activeTab !== 'overdue'" class="bg-white rounded-[2rem] shadow-xl shadow-gray-200/50 border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto p-8">
                <table class="min-w-full border-separate border-spacing-y-2">
                <thead>
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest text-left">
                    <th scope="col" class="px-6 py-4">Date</th>
                    <th scope="col" class="px-6 py-4">
                        {{ activeTab === 'clients' ? 'Client / Facture' : 'Fournisseur / BC' }}
                    </th>
                    <th scope="col" class="px-6 py-4">Moyen / Référence</th>
                    <th scope="col" class="px-6 py-4 text-right">Montant</th>
                    </tr>
                </thead>
                <tbody class="text-xs">
                    <!-- CLIENT PAYMENTS -->
                    <template v-if="activeTab === 'clients'">
                        <tr v-for="pay in invoicePayments" :key="pay.id" class="group transition-all">
                            <td class="whitespace-nowrap px-6 py-4 rounded-l-2xl bg-gray-50 group-hover:bg-blue-50/50 font-bold text-gray-900 border-y border-l border-transparent group-hover:border-blue-100 transition-all">
                                {{ new Date(pay.payment_date).toLocaleDateString() }}
                            </td>
                            <td class="px-6 py-4 bg-gray-50 group-hover:bg-blue-50/50 border-y border-transparent group-hover:border-blue-100 transition-all">
                                <div class="font-black text-gray-900">{{ pay.invoice?.order?.client?.company_name || pay.invoice?.order?.client?.contact_name || 'Passage Direct' }}</div>
                                <div class="text-[10px] text-blue-600 font-bold mt-0.5">FA: {{ pay.invoice?.invoice_number }}</div>
                            </td>
                            <td class="px-6 py-4 bg-gray-50 group-hover:bg-blue-50/50 border-y border-transparent group-hover:border-blue-100 transition-all text-gray-500">
                                <span class="font-bold capitalize">{{ pay.payment_method }}</span>
                                <span v-if="pay.reference" class="ml-2 px-2 py-0.5 bg-white rounded-lg border border-gray-200 text-[9px]">{{ pay.reference }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 rounded-r-2xl bg-gray-50 group-hover:bg-blue-50/50 text-right font-black text-green-600 border-y border-r border-transparent group-hover:border-blue-100 transition-all">
                                + {{ formatPrice(pay.amount) }} DH
                            </td>
                        </tr>
                    </template>

                    <!-- SUPPLIER PAYMENTS -->
                    <template v-else>
                        <tr v-for="pay in supplierPayments" :key="pay.id" class="group transition-all">
                            <td class="whitespace-nowrap px-6 py-4 rounded-l-2xl bg-gray-50 group-hover:bg-purple-50/50 font-bold text-gray-900 border-y border-l border-transparent group-hover:border-purple-100 transition-all">
                                {{ new Date(pay.payment_date).toLocaleDateString() }}
                            </td>
                            <td class="px-6 py-4 bg-gray-50 group-hover:bg-purple-50/50 border-y border-transparent group-hover:border-purple-100 transition-all">
                                <div class="font-black text-gray-900">{{ pay.purchase_order?.supplier?.name }}</div>
                                <div class="text-[10px] text-purple-600 font-bold mt-0.5">BC: {{ pay.purchase_order?.po_number }}</div>
                            </td>
                            <td class="px-6 py-4 bg-gray-50 group-hover:bg-purple-50/50 border-y border-transparent group-hover:border-purple-100 transition-all text-gray-500">
                                <span class="font-bold capitalize">{{ pay.payment_method }}</span>
                                <span v-if="pay.reference" class="ml-2 px-2 py-0.5 bg-white rounded-lg border border-gray-200 text-[9px]">{{ pay.reference }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 rounded-r-2xl bg-gray-50 group-hover:bg-purple-50/50 text-right font-black text-red-600 border-y border-r border-transparent group-hover:border-purple-100 transition-all">
                                - {{ formatPrice(pay.amount) }} DH
                            </td>
                        </tr>
                    </template>
                </tbody>
                </table>
            </div>
        </div>

        <!-- OVERDUE / UNPAID VIEW -->
        <div v-else class="space-y-8">
            <!-- SECTION CLIENTS -->
            <div class="bg-white rounded-[2rem] shadow-xl shadow-red-100/30 border border-red-50 p-8">
                <h3 class="text-lg font-black text-gray-900 uppercase tracking-tight mb-6 flex items-center gap-3">
                    <span class="w-2 h-8 bg-red-600 rounded-full"></span>
                    Recouvrement Clients (En attente)
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2">
                        <thead>
                            <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest text-left">
                                <th class="px-6 py-4">Status / Échéance</th>
                                <th class="px-6 py-4">Client / Facture</th>
                                <th class="px-6 py-4 text-right">Reste à encaisser</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs">
                            <tr v-for="inv in financeOverview.unpaid_invoices" :key="inv.id" class="group transition-all">
                                <td class="px-6 py-4 rounded-l-2xl bg-gray-50 border-y border-l border-transparent transition-all">
                                    <span v-if="isOverdue(inv.due_date)" class="px-3 py-1 bg-red-100 text-red-600 font-black rounded-lg text-[9px] uppercase">EN RETARD</span>
                                    <span v-else class="px-3 py-1 bg-blue-100 text-blue-600 font-black rounded-lg text-[9px] uppercase">À VENIR</span>
                                    <div class="mt-2 group/date relative">
                                        <div class="text-[10px] font-bold text-gray-900 flex items-center gap-2">
                                            <span>Échéance :</span>
                                            <input 
                                                type="date" 
                                                :value="inv.due_date ? inv.due_date.split('T')[0] : ''" 
                                                @change="updateDueDate(inv.id, 'invoice', $event)"
                                                class="bg-white/50 border border-gray-200 px-2 py-1 text-[10px] font-black text-blue-600 focus:ring-2 focus:ring-blue-500 cursor-pointer hover:bg-white rounded-lg shadow-sm transition-all h-8"
                                            >
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 bg-gray-50 border-y border-transparent transition-all">
                                    <div class="font-black text-gray-900">{{ inv.order?.client?.company_name || inv.order?.client?.contact_name }}</div>
                                    <div class="text-[10px] text-gray-400 font-bold mt-1">FA: {{ inv.invoice_number }}</div>
                                </td>
                                <td class="px-6 py-4 rounded-r-2xl bg-gray-50 text-right font-black text-gray-900 border-y border-r border-transparent">
                                    <div class="text-sm font-black">{{ formatPrice(inv.balance_due) }} DH</div>
                                    <div class="text-[9px] text-gray-400 mt-1 uppercase tracking-widest">Sur total {{ formatPrice(inv.total_amount) }}</div>
                                </td>
                            </tr>
                            <tr v-if="financeOverview.unpaid_invoices.length === 0">
                                <td colspan="3" class="py-10 text-center text-gray-300 font-bold">Toutes les factures clients sont soldées !</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION FOURNISSEURS -->
            <div class="bg-white rounded-[2rem] shadow-xl shadow-gray-200/50 border border-gray-100 p-8">
                <h3 class="text-lg font-black text-gray-900 uppercase tracking-tight mb-6 flex items-center gap-3">
                    <span class="w-2 h-8 bg-purple-600 rounded-full"></span>
                    Dettes Fournisseurs (À régler)
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full border-separate border-spacing-y-2">
                        <thead>
                            <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest text-left">
                                <th class="px-6 py-4">Échéance</th>
                                <th class="px-6 py-4">Fournisseur / BC</th>
                                <th class="px-6 py-4 text-right">Montant</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs">
                            <tr v-for="po in financeOverview.unpaid_purchases" :key="po.id" class="group transition-all text-gray-600">
                                <td class="px-6 py-4 rounded-l-2xl bg-gray-50 border-y border-l border-transparent">
                                    <div class="flex items-center gap-2">
                                        <input 
                                            type="date" 
                                            :value="po.due_date ? po.due_date.split('T')[0] : ''" 
                                            @change="updateDueDate(po.id, 'purchase', $event)"
                                            class="bg-white/50 border border-gray-200 px-2 py-1 text-[11px] font-black text-purple-600 focus:ring-2 focus:ring-purple-500 cursor-pointer hover:bg-white rounded-lg shadow-sm transition-all h-8"
                                        >
                                    </div>
                                    <span v-if="isOverdue(po.due_date)" class="text-[9px] font-bold text-red-500 uppercase tracking-tighter mt-2 inline-block">Échéance dépassée</span>
                                </td>
                                <td class="px-6 py-4 bg-gray-50 border-y border-transparent">
                                    <div class="font-black text-gray-900">{{ po.supplier?.name }}</div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">BC: {{ po.po_number }}</div>
                                        <span v-if="po.balance_due < 0.5" class="bg-green-100 text-green-700 text-[8px] font-black px-1.5 py-0.5 rounded uppercase">SOLDE OK</span>
                                    </div>

                                    <!-- Pending Checks details -->
                                    <div v-if="po.payments?.some((p: any) => p.maturity_date && new Date(p.maturity_date) >= new Date())" class="mt-2 space-y-1">
                                        <div v-for="pay in po.payments.filter((p: any) => p.maturity_date && new Date(p.maturity_date) >= new Date())" :key="pay.id" class="text-[8px] font-bold bg-blue-50 text-blue-600 px-2 py-1 rounded inline-flex items-center gap-1 border border-blue-100 uppercase">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Chèque pour le {{ new Date(pay.maturity_date).toLocaleDateString() }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 rounded-r-2xl bg-gray-50 text-right font-black text-gray-900 border-y border-r border-transparent">
                                    <div class="text-sm font-black">{{ formatPrice(po.balance_due) }} DH</div>
                                    <div class="text-[9px] text-gray-400 mt-1 uppercase tracking-widest">Sur total {{ formatPrice(po.total_ttc) }}</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
  </div>
</template>
