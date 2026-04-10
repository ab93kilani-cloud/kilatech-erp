<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

interface Client {
  id: number;
  type: string;
  company_name: string;
  contact_name: string;
  email: string;
  phone: string;
  telephone_fix: string;
  fax: string;
  address: string;
  ice?: string;
  rc?: string;
  if?: string;
  patente?: string;
  cin?: string;
}

const clients = ref<Client[]>([])
const isLoading = ref(true)
const error = ref('')

const isModalOpen = ref(false)
const isSubmitting = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
    type: 'company',
    company_name: '',
    contact_name: '',
    email: '',
    phone: '',
    telephone_fix: '',
    fax: '',
    address: '',
    ice: '',
    rc: '',
    if: '',
    patente: '',
    cin: ''
})

const openCreateModal = () => {
    isEditing.value = false
    editingId.value = null
    form.value = { type: 'company', company_name: '', contact_name: '', email: '', phone: '', telephone_fix: '', fax: '', address: '', ice: '', rc: '', if: '', patente: '', cin: '' }
    isModalOpen.value = true
}

const openEditModal = (client: any) => {
    isEditing.value = true
    editingId.value = client.id
    form.value = { 
        type: client.type || 'company',
        company_name: client.company_name || '',
        contact_name: client.contact_name || '',
        email: client.email || '',
        phone: client.phone || '',
        telephone_fix: client.telephone_fix || '',
        fax: client.fax || '',
        address: client.address || '',
        ice: client.ice || '',
        rc: client.rc || '',
        if: client.if || '',
        patente: client.patente || '',
        cin: client.cin || ''
    }
    isModalOpen.value = true
}

const fetchClients = async () => {
    try {
        const response = await api.get('/clients')
        clients.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des clients.'
    } finally {
        isLoading.value = false
    }
}

const saveClient = async () => {
    isSubmitting.value = true
    try {
        if (isEditing.value && editingId.value) {
            // Update
            const response = await api.put(`/clients/${editingId.value}`, form.value)
            const index = clients.value.findIndex(c => c.id === editingId.value)
            if (index !== -1) clients.value[index] = response.data
        } else {
            // Create
            const response = await api.post('/clients', form.value)
            clients.value.unshift(response.data)
        }
        isModalOpen.value = false
        // Reset form
        form.value = { type: 'company', company_name: '', contact_name: '', email: '', phone: '', telephone_fix: '', fax: '', address: '', ice: '', rc: '', if: '', patente: '', cin: '' }
    } catch (e: any) {
        alert(e.response?.data?.message || "Erreur lors de l'enregistrement du client (Vérifiez les champs).")
    } finally {
        isSubmitting.value = false
    }
}

const deleteClient = async (id: number) => {
    if (!confirm('Voulez-vous vraiment supprimer ce client ? Cela peut échouer s\'il a déjà des devis/commandes liés.')) return;
    try {
        await api.delete(`/clients/${id}`)
        clients.value = clients.value.filter(c => c.id !== id)
    } catch {
        alert("Impossible de supprimer ce client. Il est probablement utilisé dans des factures ou devis.")
    }
}

onMounted(() => {
    fetchClients()
})
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Clients B2B</h1>
        <p class="mt-2 text-sm text-gray-700">Gérez votre base de données clients (Acheteurs, Cliniques, Hôpitaux).</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <button @click="openCreateModal" type="button" class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:focus:ring-brand-500 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 sm:w-auto">
          Nouveau Client
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 p-4 mb-4"><p class="text-sm text-red-700">{{ error }}</p></div>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-8">Chargement...</div>

    <!-- Data Table -->
    <div v-else class="mt-8 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                   <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">Entreprise</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Contact</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Email</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Téléphone</th>
                   <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="client in clients" :key="client.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">
                     <span v-if="client.type === 'company'">🏢 {{ client.company_name }}</span>
                     <span v-else>👤 {{ client.contact_name }} <span class="text-xs text-gray-400 font-normal">(Particulier)</span></span>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ client.type === 'company' ? client.contact_name || '-' : '-' }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ client.email || '-' }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ client.phone || '-' }}</td>
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 space-x-3">
                     <button @click="openEditModal(client)" class="text-brand-600 hover:text-brand-900">Mettre à jour</button>
                     <button @click="deleteClient(client.id)" class="text-red-500 hover:text-red-900 ml-4">Supprimer</button>
                  </td>
                </tr>
                <tr v-if="clients.length === 0">
                    <td colspan="5" class="py-8 text-center text-gray-500">Aucun client trouvé.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Simple Modal for Creation -->
    <div v-if="isModalOpen" class="relative z-10" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
      <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4" id="modal-title">{{ isEditing ? 'Modifier Client' : 'Ajouter un Client' }}</h3>
            <form @submit.prevent="saveClient" class="space-y-4">
               <div>
                    <label class="block text-sm font-medium text-gray-700">Type de Client *</label>
                    <div class="mt-2 flex items-center space-x-6">
                        <label class="inline-flex items-center">
                            <input type="radio" v-model="form.type" value="company" class="form-radio text-brand-600 focus:ring-brand-500">
                            <span class="ml-2 text-sm">Société / Entreprise</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" v-model="form.type" value="individual" class="form-radio text-brand-600 focus:ring-brand-500">
                            <span class="ml-2 text-sm">Particulier</span>
                        </label>
                    </div>
               </div>

               <div v-if="form.type === 'company'">
                  <label class="block text-sm font-medium text-gray-700">Raison Sociale (Nom de la société) *</label>
                  <input v-model="form.company_name" type="text" :required="form.type === 'company'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
               </div>
               <div>
                  <label class="block text-sm font-medium text-gray-700">{{ form.type === 'company' ? 'Personne de contact' : 'Nom du Particulier *' }}</label>
                  <input v-model="form.contact_name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
               </div>

               <div v-if="form.type === 'individual'">
                  <label class="block text-sm font-medium text-gray-700">CIN (Carte d'Identité Nationale) *</label>
                  <input v-model="form.cin" type="text" :required="form.type === 'individual'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
               </div>

                <div v-if="form.type === 'company'" class="grid grid-cols-2 gap-4 bg-gray-50 p-3 rounded border">
                   <div>
                       <label class="block text-xs font-medium text-gray-700">ICE *</label>
                       <input v-model="form.ice" type="text" :required="form.type === 'company'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-2 py-1.5 border">
                   </div>
                   <div>
                       <label class="block text-xs font-medium text-gray-700">RC (Optionnel)</label>
                       <input v-model="form.rc" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-2 py-1.5 border">
                   </div>
                   <div>
                       <label class="block text-xs font-medium text-gray-700">IF (Optionnel)</label>
                       <input v-model="form.if" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-2 py-1.5 border">
                   </div>
                   <div>
                       <label class="block text-xs font-medium text-gray-700">Patente (Optionnel)</label>
                       <input v-model="form.patente" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-2 py-1.5 border">
                   </div>
               </div>
               <div class="grid grid-cols-2 gap-4">
                   <div>
                      <label class="block text-sm font-medium text-gray-700">Email</label>
                      <input v-model="form.email" type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                   </div>
                   <div>
                      <label class="block text-sm font-medium text-gray-700">Téléphone {{ form.type === 'individual' ? '*' : '(Mobile)' }}</label>
                      <input v-model="form.phone" type="text" :required="form.type === 'individual'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                   </div>
                   <div>
                      <label class="block text-sm font-medium text-gray-700">Téléphone Fixe</label>
                      <input v-model="form.telephone_fix" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                   </div>
                   <div>
                      <label class="block text-sm font-medium text-gray-700">Fax</label>
                      <input v-model="form.fax" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                   </div>
               </div>
               <div>
                  <label class="block text-sm font-medium text-gray-700">Adresse Complète {{ form.type === 'individual' ? '*' : '' }}</label>
                  <textarea v-model="form.address" rows="2" :required="form.type === 'individual'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border"></textarea>
               </div>
               <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                 <button type="submit" :disabled="isSubmitting" class="inline-flex w-full justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-brand-700 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:col-start-2 sm:text-sm">
                   {{ isSubmitting ? 'Enregistrement...' : 'Enregistrer' }}
                 </button>
                 <button @click="isModalOpen = false" type="button" class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:col-start-1 sm:mt-0 sm:text-sm">
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
