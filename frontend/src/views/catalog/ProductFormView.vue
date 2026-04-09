<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
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
    unit: 'U',
    is_showcase: false,
})

const autoReferenceDisplay = ref('Chargement...')

const categories = ref<any[]>([])
const isCategoryModalOpen = ref(false)
const newCategoryName = ref('')
const isCreatingCategory = ref(false)

const suppliers = ref<any[]>([])
const supplierSearchQuery = ref('')
const filteredSuppliers = computed(() => {
    if (!supplierSearchQuery.value) return suppliers.value
    return suppliers.value.filter(s => s.name.toLowerCase().includes(supplierSearchQuery.value.toLowerCase()))
})

const brands = ref<string[]>([]) // List of unique brands from existing products

const imageFiles = ref<File[]>([])

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
    suppliers?: {
        supplier_id: number;
        supplier_ref: string;
        purchase_price: number;
    }[];
}

const variants = ref<ConfigVariant[]>([{ 
    sku: '', name_variation: '', cost_price: null, price_ht: null, tva_rate: 20, public_price: null, margin_percent: 0, suppliers: []
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

const fetchCategories = async () => {
    try {
        const res = await api.get('/categories');
        categories.value = res.data;
    } catch (e) {
        console.error('Failed to load categories');
    }
}

const fetchBrands = async () => {
    try {
        const res = await api.get('/products'); // We fetch all products to extract unique brands
        const uniqueBrands = new Set(res.data.map((p: any) => p.brand).filter(Boolean));
        brands.value = Array.from(uniqueBrands) as string[];
    } catch (e) {
        console.error('Failed to load brands');
    }
}

const createCategory = async () => {
    if (!newCategoryName.value) return;
    isCreatingCategory.value = true;
    try {
        const res = await api.post('/categories', { name: newCategoryName.value });
        categories.value.push(res.data);
        categories.value.sort((a, b) => a.name.localeCompare(b.name));
        product.value.category = res.data.name; // On sélectionne la nouvelle catégorie Maya.
        isCategoryModalOpen.value = false;
        newCategoryName.value = '';
    } catch (e: any) {
        alert(e.response?.data?.message || 'Erreur lors de la création de la catégorie.');
    } finally {
        isCreatingCategory.value = false;
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
            is_showcase: p.is_showcase === 1 || p.is_showcase === true,
            unit: p.unit || 'U',
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
                margin_percent: v.margin_percent || 0,
                suppliers: v.suppliers ? v.suppliers.map((ps: any) => ({
                    supplier_id: ps.supplier_id,
                    supplier_ref: ps.supplier_ref || '',
                    purchase_price: ps.purchase_price ? Number(ps.purchase_price) : 0
                })) : []
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
    } catch (e) {
        autoReferenceDisplay.value = 'Auto-Générée'
    }
}

// Auto-sélection si un seul résultat Maya.
watch(filteredSuppliers, (newVal) => {
    if (newVal.length === 1 && supplierSearchQuery.value !== '') {
        product.value.supplier_id = newVal[0].id
    }
})

onMounted(() => {
    fetchSuppliers();
    fetchCategories();
    fetchBrands();
    if (isEditing.value) {
        fetchProductData();
    } else {
        fetchAutoReference();
    }
})

const handleImageUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        imageFiles.value = Array.from(target.files);
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
        margin_percent: 0,
        suppliers: []
    })
}

const addSupplierToVariant = (variant: ConfigVariant) => {
    if (!variant.suppliers) variant.suppliers = [];
    variant.suppliers.push({
        supplier_id: 0,
        supplier_ref: '',
        purchase_price: variant.cost_price || 0
    });
}

const removeSupplierFromVariant = (variant: ConfigVariant, index: number) => {
    variant.suppliers?.splice(index, 1);
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
        formData.append('is_showcase', product.value.is_showcase ? '1' : '0');
        formData.append('unit', product.value.unit || 'U');

        // Le backend génère le SKU automatiquement si vide. On envoie toutes les variantes.
        const variantsToSave = variants.value;
        formData.append('variants', JSON.stringify(variantsToSave));
        formData.append('deleted_variant_ids', JSON.stringify(deletedVariantIds.value));

        if (imageFiles.value.length > 0) {
            // First image as main image
            formData.append('image', imageFiles.value[0] as Blob);
            // Rest as additional images
            for (let i = 1; i < imageFiles.value.length; i++) {
                formData.append('images[]', imageFiles.value[i] as Blob);
            }
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

                <div class="sm:col-span-2">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium leading-6 text-gray-900">Famille (Catégorie) *</label>
                        <button @click="isCategoryModalOpen = true" type="button" class="text-xs text-brand-600 font-bold hover:underline">+ Nouvelle Catégorie</button>
                    </div>
                    <div>
                        <select v-model="product.category" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                            <option value="" disabled>-- Sélectionner une catégorie --</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium leading-6 text-gray-900">Unité</label>
                    <div class="mt-2">
                        <select v-model="product.unit" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                            <option value="U">U (Unité)</option>
                            <option value="Boite">Boite</option>
                            <option value="Lot">Lot</option>
                            <option value="Kg">Kg</option>
                            <option value="M">Mètre</option>
                            <option value="L">Litre</option>
                        </select>
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium leading-6 text-gray-900">Fournisseur (Chercher & Sélectionner)</label>
                    <div class="mt-2 space-y-2">
                        <input v-model="supplierSearchQuery" type="text" placeholder="Taper le nom du fournisseur..." class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-1 focus:ring-inset focus:ring-brand-500 sm:text-xs px-2">
                        <select v-model="product.supplier_id" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                            <option value="">-- Sélectionner dans la liste filtrée --</option>
                            <option v-for="sup in filteredSuppliers" :key="sup.id" :value="sup.id">{{ sup.name }}</option>
                            <option v-if="filteredSuppliers.length === 0" disabled>Aucun résultat pour "{{ supplierSearchQuery }}"</option>
                        </select>
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium leading-6 text-gray-900">Marque Fabriquante (Proposer)</label>
                    <div class="mt-2">
                        <input v-model="product.brand" list="brands-list" type="text" placeholder="Ex: Siemens, Philips, etc." class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                        <datalist id="brands-list">
                            <option v-for="b in brands" :key="b" :value="b">{{ b }}</option>
                        </datalist>
                    </div>
                </div>

                <div class="col-span-full">
                    <label class="block text-sm font-medium leading-6 text-gray-900">Description</label>
                    <div class="mt-2">
                        <textarea v-model="product.description" rows="3" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3"></textarea>
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium leading-6 text-gray-900">Images du Produit (JPG, PNG, WEBP)</label>
                    <div class="mt-2 text-sm text-gray-600">
                      Vous pouvez sélectionner plusieurs photos en même temps (Ctrl + Clic). La première sera l'image principale.
                    </div>
                    <div class="mt-2">
                        <input type="file" multiple accept="image/*" @change="handleImageUpload" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                    </div>
                    <div v-if="imageFiles.length > 0" class="mt-2 flex gap-2 flex-wrap text-xs text-brand-600 font-medium">
                        <span v-for="(file, i) in imageFiles" :key="file.name" class="bg-brand-50 px-2 py-1 rounded">
                            {{ i === 0 ? 'Principale: ' : 'Extra: ' }}{{ file.name }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Sera automatiquement redimensionnée.</p>
                </div>

                <div class="sm:col-span-6 flex flex-col gap-4 mt-4">
                    <div class="flex items-center gap-x-3">
                        <input v-model="product.is_medical" id="secure" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-600">
                        <label for="secure" class="block text-sm font-medium leading-6 text-gray-900">Matériel Médical (Norme CE / Validation requise)</label>
                    </div>
                    <div class="flex items-center gap-x-3 bg-amber-50 p-3 rounded-lg border border-amber-100">
                        <input v-model="product.is_showcase" id="showcase" type="checkbox" class="h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-600">
                        <label for="showcase" class="block text-sm font-bold leading-6 text-amber-900 border-l-2 border-amber-400 pl-2">🌟 AJOUTER À LA VITRINE (FEATURED PRODUCT)</label>
                        <p class="text-[10px] text-amber-600 italic">Ce produit sera visible sur votre site public et mis en avant.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Variantes (SKU) & Fournisseurs Multiples -->
        <div class="space-y-6">
            <div class="flex items-center justify-between mb-4 border-b pb-4">
                <div>
                   <h3 class="text-xl font-bold leading-6 text-gray-900">Variantes & Multi-Fournisseurs</h3>
                   <p class="text-xs text-gray-500">Gérez vos modèles et liez plusieurs sources d'approvisionnement.</p>
                </div>
                <button @click="addVariantRow" type="button" class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-4 py-2 text-sm font-bold text-brand-700 hover:bg-brand-100 transition-all border border-brand-200">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Ajouter Variante
                </button>
            </div>

            <div class="space-y-6 mt-4">
                <div v-for="(variant, index) in variants" :key="index" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <!-- Variant Main Row -->
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-4 items-end bg-gray-50/50">
                        <div class="sm:col-span-3">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Désignation Variation</label>
                            <input v-model="variant.name_variation" type="text" placeholder="Taille/Couleur/Standard" class="block w-full rounded-lg border-gray-200 py-2 px-3 text-sm font-semibold focus:ring-brand-500 focus:border-brand-500 shadow-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Prix Achat Base</label>
                            <input v-model="variant.cost_price" @input="updateTTC(variant)" type="number" step="0.01" class="block w-full rounded-lg border-gray-200 py-2 px-3 text-sm text-center focus:ring-brand-500">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Vente HT</label>
                            <input v-model="variant.price_ht" @input="updateTTC(variant)" type="number" step="0.01" class="block w-full rounded-lg border-gray-200 py-2 px-3 text-sm text-center focus:ring-brand-500">
                        </div>
                        <div class="sm:col-span-2">
                             <label class="block text-[10px] font-bold text-brand-600 uppercase mb-1">Prix TTC</label>
                             <div class="relative">
                                <input v-model="variant.public_price" type="number" step="0.01" class="block w-full rounded-lg border-brand-200 bg-brand-50/50 py-2 text-sm text-center font-black text-brand-900">
                             </div>
                        </div>
                        <div class="sm:col-span-1 text-center">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Marge</label>
                            <span :class="variant.margin_percent >= 0 ? 'text-green-600' : 'text-red-600'" class="text-xs font-black">{{ variant.margin_percent }}%</span>
                        </div>
                        <div class="sm:col-span-2 flex justify-end pb-1 gap-2">
                            <button @click="addSupplierToVariant(variant)" type="button" class="p-2 text-brand-600 hover:bg-brand-50 rounded-lg border border-brand-100 transition-colors" title="Lier un fournisseur">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011-1v5m-4 0h4" /></svg>
                            </button>
                            <button v-if="variants.length > 1" @click="removeVariantRow(index)" type="button" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg border border-red-100 transition-colors">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Alternate Suppliers Sub-Section -->
                    <div v-if="variant.suppliers && variant.suppliers.length > 0" class="p-4 bg-white border-t border-gray-50">
                        <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            Fournisseurs Alternatifs & Prix Négociés
                        </h4>
                        <div class="space-y-2">
                            <div v-for="(ps, psIdx) in variant.suppliers" :key="psIdx" class="grid grid-cols-12 gap-3 items-center">
                                <div class="col-span-4">
                                    <select v-model="ps.supplier_id" class="block w-full rounded-lg border-gray-100 py-1.5 px-3 text-xs bg-gray-50 focus:bg-white transition-all border">
                                        <option value="0" disabled>Sélectionner fournisseur...</option>
                                        <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">{{ sup.name }}</option>
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <input v-model="ps.supplier_ref" type="text" placeholder="Ref. Fournisseur" class="block w-full rounded-lg border-gray-100 py-1.5 px-3 text-xs border">
                                </div>
                                <div class="col-span-3">
                                    <div class="relative">
                                        <input v-model.number="ps.purchase_price" type="number" step="0.01" class="block w-full rounded-lg border-gray-100 py-1.5 pl-3 pr-8 text-xs font-bold text-brand-700 border">
                                        <div class="absolute inset-y-0 right-2 flex items-center text-[8px] font-bold text-gray-400">DH</div>
                                    </div>
                                </div>
                                <div class="col-span-2 flex justify-end">
                                    <button @click="removeSupplierFromVariant(variant, psIdx)" type="button" class="text-red-300 hover:text-red-500 transition-colors">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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

      <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8 bg-gray-50">
        <button type="submit" :disabled="isSubmitting" class="rounded-md bg-brand-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:opacity-50">
          {{ isSubmitting ? 'Sauvegarde...' : (isEditing ? 'Enregistrer Modifications' : 'Créer le Produit (API)') }}
        </button>
      </div>
    </form>
  </div>
</template>
