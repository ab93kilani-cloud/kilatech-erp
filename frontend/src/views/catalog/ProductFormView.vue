<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/plugins/axios'

const router = useRouter()
const route = useRoute()

const isEditing = computed(() => !!route.params.id)
const productId = computed(() => route.params.id as string)

const product = ref({
    reference: '',
    name: '',
    slug: '',
    category: '', // famille
    supplier_id: '',
    brand: '',
    description: '',
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
    social_linkedin_post: '',
    social_facebook_post: '',
    local_geo_context: '',
    is_medical: false,
})

const autoReferenceDisplay = ref('Chargement...')

const suppliers = ref<any[]>([])

const imageFile = ref<File | null>(null)

// Dynamic form for Variants
interface ConfigVariant {
    id?: number;
    sku: string;
    name_variation: string;
    cost_price: number | null;
    price_ht: number | null;
    tva_rate: number;
    public_price: number | null;
    margin_percent: number;
}

const variants = ref<ConfigVariant[]>([{ 
    sku: '', name_variation: '', cost_price: null, price_ht: null, tva_rate: 20, public_price: null, margin_percent: 0
}])

const deletedVariantIds = ref<number[]>([])

const isSubmitting = ref(false)
const errorMsg = ref('')

const fetchSuppliers = async () => {
    try {
        const res = await api.get('/suppliers');
        suppliers.value = res.data;
    } catch (e) {
        console.error('Failed to load suppliers');
    }
}

const calculateRowMargin = (variant: any) => {
    if (variant.cost_price && variant.price_ht && variant.cost_price > 0) {
        const profit = variant.price_ht - variant.cost_price;
        variant.margin_percent = parseFloat(((profit / variant.cost_price) * 100).toFixed(2));
    } else {
        variant.margin_percent = 0;
    }
}

const updateTTC = (variant: any) => {
    if (variant.price_ht !== null) {
        variant.public_price = parseFloat((variant.price_ht * (1 + variant.tva_rate / 100)).toFixed(2));
    }
    calculateRowMargin(variant);
}

const fetchProductData = async () => {
    try {
        const res = await api.get(`/products/${productId.value}`)
        const p = res.data;
        product.value = {
            reference: p.reference || '',
            name: p.name || '',
            category: p.category || '',
            supplier_id: p.supplier_id || '',
            brand: p.brand || '',
            description: p.description || '',
            slug: p.slug || '',
            meta_title: p.meta_title || '',
            meta_description: p.meta_description || '',
            meta_keywords: p.meta_keywords || '',
            social_linkedin_post: p.social_linkedin_post || '',
            social_facebook_post: p.social_facebook_post || '',
            local_geo_context: p.local_geo_context || '',
            is_medical: p.is_medical === 1 || p.is_medical === true,
        }
        
        if (p.variants && p.variants.length > 0) {
            variants.value = p.variants.map((v: any) => ({
                id: v.id,
                sku: v.sku,
                name_variation: v.name_variation || '',
                cost_price: v.cost_price ? Number(v.cost_price) : null,
                price_ht: v.price_ht ? Number(v.price_ht) : null,
                tva_rate: v.tva_rate || 20,
                public_price: v.public_price ? Number(v.public_price) : null,
                margin_percent: v.margin_percent || 0
            }))
        }
    } catch (e) {
        errorMsg.value = 'Erreur lors du chargement du produit.'
    }
}

const fetchAutoReference = async () => {
    try {
        const res = await api.get('/products/next-reference')
        autoReferenceDisplay.value = res.data.reference + ' (Automatique)'
    } catch (e) {
        autoReferenceDisplay.value = 'Auto-Générée'
    }
}

onMounted(() => {
    fetchSuppliers();
    if (isEditing.value) {
        fetchProductData();
    } else {
        fetchAutoReference();
    }
})

const handleImageUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        imageFile.value = target.files[0] || null;
    }
}

const addVariantRow = () => {
    variants.value.push({ 
        sku: '', 
        name_variation: '', 
        cost_price: null, 
        price_ht: null,
        tva_rate: 20,
        public_price: null,
        margin_percent: 0
    })
}

const removeVariantRow = (index: number) => {
    if (variants.value.length > 1) {
        const varToRemove = variants.value[index]
        if (varToRemove && varToRemove.id) {
            deletedVariantIds.value.push(varToRemove.id)
        }
        variants.value.splice(index, 1)
    }
}

const saveProduct = async () => {
    isSubmitting.value = true
    errorMsg.value = ''

    try {
        const formData = new FormData();
        if (product.value.reference) formData.append('reference', product.value.reference);
        if (product.value.supplier_id) formData.append('supplier_id', product.value.supplier_id.toString());
        if (product.value.brand) formData.append('brand', product.value.brand);
        formData.append('name', product.value.name);
        formData.append('category', product.value.category);
        formData.append('description', product.value.description);
        if (product.value.slug) formData.append('slug', product.value.slug);
        if (product.value.meta_title) formData.append('meta_title', product.value.meta_title);
        if (product.value.meta_description) formData.append('meta_description', product.value.meta_description);
        if (product.value.meta_keywords) formData.append('meta_keywords', product.value.meta_keywords);
        if (product.value.social_linkedin_post) formData.append('social_linkedin_post', product.value.social_linkedin_post);
        if (product.value.social_facebook_post) formData.append('social_facebook_post', product.value.social_facebook_post);
        if (product.value.local_geo_context) formData.append('local_geo_context', product.value.local_geo_context);
        formData.append('is_medical', product.value.is_medical ? '1' : '0');

        // Le backend génère le SKU automatiquement si vide. On envoie toutes les variantes.
        const variantsToSave = variants.value;
        formData.append('variants', JSON.stringify(variantsToSave));
        formData.append('deleted_variant_ids', JSON.stringify(deletedVariantIds.value));

        if (imageFile.value) {
            formData.append('image', imageFile.value as Blob);
        }

        await (isEditing.value 
            ? api.post(`/products/${productId.value}?_method=PUT`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
            : api.post('/products', formData, { headers: { 'Content-Type': 'multipart/form-data' } }));

        router.push({ name: 'catalog' })

    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || 'Erreur réseau.'
    } finally {
        isSubmitting.value = false
    }
}

</script>

<template>
  <div class="max-w-4xl mx-auto py-8">
    <div class="mb-8 flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-gray-900">{{ isEditing ? 'Modifier Produit' : 'Nouveau Produit' }}</h1>
      <router-link :to="{ name: 'catalog' }" class="text-sm font-medium text-gray-600 hover:text-gray-900 border px-3 py-1.5 rounded bg-white">
        Annuler
      </router-link>
    </div>

    <form @submit.prevent="saveProduct" class="bg-white shadow-sm ring-1 ring-gray-200 sm:rounded-xl">
      <div class="px-4 py-6 sm:p-8 space-y-8">
        
        <div v-if="errorMsg" class="bg-red-50 border-l-4 border-red-400 p-4">
          <p class="text-sm text-red-700">{{ errorMsg }}</p>
        </div>

        <!-- Section Informations Générales -->
        <div class="space-y-8">
            <!-- Section Produit -->
            <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 border-b pb-8 border-gray-100">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium leading-6 text-gray-900">Référence (Optionnelle)</label>
                    <div class="mt-2">
                        <input v-model="product.reference" type="text" :placeholder="isEditing ? 'Ref Interne' : autoReferenceDisplay" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                </div>

                <div class="sm:col-span-4">
                    <label class="block text-sm font-medium leading-6 text-gray-900">Nom du Produit *</label>
                    <div class="mt-2">
                        <input v-model="product.name" required type="text" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium leading-6 text-gray-900">Famille (Catégorie) *</label>
                    <div class="mt-2">
                        <select v-model="product.category" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                            <option value="" disabled>-- Sélectionner une catégorie --</option>
                            <option value="Anesthésiologie/Réanimation">Anesthésiologie/Réanimation</option>
                            <option value="Appareils de bien-être ou de fitness">Appareils de bien-être ou de fitness</option>
                            <option value="Cardiologie">Cardiologie</option>
                            <option value="Chirurgie/d'Opération">Chirurgie/d'Opération</option>
                            <option value="Cliniques mobiles">Cliniques mobiles</option>
                            <option value="Consommables/Approvisionnements">Consommables/Approvisionnements</option>
                            <option value="Cosmétologie/Dermatologie">Cosmétologie/Dermatologie</option>
                            <option value="Équipement d'urologie">Équipement d'urologie</option>
                            <option value="Équipement de Prothésiste Dentaire">Équipement de Prothésiste Dentaire</option>
                            <option value="Équipement de stérilisation">Équipement de stérilisation</option>
                            <option value="Équipement Dentaire">Équipement Dentaire</option>
                            <option value="Equipement endoscopique">Equipement endoscopique</option>
                            <option value="Équipement hospitalier">Équipement hospitalier</option>
                            <option value="Equipement Neurologique">Equipement Neurologique</option>
                            <option value="Equipement ORL">Equipement ORL</option>
                            <option value="Equipement pour Imagerie Médicale">Equipement pour Imagerie Médicale</option>
                            <option value="Equipement Veterinaire">Equipement Veterinaire</option>
                            <option value="Formation médicale">Formation médicale</option>
                            <option value="Informatique médicale, Télémédecine">Informatique médicale, Télémédecine</option>
                            <option value="Laboratoire">Laboratoire</option>
                            <option value="Logiciel médical">Logiciel médical</option>
                            <option value="Obstétrique/Gynécologie">Obstétrique/Gynécologie</option>
                            <option value="Ophtalmologie">Ophtalmologie</option>
                            <option value="Pédiatrie">Pédiatrie</option>
                            <option value="Physiothérapie">Physiothérapie</option>
                            <option value="Soins /Réhabilitation">Soins /Réhabilitation</option>
                        </select>
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium leading-6 text-gray-900">Fournisseur</label>
                    <div class="mt-2">
                        <select v-model="product.supplier_id" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                            <option value="">-- Aucun / Sélectionner --</option>
                            <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">{{ sup.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium leading-6 text-gray-900">Marque Fabriquante</label>
                    <div class="mt-2">
                        <input v-model="product.brand" type="text" placeholder="Ex: Siemens, Philips, etc." class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                </div>

                <div class="col-span-full">
                    <label class="block text-sm font-medium leading-6 text-gray-900">Description</label>
                    <div class="mt-2">
                        <textarea v-model="product.description" rows="3" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3"></textarea>
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium leading-6 text-gray-900">Image du Produit (JPG, PNG, WEBP)</label>
                    <div class="mt-2">
                        <input type="file" accept="image/*" @change="handleImageUpload" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Sera automatiquement redimensionnée et convertie en WEBP par le serveur.</p>
                </div>

                <div class="sm:col-span-6 flex items-center gap-x-3 mt-4">
                    <input v-model="product.is_medical" id="secure" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-600">
                    <label for="secure" class="block text-sm font-medium leading-6 text-gray-900">Matériel Médical (Norme CE / Validation requise)</label>
                </div>
            </div>
        </div>

        <!-- Section Variantes (SKU) -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium leading-6 text-gray-900">Variantes et Modèles (SKU)</h3>
                <button @click="addVariantRow" type="button" class="text-sm text-brand-600 font-medium hover:text-brand-900">+ Ajouter Variante</button>
            </div>

            <div class="space-y-3 mt-4">
                <div v-for="(variant, index) in variants" :key="index" class="flex flex-wrap items-end gap-3 bg-gray-50 p-4 rounded-xl border border-gray-200 shadow-sm">
                    <!-- SKU Masked (Auto-generated by Backend) -->
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Variation</label>
                        <input v-model="variant.name_variation" type="text" placeholder="Taille M / Rouge" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                    <div class="w-24">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Achat HT</label>
                        <input v-model="variant.cost_price" @input="updateTTC(variant)" type="number" step="0.01" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                    <div class="w-24">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Vente HT</label>
                        <input v-model="variant.price_ht" @input="updateTTC(variant)" type="number" step="0.01" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                    <div class="w-20">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">TVA (%)</label>
                        <select v-model="variant.tva_rate" @change="updateTTC(variant)" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-1">
                            <option value="20">20%</option>
                            <option value="10">10%</option>
                            <option value="0">0%</option>
                        </select>
                    </div>
                    <div class="w-24">
                        <label class="block text-xs font-semibold text-brand-700 mb-1">Prix TTC</label>
                        <input v-model="variant.public_price" type="number" step="0.01" class="block w-full rounded-md border-brand-300 bg-brand-50 py-1.5 font-bold text-gray-900 shadow-sm focus:ring-brand-600 sm:text-sm px-3 border">
                    </div>
                    <div class="w-20 text-center">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Marge</label>
                        <p class="py-1.5 font-bold text-sm" :class="variant.margin_percent >= 0 ? 'text-green-600' : 'text-red-600'">{{ variant.margin_percent }}%</p>
                    </div>
                    <div class="w-auto pb-1">
                        <button v-if="variants.length > 1" @click="removeVariantRow(index)" type="button" class="text-red-500 hover:text-red-700 p-1.5 bg-red-50 hover:bg-red-100 rounded shadow-sm border border-red-200 transition-colors">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

      </div>

      <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8 bg-gray-50">
        <button type="submit" :disabled="isSubmitting" class="rounded-md bg-brand-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:opacity-50">
          {{ isSubmitting ? 'Sauvegarde...' : (isEditing ? 'Enregistrer Modifications' : 'Créer le Produit (API)') }}
        </button>
      </div>
    </form>
  </div>
</template>
