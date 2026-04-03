<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'
import { useConfig } from '@/composables/useConfig'

const { getImageUrl: getStorageUrl } = useConfig()

interface Product {
  id: number;
  name: string;
  category: string;
  brand: string | null;
  public_price: string | number;
  cost_price?: string | null;
  is_medical: boolean;
  image_path: string | null;
  supplier?: {
    id: number;
    name: string;
  };
  variants: {
    id: number;
    sku: string;
    name_variation: string;
    cost_price: string | number;
    public_price: string | number;
  }[];
}

const products = ref<Product[]>([])
const isLoading = ref(true)
const error = ref('')

const fetchProducts = async () => {
    try {
        const response = await api.get('/products')
        products.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement du catalogue.'
    } finally {
        isLoading.value = false
    }
}

const getImageUrl = (path: string | null) => {
    if (!path) return 'https://via.placeholder.com/150?text=Pas+d%27image'
    return getStorageUrl(path)
}

const deleteProduct = async (id: number) => {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.')) return
    
    try {
        await api.delete(`/products/${id}`)
        products.value = products.value.filter(p => p.id !== id)
    } catch (e: any) {
        alert(e.response?.data?.message || 'Erreur lors de la suppression du produit.')
    }
}

onMounted(() => {
    fetchProducts()
})
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Catalogue Produits</h1>
        <p class="mt-2 text-sm text-gray-700">Gérez les équipements médicaux, les prix publics et les variantes (SKU).</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex items-center gap-3">
        <router-link :to="{ name: 'catalog.create' }" class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:w-auto">
          Nouveau Produit
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

    <!-- Product Table -->
    <div v-else class="mt-8 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Image</th>
                  <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">Produit & Catégorie</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Fournisseur & Marque</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Prix Achat</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Prix Public</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Variantes (SKU)</th>
                  <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                    <span class="sr-only">Modifier</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="product in products" :key="product.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                    <img :src="getImageUrl(product.image_path)" alt="Product Image" class="h-10 w-10 rounded-md object-cover border border-gray-200" />
                  </td>
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm">
                    <div class="font-medium text-gray-900">{{ product.name }}</div>
                    <div class="text-xs text-gray-500 mt-1 uppercase">{{ product.category }}</div>
                    <div class="mt-1">
                        <span v-if="product.is_medical" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-red-50 text-red-700 border border-red-100">Médical CE</span>
                        <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-50 text-gray-600 border border-gray-200">Standard</span>
                    </div>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    <div v-if="product.supplier" class="font-medium text-gray-800">{{ product.supplier.name }}</div>
                    <div v-if="product.brand" class="text-xs text-brand-600 font-bold mt-1 uppercase tracking-tighter">Marque: {{ product.brand }}</div>
                    <div v-if="!product.supplier && !product.brand" class="text-xs text-gray-400 italic">Non spécifié</div>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900">
                    <span v-if="product.cost_price && Number(product.cost_price) > 0">
                        {{ Number(product.cost_price).toFixed(2) }} MAD
                    </span>
                    <span v-else-if="product.variants.length > 0 && product.variants[0]?.cost_price">
                        {{ Number(product.variants[0].cost_price).toFixed(2) }} MAD
                    </span>
                    <span v-else class="text-gray-400">-</span>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-blue-700">
                    <span v-if="product.public_price && Number(product.public_price) > 0">
                        {{ Number(product.public_price).toFixed(2) }} MAD
                    </span>
                    <span v-else-if="product.variants.length > 0 && product.variants[0]?.public_price">
                        {{ Number(product.variants[0].public_price).toFixed(2) }} MAD
                    </span>
                    <span v-else class="text-gray-400">-</span>
                  </td>
                  <td class="px-3 py-4 text-sm text-gray-500">
                    <div class="flex flex-col gap-1">
                      <div v-for="variant in product.variants" :key="variant.id" class="flex items-center justify-between group rounded border border-gray-100 bg-gray-50 px-2 py-1.5 hover:bg-white hover:shadow-sm transition-all">
                        <div class="flex flex-col">
                            <span class="text-[10px] font-bold text-gray-400 uppercase leading-none">{{ variant.sku }}</span>
                            <span class="text-sm font-semibold text-gray-700">{{ variant.name_variation || 'Standard' }}</span>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-medium text-blue-600 bg-blue-50 px-1 rounded" v-if="variant.public_price">{{ Number(variant.public_price).toFixed(0) }} MAD</span>
                            <router-link :to="{ name: 'purchases.create', query: { variant_id: variant.id } }" title="Commander" class="p-1 rounded bg-white text-gray-400 hover:text-brand-600 hover:bg-brand-50 transition-colors shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </router-link>
                        </div>
                      </div>
                      <span v-if="!product.variants.length" class="text-gray-400 italic text-xs">Aucune variante</span>
                    </div>
                  </td>

                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                    <div class="flex items-center justify-end gap-3">
                        <router-link :to="{ name: 'catalog.edit', params: { id: product.id } }" class="text-brand-600 hover:text-brand-900 font-bold">Modifier</router-link>
                        <button @click="deleteProduct(product.id)" class="text-red-400 hover:text-red-600 transition-colors" title="Supprimer le produit">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="products.length === 0">
                    <td colspan="6" class="py-8 text-center text-gray-500">Aucun produit trouvé dans le catalogue.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
