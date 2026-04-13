<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { useSEO } from '@/composables/useSEO'
import { useConfig } from '@/composables/useConfig'
import { useCartStore } from '@/stores/cart'

const route = useRoute()
const router = useRouter()
const { updateSEO } = useSEO()
const { getImageUrl } = useConfig()
const cartStore = useCartStore()

interface Variant {
  id: number;
  sku: string;
  name_variation: string;
  public_price: string | number;
  tva_rate: string | number;
}

interface Product {
  id: number;
  name: string;
  slug: string;
  category: string;
  brand: string;
  description: string;
  meta_title: string;
  meta_description: string;
  image_path: string | null;
  images: string[];
  unit_name: string;
  packaging_unit: string;
  sales_multiplier: number;
  variants: Variant[];
}

const product = ref<Product | null>(null)
const isLoading = ref(true)
const selectedVariant = ref<Variant | null>(null)
const mainImage = ref<string | null>(null)

const fetchProduct = async () => {
    try {
        const slug = route.params.slug
        const response = await api.get(`/products/${slug}`)
        product.value = response.data
        
        if (product.value) {
            mainImage.value = product.value.image_path
            if (product.value.variants && product.value.variants.length > 0) {
                selectedVariant.value = product.value.variants[0]
            }
            
            // SEO Update
            updateSEO({
                title: product.value.meta_title || product.value.name,
                description: product.value.meta_description || product.value.description?.substring(0, 160)
            })

            // JSON-LD Structured Data
            injectJsonLd(product.value)
        }
    } catch (e) {
        console.error("Product not found", e)
        router.push({ name: 'storefront' })
    } finally {
        isLoading.value = false
    }
}

const injectJsonLd = (p: Product) => {
    const script = document.createElement('script')
    script.type = 'application/ld+json'
    const ld = {
        "@context": "https://schema.org/",
        "@type": "Product",
        "name": p.name,
        "image": [getImageUrl(p.image_path)],
        "description": p.description,
        "brand": { "@type": "Brand", "name": p.brand || "Kilatech" },
        "offers": {
            "@type": "Offer",
            "url": window.location.href,
            "priceCurrency": "MAD",
            "price": selectedVariant.value?.public_price || 0,
            "availability": "https://schema.org/InStock",
            "seller": { "@type": "Organization", "name": "Kilatech Maroc" }
        }
    }
    script.text = JSON.stringify(ld)
    document.head.appendChild(script)
}

const addToCart = () => {
    if (!product.value || !selectedVariant.value) return
    cartStore.addItem({
        variantId: selectedVariant.value.id,
        productId: product.value.id,
        name: product.value.name,
        variation: selectedVariant.value.name_variation,
        price: parseFloat(String(selectedVariant.value.public_price)),
        quantity: 1,
        image: product.value.image_path
    })
    // Optional: show feedback or open cart
}

onMounted(fetchProduct)
</script>

<template>
  <div class="min-h-screen bg-gray-50 pt-32 pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      
      <div v-if="isLoading" class="flex justify-center py-40">
        <div class="w-16 h-16 border-4 border-brand-200 border-t-brand-600 rounded-full animate-spin"></div>
      </div>

      <div v-else-if="product" class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-20">
        
        <!-- Gallery -->
        <div class="space-y-6">
          <div class="aspect-square bg-white rounded-3xl overflow-hidden shadow-xl border border-gray-100 group">
             <img :src="getImageUrl(mainImage)" :alt="product.name" class="w-full h-full object-contain p-8 group-hover:scale-105 transition-transform duration-500" />
          </div>
          
          <div v-if="product.images && product.images.length > 0" class="grid grid-cols-4 gap-4">
             <div 
               v-for="(img, i) in [product.image_path, ...product.images]" 
               :key="i"
               @click="mainImage = img"
               class="aspect-square bg-white rounded-xl overflow-hidden border-2 cursor-pointer transition-all p-2"
               :class="mainImage === img ? 'border-brand-600 shadow-md' : 'border-transparent hover:border-brand-200'"
             >
               <img :src="getImageUrl(img)" class="w-full h-full object-contain" />
             </div>
          </div>
        </div>

        <!-- Info -->
        <div class="flex flex-col">
          <div class="mb-8">
            <nav class="flex text-sm font-medium text-gray-500 mb-6 space-x-2">
              <router-link :to="{ name: 'storefront' }" class="hover:text-brand-600 transition-colors">Accueil</router-link>
              <span>/</span>
              <span class="text-brand-600">{{ product.category }}</span>
            </nav>
            
            <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 leading-tight mb-4">
              {{ product.name }}
            </h1>
            <p v-if="product.brand" class="text-brand-600 font-bold uppercase tracking-widest text-sm mb-4">Marque: {{ product.brand }}</p>
            
            <div class="flex items-center gap-4 mb-8">
               <span class="text-4xl font-black text-brand-900">
                  {{ selectedVariant?.public_price ? selectedVariant.public_price + ' MAD' : 'Sur devis' }}
               </span>
               <span class="bg-gray-100 text-gray-500 px-3 py-1 rounded-full text-xs font-bold uppercase">HT</span>
            </div>
          </div>

          <!-- Variants selection -->
          <div v-if="product.variants.length > 1" class="mb-10">
            <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-4">Choisir l'option :</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <button 
                v-for="v in product.variants" 
                :key="v.id"
                @click="selectedVariant = v"
                class="flex flex-col items-start p-4 rounded-2xl border-2 transition-all text-left"
                :class="selectedVariant?.id === v.id ? 'border-brand-600 bg-brand-50 shadow-sm' : 'border-gray-200 bg-white hover:border-brand-300'"
              >
                <span class="font-bold text-gray-900">{{ v.name_variation || 'Standard' }}</span>
                <span class="text-xs text-gray-500">{{ v.public_price }} MAD</span>
              </button>
            </div>
          </div>

          <div class="prose prose-brand max-w-none text-gray-600 mb-10 leading-relaxed">
            <div v-html="product.description"></div>
          </div>

          <div class="mt-auto space-y-4">
            <button 
              @click="addToCart"
              class="w-full py-5 bg-brand-600 text-white rounded-2xl font-black text-lg shadow-2xl shadow-brand-200 hover:bg-brand-700 hover:-translate-y-1 transition-all active:scale-95 flex items-center justify-center gap-3"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              AJOUTER AU PANIER
            </button>
            
            <div class="flex items-center justify-center gap-8 py-6 border-t border-gray-200 mt-8">
               <div class="flex items-center gap-2 text-xs font-bold text-gray-400 uppercase tracking-widest">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.45.45l1.41-1.41a1 1 0 000-1.41l-1.41-1.41a1 1 0 00-1.41 0l-1.41 1.41a1 1 0 000 1.41l1.41 1.41a1 1 0 001.41 0z" />
                  </svg>
                  Produit Certifié
               </div>
               <div class="flex items-center gap-2 text-xs font-bold text-gray-400 uppercase tracking-widest">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  SAV Partout au Maroc
               </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
