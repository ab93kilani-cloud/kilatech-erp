<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'

const router = useRouter()
const route = useRoute()

const suppliers = ref<any[]>([])
const products = ref<any[]>([])

const isEditMode = computed(() => !!route.params.id)
const poId = computed(() => route.params.id)

const form = ref({
    supplier_id: '',
    expected_delivery_date: '',
    payment_method: 'Virement',
    payment_conditions: 'A reception de facture',
})

// Panier du Bon de Commande
const lines = ref([
    { variant_id: '', quantity: 1, unit_price: 0, search: '' }
])

const isSubmitting = ref(false)
const errorMsg = ref('')

const fetchDependencies = async () => {
    try {
        const responseSuppliers = await api.get('/suppliers')
        suppliers.value = responseSuppliers.data

        const responseVariants = await api.get('/product-variants')
        products.value = responseVariants.data
    } catch (e: any) {
        errorMsg.value = "Erreur de chargement du catalogue ou des fournisseurs."
    }
}

const addLine = () => {
    lines.value.push({ variant_id: '', quantity: 1, unit_price: 0, search: '' })
}

const removeLine = (index: number) => {
    if (lines.value.length > 1) {
        lines.value.splice(index, 1)
    }
}

const filteredProducts = (searchQuery: string) => {
    if (!searchQuery) return products.value;
    const q = searchQuery.toLowerCase();
    return products.value.filter(p => {
        const name = (p.product?.name || '').toLowerCase();
        const sku = (p.sku || '').toLowerCase();
        const variation = (p.name_variation || '').toLowerCase();
        return name.includes(q) || sku.includes(q) || variation.includes(q);
    });
}

const totalQuantities = computed(() => {
    return lines.value.reduce((acc, line) => acc + (line.quantity || 0), 0)
})

const savePurchaseOrder = async () => {
    isSubmitting.value = true
    errorMsg.value = ''

    try {
        const payload = {
            supplier_id: form.value.supplier_id,
            expected_delivery_date: form.value.expected_delivery_date,
            payment_method: form.value.payment_method,
            payment_conditions: form.value.payment_conditions,
            lines: lines.value.map(line => ({
                variant_id: line.variant_id,
                ordered_quantity: line.quantity,
                unit_price: line.unit_price // Envoyé à 0, modifiable à la réception
            }))
        }

        if (isEditMode.value) {
            await api.put(`/purchase-orders/${poId.value}`, payload)
        } else {
            await api.post('/purchase-orders', payload)
        }

        router.push({ name: 'purchases' })
    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || 'Erreur lors de la création de la commande d\'achat.'
    } finally {
        isSubmitting.value = false
    }
}

onMounted(async () => {
    const d = new Date()
    d.setDate(d.getDate() + 14)
    form.value.expected_delivery_date = d.toISOString().split('T')[0] || ''
    
    // Check if variant_id is passed in URL
    const variantIdParam = router.currentRoute.value.query.variant_id;
    const firstLine = lines.value[0];
    if (variantIdParam && firstLine) {
        firstLine.variant_id = (Array.isArray(variantIdParam) ? variantIdParam[0] : variantIdParam) as string;
    }
    
    await fetchDependencies()

    if (isEditMode.value) {
        try {
            const res = await api.get(`/purchase-orders/${poId.value}`)
            const po = res.data
            form.value.supplier_id = po.supplier_id
            form.value.expected_delivery_date = po.expected_delivery_date || ''
            form.value.payment_method = po.payment_method || 'Virement'
            form.value.payment_conditions = po.payment_conditions || ''
            
            lines.value = po.lines.map((l: any) => ({
                variant_id: l.variant_id,
                quantity: l.ordered_quantity,
                unit_price: l.unit_price || 0,
                search: l.variant ? `${l.variant.product?.name || ''} - ${l.variant.name_variation || l.variant.sku}` : ''
            }))
        } catch (e) {
            errorMsg.value = "Erreur de chargement de la commande à modifier."
        }
    }
})
</script>

<template>
  <div class="max-w-5xl mx-auto py-8">
    <div class="mb-8 flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-gray-900">{{ isEditMode ? 'Modifier le Bon de Commande' : 'Nouveau Bon de Commande Fournisseur' }}</h1>
      <router-link :to="{ name: 'purchases' }" class="text-sm font-medium text-gray-600 hover:text-gray-900 border px-3 py-1.5 rounded bg-white">
        Retour à la liste
      </router-link>
    </div>

    <form @submit.prevent="savePurchaseOrder" class="bg-white shadow-sm ring-1 ring-gray-200 sm:rounded-xl">
      <div class="px-4 py-6 sm:p-8 space-y-8">
        
        <div v-if="errorMsg" class="bg-red-50 border-l-4 border-red-400 p-4">
          <p class="text-sm text-red-700">{{ errorMsg }}</p>
        </div>

        <!-- Informations PO -->
        <div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-6 border-b pb-8 border-gray-100">
            <div class="sm:col-span-3">
                <label class="block text-sm font-medium leading-6 text-gray-900">Fournisseur *</label>
                <div class="mt-2">
                    <select v-model="form.supplier_id" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                        <option value="" disabled>Sélectionner un fournisseur...</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
            </div>

            <div class="sm:col-span-3">
                <label class="block text-sm font-medium leading-6 text-gray-900">Date de Livraison Prévue *</label>
                <div class="mt-2">
                    <input v-model="form.expected_delivery_date" required type="date" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                </div>
            </div>

            <div class="sm:col-span-3">
                <label class="block text-sm font-medium leading-6 text-gray-900">Mode de paiement *</label>
                <div class="mt-2">
                    <select v-model="form.payment_method" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                        <option value="Virement">Virement</option>
                        <option value="Chèque">Chèque</option>
                        <option value="Espèces">Espèces</option>
                        <option value="LCR / Traite">LCR / Traite</option>
                    </select>
                </div>
            </div>

            <div class="sm:col-span-3">
                <label class="block text-sm font-medium leading-6 text-gray-900">Échéance de paiement *</label>
                <div class="mt-2">
                    <input v-model="form.payment_conditions" placeholder="ex: 30 jours fin de mois, À réception..." required type="text" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                </div>
            </div>
        </div>

        <!-- Lignes du Bon de Commande -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Lignes d'achat (Produits Génériques)</h3>
                <button @click="addLine" type="button" class="text-sm text-brand-600 font-medium hover:text-brand-900 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Ajouter Ligne
                </button>
            </div>

            <div class="space-y-2">
                <div class="hidden sm:grid sm:grid-cols-12 gap-4 px-4 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100 bg-gray-50/50 rounded-t-lg">
                    <div class="col-span-9">Variante Produit (SKU)</div>
                    <div class="col-span-2 text-center">Quantité</div>
                    <div class="col-span-1 text-right pr-2">Action</div>
                </div>

                <div v-for="(line, index) in lines" :key="index" class="group relative flex flex-col sm:grid sm:grid-cols-12 gap-4 items-center bg-white hover:bg-brand-50/30 p-4 sm:px-4 sm:py-3 rounded-lg border border-gray-100 sm:border-0 sm:border-b sm:border-gray-50 transition-all">
                    
                    <div class="w-full sm:col-span-9 flex flex-col gap-2">
                        <div class="relative">
                            <input v-model="line.search" type="text" placeholder="🔍 Filtrer les produits..." class="block w-full rounded-t-lg border-gray-200 py-1.5 text-gray-900 shadow-sm focus:ring-2 focus:ring-brand-500 sm:text-[10px] px-3 bg-gray-50/50">
                            <select v-model="line.variant_id" required class="block w-full rounded-b-lg border-t-0 border-gray-200 py-2 text-gray-900 shadow-sm focus:ring-2 focus:ring-brand-500 sm:text-sm font-medium">
                                <option value="" disabled>Choisir un produit (SKU)...</option>
                                <option v-for="p in filteredProducts(line.search)" :key="p.id" :value="p.id">
                                    {{ p.product?.brand ? '[' + p.product.brand + '] ' : '' }}{{ p.product?.name }} - {{ p.name_variation || p.sku }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="w-full sm:col-span-2">
                        <input v-model.number="line.quantity" required type="number" min="1" class="block w-full rounded-lg border-gray-200 py-2 text-center text-gray-900 shadow-sm focus:ring-2 focus:ring-brand-500 sm:text-sm font-bold">
                    </div>

                    <div class="w-full sm:col-span-1 flex items-center justify-end">
                        <button v-if="lines.length > 1" @click="removeLine(index)" type="button" class="text-gray-300 hover:text-red-500 transition-colors p-1">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex flex-col items-end pt-8 border-t border-gray-100">
             <div class="flex items-center justify-between w-full max-w-xs text-lg font-black text-white bg-brand-600 p-4 rounded-xl shadow-lg shadow-brand-200">
                <span class="uppercase tracking-widest text-[10px]">Total Unités</span>
                <span>{{ totalQuantities }}</span>
             </div>
        </div>
      </div>

      <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8 bg-gray-50">
        <button type="submit" :disabled="isSubmitting || lines.length === 0" class="rounded-md bg-brand-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:opacity-50">
          {{ isSubmitting ? 'Génération...' : 'Envoyer la Commande' }}
        </button>
      </div>
    </form>
  </div>
</template>
