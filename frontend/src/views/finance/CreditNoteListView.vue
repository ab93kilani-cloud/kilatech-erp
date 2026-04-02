<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

interface CreditNote {
  id: number;
  credit_note_number: string;
  client: { company_name: string, type: string, contact_name: string };
  invoice?: { invoice_number: string };
  total_amount: number;
  reason: string;
  status: string;
  created_at: string;
}

const creditNotes = ref<CreditNote[]>([])
const isLoading = ref(true)
const error = ref('')

const fetchCreditNotes = async () => {
    isLoading.value = true
    try {
        const response = await api.get('/credit-notes')
        creditNotes.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des avoirs.'
    } finally {
        isLoading.value = false
    }
}

const showAlert = (msg: string) => { window.alert(msg) }

const downloadPdf = async (id: number) => {
    try {
        const response = await api.get(`/credit-notes/${id}/pdf`, { responseType: 'blob' })
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
        alert('Erreur lors du téléchargement du PDF.')
    }
}

onMounted(() => {
    fetchCreditNotes()
})
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Avoirs Clients (Credit Notes)</h1>
        <p class="mt-2 text-sm text-gray-700">Gestion des avoirs émis pour vos clients (retours, annulations, remises).</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <router-link :to="{ name: 'credit-notes.create' }" class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:w-auto">
          Nouvel Avoir
        </router-link>
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
                   <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">N° Avoir</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Client</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Lié à la Facture</th>
                   <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Montant</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Statut</th>
                   <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="note in creditNotes" :key="note.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">{{ note.credit_note_number }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <div class="font-medium text-gray-900">{{ note.client?.type === 'company' ? note.client?.company_name : note.client?.contact_name }}</div>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ note.invoice ? note.invoice.invoice_number : '-' }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900 text-right font-medium text-red-600">-{{ note.total_amount }} MAD</td>

                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                     <span v-if="note.status === 'validated'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Validé</span>
                     <span v-else-if="note.status === 'used'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Utilisé</span>
                     <span v-else class="text-gray-500 uppercase text-xs">{{ note.status }}</span>
                  </td>
                  
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 space-x-3">
                        <button @click="showAlert('Aperçu de l\'avoir à venir.')" class="text-brand-600 hover:text-brand-900">Aperçu</button>
                        <button @click="downloadPdf(note.id)" title="Télécharger le PDF" class="text-blue-500 hover:text-blue-700 ml-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </button>
                  </td>
                </tr>
                <tr v-if="creditNotes.length === 0">
                    <td colspan="6" class="py-8 text-center text-gray-500">Aucun avoir enregistré.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
