<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/plugins/axios'

const router = useRouter()
const route = useRoute()

const isEditing = ref(false)
const quoteId = ref<string | null>(null)

// Données Backend
const clients = ref<any[]>([])
const allVariants = ref<any[]>([]) // toutes les variantes brutes

// Maya: Groupe les variantes par produit pour la sélection en 2 étapes
const products = computed(() => {
    const map = new Map<number, any>()
    allVariants.value.forEach(v => {
        if (!v.product) return
        if (!map.has(v.product.id)) {
            map.set(v.product.id, {
                id: v.product.id,
                name: v.product.name,
                brand: v.product.brand,
                reference: v.product.reference,
                variants: []
            })
        }
        map.get(v.product.id).variants.push(v)
    })
    return Array.from(map.values())
})

const form = ref({
    client_id: '',
    valid_until: '',
    discount_amount: 0,
    payment_method: '',
    payment_term: ''
})

// Panier du Devis — chaque ligne a un selectedProductId pour la sélection en 2 étapes
const lines = ref<any[]>([
    { selectedProductId: '', variant_id: '', quantity: 1, unit_price: 0, tax_rate: 20.00, discount_percent: 0 }
])

const isSubmitting = ref(false)
const errorMsg = ref('')

// Variantes disponibles pour un produit sélectionné sur une ligne
const variantsForLine = (line: any) => {
    if (!line.selectedProductId) return []
    const prod = products.value.find(p => p.id === Number(line.selectedProductId))
    return prod ? prod.variants : []
}

// Quand le produit change sur une ligne, on réinitialise la variante
const onProductSelect = (line: any) => {
    line.variant_id = ''
    line.unit_price = 0
}

// Récupérer les données de base nécessaires pour créer un devis
const fetchDependencies = async () => {
    try {
        const responseClients = await api.get('/clients')
        clients.value = responseClients.data

        const responseVariants = await api.get('/product-variants')
        allVariants.value = responseVariants.data
    } catch (e: any) {
        errorMsg.value = "Erreur de chargement du catalogue ou des clients."
    }
}

// Actions Panier
const addLine = () => {
    lines.value.push({ selectedProductId: '', variant_id: '', quantity: 1, unit_price: 0, tax_rate: 20, discount_percent: 0 })
}

const removeLine = (index: number) => {
    if (lines.value.length > 1) {
        lines.value.splice(index, 1)
    }
}

// Mise à jour automatique du prix unitaire lors du choix de la variante
const onVariantSelect = (index: number) => {
    if (!lines.value[index]) return
    const selectedVariantId = lines.value[index].variant_id
    const variantObj = allVariants.value.find(v => v.id === selectedVariantId)
    if (variantObj) {
        const ttcPrice = Number(variantObj.public_price || variantObj.product?.public_price || 0);
        const tvaRate = Number(variantObj.tva_rate || 20);
        lines.value[index].unit_price = Number((ttcPrice / (1 + tvaRate/100)).toFixed(4));
        lines.value[index].tax_rate = tvaRate;
    }
}

// Moteur de recherche rapide de produits
const productSearch = ref('')
const showSearchDropdown = ref(false)

const filteredCatalog = computed(() => {
    if (!productSearch.value) return products.value.slice(0, 50)
    const term = productSearch.value.toLowerCase()
    return products.value.filter(p =>
        (p.name && p.name.toLowerCase().includes(term)) ||
        (p.brand && p.brand.toLowerCase().includes(term)) ||
        (p.reference && p.reference.toLowerCase().includes(term)) ||
        p.variants.some((v: any) => v.sku && v.sku.toLowerCase().includes(term))
    ).slice(0, 50)
})

const hideSearchDropdown = () => {
    showSearchDropdown.value = false
}

const addSearchedProduct = (product: any) => {
    if (!product) return
    // Si la première ligne est vide, on la pré-remplit avec le produit
    const firstVariant = product.variants[0]
    const ttcPrice = Number(firstVariant?.public_price || product?.public_price || 0);
    const tvaRate = Number(firstVariant?.tva_rate || 20);
    const newLine = {
        selectedProductId: product.id,
        variant_id: product.variants.length === 1 ? firstVariant.id : '', // Pré-sélectionne si une seule variante
        quantity: 1,
        unit_price: product.variants.length === 1 ? Number((ttcPrice / (1 + tvaRate/100)).toFixed(4)) : 0,
        tax_rate: tvaRate,
        discount_percent: 0
    }
    if (lines.value.length === 1 && lines.value[0] && !lines.value[0].variant_id && !lines.value[0].selectedProductId) {
        lines.value[0] = newLine
    } else {
        lines.value.push(newLine)
    }
    productSearch.value = ''
    showSearchDropdown.value = false
}

const applyGlobalDiscountTooltip = () => {
    const p = prompt("Entrez le pourcentage de remise à appliquer à TOUTES les lignes (%) :", "0");
    if (p !== null) {
        const val = parseFloat(p);
        if (!isNaN(val) && val >= 0 && val <= 100) {
            lines.value.forEach(line => {
                line.discount_percent = val;
            });
        }
    }
}

// Calculs dynamiques
const totalHT = computed(() => {
    return lines.value.reduce((acc, line) => {
        const lineHT = line.quantity * line.unit_price;
        const discountVal = lineHT * ((line.discount_percent || 0) / 100);
        return acc + (lineHT - discountVal);
    }, 0)
})

const totalTTC = computed(() => {
    const rawTTC = lines.value.reduce((acc, line) => {
        const lineHT = line.quantity * line.unit_price;
        const discountVal = lineHT * ((line.discount_percent || 0) / 100);
        const netHT = lineHT - discountVal;
        const taxVal = netHT * (line.tax_rate / 100);
        return acc + netHT + taxVal;
    }, 0)
    return rawTTC - (form.value.discount_amount || 0)
})

const saveQuote = async () => {
    isSubmitting.value = true
    errorMsg.value = ''

    try {
        const payload = {
            client_id: form.value.client_id,
            valid_until: form.value.valid_until,
            discount_amount: form.value.discount_amount,
            payment_method: form.value.payment_method,
            payment_term: form.value.payment_term,
            lines: lines.value
        }

        if (isEditing.value && quoteId.value) {
            await api.put(`/quotes/${quoteId.value}`, payload)
        } else {
            await api.post('/quotes', payload)
        }

        router.push({ name: 'quotes' })
    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || 'Erreur lors de la sauvegarde du devis.'
    } finally {
        isSubmitting.value = false
    }
}

const deleteQuote = async () => {
    if (!confirm("Êtes-vous sûr de vouloir supprimer ce devis ? Cette action est irréversible.")) return;
    
    isSubmitting.value = true;
    try {
        await api.delete(`/quotes/${quoteId.value}`);
        router.push({ name: 'quotes' });
    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || "Erreur lors de la suppression du devis.";
        isSubmitting.value = false;
    }
}

const fetchQuote = async () => {
    try {
        const response = await api.get(`/quotes/${quoteId.value}`)
        const data = response.data
        form.value = {
            client_id: data.client_id,
            valid_until: data.valid_until,
            discount_amount: data.discount_amount,
            payment_method: data.payment_method || '',
            payment_term: data.payment_term || ''
        }
        lines.value = data.lines.map((l: any) => ({
            selectedProductId: allVariants.value.find((v: any) => v.id === l.variant_id)?.product?.id || '',
            variant_id: l.variant_id,
            quantity: l.quantity,
            unit_price: Number(l.unit_price),
            discount_percent: Number(l.discount_percent),
            tax_rate: 20
        }))
    } catch (e) {
        errorMsg.value = "Erreur de chargement du devis."
    }
}

onMounted(() => {
    if (route.params.id) {
        isEditing.value = true
        quoteId.value = route.params.id as string
        fetchQuote()
    } else {
        const d = new Date()
        d.setDate(d.getDate() + 15)
        form.value.valid_until = d.toISOString().split('T')[0] || ''
    }
    
    fetchDependencies()
})
</script>

<template>
  <div class="max-w-5xl mx-auto py-8">
    <div class="mb-8 flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-gray-900">{{ isEditing ? 'Modifier Devis #' + quoteId : 'Nouveau Devis' }}</h1>
      <router-link :to="{ name: 'quotes' }" class="text-sm font-medium text-gray-600 hover:text-gray-900 border px-3 py-1.5 rounded bg-white">
        Retour à la liste
      </router-link>
    </div>

    <form @submit.prevent="saveQuote" class="bg-white shadow-sm ring-1 ring-gray-200 sm:rounded-xl">
      <div class="px-4 py-6 sm:p-8 space-y-8">
        
        <div v-if="errorMsg" class="bg-red-50 border-l-4 border-red-400 p-4">
          <p class="text-sm text-red-700">{{ errorMsg }}</p>
        </div>

        <!-- Informations Devis (Dates et Paiement) -->
        <div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-6 border-b pb-8 border-gray-100">
            <div class="sm:col-span-3 lg:col-span-2">
                <label class="block text-sm font-medium leading-6 text-gray-900">Client B2B *</label>
                <div class="mt-2">
                    <select v-model="form.client_id" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                        <option value="" disabled>Sélectionner un client...</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">
                            {{ c.type === 'company' ? '🏢 ' + (c.company_name || c.contact_name || 'Société N/A') : '👤 ' + (c.contact_name || 'Client N/A') }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="sm:col-span-3 lg:col-span-2">
                <label class="block text-sm font-medium leading-6 text-gray-900">Date de Validité *</label>
                <div class="mt-2">
                    <input v-model="form.valid_until" required type="date" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                </div>
            </div>

            <div class="sm:col-span-3 lg:col-span-2">
                <label class="block text-sm font-medium leading-6 text-gray-900">Mode de Paiement Préféré</label>
                <div class="mt-2">
                    <select v-model="form.payment_method" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                        <option value="">(Non spécifié)</option>
                        <option value="virement">Virement Bancaire</option>
                        <option value="cheque">Chèque</option>
                        <option value="especes">Espèces</option>
                        <option value="lettre_credit">Lettre de Crédit</option>
                    </select>
                </div>
            </div>

            <div class="sm:col-span-3 lg:col-span-2">
                <label class="block text-sm font-medium leading-6 text-gray-900">Échéance de Paiement</label>
                <div class="mt-2">
                    <select v-model="form.payment_term" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                        <option value="">(Standard)</option>
                        <option value="a_reception">Paiement à réception</option>
                        <option value="30_jours">À 30 Jours</option>
                        <option value="60_jours">À 60 Jours</option>
                        <option value="fin_de_mois">Fin de mois + 30 Jours</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Corps du Devis (Lignes) -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Catalogue & Lignes de l'offre</h3>
                <button @click="addLine" type="button" class="text-sm text-brand-600 font-medium hover:text-brand-900 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Ajout Manuel
                </button>
            </div>

            <!-- SEARCH BAR RAPIDE -->
            <div class="mb-6 relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input v-model="productSearch" @focus="showSearchDropdown = true" @blur="hideSearchDropdown" type="text" class="block w-full rounded-md border-0 py-3 pl-10 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 bg-brand-50 font-medium" placeholder="Rechercher un produit (Nom, SKU...) pour l'ajouter rapidement au devis">
                
                <div v-if="showSearchDropdown && filteredCatalog.length > 0" class="absolute z-10 mt-1 w-full bg-white shadow-xl max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto sm:text-sm">
                    <div v-for="p in filteredCatalog" :key="p.id" @mousedown.prevent="addSearchedProduct(p)" class="text-gray-900 relative cursor-pointer select-none py-3 pl-3 pr-9 border-b border-gray-50 last:border-0 hover:bg-brand-50 transition-colors">
                        <div class="flex items-center justify-between">
                          <div class="flex flex-col">
                            <span class="font-bold block truncate text-brand-700">
                                <span v-if="p.brand" class="text-[9px] bg-brand-600 text-white px-1.5 py-0.5 rounded mr-1 uppercase font-black">{{ p.brand }}</span>
                                {{ p.name }}
                            </span>
                            <span class="text-gray-500 text-xs mt-1">{{ p.variants.length }} variante(s) — Réf: {{ p.reference }}</span>
                          </div>
                          <span class="text-xs text-brand-600 font-bold ml-4">{{ p.variants.length > 1 ? '→ Choisir taille' : '+ Ajouter' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                 <!-- Header Desktop Only -->
                <div class="hidden sm:grid sm:grid-cols-12 gap-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                    <div class="col-span-3">Article / SKU</div>
                    <div class="col-span-2">Quantité</div>
                    <div class="col-span-2">P.U HT (MAD)</div>
                    <div class="col-span-2 flex items-center gap-2">
                        Remise (%)
                        <button @click="applyGlobalDiscountTooltip" type="button" class="text-[9px] bg-brand-600 text-white px-1 rounded hover:bg-brand-700" title="Appliquer un % à toutes les lignes">TOUS</button>
                    </div>
                    <div class="col-span-1">TVA (%)</div>
                    <div class="col-span-2 text-right">Total HT</div>
                </div>

                <!-- Lignes dynamiques -->
                <div v-for="(line, index) in lines" :key="index" class="flex flex-col sm:grid sm:grid-cols-12 gap-4 items-start bg-gray-50 sm:bg-transparent p-4 sm:p-0 rounded-lg">
                    
                    <!-- Étape 1 : Choisir le Produit -->
                    <div class="w-full sm:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Produit</label>
                        <select v-model="line.selectedProductId" @change="onProductSelect(line)" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm">
                            <option value="" disabled>Choisir produit...</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">
                                {{ p.brand ? '(' + p.brand + ') ' : '' }}{{ p.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Étape 2 : Choisir la Variante (taille/couleur) -->
                    <div class="w-full sm:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Variante {{ variantsForLine(line).length > 0 ? '(' + variantsForLine(line).length + ')' : '' }}</label>
                        <select v-model="line.variant_id" @change="onVariantSelect(index)" :disabled="!line.selectedProductId" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm disabled:bg-gray-100 disabled:text-gray-400">
                            <option value="" disabled>{{ line.selectedProductId ? 'Choisir variante...' : 'D’abord choisir un produit' }}</option>
                            <option v-for="v in variantsForLine(line)" :key="v.id" :value="v.id">
                                {{ v.name_variation || v.sku }}
                            </option>
                        </select>
                    </div>

                    <div class="w-full sm:col-span-2">
                        <label class="sm:hidden block text-xs text-gray-500 mb-1">Qte</label>
                        <input v-model.number="line.quantity" required type="number" min="1" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                    </div>

                    <div class="w-full sm:col-span-2">
                         <label class="sm:hidden block text-xs text-gray-500 mb-1">Prix Unitaire HT</label>
                        <input v-model.number="line.unit_price" required type="number" step="0.01" min="0" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                    </div>

                    <div class="w-full sm:col-span-2">
                        <label class="sm:hidden block text-xs text-gray-500 mb-1">Remise (%)</label>
                        <input v-model.number="line.discount_percent" type="number" step="0.01" min="0" max="100" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3" placeholder="0">
                    </div>

                    <div class="w-full sm:col-span-1">
                        <label class="sm:hidden block text-xs text-gray-500 mb-1">TVA</label>
                        <select v-model.number="line.tax_rate" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                            <option :value="20">20%</option>
                            <option :value="10">10%</option>
                            <option :value="0">0%</option>
                        </select>
                    </div>

                    <div class="w-full sm:col-span-2 flex items-center justify-between sm:justify-end gap-4">
                        <span class="font-medium text-gray-900 text-sm">
                            {{ ((line.quantity * line.unit_price) * (1 - (line.discount_percent || 0)/100)).toFixed(2) }} 
                        </span>
                        
                        <button v-if="lines.length > 1" @click="removeLine(index)" type="button" class="text-red-500 hover:text-red-700 p-1 bg-white rounded-full sm:bg-transparent shadow sm:shadow-none">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Totaux -->
        <div class="flex flex-col items-end pt-6 border-t border-gray-100 space-y-3">
             <div class="flex items-center justify-between w-64 text-sm text-gray-600">
                <span>Total HT</span>
                <span class="font-medium text-gray-900">{{ totalHT.toFixed(2) }} MAD</span>
             </div>

             <div class="flex items-center justify-between w-64 text-sm text-gray-600 pt-3 border-t">
                <span class="flex items-center gap-2">Remise Supplémentaire (MAD)</span>
                <input v-model.number="form.discount_amount" type="number" step="0.01" min="0" class="w-24 rounded-md border-0 py-1 text-right text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm px-2">
             </div>

             <div class="flex items-center justify-between w-64 text-lg font-bold text-gray-900 py-3 border-t border-gray-900">
                <span>Total TTC</span>
                <span class="text-brand-600">{{ totalTTC.toFixed(2) }} MAD</span>
             </div>
        </div>

      </div>

      <div class="flex items-center justify-between border-t border-gray-900/10 px-4 py-4 sm:px-8 bg-gray-50">
        <div>
            <button v-if="isEditing" @click="deleteQuote" type="button" :disabled="isSubmitting" class="text-sm rounded-md font-semibold text-red-600 hover:text-red-800 focus:outline-none flex items-center gap-1 disabled:opacity-50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                Supprimer le devis
            </button>
        </div>
        <button type="submit" :disabled="isSubmitting || lines.length === 0" class="rounded-md bg-brand-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:opacity-50">
          {{ isSubmitting ? 'Chargement...' : (isEditing ? 'Enregistrer Modification' : 'Générer Devis') }}
        </button>
      </div>
    </form>
  </div>
</template>
```
