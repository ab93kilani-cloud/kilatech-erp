<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

interface Order {
  id: number;
  order_number: string;
  order_date: string | null;
  client: { company_name: string, contact_name: string, type: string };
  created_at: string;
  status: string;
  invoice: { status: string } | null;
}

const orders = ref<Order[]>([])
const isLoading = ref(true)
const error = ref('')
const viewArchived = ref(false)

const fetchOrders = async () => {
    isLoading.value = true
    try {
        const response = await api.get('/orders', {
            params: {
                ...(viewArchived.value ? { archived: 1 } : {}),
                _t: Date.now()
            }
        })
        orders.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des bons de livraison.'
    } finally {
        isLoading.value = false
    }
}

const feedbackMessage = ref('')
const showAlert = (msg: string) => { 
    feedbackMessage.value = msg;
    setTimeout(() => { feedbackMessage.value = '' }, 4000);
}

// --- Invoicing Modal ---
const showInvoiceModal = ref(false)
const selectedOrderForInvoice = ref<Order | null>(null)
const invoiceForm = ref({
    invoice_date: new Date().toISOString().split('T')[0],
    due_date: new Date().toISOString().split('T')[0]
})

const openInvoiceModal = (order: Order) => {
    selectedOrderForInvoice.value = order
    invoiceForm.value.invoice_date = new Date().toISOString().split('T')[0]
    // Default due date to today + 30 days or same day
    invoiceForm.value.due_date = new Date().toISOString().split('T')[0]
    showInvoiceModal.value = true
}

const confirmGenerateInvoice = async () => {
    if (!selectedOrderForInvoice.value) return
    try {
        await api.post(`/orders/${selectedOrderForInvoice.value.id}/generate-invoice`, invoiceForm.value)
        showInvoiceModal.value = false
        fetchOrders()
        showAlert('✅ Facture générée avec succès.')
    } catch (e: any) {
        showAlert('❌ Erreur : ' + (e.response?.data?.message || e.message))
    }
}

const generateInvoice = async (order: Order) => {
    openInvoiceModal(order)
}

const revertOrder = async (order: Order) => {
    try {
        await api.post(`/orders/${order.id}/revert`)
        fetchOrders()
        showAlert('↩️ Bon de livraison annulé et devis restauré en mode brouillon.')
    } catch (e: any) {
        showAlert('❌ Erreur : ' + (e.response?.data?.message || e.message))
    }
}

const updateOrderDate = async (order: Order) => {
    const newDate = prompt("Modifier la date du document (YYYY-MM-DD) :", order.order_date || order.created_at.split('T')[0]);
    if (newDate && /^\d{4}-\d{2}-\d{2}$/.test(newDate)) {
        try {
            await api.patch(`/orders/${order.id}`, { order_date: newDate });
            fetchOrders();
            showAlert('📅 Date mise à jour avec succès.');
        } catch (e: any) {
            showAlert('❌ Erreur : ' + (e.response?.data?.message || e.message));
        }
    }
}

const downloadPdf = async (id: number) => {
    try {
        const response = await api.get(`/orders/${id}/pdf`, { responseType: 'blob' })
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
        alert('Erreur lors de la génération du PDF du bon de livraison.')
    }
}

onMounted(() => {
    fetchOrders()
})
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Bons de Livraison (Ventes)</h1>
        <p class="mt-2 text-sm text-gray-700">Ces BL sont générés automatiquement depuis les Devis confirmés. Ils engagent la sortie des stocks.</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <button @click="viewArchived = !viewArchived; fetchOrders()" :class="viewArchived ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-white text-gray-700 border-gray-300'" class="inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md shadow-sm hover:bg-gray-50 focus:outline-none transition">
           {{ viewArchived ? '🔙 Voir BL Actifs' : '📂 Voir les Archives' }}
        </button>
      </div>
    </div>

    <div v-if="feedbackMessage" class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
        <p class="text-sm text-blue-700 font-bold">{{ feedbackMessage }}</p>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-8">Chargement...</div>

    <!-- Product Table -->
    <div v-else class="mt-8 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                   <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Réf Commande</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Client</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Statut (Sortie Stock)</th>
                   <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="order in orders" :key="order.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                    {{ order.order_number }}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                     {{ order.client?.type === 'company' ? order.client?.company_name : order.client?.contact_name || 'Inconnu' }}
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                     <span class="flex items-center gap-2">
                        {{ order.order_date || new Date(order.created_at).toLocaleDateString('fr-FR') }}
                        <button @click="updateOrderDate(order)" class="text-gray-400 hover:text-brand-600 transition" title="Modifier la date">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                        </button>
                     </span>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                     <span v-if="order.status === 'pending'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                       En attente (Stock Réservé)
                     </span>
                     <span v-else-if="order.status === 'shipped'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                       Expédiée
                     </span>
                     <span v-else-if="order.status === 'delivered'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                       Livrèe
                     </span>
                      <span v-else class="text-gray-500 capitalize">{{ order.status }}</span>
                  </td>
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 space-x-3">
                    <template v-if="!viewArchived">
                        <button v-if="!order.invoice" @click="generateInvoice(order)" class="text-blue-600 hover:text-blue-900 font-bold border border-blue-200 bg-blue-50 px-2 py-1 rounded text-xs transition duration-150">
                            Facturer
                        </button>
                        <button v-if="!order.invoice" @click="revertOrder(order)" class="text-red-500 hover:text-red-700 text-xs underline font-medium transition duration-150">
                            Retour en Devis
                        </button>
                        <span v-if="order.invoice" class="text-green-600 font-semibold text-xs border border-green-200 bg-green-50 px-2 py-1 rounded">Facturé</span>
                    </template>
                    <span v-else class="text-gray-400 italic text-xs">Bon de livraison archivé</span>

                    <button @click="downloadPdf(order.id)" title="Télécharger le PDF" class="text-blue-500 hover:text-blue-700 ml-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </button>
                  </td>
                </tr>
                <tr v-if="orders.length === 0">
                    <td colspan="6" class="py-8 text-center text-gray-500">Il n'y a actuellement aucun bon de livraison généré.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <!-- Invoicing Confirmation Modal -->
    <div v-if="showInvoiceModal" class="relative z-50">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-[2rem] bg-white px-8 pb-8 pt-10 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md">
                    <div class="mb-6">
                        <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight">Générer la Facture</h3>
                        <p class="text-xs text-gray-500 mt-1">Définissez les dates pour le document {{ selectedOrderForInvoice?.order_number }}</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Date de Facture</label>
                            <input type="date" v-model="invoiceForm.invoice_date" class="w-full rounded-xl border-gray-100 bg-gray-50 text-sm font-bold focus:ring-blue-500 focus:border-blue-500 p-3">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Date d'Échéance (Paiement)</label>
                            <input type="date" v-model="invoiceForm.due_date" class="w-full rounded-xl border-gray-100 bg-gray-50 text-sm font-bold focus:ring-blue-500 focus:border-blue-500 p-3">
                        </div>
                    </div>

                    <div class="mt-10 flex gap-3">
                        <button @click="showInvoiceModal = false" class="flex-1 py-3 px-6 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:bg-gray-50 rounded-xl transition-all">
                            Annuler
                        </button>
                        <button @click="confirmGenerateInvoice" class="flex-1 py-3 px-6 text-[10px] font-black uppercase tracking-widest bg-blue-600 text-white rounded-xl shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all">
                            Confirmer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </div>
</template>
