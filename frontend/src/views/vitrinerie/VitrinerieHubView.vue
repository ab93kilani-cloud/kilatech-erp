<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-bold text-gray-900 uppercase">🌍 Vitrinerie (Tri du Web)</h2>
        <p class="text-sm text-gray-500 mt-1">Gérez ici les demandes provenant de votre site kilatech.ma avant intégration officielle.</p>
      </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="border-b border-gray-200">
      <nav class="-mb-px flex space-x-8">
        <button v-for="tab in tabs" :key="tab.id" 
          @click="activeTab = tab.id"
          :class="[
            activeTab === tab.id 
              ? 'border-brand-500 text-brand-600' 
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
          ]">
          {{ tab.name }}
          <span v-if="tab.count > 0" class="ml-2 bg-red-100 text-red-600 px-2 py-0.5 rounded-full text-[10px]">{{ tab.count }}</span>
        </button>
      </nav>
    </div>

    <!-- Tab Content: Devi Web -->
    <div v-if="activeTab === 'quotes'" class="space-y-4">
      <div v-if="fetchError" class="py-12 text-center text-red-500 bg-red-50 rounded-xl border border-red-100">
        {{ fetchError }}
      </div>
      <div v-else-if="isLoading" class="py-12 text-center text-gray-400">Chargement des demandes...</div>
      <div v-else-if="webQuotes.length === 0" class="py-20 bg-white rounded-xl border-2 border-dashed border-gray-100 text-center">
         <p class="text-gray-400">Aucune demande de devis en attente.</p>
      </div>
      <div v-else class="bg-white shadow-sm border border-gray-200 rounded-xl overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Prospect</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Produits</th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Total Est.</th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="quote in webQuotes" :key="quote.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                {{ new Date(quote.created_at).toLocaleDateString() }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-bold text-gray-900">{{ quote.client?.contact_name }}</div>
                <div class="text-xs text-gray-500">{{ quote.client?.email }}</div>
              </td>
              <td class="px-6 py-4 text-xs text-gray-500 capitalize">
                {{ quote.lines?.length }} article(s)
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-brand-600">
                {{ quote.total_ttc?.toLocaleString() }} <span class="text-[10px]">MAD</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <router-link :to="{ name: 'quotes.edit', params: { id: quote.id } }" class="text-brand-600 hover:text-brand-900 mr-4">Réviser / Modifier</router-link>
                <button @click="confirmAndSend(quote)" class="text-white bg-brand-600 px-3 py-1 rounded-md text-xs hover:bg-brand-700">Valider & Envoyer</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tab Content: Nouveaux Clients -->
    <div v-if="activeTab === 'clients'" class="space-y-4">
      <div v-if="isLoading" class="py-12 text-center text-gray-400">Chargement des prospects...</div>
      <div v-else-if="webClients.length === 0" class="py-20 bg-white rounded-xl border-2 border-dashed border-gray-100 text-center">
         <p class="text-gray-400">Aucun nouveau prospect enregistré via le web.</p>
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="client in webClients" :key="client.id" class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm relative group overflow-hidden">
          <div class="absolute top-0 right-0 p-4 opacity-0 group-hover:opacity-100 transition-opacity">
             <button @click="promoteClient(client)" title="Intégrer aux clients officiels" class="p-2 bg-brand-50 text-brand-600 rounded-full hover:bg-brand-600 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                   <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd" />
                </svg>
             </button>
          </div>
          <div class="flex items-center gap-4 mb-4">
             <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center text-xl">🌍</div>
             <div>
                <h3 class="font-bold text-gray-900 capitalize">{{ client.contact_name }}</h3>
                <span class="text-[10px] bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full uppercase tracking-tighter">{{ client.type }}</span>
             </div>
          </div>
          <div class="space-y-2 text-xs text-gray-500">
             <p class="flex items-center gap-2"><strong>CIN:</strong> {{ client.cin || 'N/A' }}</p>
             <p class="flex items-center gap-2"><strong>Email:</strong> {{ client.email || 'N/A' }}</p>
             <p class="flex items-center gap-2"><strong>Tel:</strong> {{ client.phone || 'N/A' }}</p>
             <p class="flex items-center gap-2"><strong>Ville:</strong> {{ client.city || 'N/A' }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab Content: Messages Contact -->
    <div v-if="activeTab === 'messages'" class="space-y-4">
      <div v-if="isLoading" class="py-12 text-center text-gray-400">Chargement des messages...</div>
      <div v-else-if="messages.length === 0" class="py-20 bg-white rounded-xl border-2 border-dashed border-gray-100 text-center">
         <p class="text-gray-400">Aucun message de contact reçu.</p>
      </div>
      <div v-else class="space-y-4">
         <div v-for="msg in messages" :key="msg.id" :class="['bg-white p-6 rounded-2xl border transition-all', msg.status === 'unread' ? 'border-brand-200 border-l-4 border-l-brand-600 shadow-sm' : 'border-gray-100']">
            <div class="flex justify-between items-start mb-4">
               <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center font-bold text-gray-500 uppercase">{{ msg.first_name[0] }}{{ msg.last_name[0] }}</div>
                  <div>
                     <h4 class="text-sm font-bold text-gray-900">{{ msg.first_name }} {{ msg.last_name }}</h4>
                     <p class="text-[11px] text-gray-400">{{ msg.email }} • {{ msg.phone }}</p>
                  </div>
               </div>
               <div class="flex items-center gap-2">
                  <button v-if="msg.status === 'unread'" @click="markAsRead(msg.id)" class="text-[10px] text-brand-600 hover:underline uppercase font-bold px-2 py-1">Marquer lu</button>
                  <button @click="deleteMessage(msg.id)" class="text-[10px] text-red-500 hover:text-red-700 uppercase font-bold px-2 py-1">Supprimer</button>
               </div>
            </div>
            <p class="text-sm text-gray-700 bg-gray-50 p-4 rounded-xl italic font-light">"{{ msg.message }}"</p>
            <div class="mt-4 flex justify-between items-center text-[10px] text-gray-400 uppercase tracking-widest">
               <span>Pays: {{ msg.country || 'N/A' }}</span>
               <span>Recu le: {{ new Date(msg.created_at).toLocaleString() }}</span>
            </div>
         </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '@/plugins/axios'
import Swal from 'sweetalert2'

const activeTab = ref('quotes')
const webQuotes = ref<any[]>([])
const webClients = ref<any[]>([])
const messages = ref<any[]>([])
const isLoading = ref(true)
const fetchError = ref<string | null>(null)

const tabs = computed(() => [
  { id: 'quotes', name: 'Demandes de Devis', count: webQuotes.value.length },
  { id: 'clients', name: 'Nouveaux Prospects', count: webClients.value.length },
  { id: 'messages', name: 'Messages Contact', count: messages.value.filter(m => m.status === 'unread').length }
])

const fetchData = async () => {
    isLoading.value = true
    fetchError.value = null
    try {
        const [qRes, cRes, mRes] = await Promise.all([
            api.get('/vitrinerie/quotes'),
            api.get('/vitrinerie/clients'),
            api.get('/vitrinerie/messages')
        ])
        webQuotes.value = qRes.data
        webClients.value = cRes.data
        messages.value = mRes.data
    } catch (e: any) {
        console.error('Failed to fetch Vitrinerie data', e)
        fetchError.value = "Impossible de charger les données. Vérifiez que votre base de données est à jour (migration 'source' manquante ?)."
    } finally {
        isLoading.value = false
    }
}

const promoteClient = async (client: any) => {
    const result = await Swal.fire({
        title: 'Intégrer le prospect ?',
        text: "Ce client rejoindra votre liste officielle (B2B).",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#26a69a',
        confirmButtonText: 'Oui, intégrer'
    })
    
    if (result.isConfirmed) {
        try {
            await api.patch(`/clients/${client.id}`, { source: 'erp' })
            Swal.fire('Intégré !', 'Le prospect est maintenant un client officiel.', 'success')
            fetchData()
        } catch (e) {
            Swal.fire('Erreur', 'Impossible d\'intégrer le client.', 'error')
        }
    }
}

const confirmAndSend = async (quote: any) => {
    const result = await Swal.fire({
        title: 'Valider et Envoyer ?',
        text: "Le client recevra son devis officiel par email.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#26a69a',
        confirmButtonText: 'Oui, Valider & Envoyer'
    })
    
    if (result.isConfirmed) {
        try {
            await api.post(`/quotes/${quote.id}/confirm-and-send`)
            // After sending, we might want to keep it as 'web' or change to 'erp'
            // User requested "On va creer une copie dans la liste de lapplication"
            // Actually changing source to 'erp' makes it appear in the main list.
            await api.patch(`/quotes/${quote.id}`, { source: 'erp' })
            
            Swal.fire('Envoyé !', 'Le devis a été validé et envoyé.', 'success')
            fetchData()
        } catch (e) {
            Swal.fire('Erreur', 'Impossible d\'envoyer le devis.', 'error')
        }
    }
}

const markAsRead = async (id: number) => {
    try {
        await api.post(`/vitrinerie/messages/${id}/read`)
        fetchData()
    } catch (e) {
        console.error('Failed to mark message as read')
    }
}

const deleteMessage = async (id: number) => {
    const result = await Swal.fire({
        title: 'Supprimer le message ?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Supprimer'
    })
    
    if (result.isConfirmed) {
        await api.delete(`/vitrinerie/messages/${id}`)
        fetchData()
    }
}

onMounted(fetchData)
</script>

<style scoped>
.text-brand-600 { color: #26a69a; }
.text-brand-700 { color: #00897b; }
.bg-brand-600 { background-color: #26a69a; }
.bg-brand-50 { background-color: #e0f2f1; }
.border-brand-500 { border-color: #4db6ac; }
.border-brand-200 { border-color: #b2dfdb; }
</style>
