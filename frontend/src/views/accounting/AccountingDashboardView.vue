<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const data = ref<any>({
    sales_invoices: [],
    purchase_invoices: [],
    bank_statements: [],
    suppliers_missing_certificates: [],
    suppliers_with_certificates: [],
    overdue_sales_count: 0,
    overdue_purchase_count: 0
})

const currentYear = new Date().getFullYear()
const years = [currentYear, currentYear - 1]
const selectedYear = ref(currentYear)
const selectedQuarter = ref(Math.ceil((new Date().getMonth() + 1) / 3))
const isLoading = ref(true)

const fetchDashboard = async () => {
    isLoading.value = true
    try {
        const res = await api.get('/accounting/dashboard', {
            params: { quarter: selectedQuarter.value, year: selectedYear.value }
        })
        data.value = res.data
    } catch (e) {
        console.error('Failed to load accounting dashboard')
    } finally {
        isLoading.value = false
    }
}

const downloadFile = async (path: string, fileName: string = 'document.pdf') => {
    try {
        const isApiRoute = path.includes('/pdf');
        // Les routes /pdf sont gérées par les contrôleurs dédiés. 
        // Les autres sont des fichiers physiques gérés par FileController via /documents/
        const url = isApiRoute 
            ? (path.startsWith('/') ? path : `/${path}`)
            : `/documents/${path.startsWith('/') ? path.slice(1) : path}`;

        const response = await api.get(url, { responseType: 'blob' });

        const blob = new Blob([response.data], { type: 'application/pdf' });
        const blobUrl = window.URL.createObjectURL(blob);
        
        const link = document.createElement('a');
        link.href = blobUrl;
        link.setAttribute('download', fileName.endsWith('.pdf') ? fileName : `${fileName}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
        console.error('Download failed', e);
        alert('Erreur: Impossible de récupérer le document. Il est peut-être introuvable sur le serveur.');
    }
}

const getQuarterName = (q: number) => {
    const names = ['1er Trimestre (Jan-Mar)', '2ème Trimestre (Avr-Juin)', '3ème Trimestre (Juil-Sep)', '4ème Trimestre (Oct-Déc)']
    return names[q - 1]
}

onMounted(fetchDashboard)
</script>

<template>
  <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
      <div>
        <h1 class="text-4xl font-black text-gray-900 tracking-tight uppercase">Espace <span class="text-blue-600">Comptabilité</span></h1>
        <p class="mt-2 text-sm text-gray-600">Centralisation des documents financiers et justificatifs trimestriels.</p>
      </div>
      
      <div class="flex flex-wrap items-center gap-3 bg-white p-2 rounded-[2rem] shadow-sm border border-gray-100">
          <select v-model="selectedQuarter" @change="fetchDashboard" class="px-6 py-3 rounded-full border-0 bg-gray-50 text-sm font-bold text-gray-700 focus:ring-0 cursor-pointer">
              <option :value="1">T1</option>
              <option :value="2">T2</option>
              <option :value="3">T3</option>
              <option :value="4">T4</option>
          </select>
          <select v-model="selectedYear" @change="fetchDashboard" class="px-6 py-3 rounded-full border-0 bg-gray-50 text-sm font-bold text-gray-700 focus:ring-0 cursor-pointer">
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
          <div class="h-8 w-px bg-gray-100 mx-2"></div>
          <div class="px-4 text-[10px] font-black uppercase tracking-widest text-blue-600">
              {{ getQuarterName(selectedQuarter) }}
          </div>
      </div>
    </div>
    
    <!-- Red Alert Counter for Overdue Items -->
    <div v-if="(data.overdue_sales_count + data.overdue_purchase_count) > 0" class="mb-10 animate-pulse">
        <div class="bg-red-600 rounded-[2rem] p-6 shadow-2xl shadow-red-200 text-white flex items-center justify-between">
            <div class="flex items-center gap-6">
                <div class="w-16 h-16 rounded-2xl bg-white/20 flex items-center justify-center backdrop-blur-md">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <h3 class="text-xl font-black uppercase tracking-tight">Alerte Échéances Dépassées</h3>
                    <p class="text-sm font-bold text-red-100 uppercase tracking-widest mt-1">
                        {{ data.overdue_sales_count }} Factures Clients • {{ data.overdue_purchase_count }} Règlements Fournisseurs en retard
                    </p>
                </div>
            </div>
            <router-link :to="{ name: 'payments' }" class="px-8 py-4 bg-white text-red-600 rounded-xl font-black uppercase tracking-widest text-xs hover:bg-red-50 transition-colors shadow-lg">
                Gérer le recouvrement
            </router-link>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-6 mb-10">
        <div class="bg-white p-8 rounded-[2rem] shadow-xl shadow-gray-100/50 border border-gray-50 flex flex-col items-center">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Ventes (Factures)</div>
            <div class="text-3xl font-black text-gray-900">{{ data.sales_invoices.length }}</div>
        </div>
        <div class="bg-white p-8 rounded-[2rem] shadow-xl shadow-gray-100/50 border border-gray-50 flex flex-col items-center">
            <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
            </div>
            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Achats (Confirmés)</div>
            <div class="text-3xl font-black text-gray-900">{{ data.purchase_invoices.length }}</div>
        </div>
        <div class="bg-white p-8 rounded-[2rem] shadow-xl shadow-gray-100/50 border border-gray-50 flex flex-col items-center">
            <div class="w-12 h-12 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Relevés Banque</div>
            <div class="text-3xl font-black text-gray-900">{{ data.bank_statements.length }}</div>
        </div>
        <div class="bg-white p-8 rounded-[2rem] shadow-xl shadow-red-100/50 border border-red-50 flex flex-col items-center">
            <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Certs. Manquants</div>
            <div class="text-3xl font-black text-red-600">{{ data.suppliers_missing_certificates.length }}</div>
        </div>
        <div class="bg-white p-8 rounded-[2rem] shadow-xl shadow-gray-100/50 border border-gray-50 flex flex-col items-center">
            <div class="w-12 h-12 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Règlements reçus</div>
            <div class="text-3xl font-black text-gray-900">{{ data.payments?.length || 0 }}</div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
        <!-- VENTES SECTION -->
        <div class="bg-white p-10 rounded-[3rem] shadow-xl shadow-gray-200/50 border border-gray-100 relative overflow-hidden">
            <h2 class="text-xl font-bold text-gray-900 mb-8 flex items-center justify-between">
                <span>Factures de Vente</span>
                <span class="text-[10px] text-gray-400 font-mono tracking-widest uppercase">Exports PDF</span>
            </h2>
            <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                <div v-for="invoice in data.sales_invoices" :key="invoice.id" class="flex items-center justify-between p-4 bg-gray-50/50 rounded-2xl border border-gray-100 group hover:bg-white hover:shadow-lg transition-all">
                    <div>
                        <div class="text-sm font-black text-gray-900">{{ invoice.invoice_number }}</div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase">{{ invoice.order?.client?.company_name || 'Passage Direct' }}</div>
                    </div>
                    <button @click="downloadFile(`invoices/${invoice.id}/pdf`, invoice.invoice_number)" class="p-2 rounded-xl bg-white text-blue-600 border border-blue-100 opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path></svg>
                    </button>
                </div>
                <div v-if="data.sales_invoices.length === 0" class="text-center py-20 text-gray-300">Aucune facture enregistrée pour cette période.</div>
            </div>
        </div>

        <!-- ACHATS SECTION -->
        <div class="bg-white p-10 rounded-[3rem] shadow-xl shadow-gray-200/50 border border-gray-100 relative overflow-hidden">
            <h2 class="text-xl font-bold text-gray-900 mb-8 flex items-center justify-between">
                <span>Factures d'Achat (Physiques)</span>
                <span class="text-[10px] text-gray-400 font-mono tracking-widest uppercase">Uploads QA</span>
            </h2>
            <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                <div v-for="receipt in data.purchase_invoices" :key="receipt.id" class="flex items-center justify-between p-4 bg-gray-50/50 rounded-2xl border border-gray-100 group hover:bg-white hover:shadow-lg transition-all">
                    <div>
                        <div class="text-sm font-black text-gray-900">{{ receipt.receipt_number }}</div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase">
                            {{ receipt.purchase_order?.supplier?.name }} 
                            (BC {{ receipt.purchase_order?.po_number }} - {{ Number(receipt.purchase_order?.total_ttc || 0).toLocaleString() }} DH TTC)
                        </div>
                    </div>
                    <button @click="downloadFile(receipt.invoice_path, `Facture-Achat-${receipt.receipt_number}`)" class="p-2 rounded-xl bg-white text-purple-600 border border-purple-100 opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path></svg>
                    </button>
                </div>
                <div v-if="data.purchase_invoices.length === 0" class="text-center py-20 text-gray-300">Aucune facture d'achat scannée par le service QA pour cette période.</div>
            </div>
        </div>

        <!-- RÈGLEMENTS SECTION -->
        <div class="lg:col-span-2 bg-white p-10 rounded-[3rem] shadow-xl shadow-green-200/20 border border-green-50 relative overflow-hidden">
            <h2 class="text-xl font-bold text-gray-900 mb-8 flex items-center justify-between">
                <span>Règlements Clients</span>
                <span class="text-[10px] text-green-500 font-black tracking-widest uppercase">Trésorerie Reçue</span>
            </h2>
            <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                <div v-for="payment in data.payments" :key="payment.id" class="flex items-center justify-between p-4 bg-green-50/30 rounded-2xl border border-green-100/50 group hover:bg-white hover:shadow-lg transition-all">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black text-gray-900 uppercase">{{ payment.invoice?.order?.client?.company_name || 'Client Direct' }}</span>
                            <span class="text-[9px] px-2 py-0.5 bg-gray-100 rounded text-gray-400 font-bold">{{ payment.invoice?.invoice_number }}</span>
                        </div>
                        <div class="text-[9px] text-gray-400 font-bold mt-1 uppercase">
                            {{ new Date(payment.payment_date).toLocaleDateString() }} • {{ payment.payment_method }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-black text-green-600">+ {{ Number(payment.amount).toLocaleString() }} DH</div>
                    </div>
                </div>
                <div v-if="data.payments?.length === 0" class="text-center py-20 text-gray-300 text-sm">Aucun règlement enregistré pour cette période.</div>
            </div>
        </div>

        <!-- BANQUE SECTION -->
        <div class="bg-white p-10 rounded-[3rem] shadow-xl shadow-gray-200/50 border border-gray-100">
            <h2 class="text-xl font-bold text-gray-900 mb-8 flex items-center justify-between">
                <span>Relevés Bancaires</span>
                <span class="px-3 py-1 bg-green-50 text-green-600 text-[9px] font-black rounded-lg border border-green-100 uppercase tracking-widest">Validés Manager</span>
            </h2>
            <div class="grid grid-cols-1 gap-6">
                <div v-for="statement in data.bank_statements" :key="statement.id" class="flex items-center gap-6 p-6 rounded-3xl bg-gray-50/50 border border-gray-100 hover:bg-white hover:shadow-xl transition-all group">
                    <div class="w-16 h-16 rounded-2xl bg-white border border-gray-100 flex items-center justify-center text-green-600 shadow-sm">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <div class="flex-1">
                        <div class="text-lg font-black text-gray-900">Mois : {{ statement.month }}/{{ statement.year }}</div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Déposé par : {{ statement.uploader?.name }}</div>
                    </div>
                    <button @click="downloadFile(statement.file_path, `Releve-${statement.month}-${statement.year}`)" class="px-6 py-3 rounded-2xl bg-white border border-gray-100 text-gray-400 font-bold group-hover:text-green-600 group-hover:border-green-100 transition-all text-xs">Ouvrir PDF</button>
                </div>
                <div v-if="data.bank_statements.length === 0" class="text-center py-20 text-gray-300">Aucun relevé disponible pour ce trimestre.</div>
            </div>
        </div>

        <!-- ATTESTATIONS SECTION -->
        <div class="bg-white p-10 rounded-[3rem] shadow-xl shadow-red-100/50 border border-red-50 relative overflow-hidden">
            <h2 class="text-xl font-bold text-gray-900 mb-8 flex items-center justify-between">
                <span>Régularité Fiscale</span>
                <span class="text-[10px] text-red-500 font-black tracking-widest uppercase">Alertes Action</span>
            </h2>
            <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                <!-- LISTE COMPLETE DES ATTESTATIONS (DISPONIBLES) -->
                <div class="space-y-3">
                    <div v-for="supplier in data.suppliers_with_certificates" :key="'valid-' + supplier.id" class="flex items-center justify-between p-3 bg-gray-50/30 rounded-xl border border-gray-100 hover:bg-white hover:shadow-md transition-all group">
                        <div>
                            <div class="text-[11px] font-bold text-gray-800">{{ supplier.name }}</div>
                            <div class="text-[9px] text-gray-400 mt-0.5">Exp: {{ new Date(supplier.tax_certificate_expires_at).toLocaleDateString() }}</div>
                        </div>
                        <button @click="downloadFile(supplier.tax_certificate_path, `Cert-Fis-${supplier.name}`)" class="p-1.5 rounded-lg bg-white text-blue-600 border border-blue-50 opacity-0 group-hover:opacity-100 transition-opacity shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path></svg>
                        </button>
                    </div>
                </div>

                <div v-if="data.suppliers_missing_certificates.length > 0" class="h-px bg-gray-100 my-6"></div>

                <!-- ALERTES (MANQUANTS/PÉRIMÉS) -->
                <div v-for="supplier in data.suppliers_missing_certificates" :key="'alert-' + supplier.id" class="p-4 bg-red-50/50 rounded-2xl border border-red-100 flex flex-col gap-2 mb-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black text-gray-900 uppercase">{{ supplier.name }}</span>
                        <span class="text-[9px] font-black px-2 py-0.5 bg-red-100 text-red-600 rounded-full">ALERTE</span>
                    </div>
                    <div class="text-[9px] text-red-500 font-bold uppercase tracking-widest underline decoration-red-200">
                        {{ !supplier.tax_certificate_path ? 'DOCUMENT MANQUANT' : 'DOCUMENT PÉRIMÉ' }}
                    </div>
                </div>

                <div v-if="data.suppliers_with_certificates.length === 0 && data.suppliers_missing_certificates.length === 0" class="text-center py-20 text-gray-300 italic text-xs">Aucune donnée fournisseur disponible.</div>
            </div>
        </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #d1d5db; }
</style>
