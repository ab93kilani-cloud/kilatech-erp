<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/plugins/axios'

const router = useRouter()
const route = useRoute()

const isEditing = computed(() => !!route.params.id)
const productId = computed(() => route.params.id as string)

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

const product = ref({
    reference: '',
    name: '',
    slug: '',
    category: '', 
    brand: '',
    description: '',
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
    social_linkedin_post: '',
    social_facebook_post: '',
    local_geo_context: '',
    is_medical: false,
    unit: 'U',
    is_showcase: false,
})

const variants = ref<ConfigVariant[]>([{ 
    sku: '', name_variation: '', cost_price: null, price_ht: null, tva_rate: 20, public_price: null, margin_percent: 0
}])

const suppliers = ref<any[]>([])
const selectedSupplierIds = ref<number[]>([])

const categories = ref<any[]>([])
const brands = ref<string[]>([])
const autoReferenceDisplay = ref('Chargement...')
const isCategoryModalOpen = ref(false)
const newCategoryName = ref('')
const isCreatingCategory = ref(false)
const imageFiles = ref<File[]>([])
const deletedVariantIds = ref<number[]>([])
const isSubmitting = ref(false)
const errorMsg = ref('')

const fetchSuppliers = async () => {
    try {
        const res = await api.get('/suppliers');
        suppliers.value = res.data;
    } catch (e) { console.error('Failed to load suppliers'); }
}

const fetchCategories = async () => {
    try {
        const res = await api.get('/categories');
        categories.value = res.data;
    } catch (e) { console.error('Failed to load categories'); }
}

const fetchBrands = async () => {
    try {
        const res = await api.get('/products');
        const uniqueBrands = new Set(res.data.map((p: any) => p.brand).filter(Boolean));
        brands.value = Array.from(uniqueBrands) as string[];
    } catch (e) { console.error('Failed to load brands'); }
}

const createCategory = async () => {
    if (!newCategoryName.value) return;
    isCreatingCategory.value = true;
    try {
        const res = await api.post('/categories', { name: newCategoryName.value });
        categories.value.push(res.data);
        categories.value.sort((a, b) => a.name.localeCompare(b.name));
        product.value.category = res.data.name;
        isCategoryModalOpen.value = false;
        newCategoryName.value = '';
    } catch (e: any) {
        alert(e.response?.data?.message || 'Erreur lors de la création de la catégorie.');
    } finally { isCreatingCategory.value = false; }
}

const updateTTC = (variant: any) => {
    if (variant.price_ht !== null) {
        variant.public_price = parseFloat((variant.price_ht * (1 + variant.tva_rate / 100)).toFixed(2));
    }
    if (variant.cost_price && variant.price_ht && variant.cost_price > 0) {
        const profit = variant.price_ht - variant.cost_price;
        variant.margin_percent = parseFloat(((profit / variant.cost_price) * 100).toFixed(2));
    } else {
        variant.margin_percent = 0;
    }
}

const fetchProductData = async () => {
    try {
        const res = await api.get(`/products/${productId.value}`)
        const p = res.data;
        
        product.value = {
            reference: p.reference || '',
            name: p.name || '',
            category: p.category || '',
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
            is_showcase: p.is_showcase === 1 || p.is_showcase === true,
            unit: p.unit || 'U',
        }

        // Charger les multiples fournisseurs
        if (p.variants && p.variants[0] && p.variants[0].suppliers) {
            selectedSupplierIds.value = p.variants[0].suppliers.map((s: any) => s.supplier_id)
        } else if (p.supplier_id) {
            selectedSupplierIds.value = [p.supplier_id]
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
    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || 'Erreur lors du chargement du produit.'
    }
}

const fetchAutoReference = async () => {
    try {
        const res = await api.get('/products/next-reference')
        autoReferenceDisplay.value = res.data.reference + ' (Automatique)'
    } catch (e) { autoReferenceDisplay.value = 'Auto-Générée' }
}

onMounted(() => {
    fetchSuppliers();
    fetchCategories();
    fetchBrands();
    if (isEditing.value) { fetchProductData(); } else { fetchAutoReference(); }
})

const handleImageUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files) { imageFiles.value = Array.from(target.files); }
}

const addVariantRow = () => {
    variants.value.push({ 
        sku: '', name_variation: '', cost_price: null, price_ht: null, tva_rate: 20, public_price: null, margin_percent: 0 
    })
}

const addSupplier = (event: Event) => {
    const id = Number((event.target as HTMLSelectElement).value);
    if (id && !selectedSupplierIds.value.includes(id)) {
        selectedSupplierIds.value.push(id);
    }
    (event.target as HTMLSelectElement).value = '';
}

const removeVariantRow = (index: number) => {
    if (variants.value.length > 1) {
        const varToRemove = variants.value[index]
        if (varToRemove?.id) { deletedVariantIds.value.push(varToRemove.id) }
        variants.value.splice(index, 1)
    }
}

const saveProduct = async () => {
    isSubmitting.value = true
    errorMsg.value = ''
    try {
        const formData = new FormData();
        Object.entries(product.value).forEach(([key, value]) => {
            // On ignore les clés image/images ici car elles sont gérées séparément plus bas pour les fichiers
            if (key !== 'image' && key !== 'images' && value !== null && value !== undefined) {
                formData.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value.toString());
            }
        });

        // Sync suppliers to variants (product level management but saved per variant for system compatibility)
        const variantsToSave = variants.value.map(v => ({
            ...v,
            suppliers: selectedSupplierIds.value.map(id => ({ supplier_id: id }))
        }));
        
        formData.append('variants', JSON.stringify(variantsToSave));
        formData.append('deleted_variant_ids', JSON.stringify(deletedVariantIds.value));
        
        if (imageFiles.value.length > 0) {
            imageFiles.value.forEach((file, i) => {
                formData.append(i === 0 ? 'image' : 'images[]', file);
            });
        }

        if (isEditing.value && productId.value) {
            await api.post(`/products/${productId.value}?_method=PUT`, formData);
        } else {
            await api.post('/products', formData);
        }

        router.push({ name: 'catalog' })
    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || 'Erreur lors de la sauvegarde.'
    } finally { isSubmitting.value = false }
}
</script>

<template>
  <div class="max-w-4xl mx-auto py-8">
    <div class="mb-8 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">{{ isEditing ? 'Modifier Produit' : 'Nouveau Produit' }}</h1>
      <router-link :to="{ name: 'catalog' }" class="text-sm font-bold text-gray-600 border px-4 py-2 rounded-lg bg-white shadow-sm hover:bg-gray-50">Annuler</router-link>
    </div>

    <form @submit.prevent="saveProduct" class="bg-white shadow-sm ring-1 ring-gray-200 sm:rounded-2xl overflow-hidden">
      <div class="px-6 py-8 space-y-10">
        
        <div v-if="errorMsg" class="bg-red-50 border-l-4 border-red-500 p-4 rounded text-red-700 text-sm font-bold">{{ errorMsg }}</div>

        <!-- Section Haut : Informations Générales -->
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6 border-b pb-10 border-gray-100">
            <div class="sm:col-span-2">
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Référence</label>
                <input v-model="product.reference" type="text" :placeholder="autoReferenceDisplay" class="block w-full rounded-xl border-gray-200 py-2.5 px-4 text-sm font-bold focus:ring-brand-500 focus:border-brand-500 shadow-sm">
            </div>

            <div class="sm:col-span-4">
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Nom du Produit *</label>
                <input v-model="product.name" required type="text" class="block w-full rounded-xl border-gray-200 py-2.5 px-4 text-sm font-bold focus:ring-brand-500 focus:border-brand-500 shadow-sm">
            </div>

            <div class="sm:col-span-3">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest">Famille / Catégorie *</label>
                    <button @click="isCategoryModalOpen = true" type="button" class="text-[10px] text-brand-600 font-black hover:underline">+ NOUVELLE</button>
                </div>
                <select v-model="product.category" required class="block w-full rounded-xl border-gray-200 py-2.5 px-4 text-sm font-bold focus:ring-brand-500 shadow-sm">
                    <option value="" disabled>-- Catégorie --</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
                </select>
            </div>

            <!-- Multi-fournisseurs ICI -->
            <div class="sm:col-span-3">
                <label class="block text-xs font-black text-brand-600 uppercase tracking-widest mb-2 leading-tight">Fournisseurs autorisés (Multiples)</label>
                <div class="space-y-2">
                    <div class="flex flex-wrap gap-2 min-h-[44px] p-2 bg-gray-50 rounded-xl border border-gray-100">
                        <span v-for="id in selectedSupplierIds" :key="id" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white text-gray-700 text-[10px] font-black border border-gray-200 shadow-sm">
                            {{ suppliers.find(s => s.id === id)?.name }}
                            <button @click="selectedSupplierIds = selectedSupplierIds.filter(sid => sid !== id)" type="button" class="text-red-400 hover:text-red-600">×</button>
                        </span>
                        <p v-if="selectedSupplierIds.length === 0" class="text-[10px] text-gray-400 italic py-1.5">Aucun fournisseur choisi...</p>
                    </div>
                    <select @change="addSupplier" class="block w-full rounded-xl border-gray-200 py-2.5 text-xs font-bold focus:ring-brand-500 shadow-sm">
                        <option value="">+ Ajouter un fournisseur à la liste...</option>
                        <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">{{ sup.name }}</option>
                    </select>
                </div>
            </div>

            <div class="sm:col-span-1">
                <label class="block text-xs font-black text-gray-400 uppercase mb-2">Unité</label>
                <select v-model="product.unit" class="block w-full rounded-xl border-gray-200 py-2.5 text-sm font-bold">
                    <option value="U">U</option>
                    <option value="Boite">Boite</option>
                    <option value="Lot">Lot</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">Marque</label>
                <input v-model="product.brand" list="brands-list" type="text" class="block w-full rounded-xl border-gray-200 py-2.5 px-4 text-sm font-bold shadow-sm">
                <datalist id="brands-list"><option v-for="b in brands" :key="b" :value="b">{{ b }}</option></datalist>
            </div>

            <div class="col-span-full">
                <label class="block text-xs font-black text-gray-400 uppercase mb-2">Images (JPG, PNG, WEBP)</label>
                <input type="file" multiple accept="image/*" @change="handleImageUpload" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 border p-1 rounded-xl">
            </div>
        </div>

        <!-- Section Bas : Variantes Simples -->
        <div>
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-black text-gray-900">Variantes & Modèles (Flat List)</h3>
                <button @click="addVariantRow" type="button" class="text-sm text-brand-600 font-black hover:text-brand-800">+ Ajouter Variante</button>
            </div>

            <div class="space-y-4">
                <div v-for="(variant, index) in variants" :key="index" class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end bg-gray-50/50 p-5 rounded-2xl border border-gray-100 shadow-sm transition-all hover:bg-white hover:shadow-md">
                    <div class="sm:col-span-3">
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Désignation Variation</label>
                        <input v-model="variant.name_variation" type="text" placeholder="Taille / Standard" class="block w-full rounded-xl border-gray-200 py-2 px-3 text-sm font-bold">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Achat HT</label>
                        <input v-model="variant.cost_price" @input="updateTTC(variant)" type="number" step="0.01" class="block w-full rounded-xl border-gray-200 py-2 px-3 text-sm font-bold text-center">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Vente HT</label>
                        <input v-model="variant.price_ht" @input="updateTTC(variant)" type="number" step="0.01" class="block w-full rounded-xl border-gray-200 py-2 px-3 text-sm font-bold text-center">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[10px] font-black text-brand-600 uppercase mb-1">Prix TTC</label>
                        <input v-model="variant.public_price" type="number" step="0.01" class="block w-full rounded-xl border-brand-200 bg-brand-50 py-2 text-sm font-black text-center text-brand-900">
                    </div>
                    <div class="sm:col-span-2 text-center">
                        <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Marge</label>
                        <p class="py-2 text-sm font-black" :class="variant.margin_percent >= 0 ? 'text-green-600' : 'text-red-600'">{{ variant.margin_percent }}%</p>
                    </div>
                    <div class="sm:col-span-1 flex justify-center pb-2">
                        <button v-if="variants.length > 1" @click="removeVariantRow(index)" type="button" class="text-red-400 hover:text-red-600">×</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-x-3 bg-amber-50 p-4 rounded-2xl border border-amber-100">
            <input v-model="product.is_showcase" id="sw" type="checkbox" class="h-5 w-5 rounded-lg border-amber-300 text-amber-600">
            <label for="sw" class="text-sm font-black text-amber-900 uppercase">🌟 Ajouter à la vitrine publique</label>
        </div>
      </div>

      <div class="flex items-center justify-end border-t border-gray-100 px-8 py-6 bg-gray-50 gap-4">
        <button type="submit" :disabled="isSubmitting" class="rounded-xl bg-brand-600 px-10 py-3 text-sm font-black text-white shadow-lg hover:bg-brand-700 disabled:opacity-50">
          {{ isSubmitting ? 'EN COURS...' : (isEditing ? 'ENREGISTRER LES MODIFICATIONS' : 'CRÉER LE PRODUIT') }}
        </button>
      </div>

      <!-- Modal Nouvelle Catégorie Maya -->
      <div v-if="isCategoryModalOpen" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
          <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="isCategoryModalOpen = false"></div>
          <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-sm sm:p-6">
            <div>
              <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Créer une Famille Maya</h3>
              <input v-model="newCategoryName" type="text" placeholder="Nom de la catégorie..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
            </div>
            <div class="mt-5 sm:mt-6 flex gap-3">
              <button @click="createCategory" :disabled="isCreatingCategory" class="inline-flex w-full justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-brand-700 sm:text-sm">
                {{ isCreatingCategory ? '...' : 'Ajouter' }}
              </button>
              <button @click="isCategoryModalOpen = false" class="inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:text-sm">
                Annuler
              </button>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>
