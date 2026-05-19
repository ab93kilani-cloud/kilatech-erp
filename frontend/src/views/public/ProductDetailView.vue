<script setup lang="ts">
// Trigger Hostinger Deploy Rebuild
import { ref, onMounted, watch, computed } from 'vue'
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

const trustBadges = [
  {
    id: 'shipping',
    text: "Livraison gratuite à partir de 500dh d'achats",
    iconPath: "M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
  },
  {
    id: 'delivery',
    text: "Livré en 1-2 jours ouvrables",
    iconPath: "M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.75a1.125 1.125 0 01-1.125-1.125V11.25M15 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.5a1.5 1.5 0 001.5-1.5v-6a1.5 1.5 0 00-1.5-1.5H3.75m16.5 0h-3L18 9.75h3A1.5 1.5 0 0122.5 11.25v6a1.5 1.5 0 01-1.5 1.5H19.5"
  },
  {
    id: 'payment',
    text: "Paiement à la livraison",
    iconPath: "M9 14.25l6-6m4.5-3.75h-15A2.25 2.25 0 001.5 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25zm-13.5 13.5h.008v.008h-.008v-.008zm0-3h.008v.008h-.008v-.008z"
  }
]

// Watch for route changes to refresh data when clicking related products
watch(() => route.params.slug, (newSlug) => {
    if (newSlug) {
        isLoading.value = true
        quantity.value = 1
        fetchProduct()
        window.scrollTo({ top: 0, behavior: 'smooth' })
    }
})

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
  description: string;
  meta_title: string;
  meta_description: string;
  seo_title?: string;
  seo_description?: string;
  seo_content?: string;
  seo_h1?: string;
  seo_alt?: string;
  seo_score?: number;
  seo_status?: string;
  image_path: string | null;
  images: string[];
  unit_name: string;
  packaging_unit: string;
  sales_multiplier: number;
  variants: Variant[];
  public_price?: string | number;
  brand?: string;
  related?: Product[];
}

const product = ref<Product | null>(null)
const isLoading = ref(true)
const selectedVariant = ref<Variant | null>(null)
const mainImage = ref<string | null>(null)
const quantity = ref(1)

const getStep = () => {
    return product.value?.sales_multiplier && product.value.sales_multiplier > 1
        ? Number(product.value.sales_multiplier)
        : 1
}

const formatPrice = (price: number | string | null | undefined) => {
    if (price === null || price === undefined || price === '') return 'Sur devis';
    const num = parseFloat(price.toString());
    return num.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MAD';
}

const getDisplayPrice = (variant: Variant | null) => {
    if (!variant || !variant.public_price) return null;
    return parseFloat(variant.public_price.toString());
}

const incrementQty = () => {
    const step = getStep()
    quantity.value += step
}

const decrementQty = () => {
    const step = getStep()
    if (quantity.value > step) {
        quantity.value -= step
    } else {
        quantity.value = step
    }
}

const sanitizeQuantity = () => {
    const step = getStep()
    if (quantity.value < step) {
        quantity.value = step
    } else {
        quantity.value = Math.round(quantity.value / step) * step
    }
}

const totalPrice = computed(() => {
    const price = getDisplayPrice(selectedVariant.value);
    return price ? price * quantity.value : 0;
});

const fetchProduct = async () => {
    try {
        const slug = route.params.slug
        const response = await api.get(`/products/${slug}`)
        product.value = response.data
        
        if (product.value) {
            mainImage.value = product.value.image_path
            quantity.value = product.value.sales_multiplier && product.value.sales_multiplier > 1
                ? Number(product.value.sales_multiplier)
                : 1
            if (product.value.variants && product.value.variants.length > 0) {
                const firstVariant = product.value.variants[0];
                if (firstVariant) {
                    selectedVariant.value = firstVariant;
                }
            }
            
            // SEO Update (Prioritize Optimized AI Content)
            updateSEO({
                title: product.value.seo_title || product.value.meta_title || product.value.name,
                description: product.value.seo_description || product.value.meta_description || product.value.description?.substring(0, 160)
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
            "price": selectedVariant.value?.public_price ? parseFloat(selectedVariant.value.public_price.toString()) : 0,
            "availability": "https://schema.org/InStock",
            "seller": { "@type": "Organization", "name": "Kilatech Maroc" }
        }
    }
    script.text = JSON.stringify(ld)
    document.head.appendChild(script)
}

const addToCart = () => {
    if (!product.value || !selectedVariant.value) return
    cartStore.addItem(product.value, selectedVariant.value, quantity.value)
    
    // Simple feedback (could be more advanced)
    alert('Produit ajouté au panier !')
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
             <img :src="getImageUrl(mainImage)" :alt="product.seo_alt || product.name" class="w-full h-full object-contain p-8 group-hover:scale-105 transition-transform duration-500" />
          </div>
          
          <div v-if="product.images && product.images.length > 0" class="grid grid-cols-4 gap-4">
             <div 
               v-for="(img, i) in [product.image_path, ...(product.images || [])]" 
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
              {{ product.seo_h1 || product.name }}
            </h1>
            <p v-if="product.brand" class="text-brand-600 font-bold uppercase tracking-widest text-sm mb-4">Marque: {{ product.brand }}</p>
            
            <div class="flex items-center gap-4 mb-8">
               <div class="flex flex-col">
                  <span class="text-xs font-bold text-brand-600 uppercase tracking-tighter">Prix TTC</span>
                  <span class="text-4xl font-black text-brand-900">
                    {{ getDisplayPrice(selectedVariant) ? formatPrice(getDisplayPrice(selectedVariant)) : 'Sur devis' }}
                  </span>
               </div>
            </div>
          </div>

          <!-- Variants selection -->
          <div v-if="product.variants.length > 1" class="mb-8">
            <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-4">Choisir l'option :</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <button 
                v-for="v in product.variants" 
                :key="v.id"
                @click="selectedVariant = v"
                class="flex justify-between items-center p-4 rounded-2xl border-2 transition-all text-left"
                :class="selectedVariant?.id === v.id ? 'border-brand-600 bg-brand-50 shadow-sm' : 'border-gray-200 bg-white hover:border-brand-300'"
              >
                <div class="flex flex-col">
                  <span class="font-bold text-gray-900">{{ v.name_variation || 'Standard' }}</span>
                </div>
                <div class="text-right">
                   <span class="text-sm font-black text-brand-700">{{ formatPrice(v.public_price) }}</span>
                   <span class="text-[10px] block font-bold text-gray-300">TTC</span>
                </div>
              </button>
            </div>
          </div>

          <!-- Quantity Selector -->
          <div class="mb-10 p-6 bg-white rounded-3xl border border-gray-100 shadow-sm">
            <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-4">Quantité :</h3>
            <div class="flex flex-col gap-4">
              <div class="flex items-center gap-6">
                <div class="flex items-center border-2 border-gray-100 rounded-2xl overflow-hidden bg-gray-50">
                  <button @click="decrementQty" class="px-5 py-3 hover:bg-gray-200 transition-colors text-gray-600 font-bold text-xl">-</button>
                  <input 
                    type="number" 
                    v-model.number="quantity" 
                    class="w-20 text-center bg-transparent border-none focus:ring-0 font-bold text-lg" 
                    :min="getStep()" 
                    :step="getStep()"
                    @blur="sanitizeQuantity"
                    @change="sanitizeQuantity"
                  />
                  <button @click="incrementQty" class="px-5 py-3 hover:bg-gray-200 transition-colors text-gray-600 font-bold text-xl">+</button>
                </div>
                <p class="text-xs text-gray-400 font-medium">Prix total : <span class="text-brand-600 font-bold">{{ formatPrice(totalPrice) }}</span></p>
              </div>
              <div v-if="product.sales_multiplier > 1" class="text-xs font-bold text-brand-600 uppercase tracking-tighter">
                Vendu par {{ product.packaging_unit || 'boîte' }} de {{ product.sales_multiplier }} 
                <span class="text-gray-400 font-medium normal-case">
                  (soit {{ (quantity / product.sales_multiplier).toFixed(0) }} {{ product.packaging_unit || 'boîte' }}{{ quantity / product.sales_multiplier > 1 ? 's' : '' }})
                </span>
              </div>
            </div>
          </div>

          <div class="p-8 bg-gray-50 rounded-3xl mb-10 border border-gray-100">
            <h3 class="text-xs font-black text-gray-900 uppercase tracking-widest mb-4 border-b border-gray-200 pb-2">
                {{ product.seo_content ? 'Expertise & Description' : 'Description du produit' }}
            </h3>
            <div class="prose prose-brand max-w-none text-gray-600 leading-relaxed text-sm">
              <div v-if="product.seo_content" v-html="product.seo_content"></div>
              <div v-else v-html="product.description"></div>
            </div>
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

            <!-- Trust badges/Conditions section -->
            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm space-y-5 mt-6">
              <div v-for="badge in trustBadges" :key="badge.id" class="flex items-center gap-4 text-gray-700">
                <div class="flex-shrink-0 w-12 h-12 rounded-full border border-gray-200 flex items-center justify-center bg-gray-50 text-gray-500">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="badge.iconPath" />
                  </svg>
                </div>
                <span class="text-sm font-semibold text-gray-700">{{ badge.text }}</span>
              </div>
            </div>
            
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

      <!-- Related Products -->
      <div v-if="product && product.related && product.related.length > 0" class="mt-32">
        <div class="flex items-center justify-between mb-12">
          <div>
            <h2 class="text-3xl font-black text-gray-900">Vous pourriez aussi aimer</h2>
            <p class="text-gray-500 mt-2">Découvrez nos autres produits dans la catégorie <span class="text-brand-600 font-bold">{{ product.category }}</span></p>
          </div>
          <div class="h-px flex-1 bg-gray-100 mx-10 hidden sm:block"></div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
           <router-link 
             v-for="rel in product.related" 
             :key="rel.id"
             :to="{ name: 'product-detail', params: { slug: rel.slug } }"
             class="group bg-white rounded-3xl p-6 border border-gray-100 hover:shadow-2xl hover:shadow-brand-100 transition-all duration-500"
           >
             <div class="aspect-square rounded-2xl bg-gray-50 overflow-hidden mb-6 p-4">
                <img :src="getImageUrl(rel.image_path)" :alt="rel.name" class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500" />
             </div>
             <h4 class="font-bold text-gray-900 group-hover:text-brand-600 transition-colors">{{ rel.name }}</h4>
             <p class="text-xs text-gray-400 mt-2 uppercase font-bold">{{ rel.brand }}</p>
             <div class="mt-4 flex items-center justify-between">
                <span class="font-black text-brand-900">{{ rel.public_price ? formatPrice(rel.public_price) : 'Sur devis' }}</span>
                <div class="w-10 h-10 rounded-full bg-brand-50 flex items-center justify-center text-brand-600 group-hover:bg-brand-600 group-hover:text-white transition-all">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                  </svg>
                </div>
             </div>
           </router-link>
        </div>
      </div>
    </div>
  </div>
</template>
