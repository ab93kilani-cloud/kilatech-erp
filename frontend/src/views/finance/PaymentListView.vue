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

const activeTab = ref<'clients' | 'suppliers'>('clients')
const invoicePayments = ref<InvoicePayment[]>([])
const supplierPayments = ref<SupplierPayment[]>([])
const isLoading = ref(true)
const error = ref('')

const fetchPayments = async () => {
    isLoading.value = true
    error.value = ''
    try {
        const [invoiceRes, supplierRes] = await Promise.all([
            api.get('/invoice-payments'),
            api.get('/supplier-payments')
        ])
        invoicePayments.value = invoiceRes.data
        supplierPayments.value = supplierRes.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des règlements.'
    } finally {
        isLoading.value = false
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
    <div class="flex space-x-1 bg-white p-1 rounded-2xl shadow-sm border border-gray-100 mb-8 max-w-md">
        <button 
            @click="activeTab = 'clients'"
            :class="[
                'flex-1 py-3 px-6 text-xs font-black uppercase tracking-widest rounded-xl transition-all',
                activeTab === 'clients' ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'text-gray-400 hover:bg-gray-50'
            ]"
        >
            Clients (Encaissements)
        </button>
        <button 
            @click="activeTab = 'suppliers'"
            :class="[
                'flex-1 py-3 px-6 text-xs font-black uppercase tracking-widest rounded-xl transition-all',
                activeTab === 'suppliers' ? 'bg-purple-600 text-white shadow-lg shadow-purple-200' : 'text-gray-400 hover:bg-gray-50'
            ]"
        >
            Fournisseurs (Décaissements)
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

    <!-- Data Table -->
    <div v-else class="bg-white rounded-[2rem] shadow-xl shadow-gray-200/50 border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
          <div class="p-8">
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
                            + {{ Number(pay.amount).toLocaleString() }} DH
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
                            - {{ Number(pay.amount).toLocaleString() }} DH
                        </td>
                    </tr>
                </template>

                <!-- EMPTY STATES -->
                <tr v-if="(activeTab === 'clients' && invoicePayments.length === 0) || (activeTab === 'suppliers' && supplierPayments.length === 0)">
                    <td colspan="4" class="py-32">
                        <div class="flex flex-col items-center">
                            <div class="w-16 h-16 rounded-full bg-gray-50 flex items-center justify-center text-gray-200 mb-4">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                            <p class="text-xs font-black text-gray-300 uppercase tracking-widest">Aucun règlement enregistré pour le moment.</p>
                        </div>
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
