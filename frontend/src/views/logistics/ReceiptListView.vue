<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

interface Receipt {
  id: number;
  receipt_number: string;
  purchase_order: { po_number: string, supplier: { name: string } };
  receipt_date: string;
  status: string;
}

const receipts = ref<Receipt[]>([])
const isLoading = ref(true)
const error = ref('')

const fetchReceipts = async () => {
    try {
        const response = await api.get('/receipts')
        receipts.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des bons de livraison.'
    } finally {
        isLoading.value = false
    }
}

const showAlert = (msg: string) => { window.alert(msg) }

onMounted(() => {
    fetchReceipts()
})
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Bons de Livraison (Logistique & Réception)</h1>
        <p class="mt-2 text-sm text-gray-700">Contrôlez les arrivages physiques suite aux Commandes Fournisseurs. Les articles entrent ici en attente de validation QA (Lots/Séries).</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <button @click="showAlert('La création manuelle de BL est en cours de développement. Pour l\'instant, ils sont auto-générés.')" type="button" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:w-auto">
          Nouvelle Réception
        </button>
      </div>
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
                   <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">N° BL Interne</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">N° Commande (PO)</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Fournisseur</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date Réception</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Statut (Contrôle)</th>
                   <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="rec in receipts" :key="rec.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">{{ rec.receipt_number }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ rec.purchase_order?.po_number }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ rec.purchase_order?.supplier?.name }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ rec.receipt_date }}</td>
                  
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                     <span v-if="rec.status === 'draft'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Saisie en cours</span>
                     <span v-else-if="rec.status === 'validated'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Validé (Attente QA)</span>
                     <span v-else-if="rec.status === 'qa_passed'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Contrôle Qualité OK (Stocké)</span>
                     <span v-else class="text-gray-500 uppercase text-xs">{{ rec.status }}</span>
                  </td>
                  
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 space-x-3">
                     <router-link v-if="rec.status === 'validated'" :to="{ name: 'stock.qa' }" class="text-amber-600 hover:text-amber-900 font-bold">Effectuer QA</router-link>
                     <button @click="showAlert('Aperçu complet du BL en construction.')" class="text-gray-400 hover:text-gray-900 ml-4">Ouvrir</button>
                  </td>
                </tr>
                <tr v-if="receipts.length === 0">
                    <td colspan="6" class="py-8 text-center text-gray-500">Aucune réception logistique enregistrée.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
