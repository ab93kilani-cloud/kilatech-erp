<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'

const router = useRouter()

const form = ref({
    client_id: '',
    invoice_id: '',
    total_amount: 0,
    reason: ''
})

const clients = ref<any[]>([])
const invoices = ref<any[]>([])
const error = ref('')

const isSubmitting = ref(false)

const fetchClients = async () => {
    try {
        const response = await api.get('/clients')
        clients.value = response.data
    } catch (e) {
        console.error('Erreur clients', e)
    }
}

const fetchInvoices = async () => {
    try {
        const response = await api.get('/invoices')
        invoices.value = response.data
    } catch (e) {
        console.error('Erreur factures', e)
    }
}

// Filtrer les factures pour le client sélectionné, et ne montrer que celles qui ne sont pas archivées
const filteredInvoices = computed(() => {
    if (!form.value.client_id) return []
    return invoices.value.filter(inv => {
        // La facture est liée à une order, qui est liée à un client
        // On ne permet la création d'avoir QUE si la facture est totalement payée
        const isClientMatch = inv.order?.client_id == form.value.client_id;
        const isPaid = inv.balance_due == 0 || inv.status === 'paid';
        return isClientMatch && isPaid;
    })
})

const submitForm = async () => {
    isSubmitting.value = true
    error.value = ''
    try {
        await api.post('/credit-notes', {
            client_id: form.value.client_id,
            invoice_id: form.value.invoice_id || null,
            total_amount: form.value.total_amount,
            reason: form.value.reason
        })
        window.alert('✅ Avoir créé avec succès.')
        router.push({ name: 'credit-notes' })
    } catch (e: any) {
        error.value = e.response?.data?.message || "Erreur lors de la création de l'avoir."
    } finally {
        isSubmitting.value = false
    }
}

const cancel = () => {
    router.push({ name: 'credit-notes' })
}

onMounted(() => {
    fetchClients()
    fetchInvoices()
})
</script>

<template>
  <div>
    <div class="md:flex md:items-center md:justify-between mb-8">
      <div class="min-w-0 flex-1">
        <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">Créer un Avoir</h2>
      </div>
    </div>

    <form @submit.prevent="submitForm" class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2 max-w-2xl">
      <div class="px-4 py-6 sm:p-8">
        
        <div v-if="error" class="mb-4 bg-red-50 p-4 rounded-md">
            <p class="text-sm text-red-700">{{ error }}</p>
        </div>

        <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
          
          <!-- Client -->
          <div class="sm:col-span-full">
            <label class="block text-sm font-medium leading-6 text-gray-900">Client *</label>
            <div class="mt-2">
              <select v-model="form.client_id" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:max-w-xs sm:text-sm sm:leading-6">
                <option value="" disabled>Sélectionner un client</option>
                <option v-for="client in clients" :key="client.id" :value="client.id">
                  {{ client.type === 'company' ? client.company_name : client.contact_name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Facture Liée (Optionnelle) -->
          <div class="sm:col-span-full">
            <label class="block text-sm font-medium leading-6 text-gray-900">Facture de référence (Optionnel)</label>
            <div class="mt-2">
              <select v-model="form.invoice_id" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:max-w-xs sm:text-sm sm:leading-6" :disabled="!form.client_id">
                <option value="">Aucune (Avoir global)</option>
                <option v-for="inv in filteredInvoices" :key="inv.id" :value="inv.id">
                  {{ inv.invoice_number }} - {{ inv.total_amount }} MAD
                </option>
              </select>
            </div>
            <p v-if="!form.client_id" class="mt-1 text-xs text-gray-500">Veuillez d'abord sélectionner un client.</p>
          </div>

          <!-- Montant -->
          <div class="sm:col-span-3">
            <label class="block text-sm font-medium leading-6 text-gray-900">Montant de l'avoir (MAD) *</label>
            <div class="mt-2">
              <input type="number" step="0.01" min="0.01" v-model="form.total_amount" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
            </div>
          </div>

          <!-- Motif -->
          <div class="sm:col-span-full">
            <label class="block text-sm font-medium leading-6 text-gray-900">Motif de l'avoir / Notes</label>
            <div class="mt-2">
              <textarea v-model="form.reason" rows="3" placeholder="Ex: Retour de marchandise, Geste commercial..." class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3"></textarea>
            </div>
          </div>

        </div>
      </div>
      
      <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8 bg-gray-50 rounded-b-xl">
        <button type="button" @click="cancel" class="text-sm font-semibold leading-6 text-gray-900">Annuler</button>
        <button type="submit" :disabled="isSubmitting" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:opacity-50">
          {{ isSubmitting ? 'Enregistrement...' : 'Valider l\'Avoir' }}
        </button>
      </div>
    </form>
  </div>
</template>
