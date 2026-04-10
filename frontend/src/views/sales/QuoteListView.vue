<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

interface Quote {
  id: number;
  client: { company_name: string, contact_name: string, type: string };
  version: number;
  status: string;
  locked_by_user_id: number | null;
  locked_by: { name: string } | null;
  locked_at: string | null;
  source: string;
}

const quotes = ref<Quote[]>([])
const isLoading = ref(true)
const error = ref('')
const viewArchived = ref(false)

const fetchQuotes = async () => {
    isLoading.value = true
    try {
        const response = await api.get('/quotes', {
            params: {
              ...(viewArchived.value ? { archived: 1 } : {}),
              exclude_source: 'web',
              _t: Date.now()
            }
        })
        quotes.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des devis.'
    } finally {
        isLoading.value = false
    }
}

const toggleLock = async (quote: Quote) => {
    try {
        if (quote.locked_by_user_id) {
            // Unlock
            await api.post(`/quotes/${quote.id}/unlock`)
            quote.locked_by_user_id = null
            quote.locked_by = null
            quote.locked_at = null
        } else {
            // Lock
            await api.post(`/quotes/${quote.id}/lock`)
            quote.locked_by_user_id = authStore.user?.id || 0
            quote.locked_by = { name: authStore.user?.name || 'Moi' }
            quote.locked_at = new Date().toISOString()
        }
    } catch (e: any) {
        showAlert('Erreur Lock/Unlock : ' + (e.response?.data?.message || e.message))
    }
}

const feedbackMessage = ref('')
const showAlert = (msg: string) => { 
    feedbackMessage.value = msg;
    setTimeout(() => { feedbackMessage.value = '' }, 4000);
}

const confirmQuote = async (quote: Quote) => {
    try {
        await api.post(`/quotes/${quote.id}/confirm`)
        fetchQuotes()
        showAlert('✅ Le devis a été transformé en Bon de Livraison avec succès.')
    } catch (e: any) {
        showAlert('❌ Erreur : ' + (e.response?.data?.message || e.message))
    }
}

// Maya: Envoyer le devis au client après validation agent.
const confirmAndSend = async (quote: Quote) => {
    try {
        const res = await api.post(`/quotes/${quote.id}/confirm-and-send`)
        fetchQuotes()
        showAlert(res.data.message || '✅ Devis envoyé au client avec succès.')
    } catch (e: any) {
        showAlert('❌ Erreur envoi : ' + (e.response?.data?.message || e.message))
    }
}

const downloadPdf = async (id: number) => {
    try {
        const response = await api.get(`/quotes/${id}/pdf`, { responseType: 'blob' })
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
        alert('Erreur lors de la génération du PDF du devis.')
    }
}

onMounted(() => {
    fetchQuotes()
})
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Devis Commerciaux (B2B)</h1>
        <p class="mt-2 text-sm text-gray-700">Gestion des offres commerciales avec verrouillage collaboratif (Pessimistic Locking).</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex items-center space-x-3">
        <button @click="viewArchived = !viewArchived; fetchQuotes()" :class="viewArchived ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-white text-gray-700 border-gray-300'" class="inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md shadow-sm hover:bg-gray-50 focus:outline-none">
           {{ viewArchived ? '🔙 Voir Devis Actifs' : '📂 Voir les Archives' }}
        </button>
        <router-link v-if="!viewArchived" :to="{ name: 'quotes.create' }" class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:w-auto">
          Nouveau Devis
        </router-link>
      </div>
    </div>

    <div v-if="feedbackMessage" class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
        <p class="text-sm text-blue-700 font-bold">{{ feedbackMessage }}</p>
    </div>

    <div v-if="error" class="bg-red-50 p-4 mb-4"><p class="text-sm text-red-700">{{ error }}</p></div>

    <div v-if="isLoading" class="text-center py-8">Chargement...</div>

    <div v-else class="mt-8 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">ID / Source</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Client</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Statut</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Verrou (Edition)</th>
                  <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                    <span class="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="quote in quotes" :key="quote.id" :class="{'bg-yellow-50': quote.locked_by_user_id}">
                  
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">
                    #{{ quote.id }} <span class="text-gray-400 font-normal">(v{{ quote.version }})</span>
                    <div v-if="quote.source === 'web'" class="mt-1">
                       <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700 border border-purple-200 uppercase tracking-tighter">🌍 En Ligne</span>
                    </div>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    {{ quote.client?.company_name || quote.client?.contact_name || 'Client Inconnu' }}
                  </td>
                   <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <span v-if="quote.status === 'pending_approval'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">⏳ En attente de validation</span>
                      <span v-else-if="quote.status === 'draft'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Brouillon</span>
                      <span v-else-if="quote.status === 'sent'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">📧 Envoyé client</span>
                      <span v-else-if="quote.status === 'confirmed'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Confirmé</span>
                      <span v-else class="text-gray-500 capitalize">{{ quote.status }}</span>
                   </td>
                  
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                     <div v-if="quote.locked_by_user_id" class="flex items-center text-amber-600">
                        <svg class="mr-1.5 h-4 w-4 flex-shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                           <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                        </svg>
                        Verrouillé par {{ quote.locked_by?.name || 'Inconnu' }}
                     </div>
                     <span v-else class="text-gray-400">Libre</span>
                  </td>

                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 space-x-3">
                    <!-- Maya: Bouton Confirmer et Envoyer pour les devis issus de la vitrine -->
                    <button
                      v-if="quote.status === 'pending_approval' && !viewArchived"
                      @click="confirmAndSend(quote)"
                      class="text-white bg-brand-600 hover:bg-brand-700 font-bold border border-brand-700 px-3 py-1 rounded text-xs transition"
                    >
                      ✅ Confirmer & Envoyer
                    </button>
                    <button v-if="quote.status === 'draft' && !viewArchived" @click="confirmQuote(quote)" class="text-green-600 hover:text-green-900 font-bold border border-green-200 bg-green-50 px-2 py-1 rounded text-xs">
                        Transformer en BL
                    </button>
                    <button v-if="!viewArchived" @click="toggleLock(quote)" :class="quote.locked_by_user_id ? 'text-gray-600 hover:text-gray-900' : 'text-amber-600 hover:text-amber-900'">
                        {{ quote.locked_by_user_id ? 'Déverrouiller' : 'Verrouiller' }}
                    </button>
                    <template v-if="!viewArchived">
                        <router-link :to="{ name: 'quotes.edit', params: { id: quote.id } }" class="text-brand-600 hover:text-brand-900 ml-4 hover:underline transition">Modifier</router-link>
                    </template>
                    <span v-else class="text-gray-400 italic">Document archivé</span>

                    <button @click="downloadPdf(quote.id)" title="Télécharger le PDF" class="text-blue-500 hover:text-blue-700 ml-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </button>
                  </td>
                </tr>
                <tr v-if="quotes.length === 0">
                    <td colspan="5" class="py-8 text-center text-gray-500">Aucun devis trouvé.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
