<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

interface Payment {
  id: number;
  invoice: { invoice_number: string, order: { client: { company_name: string, type: string, contact_name: string } } };
  amount: number;
  payment_date: string;
  payment_method: string;
  reference: string;
}

const payments = ref<Payment[]>([])
const isLoading = ref(true)
const error = ref('')

const fetchPayments = async () => {
    try {
        const response = await api.get('/invoice-payments')
        payments.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des encaissements.'
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    fetchPayments()
})
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Journal d'Encaissements</h1>
        <p class="mt-2 text-sm text-gray-700">Historique des versements clients via chèque, espèce, lettrage bancaire ou carte.</p>
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
                   <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">Date Versement</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Attaché à (Client / Facture)</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Moyen de paiement</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Réf (Chèque/Virement)</th>
                   <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Montant Encaissé</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="pay in payments" :key="pay.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">{{ pay.payment_date }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <div class="font-medium text-gray-900">{{ pay.invoice?.order?.client?.type === 'company' ? pay.invoice?.order?.client?.company_name : pay.invoice?.order?.client?.contact_name }}</div>
                      <div class="text-xs text-gray-400">FA: {{ pay.invoice?.invoice_number }}</div>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 capitalize">{{ pay.payment_method }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ pay.reference || '-' }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-green-600 text-right font-bold">+ {{ pay.amount }} MAD</td>
                </tr>
                <tr v-if="payments.length === 0">
                    <td colspan="5" class="py-8 text-center text-gray-500">Aucun règlement n'a été enregistré.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
