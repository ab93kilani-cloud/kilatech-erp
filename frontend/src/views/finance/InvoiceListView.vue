<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '@/plugins/axios'
import { useAuthStore } from '@/stores/auth'

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
            params: viewArchived.value ? { archived: 1 } : {}
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
    invoice_date: '',
    lines: [] as any[]
})

const openEditModal = (invoice: any) => {
    editForm.value.id = invoice.id
    editForm.value.invoice_date = invoice.invoice_date || invoice.created_at.split('T')[0]
    // Cloner les lignes pour l'édition
    editForm.value.lines = invoice.order.lines.map((l: any) => ({
        id: l.id,
        name: (l.variant?.product?.name || 'Produit') + ' ' + (l.variant?.name_variation || ''),
        quantity: l.quantity,
        unit_price: l.unit_price
    }))
    showEditModal.value = true
}

const submitEdit = async () => {
    isSubmitting.value = true
    try {
        await api.patch(`/invoices/${editForm.value.id}`, {
            invoice_date: editForm.value.invoice_date,
            lines: editForm.value.lines
        })
        showEditModal.value = false
        fetchInvoices()
        showAlert('✅ Facture, Stock et BL mis à jour avec succès.')
    } catch (e: any) {
        alert(e.response?.data?.message || "Erreur lors de la modification.")
    } finally {
        isSubmitting.value = false
    }
}

onMounted(() => {
    fetchInvoices()
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

    <!-- Edit Invoice Modal -->
    <div v-if="showEditModal" class="relative z-10" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
      <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Modifier la Facture #{{ editForm.id }}</h3>
            
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Date de Facturation</label>
                    <input type="date" v-model="editForm.invoice_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                </div>

                <div class="overflow-hidden border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Désignation</th>
                                <th class="px-4 py-2 text-center text-xs font-bold text-gray-500 uppercase">Quantité</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase">P.U HT</th>
                                <th class="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase">Total HT</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="line in editForm.lines" :key="line.id">
                                <td class="px-4 py-2 text-sm text-gray-900">{{ line.name }}</td>
                                <td class="px-4 py-2">
                                    <input type="number" v-model.number="line.quantity" min="1" class="w-20 text-center border-gray-300 rounded-md shadow-sm sm:text-sm">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" step="0.01" v-model.number="line.unit_price" class="w-32 text-right border-gray-300 rounded-md shadow-sm sm:text-sm">
                                </td>
                                <td class="px-4 py-2 text-right text-sm font-medium">
                                    {{ (line.quantity * line.unit_price).toFixed(2) }} MAD
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button @click="showEditModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Annuler</button>
                    <button @click="submitEdit" :disabled="isSubmitting" class="px-4 py-2 text-sm font-medium text-white bg-brand-600 rounded-md shadow-sm hover:bg-brand-700 disabled:opacity-50">
                        {{ isSubmitting ? 'Synchronisation...' : 'Enregistrer & Synchroniser (Stock/BL/Devis)' }}
                    </button>
                </div>
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
