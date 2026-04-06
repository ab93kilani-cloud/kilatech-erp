<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '@/plugins/axios'
import { useAuthStore } from '@/stores/auth'
import logoUrl from '@/assets/logo.png'

interface Invoice {
  id: number;
  invoice_number: string;
  order: { order_number: string, client: { company_name: string, type: string, contact_name: string } };
  total_amount: number;
  balance_due: number;
  status: string;
  invoice_date: string;
  due_date: string;
  created_at : string;
}

const authStore = useAuthStore()
const user = computed(() => authStore.user)
const isAdminOrManager = computed(() => {
    return user.value?.role?.name === 'Admin' || user.value?.role?.name === 'Gérant'
})

const invoices = ref<Invoice[]>([])
const isLoading = ref(true)
const error = ref('')
const viewArchived = ref(false)

const fetchInvoices = async () => {
    isLoading.value = true
    try {
        const response = await api.get('/invoices', {
            params: { 
                ...(viewArchived.value ? { archived: 1 } : {}),
                _t: Date.now() // Force refresh
            }
        })
        invoices.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des factures.'
    } finally {
        isLoading.value = false
    }
}

const showAlert = (msg: string) => { window.alert(msg) }

// --- Modal de Paiement ---
const showPaymentModal = ref(false)
const selectedInvoice = ref<Invoice | null>(null)
const isSubmitting = ref(false)
const modalError = ref('')
const paymentForm = ref({
    amount: 0,
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'virement',
    notes: ''
})

const openPaymentModal = (invoice: Invoice) => {
    selectedInvoice.value = invoice
    paymentForm.value.amount = invoice.balance_due // Pré-remplir avec le Reste à Payer
    paymentForm.value.payment_date = new Date().toISOString().split('T')[0]
    paymentForm.value.payment_method = 'virement'
    paymentForm.value.notes = ''
    modalError.value = ''
    showPaymentModal.value = true
}

const closePaymentModal = () => {
    showPaymentModal.value = false
    selectedInvoice.value = null
}

const submitPayment = async () => {
    if (!selectedInvoice.value) return;
    isSubmitting.value = true
    modalError.value = ''

    try {
        await api.post('/invoice-payments', {
            invoice_id: selectedInvoice.value.id,
            amount: paymentForm.value.amount,
            payment_date: paymentForm.value.payment_date,
            payment_method: paymentForm.value.payment_method,
            notes: paymentForm.value.notes
        })
        closePaymentModal()
        fetchInvoices() // Recharger tableau
        showAlert('✅ Règlement enregistré avec succès.')
    } catch (e: any) {
        modalError.value = e.response?.data?.message || "Erreur lors de l'enregistrement du paiement."
    } finally {
        isSubmitting.value = false
    }
}

const archiveInvoice = async (invoice: Invoice) => {
    if (!confirm('Voulez-vous vraiment archiver cette facture ?')) return;
    try {
        await api.delete(`/invoices/${invoice.id}`)
        fetchInvoices()
        showAlert('✅ Facture archivée.')
    } catch (e: any) {
        console.error('Erreur lors de l\'archivage', e)
        alert('Erreur lors de l\'archivage')
    }
}


const downloadPdf = async (id: number) => {
    try {
        const response = await api.get(`/invoices/${id}/pdf`, { responseType: 'blob' })
        // Vérification de l'intégrité de la réponse
        const blob = new Blob([response.data], { type: 'application/pdf' })
        if (blob.size === 0) {
            alert('Le document généré est vide (0 octets). Erreur serveur.')
            return
        }
        if (response.data.type && response.data.type.includes('text/html')) {
            alert('Le serveur a retourné une page d\'erreur HTML au lieu d\'un PDF.')
            return
        }

        const url = window.URL.createObjectURL(blob)
        window.open(url, '_blank')
    } catch (error) {
        console.error('Erreur lors du téléchargement du PDF', error)
        alert('Erreur lors de la génération du PDF de la facture.')
    }
}

// --- Modal d'Édition ---
const showEditModal = ref(false)
const editForm = ref({
    id: 0,
    invoice_number: '',
    client_name: '',
    invoice_date: '',
    due_date: '',
    payment_method: '',
    lines: [] as any[]
})

// Moteur de recherche de produits (Catalogue)
const allVariants = ref<any[]>([])
const products = computed(() => {
    const map = new Map<number, any>()
    allVariants.value.forEach(v => {
        if (!v.product) return
        if (!map.has(v.product.id)) {
            map.set(v.product.id, { id: v.product.id, name: v.product.name, brand: v.product.brand, variants: [] })
        }
        map.get(v.product.id).variants.push(v)
    })
    return Array.from(map.values())
})
const productSearch = ref('')
const showProductSearch = ref(false)
const filteredCatalog = computed(() => {
    if (!productSearch.value) return products.value.slice(0, 50)
    const term = productSearch.value.toLowerCase()
    return products.value.filter(p => p.name.toLowerCase().includes(term) || p.brand?.toLowerCase().includes(term)).slice(0, 50)
})

const fetchCatalog = async () => {
    try {
        const res = await api.get('/product-variants')
        allVariants.value = res.data
    } catch (e) {}
}

const openEditModal = (invoice: any) => {
    if (!invoice.order || !invoice.order.lines) {
        alert("Impossible de modifier cette facture : Données de commande (BL) introuvables.");
        return;
    }
    editForm.value.id = invoice.id
    editForm.value.invoice_number = invoice.invoice_number
    editForm.value.client_name = invoice.order?.client?.company_name || invoice.order?.client?.contact_name || 'Client Inconnu'
    editForm.value.invoice_date = invoice.invoice_date || invoice.created_at.split('T')[0]
    editForm.value.due_date = invoice.due_date || ''
    editForm.value.payment_method = invoice.payment_method || ''
    
    // Cloner les lignes pour l'édition avec tous les champs financiers
    editForm.value.lines = invoice.order.lines.map((l: any) => ({
        id: l.id,
        name: (l.variant?.product?.name || 'Produit') + ' ' + (l.variant?.name_variation || ''),
        variant_id: l.variant_id,
        quantity: l.quantity,
        unit_price: l.unit_price,
        discount_percent: l.discount_percent || 0,
        tax_rate: l.tax_rate || 20
    }))
    showEditModal.value = true
}

const addSearchedProduct = (product: any) => {
    const v = product.variants[0] // Prendre la premiere variante par defaut
    const tva = v?.tva_rate || 20
    const price = (v?.public_price || 0) / (1 + tva/100)
    editForm.value.lines.push({
        name: product.name + (v?.name_variation ? ' ' + v.name_variation : ''),
        variant_id: v.id,
        quantity: 1,
        unit_price: Number(price.toFixed(4)),
        discount_percent: 0,
        tax_rate: tva
    })
    productSearch.value = ''
    showProductSearch.value = false
}

const removeLine = (index: number) => {
    if (confirm('Supprimer cet article de la facture ? (Le stock sera réintégré lors de la validation)')) {
        editForm.value.lines.splice(index, 1)
    }
}

const calculateLineTotalHT = (line: any) => {
    const brit = line.quantity * line.unit_price
    const discount = brit * ((line.discount_percent || 0) / 100)
    return brit - discount
}

const currentTotals = computed(() => {
    let ht = 0
    let tva = 0
    editForm.value.lines.forEach(line => {
        const lineHT = calculateLineTotalHT(line)
        ht += lineHT
        tva += lineHT * (line.tax_rate / 100)
    })
    return { ht, tva, ttc: ht + tva }
})

const submitEdit = async () => {
    isSubmitting.value = true
    try {
        await api.patch(`/invoices/${editForm.value.id}`, editForm.value)
        showEditModal.value = false
        fetchInvoices()
        showAlert('✅ Modification synchronisée avec succès (Stock/BL/Devis).')
    } catch (e: any) {
        alert('Erreur : ' + (e.response?.data?.message || e.message))
    } finally {
        isSubmitting.value = false
    }
}

onMounted(() => {
    fetchInvoices()
    fetchCatalog()
})
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Facturation Client (B2B)</h1>
        <p class="mt-2 text-sm text-gray-700">Centralisation de tous les avoirs et factures de vente (Suivi des paiements partiels).</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <button @click="viewArchived = !viewArchived; fetchInvoices()" :class="viewArchived ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-white text-gray-700 border-gray-300'" class="inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md shadow-sm hover:bg-gray-50 focus:outline-none transition">
           {{ viewArchived ? '🔙 Voir Factures Actives' : '📂 Voir les Archives' }}
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 p-4 mb-4"><p class="text-sm text-red-700">{{ error }}</p></div>
    <div v-if="isLoading" class="text-center py-8">Chargement...</div>

    <!-- Data Table -->
    <div v-else class="mt-8 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                   <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">Facture N°</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Client / Origine</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Échéance</th>
                   <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Montant Total</th>
                   <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Reste à Payer</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Statut</th>
                   <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="inv in invoices" :key="inv.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">{{ inv.invoice_number }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <div class="font-medium text-gray-900">{{ inv.order?.client?.type === 'company' ? inv.order?.client?.company_name : inv.order?.client?.contact_name }}</div>
                      <div class="text-xs text-gray-400">Réf: {{ inv.order?.order_number }}</div>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <span class="flex items-center gap-2">
                          {{ inv.invoice_date || new Date(inv.created_at).toLocaleDateString('fr-FR') }}
                          <button v-if="isAdminOrManager" @click="openEditModal(inv)" class="text-gray-400 hover:text-brand-600 transition" title="Modifier">
                              <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                          </button>
                      </span>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ inv.due_date || '-' }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900 text-right font-medium">{{ inv.total_amount }} MAD</td>
                  
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-right">
                     <span v-if="inv.balance_due == 0" class="text-green-600 font-bold">Soldé (0.00)</span>
                     <span v-else class="text-red-500 font-bold">{{ inv.balance_due }} MAD</span>
                  </td>

                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                     <span v-if="inv.status === 'draft'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Brouillon</span>
                     <span v-else-if="inv.status === 'sent'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Émise</span>
                     <span v-else-if="inv.status === 'partially_paid'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Paiement Partiel</span>
                     <span v-else-if="inv.status === 'paid'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Payée</span>
                     <span v-else class="text-gray-500 uppercase text-xs">{{ inv.status }}</span>
                  </td>
                  
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 space-x-3">
                    <template v-if="!viewArchived">
                        <button v-if="inv.balance_due > 0" @click="openPaymentModal(inv)" class="text-brand-600 font-bold hover:text-brand-900 border border-brand-200 bg-brand-50 px-3 py-1 rounded text-xs transition duration-150">
                            + Règlement
                        </button>
                        <button v-if="isAdminOrManager" @click="openEditModal(inv)" class="text-blue-500 hover:text-blue-700 text-xs font-bold uppercase tracking-tighter transition">Modifier</button>
                        <button v-if="inv.status === 'paid'" @click="archiveInvoice(inv)" class="text-amber-600 hover:text-amber-900 text-xs underline decoration-dotted">Archiver</button>
                    </template>
                    <span v-else class="text-gray-400 italic text-xs">Facture archivée</span>
                    
                    <button @click="downloadPdf(inv.id)" title="Télécharger le PDF" class="text-blue-500 hover:text-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </button>
                  </td>
                </tr>
                <tr v-if="invoices.length === 0">
                    <td colspan="7" class="py-8 text-center text-gray-500">Aucune facture enregistrée.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Invoice Modal (Style Devis) -->
    <div v-if="showEditModal" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
      <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-7xl sm:p-8">
            <!-- Header Mode Devis -->
            <div class="flex justify-between items-start border-b pb-6 mb-6">
                <div>
                    <h2 class="text-3xl font-extrabold text-brand-700 tracking-tight">MODIFIER LA FACTURE</h2>
                    <p class="text-sm text-gray-500 mt-1 uppercase font-bold tracking-widest">Référence : {{ editForm.invoice_number }}</p>
                </div>
                <div class="text-right">
                    <img :src="logoUrl" alt="Kilatech" class="h-12 w-auto ml-auto mb-2 opacity-80">
                    <div class="text-xs text-gray-400 font-medium tracking-widest">B2B SALES WORKFLOW</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 bg-gray-50 p-6 rounded-xl border border-gray-100">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Date Facture</label>
                    <input type="date" v-model="editForm.invoice_date" class="mt-2 block w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm h-11 transition">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Échéance</label>
                    <input type="date" v-model="editForm.due_date" class="mt-2 block w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm h-11 transition">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Mode de Paiement</label>
                    <select v-model="editForm.payment_method" class="mt-2 block w-full rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm h-11 transition">
                        <option value="Espèces">Espèces</option>
                        <option value="Chèque">Chèque</option>
                        <option value="Virement">Virement</option>
                        <option value="Effet">Effet</option>
                    </select>
                </div>
                <div class="text-right">
                  <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Client Destinataire</label>
                  <p class="mt-4 text-sm font-black text-gray-900 border-r-4 border-brand-500 pr-3">{{ editForm.client_name }}</p>
                </div>
            </div>

            <!-- Barre de Recherche Katalogue (Style Devis) -->
            <div class="relative mb-8">
                <div class="flex items-center gap-3 bg-brand-50/50 p-3 rounded-2xl border border-brand-100">
                    <div class="flex-1 relative">
                        <input type="text" v-model="productSearch" @focus="showProductSearch = true" placeholder="🔍 Rechercher un produit à ajouter à cette facture..." class="w-full pl-12 pr-4 py-4 rounded-xl border-brand-200 focus:ring-brand-500 focus:border-brand-500 text-sm shadow-inner transition">
                        <span class="absolute left-4 top-4.5 text-brand-400">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </span>
                    </div>
                </div>
                <!-- Dropdown Résultats -->
                <div v-if="showProductSearch && filteredCatalog.length > 0" class="absolute z-[100] w-full mt-3 bg-white rounded-2xl shadow-2xl border border-gray-200 max-h-96 overflow-y-auto ring-1 ring-black ring-opacity-5">
                    <div v-for="prod in filteredCatalog" :key="prod.id" @click="addSearchedProduct(prod)" class="flex items-center justify-between p-5 hover:bg-brand-50 cursor-pointer border-b last:border-0 transition-colors">
                        <div>
                            <div class="text-sm font-black text-gray-900 tracking-tight">{{ prod.name }}</div>
                            <div class="text-[10px] text-gray-500 uppercase font-bold mt-0.5 tracking-wider">{{ prod.brand }} | Réf: {{ prod.reference }}</div>
                        </div>
                        <div class="bg-brand-100 text-brand-700 px-3 py-1 rounded-full text-[10px] font-black uppercase">+ Ajouter</div>
                    </div>
                </div>
                <!-- Overlay pour fermer le dropdown -->
                <div v-if="showProductSearch" @click="showProductSearch = false" class="fixed inset-0 z-[90] bg-transparent"></div>
            </div>

            <!-- Table Edition Panier (Style Devis) -->
            <div class="overflow-hidden border border-gray-200 rounded-2xl mb-8 shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-widest">Désignation</th>
                            <th class="px-4 py-4 text-center text-xs font-black text-gray-400 uppercase tracking-widest">Qté</th>
                            <th class="px-4 py-4 text-right text-xs font-black text-gray-400 uppercase tracking-widest">P.U HT (MAD)</th>
                            <th class="px-4 py-4 text-center text-xs font-black text-gray-400 uppercase tracking-widest">Remise (%)</th>
                            <th class="px-4 py-4 text-center text-xs font-black text-gray-400 uppercase tracking-widest">TVA (%)</th>
                            <th class="px-6 py-4 text-right text-xs font-black text-gray-400 uppercase tracking-widest">Sous-Total</th>
                            <th class="px-4 py-4 text-center text-xs font-black text-gray-400 uppercase tracking-widest">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="(line, index) in editForm.lines" :key="index" class="hover:bg-brand-50/20 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-gray-900 group-hover:text-brand-700 transition">{{ line.name }}</div>
                                <div v-if="!line.id" class="text-[10px] text-green-600 font-black uppercase mt-1 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-ping"></span>
                                    Nouveau Produit ✨
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <input type="number" v-model.number="line.quantity" min="1" class="w-20 text-center border-gray-200 rounded-xl shadow-sm focus:ring-brand-500 focus:border-brand-500 sm:text-sm h-11 font-black bg-gray-50/50">
                            </td>
                            <td class="px-4 py-4">
                                <input type="number" step="0.01" v-model.number="line.unit_price" class="w-32 text-right border-gray-200 rounded-xl shadow-sm focus:ring-brand-500 focus:border-brand-500 sm:text-sm h-11 font-black bg-gray-50/50">
                            </td>
                            <td class="px-4 py-4">
                                <input type="number" step="0.1" v-model.number="line.discount_percent" class="w-20 text-center border-gray-200 rounded-xl shadow-sm focus:ring-brand-500 focus:border-brand-500 sm:text-sm h-11">
                            </td>
                            <td class="px-4 py-4">
                                <select v-model.number="line.tax_rate" class="w-24 text-center border-gray-200 rounded-xl shadow-sm focus:ring-brand-500 focus:border-brand-500 sm:text-sm h-11 font-bold">
                                    <option :value="20">20%</option>
                                    <option :value="10">10%</option>
                                    <option :value="7">7%</option>
                                    <option :value="0">0%</option>
                                </select>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-black text-brand-600 tabular-nums">
                                {{ calculateLineTotalHT(line).toFixed(2) }} MAD
                            </td>
                            <td class="px-4 py-4 text-center">
                                <button @click="removeLine(index)" class="p-2 text-red-300 hover:text-red-600 hover:bg-red-50 rounded-full transition" title="Supprimer la ligne">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Totaux -->
            <div class="flex flex-col md:flex-row justify-between items-start gap-8 bg-brand-50/30 p-8 rounded-2xl border border-brand-100">
                <div class="max-w-md text-sm text-gray-500 italic">
                    <p>🚨 <strong>Attention :</strong> Toute modification de quantité impacte directement votre stock réel. Les unités seront automatiquement réintégrées ou sorties selon vos ajustements.</p>
                </div>
                <div class="w-full md:w-80 space-y-3">
                    <div class="flex justify-between text-sm font-medium text-gray-600">
                        <span>TOTAL HT NET</span>
                        <span>{{ currentTotals.ht.toFixed(2) }} MAD</span>
                    </div>
                    <div class="flex justify-between text-sm font-medium text-gray-600">
                        <span>TOTAL TVA</span>
                        <span>{{ currentTotals.tva.toFixed(2) }} MAD</span>
                    </div>
                    <div class="flex justify-between text-xl font-black text-brand-900 border-t border-brand-200 pt-3">
                        <span>TOTAL TTC</span>
                        <span>{{ currentTotals.ttc.toFixed(2) }} MAD</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-10">
                <button @click="showEditModal = false" class="px-6 py-3 text-sm font-bold text-gray-600 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition shadow-sm">ANNULER</button>
                <button @click="submitEdit" :disabled="isSubmitting" class="px-8 py-3 text-sm font-black text-white bg-brand-600 rounded-xl shadow-lg shadow-brand-200 hover:bg-brand-700 hover:-translate-y-0.5 transform transition disabled:opacity-50">
                    {{ isSubmitting ? 'SYNCHRONISATION...' : 'ENREGISTRER & METTRE À JOUR LE STOCK' }}
                </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Modal -->
    <div v-if="showPaymentModal && selectedInvoice" class="relative z-10" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
      <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
            <div>
              <div class="mt-3 text-center sm:mt-5">
                <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Saisir un Règlement</h3>
                <div class="mt-2 text-sm text-gray-500">
                    Facture <strong class="text-gray-900">{{ selectedInvoice.invoice_number }}</strong> - Reste à payer : <span class="text-red-500 font-bold">{{ selectedInvoice.balance_due }} MAD</span>
                </div>
              </div>
            </div>

            <form @submit.prevent="submitPayment" class="mt-5 sm:mt-6 space-y-4 text-left">
                <div v-if="modalError" class="bg-red-50 text-red-700 p-3 rounded text-sm mb-4">
                    {{ modalError }}
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900">Date du paiement</label>
                    <input type="date" v-model="paymentForm.payment_date" required class="mt-1 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900">Montant Encaissé (MAD)</label>
                    <input type="number" step="0.01" min="0.01" :max="selectedInvoice.balance_due" v-model="paymentForm.amount" required class="mt-1 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900">Mode de règlement</label>
                    <select v-model="paymentForm.payment_method" required class="mt-1 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                        <option value="virement">Virement Bancaire</option>
                        <option value="cheque">Chèque</option>
                        <option value="especes">Espèces</option>
                        <option value="carte">Carte Bleue</option>
                        <option value="caisse">Caisse</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900">Note ou Réf. de paiement (Optionnel)</label>
                    <input type="text" v-model="paymentForm.notes" placeholder="Ex: Chèque n°1234..." class="mt-1 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                </div>

                <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3 border-t pt-4">
                    <button type="submit" :disabled="isSubmitting" class="inline-flex w-full justify-center rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 sm:col-start-2 disabled:opacity-50">
                        {{ isSubmitting ? 'Validation...' : 'Valider le paiement' }}
                    </button>
                    <button type="button" @click="closePaymentModal" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0">
                        Annuler
                    </button>
                </div>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>
