<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

interface StockUnit {
  id: number;
  quantity?: number;
  serial_number: string | null;
  status: string;
  lot: {
    lot_number: string;
    expiration_date: string;
    variant: {
        sku: string;
        product: {
            name: string;
            brand: string | null;
        }
    }
  };
  movements?: Array<{
    type: string;
    document?: {
        po_line?: {
            purchase_order?: {
                po_number: string;
                supplier?: { name: string }
            }
        }
    }
  }>;
}

const searchQuery = ref('')
const units = ref<StockUnit[]>([])
const isLoading = ref(true)
const error = ref('')

const fetchStock = async () => {
    try {
        const response = await api.get('/stock-units')
        units.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des stocks.'
    } finally {
        isLoading.value = false
    }
}

import { computed } from 'vue'
const filteredUnits = computed(() => {
    if (!searchQuery.value) return units.value;
    const q = searchQuery.value.toLowerCase();
    return units.value.filter(u => {
        const name = u.lot?.variant?.product?.name?.toLowerCase() || '';
        const sku = u.lot?.variant?.sku?.toLowerCase() || '';
        const lot = u.lot?.lot_number?.toLowerCase() || '';
        return name.includes(q) || sku.includes(q) || lot.includes(q);
    });
})

const isNearExpiration = (dateStr?: string) => {
    if (!dateStr) return false;
    const expDate = new Date(dateStr);
    const today = new Date();
    const limit = new Date(today.getTime() + (90 * 24 * 60 * 60 * 1000)); // 3 mois
    return expDate <= limit && expDate >= today;
}

const isExpired = (dateStr?: string) => {
    if (!dateStr) return false;
    const expDate = new Date(dateStr);
    const today = new Date();
    return expDate < today;
}

onMounted(() => {
    fetchStock()
})
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Inventaire et Traçabilité</h1>
        <p class="mt-2 text-sm text-gray-700">Vue détaillée des unités de stock physiques, avec traçabilité par numéro de Lot et Série (FIFO).</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <router-link :to="{ name: 'stock.qa' }" class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:w-auto">
          Nouvelle Entrée QA (Scanner)
        </router-link>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
      <div class="flex">
        <div class="ml-3">
          <p class="text-sm text-red-700">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex justify-center p-8">
      <svg class="animate-spin h-8 w-8 text-brand-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <!-- Actions & Search -->
    <div class="mt-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="relative flex-1 max-w-md">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" /></svg>
            </div>
            <input v-model="searchQuery" type="text" placeholder="Rechercher un article, SKU ou N° Lot..." class="block w-full pl-10 pr-3 py-2.5 border-none bg-white rounded-2xl text-sm shadow-sm ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-inset focus:ring-brand-500 transition-all font-medium">
        </div>
        <div class="text-xs text-gray-400 font-bold uppercase tracking-widest bg-gray-50 px-4 py-2 rounded-lg border border-gray-100">
            {{ filteredUnits.length }} élément(s) affiché(s)
        </div>
    </div>

    <!-- Stock Table -->
    <div v-else class="mt-4 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Article & SKU</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Numéro de Lot</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Origine (Fournisseur)</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date d'Expiration</th>
                  <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900 bg-gray-100">Quantité en Stock</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Statut</th>
                  <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                    <span class="sr-only">Détails</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="unit in filteredUnits" :key="unit.id">
                  
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                    <div class="font-medium text-gray-900">{{ unit.lot?.variant?.product?.name || 'Inconnu' }}</div>
                    <div v-if="unit.lot?.variant?.product?.brand" class="text-[10px] font-black text-brand-600 uppercase tracking-tighter">Marque: {{ unit.lot?.variant?.product?.brand }}</div>
                    <div class="text-gray-500 font-mono text-xs mt-0.5">{{ unit.lot?.variant?.sku || '-' }}</div>
                  </td>
                  
                  <td class="whitespace-nowrap px-3 py-4 text-sm font-mono font-medium text-gray-900">
                    {{ unit.lot?.lot_number }}
                  </td>

                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    <template v-if="unit.movements?.[0]?.document?.po_line?.purchase_order">
                        <span class="inline-flex items-center gap-x-1.5 rounded-md px-2 py-1 text-xs font-medium text-gray-900 ring-1 ring-inset ring-gray-200">
                           <svg class="h-3 w-3 fill-gray-400" viewBox="0 0 6 6" aria-hidden="true"><circle cx="3" cy="3" r="3" /></svg>
                           {{ unit.movements?.[0]?.document?.po_line?.purchase_order?.po_number }}
                        </span>
                        <div class="text-xs text-gray-400 mt-1">{{ unit.movements?.[0]?.document?.po_line?.purchase_order?.supplier?.name }}</div>
                    </template>
                    <span v-else class="text-gray-400 text-xs">Stock Initial / Manuel</span>
                  </td>

                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 border-x border-gray-100">
                    <span v-if="isExpired(unit.lot?.expiration_date)" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-red-100 text-red-800 font-bold border border-red-200">
                        Périmé le {{ unit.lot?.expiration_date }}
                    </span>
                    <span v-else-if="isNearExpiration(unit.lot?.expiration_date)" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-orange-100 text-orange-800 font-bold border border-orange-200">
                        Proche ({{ unit.lot?.expiration_date }})
                    </span>
                    <span v-else>
                        {{ unit.lot?.expiration_date || 'N/A' }}
                    </span>
                  </td>

                  <td class="whitespace-nowrap px-3 py-4 text-sm font-bold text-gray-900 border-r border-gray-100 bg-gray-50 text-center">
                    {{ unit.quantity }} unité(s)
                    <div v-if="unit.quantity === 1 && unit.serial_number !== 'Multiple (Bulk)' && unit.serial_number" class="text-xs font-normal text-gray-400 font-mono mt-1">SN: {{ unit.serial_number }}</div>
                  </td>

                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    <span v-if="unit.status === 'disponible'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                      En Stock
                    </span>
                    <span v-else-if="unit.status === 'quarantaine'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                      Quarantaine
                    </span>
                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                      {{ unit.status }}
                    </span>
                  </td>
                  
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                    <a href="#" class="text-brand-600 hover:text-brand-900">Historique</a>
                  </td>
                </tr>
                <tr v-if="units.length === 0">
                    <td colspan="6" class="py-8 text-center text-gray-500">Aucune unité de stock physique trouvée. Validez des réceptions QA pour générer du stock.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
