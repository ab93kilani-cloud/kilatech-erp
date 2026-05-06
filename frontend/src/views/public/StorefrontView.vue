<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import api from '@/plugins/axios'
import { useSEO } from '@/composables/useSEO'
import { useCartStore } from '@/stores/cart'
import CartDrawer from '@/components/public/CartDrawer.vue'
import { useConfig } from '@/composables/useConfig'

const { updateSEO } = useSEO()
const { getImageUrl } = useConfig()

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
  public_price: number | string;
  is_medical: boolean;
  image_path: string | null;
  is_showcase: boolean;
  unit_name: string;
  packaging_unit: string;
  sales_multiplier: number;
  variants: Variant[];
}

const products = ref<Product[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const activeCategory = ref('Tous')

const cartStore = useCartStore()
const isCartOpen = ref(false)

const addToCart = (product: Product, event: Event) => {
    event.preventDefault()
    event.stopPropagation()
    
    if (product.variants && product.variants.length > 0) {
        cartStore.addItem(product, product.variants[0], 1)
        // Opening cart drawer for feedback
        isCartOpen.value = true
    }
}

const formatTTCPrice = (product: Product) => {
    if (!product.variants || product.variants.length === 0) return null
    const firstVariant = product.variants[0]
    if (!firstVariant) return null
    return parseFloat(String(firstVariant.public_price || '0')).toFixed(2)
}

const fetchProducts = async () => {
    try {
        const response = await api.get('/products')
        products.value = response.data
    } catch (e: any) {
        console.error("API error or protected route", e)
    } finally {
        isLoading.value = false
    }
}

const categories = computed(() => {
    const cats = new Set(products.value.map(p => p.category).filter(Boolean))
    return ['Tous', ...Array.from(cats).sort()]
})

const filteredProducts = computed(() => {
    return products.value.filter(p => {
        // --- FILTRE VITRINE ---
        if (!p.is_showcase) return false

        const matchesSearch = p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                             p.category.toLowerCase().includes(searchQuery.value.toLowerCase())
        const matchesCategory = activeCategory.value === 'Tous' || p.category === activeCategory.value
        return matchesSearch && matchesCategory
    })
})

const handleImageError = (e: Event) => {
    const target = e.target as HTMLImageElement;
    if (target.src !== window.location.origin + '/logo.png') {
        target.src = '/logo.png';
        target.classList.add('opacity-50', 'p-4', 'object-contain');
    }
}

const clearFilters = () => {
    searchQuery.value = ''
    activeCategory.value = 'Tous'
}

let searchLogTimeout: any = null
watch([searchQuery, filteredProducts], ([newQuery, newProducts]) => {
    if (newQuery.length >= 1 && newProducts.length === 0) {
        console.log('Search miss detected:', newQuery)
        clearTimeout(searchLogTimeout)
        searchLogTimeout = setTimeout(async () => {
            try {
                await api.post(`/public/search-miss?query=${encodeURIComponent(newQuery)}`)
            } catch (e) {
                // Silent fail
            }
        }, 1000)
    }
})

onMounted(() => {
    fetchProducts()
    updateSEO({
        title: 'Équipements Médicaux & Solutions Santé',
        description: 'Kilatech est votre partenaire de confiance pour les dispositifs médicaux de haute qualité au Maroc. Commandez en ligne vos équipements.',
        keywords: 'médical, santé, équipement hospitalier, Maroc, Kilatech'
    })
})
</script>

<template>
  <div class="min-h-screen bg-white font-sans text-gray-900">


    <!-- Hero Section -->
    <section class="relative h-[650px] flex items-center overflow-hidden">
      <!-- Background Image with Overlay -->
      <div class="absolute inset-0 z-0">
        <img src="/images/hero-bg.png" alt="Medical Background" class="w-full h-full object-cover" />
        <div class="absolute inset-0 bg-black/50 bg-gradient-to-r from-black/60 to-transparent"></div>
      </div>

      <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 w-full text-white mt-20 sm:mt-0">
        <div class="max-w-2xl animate-fade-in-up">
          <h1 class="text-4xl sm:text-5xl md:text-7xl font-bold leading-tight mb-4 sm:mb-6">
            Bienvenue chez <br/>
            <span class="text-brand-400">KILATECH,</span>
          </h1>
          <p class="text-base sm:text-lg md:text-xl text-gray-100 font-light mb-8 sm:mb-10 leading-relaxed max-w-xl">
            Votre partenaire de confiance pour prendre soin de vos patients. 
            Nous nous engageons à fournir des produits de haute qualité, 
            adaptés aux besoins des professionnels de la santé.
          </p>
          
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 sm:gap-6">
           <router-link :to="{ name: 'catalogues' }" class="w-full sm:w-auto text-center px-8 sm:px-10 py-3 sm:py-3.5 border-2 border-white rounded-full font-semibold hover:bg-white hover:text-brand-900 transition-all duration-300">
             Consulter notre catalogue
           </router-link>
            <router-link :to="{ name: 'about' }" class="group flex items-center gap-2 font-medium hover:text-brand-300 transition-colors py-2">
              En savoir plus
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
              </svg>
            </router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- Product Grid Section -->
    <section class="py-20 bg-gray-50" id="catalogue">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="flex flex-col lg:flex-row lg:items-end justify-between mb-8 gap-6 border-b border-gray-200 pb-8 sm:pb-10">
          <div class="flex-1">
            <span class="text-brand-600 font-bold tracking-widest text-[10px] sm:text-xs uppercase">Catalogue Kilatech</span>
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mt-2">Équipements Médicaux</h2>
            <p class="text-gray-500 mt-3 sm:mt-4 max-w-lg text-xs sm:text-sm leading-relaxed">
              Explorez notre sélection rigoureuse des technologies médicales les plus innovantes pour votre établissement.
            </p>
          </div>
        </div>

        <!-- Category & Search Filter Bar -->
        <div class="flex flex-col md:flex-row items-center gap-4 mb-12 bg-white p-4 rounded-3xl shadow-sm border border-gray-100">
          <!-- Category Selector -->
          <div class="relative w-full md:w-80 group">
            <label class="absolute -top-2.5 left-4 bg-white px-2 text-[10px] font-black text-brand-600 uppercase tracking-widest z-10">Famille d'équipement</label>
            <div class="relative">
              <select 
                v-model="activeCategory" 
                class="w-full pl-4 pr-10 py-4 bg-gray-50 border-none rounded-2xl text-sm font-bold text-gray-700 focus:ring-2 focus:ring-brand-500 outline-none appearance-none cursor-pointer transition-all hover:bg-gray-100"
              >
                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-brand-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Search Bar -->
          <div class="relative flex-1 w-full">
            <input 
              v-model="searchQuery" 
              type="text" 
              placeholder="Rechercher par nom, référence ou spécialité..." 
              class="w-full pl-12 pr-4 py-4 bg-gray-50 border-none rounded-2xl text-sm font-medium focus:ring-2 focus:ring-brand-500 outline-none transition-all placeholder:text-gray-400"
            />
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <!-- Active Filter Badge (Desktop Only) -->
          <div v-if="activeCategory !== 'Tous'" class="hidden lg:flex items-center gap-2 px-4 py-2 bg-brand-50 border border-brand-100 rounded-xl">
            <span class="text-[10px] font-black text-brand-700 uppercase tracking-tighter">{{ activeCategory }}</span>
            <button @click="activeCategory = 'Tous'" class="text-brand-400 hover:text-brand-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>

        <div v-if="isLoading" class="flex justify-center py-20">
          <div class="w-12 h-12 border-4 border-brand-200 border-t-brand-600 rounded-full animate-spin"></div>
        </div>
        
        <div v-else-if="filteredProducts.length > 0" class="grid grid-cols-2 gap-y-8 gap-x-4 sm:gap-x-8 lg:grid-cols-4 animate-fade-in">
          <router-link v-for="product in filteredProducts" :key="product.id" :to="{ name: 'product-detail', params: { slug: product.slug || product.id } }" class="group block cursor-pointer">
            <div class="relative aspect-[4/5] overflow-hidden rounded-2xl bg-gray-200 shadow-sm border border-gray-100">
              <img :src="getImageUrl(product.image_path)" 
                   @error="handleImageError" 
                   :alt="product.name" 
                   class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110" />
              <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
              <div class="absolute bottom-6 left-6 right-6 text-white translate-y-8 group-hover:translate-y-0 transition-all duration-500">
                <span class="text-[10px] font-bold bg-brand-600 px-3 py-1.5 rounded-lg shadow-lg uppercase tracking-widest">En savoir plus</span>
              </div>
            </div>
            
              <div class="mt-4 sm:mt-6 flex flex-col gap-4">
                <div>
                  <div class="flex items-center justify-between mb-1 sm:mb-2">
                    <span class="text-[8px] sm:text-[10px] font-bold text-brand-700 bg-brand-50 px-2 py-0.5 rounded border border-brand-100 uppercase tracking-tighter">{{ product.category }}</span>
                  </div>
                  <h3 class="text-sm sm:text-lg font-bold text-gray-900 group-hover:text-brand-600 transition-colors line-clamp-1">
                    {{ product.name }}
                  </h3>
                  <p class="mt-1 sm:mt-2 text-base sm:text-xl font-black text-brand-900 leading-tight">
                    {{ formatTTCPrice(product) ? formatTTCPrice(product) + ' MAD' : 'Sur devis' }}
                    <span v-if="formatTTCPrice(product)" class="text-[8px] sm:text-[10px] font-bold text-gray-400">/ {{ product.unit_name || 'unité' }}</span>
                  </p>
                  <div v-if="product.sales_multiplier > 1" class="mt-1 flex flex-col">
                      <span class="text-[8px] sm:text-[10px] text-brand-600 font-bold uppercase tracking-tighter">Vendu par {{ product.packaging_unit }} de {{ product.sales_multiplier }}</span>
                      <span class="text-[7px] sm:text-[9px] text-gray-400 font-medium">Soit {{ (parseFloat(String(formatTTCPrice(product) || 0)) * product.sales_multiplier).toFixed(2) }} MAD TTC</span>
                  </div>
                </div>
                
                <button 
                  @click.stop.prevent="addToCart(product, $event)"
                  class="w-full py-3 bg-brand-600 text-white rounded-xl font-bold text-xs sm:text-sm shadow-lg shadow-brand-100 hover:bg-brand-700 hover:-translate-y-0.5 transition-all active:scale-95 flex items-center justify-center gap-2"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                  </svg>
                  AJOUTER AU PANIER
                </button>
              </div>
          </router-link>
        </div>
        
        <!-- Empty State -->
        <div v-else class="text-center py-24 bg-white rounded-[40px] border border-dashed border-gray-300 animate-fade-in shadow-inner">
            <div class="mx-auto h-20 w-20 bg-gray-50 rounded-full flex items-center justify-center mb-6">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900">Aucun produit trouvé</h3>
            <p class="mt-3 text-gray-500 font-medium px-4">Nous n'avons trouvé aucun équipement correspondant à "{{ searchQuery }}" dans cette catégorie.</p>
            <button @click="clearFilters" class="mt-8 px-8 py-3 bg-brand-600 text-white rounded-full font-bold hover:bg-brand-700 transition-all active:scale-95 shadow-lg shadow-brand-100">
              Réinitialiser les filtres
            </button>
        </div>
      </div>
    </section>

    <!-- WhatsApp Floating Button -->
    <a href="https://wa.me/212765419754" target="_blank" class="fixed bottom-8 left-8 z-[60] group">
       <div class="relative flex items-center justify-center w-16 h-16 bg-[#25D366] rounded-full shadow-2xl hover:scale-110 transition-transform animate-bounce-slow">
         <svg xmlns="http://www.w3.org/2000/svg" class="w-9 h-9 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
         </svg>
       </div>
       <div class="absolute left-full ml-4 top-1/2 -translate-y-1/2 bg-white text-gray-900 px-4 py-2 rounded-lg shadow-xl font-bold text-sm whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity invisible group-hover:visible">
          Besoin d'aide ?
       </div>
    </a>


    <!-- Floating Cart Button -->
    <button @click="isCartOpen = true" class="fixed bottom-8 right-8 z-[60] group">
       <div class="relative flex items-center justify-center w-20 h-20 bg-brand-600 text-white rounded-full shadow-2xl hover:scale-110 transition-transform shadow-brand-200">
         <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
         </svg>
         <div v-if="cartStore.totalItems > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-black w-6 h-6 rounded-full flex items-center justify-center shadow-lg border-2 border-white animate-pulse">
            {{ cartStore.totalItems }}
         </div>
       </div>
       <div class="absolute right-full mr-4 top-1/2 -translate-y-1/2 bg-white text-gray-900 px-6 py-3 rounded-2xl shadow-2xl font-black text-xs whitespace-nowrap opacity-0 group-hover:opacity-100 transition-all invisible group-hover:visible border border-gray-100">
          VOTRE PANIER ({{ cartStore.totalItems }} ARTICLES)
       </div>
    </button>

    <CartDrawer 
      :show="isCartOpen" 
      @close="isCartOpen = false" 
    />
  </div>
</template>

<style scoped>
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up {
  animation: fadeInUp 1s ease-out forwards;
}
.animate-bounce-slow {
  animation: bounce 3s infinite;
}
@keyframes bounce {
  0%, 100% { transform: translateY(-5%); animation-timing-function: cubic-bezier(0.8, 0, 1, 1); }
  50% { transform: translateY(0); animation-timing-function: cubic-bezier(0, 0, 0.2, 1); }
}
</style>
