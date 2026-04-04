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

        <!-- Lignes du Bon de Commande (Refonte moderne) -->
        <div class="space-y-6">
            <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                <div>
                   <h3 class="text-lg font-bold text-gray-900">Articles à commander</h3>
                   <p class="text-xs text-gray-500">Ajoutez les produits et spécifiez les quantités attendues.</p>
                </div>
                <button @click="addLine" type="button" class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-4 py-2 text-sm font-bold text-brand-700 hover:bg-brand-100 transition-all shadow-sm border border-brand-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Ajouter un article
                </button>
            </div>

            <div class="space-y-3">
                <!-- Header de grille (Synchronisé avec le contenu) -->
                <div class="hidden sm:grid sm:grid-cols-12 gap-4 px-6 py-2 text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] bg-gray-50/50 rounded-lg border border-gray-100">
                    <div class="col-span-8">Désignation du Produit / Variante</div>
                    <div class="col-span-1 text-center">Unité</div>
                    <div class="col-span-2 text-center">Quantité</div>
                    <div class="col-span-1 text-right">Action</div>
                </div>

                <!-- Lignes de produits -->
                <div v-for="(line, index) in lines" :key="index" class="relative group grid grid-cols-1 sm:grid-cols-12 gap-4 items-center bg-white p-4 sm:p-2 sm:px-4 rounded-xl border border-gray-100 hover:border-brand-200 hover:shadow-md transition-all duration-300">
                    
                    <!-- Sélection Produit (Multi-étapes simulée) -->
                    <div class="sm:col-span-8 space-y-1">
                        <div class="relative">
                            <input v-model="line.search" type="text" placeholder="Rechercher (Nom, SKU...)" class="block w-full rounded-t-lg border-gray-100 py-1 px-3 text-[10px] bg-gray-50/30 focus:ring-brand-500 focus:border-brand-500 border-b-0 italic">
                            <select v-model="line.variant_id" required class="block w-full rounded-b-lg border-gray-200 py-2 px-3 text-sm font-semibold text-gray-900 focus:ring-brand-500 focus:border-brand-500 shadow-sm">
                                <option value="" disabled>Choisir la variante...</option>
                                <option v-for="p in filteredProducts(line.search)" :key="p.id" :value="p.id">
                                    {{ p.product?.brand ? '[' + p.product.brand + '] ' : '' }}{{ p.product?.name }} - {{ p.name_variation || p.sku }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Unité (Lecture seule ou info) -->
                    <div class="sm:col-span-1">
                        <div class="text-center py-2 bg-gray-50 rounded-lg text-[10px] font-bold text-gray-500 border border-gray-100">
                            {{ products.find(p => p.id === line.variant_id)?.product?.unit || 'U' }}
                        </div>
                    </div>

                    <!-- Quantité -->
                    <div class="sm:col-span-2">
                         <div class="relative">
                            <input v-model.number="line.quantity" required type="number" min="1" class="block w-full rounded-lg border-gray-200 py-2 text-center text-gray-900 font-black focus:ring-brand-500 focus:border-brand-500 sm:text-sm">
                            <div class="absolute -top-2 left-1/2 -translate-x-1/2 px-1 bg-white text-[8px] font-bold text-gray-400 uppercase tracking-tighter">Qté</div>
                         </div>
                    </div>

                    <!-- Bouton Supprimer -->
                    <div class="sm:col-span-1 flex items-center justify-end">
                        <button v-if="lines.length > 1" @click="removeLine(index)" type="button" class="text-gray-300 hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-50" title="Supprimer la ligne">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Résumé des totaux -->
            <div class="flex flex-col items-end pt-6 border-t border-gray-100 space-y-4">
                 <div class="flex items-center justify-between w-full max-w-sm text-lg font-black text-white bg-brand-600 p-4 rounded-xl shadow-lg shadow-brand-200">
                    <span class="uppercase tracking-widest text-[10px] opacity-80">Total des Unités à recevoir</span>
                    <span>{{ totalQuantities }} Items</span>
                 </div>
            </div>
        </div>

      </div>

      <!-- Action finale -->
      <div class="flex items-center justify-between gap-x-6 border-t border-gray-900/10 px-8 py-6 bg-gray-50 sm:rounded-b-xl">
        <p class="text-xs text-gray-500">* Vérifiez le fournisseur avant de valider la commande.</p>
        <button type="submit" :disabled="isSubmitting || lines.length === 0" class="rounded-lg bg-brand-600 px-8 py-3 text-sm font-bold text-white shadow-xl hover:bg-brand-700 transition-all transform hover:-translate-y-0.5 disabled:opacity-50">
          {{ isSubmitting ? 'Génération...' : 'Valider & Envoyer le Bon de Commande' }}
        </button>
      </div>
    </form>
  </div>
</template>
