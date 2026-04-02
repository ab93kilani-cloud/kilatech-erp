<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'

const route = useRoute()
const poId = route.params.id as string

const po = ref<any>(null)
const isLoading = ref(true)
const error = ref('')

const fetchPO = async () => {
    try {
        const response = await api.get(`/purchase-orders/${poId}`)
        po.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des détails.'
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    fetchPO()
})

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'draft': return { class: 'bg-gray-100 text-gray-800', label: 'Brouillon' }
        case 'ordered': return { class: 'bg-blue-100 text-blue-800', label: 'Envoyée' }
        case 'partially_received': return { class: 'bg-yellow-100 text-yellow-800', label: 'Partiellement Reçu' }
        case 'completed': return { class: 'bg-green-100 text-green-800', label: 'Terminée' }
        default: return { class: 'bg-gray-100 text-gray-800', label: status }
    }
}
</script>

<template>
  <div class="max-w-7xl mx-auto py-8">
    <div class="mb-8 flex items-center justify-between">
      <div>
         <h1 class="text-2xl font-semibold text-gray-900">
             Détail de la Commande : {{ po?.po_number }}
         </h1>
         <p class="mt-2 text-sm text-gray-600">Récapitulatif des lignes et traçabilité vers les lots de stock.</p>
      </div>
      <router-link :to="{ name: 'purchases' }" class="text-sm font-medium text-gray-600 hover:text-gray-900 border px-3 py-1.5 rounded bg-white">
        Retour aux Commandes
      </router-link>
    </div>

    <div v-if="isLoading" class="flex justify-center p-8">
       <span class="text-gray-500">Chargement des relations Achat -> Stock...</span>
    </div>
    
    <div v-else-if="error" class="bg-red-50 p-4 rounded text-red-700">
        {{ error }}
    </div>

    <div v-else class="space-y-8">
        
        <!-- Header PO -->
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Fournisseur : {{ po.supplier?.name }}</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Créé le {{ po.created_at?.split('T')[0] }} par {{ po.creator?.name }}</p>
                </div>
                <div>
                     <span :class="['inline-flex items-center px-3 py-1 rounded-full text-sm font-medium', getStatusBadge(po.status).class]">
                        {{ getStatusBadge(po.status).label }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Lignes commandées -->
        <div class="bg-white shadow sm:rounded-lg overflow-hidden">
            <div class="px-4 py-5 sm:px-6 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Lignes d'Achat (Prévues)</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-white">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qte Demandée</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qte Reçue Absolue</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="line in po.lines" :key="line.id">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-l border-brand-100">
                            {{ line.variant?.product?.name }} - {{ line.variant?.sku }}
                            <div v-if="line.variant?.product?.brand" class="text-[10px] font-black text-brand-600 uppercase tracking-tighter mt-1 italic">Marque: {{ line.variant?.product?.brand }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ line.ordered_quantity }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold" :class="line.received_quantity >= line.ordered_quantity ? 'text-green-600' : 'text-yellow-600'">
                            {{ line.received_quantity }} / {{ line.ordered_quantity }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Traçabilité des Lots en Stock (Le lien Achat -> Stock demandé) -->
        <div class="bg-white shadow sm:rounded-lg overflow-hidden border-2 border-brand-100">
            <div class="px-4 py-5 sm:px-6 bg-brand-50 border-b border-brand-200 flex justify-between items-center">
                <h3 class="text-lg font-medium leading-6 text-brand-900">Liaison Traçabilité (Lots & Stock Physique Actuel)</h3>
            </div>
            
            <div class="p-0">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bon de Réception</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lot en Stock QA</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unités Générées</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template v-for="receipt in po.receipts" :key="receipt.id">
                            <template v-for="rLine in receipt.lines" :key="rLine.id">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ receipt.receipt_number }} <br>
                                        <span class="text-xs text-gray-500 font-normal">{{ receipt.receipt_date }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ rLine.poLine?.variant?.sku }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                                        <!-- Affichage si un lien vers un lot est trouvé dans les StockMovements -->
                                        <span v-if="rLine.stock_movements && rLine.stock_movements.length > 0 && rLine.stock_movements[0].unit?.lot">
                                            Lot: {{ rLine.stock_movements[0].unit.lot.lot_number }}
                                            <div class="text-xs text-gray-400 mt-1">Exp: {{ rLine.stock_movements[0].unit.lot.expiration_date }}</div>
                                        </span>
                                        <span v-else class="text-yellow-600 font-semibold text-xs inline-flex bg-yellow-100 px-2 py-1 rounded">
                                            En attente de validation QA
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-700">
                                        <span v-if="rLine.stock_movements">
                                            {{ rLine.stock_movements.length }} unité(s) traçable(s)
                                        </span>
                                        <span v-else>0</span>
                                    </td>
                                </tr>
                            </template>
                        </template>
                        <tr v-if="po.receipts?.length === 0">
                            <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">
                                Aucune réception enregistrée pour cette commande pour le moment.<br>
                                Les lots de stock apparaitront ici après validation QA.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
  </div>
</template>
