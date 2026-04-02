<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useCartStore } from '@/stores/cart'
import { useConfig } from '@/composables/useConfig'

const { getImageUrl } = useConfig()

const props = defineProps<{
  product: any;
  show: boolean;
}>()

const emit = defineEmits(['close'])
const cartStore = useCartStore()

const selectedVariant = ref(props.product?.variants?.[0] || null)
const quantity = ref(1)

watch(() => props.product, (newProduct) => {
  selectedVariant.value = newProduct?.variants?.[0] || null
  quantity.value = 1
}, { deep: true })

const displayPrice = computed(() => {
  if (!selectedVariant.value) return 0
  return parseFloat(selectedVariant.value.public_price || 0)
})

const handleAddToCart = () => {
  if (!selectedVariant.value) return
  cartStore.addItem(props.product, selectedVariant.value, quantity.value)
  emit('close')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <!-- Overlay -->
      <div @click="emit('close')" class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>

      <!-- Modal panel -->
      <div class="inline-block transform overflow-hidden rounded-[32px] bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:align-middle">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-0">
          <div class="sm:flex sm:items-start">
            <!-- Image Section -->
            <div class="w-full sm:w-1/2 bg-gray-50 aspect-square overflow-hidden relative">
               <img :src="getImageUrl(product.image_path)" :alt="product.name" class="w-full h-full object-cover" />
               <button @click="emit('close')" class="absolute top-6 left-6 p-2 bg-white/80 backdrop-blur rounded-full shadow-lg hover:bg-white transition-colors">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                  </svg>
               </button>
            </div>

            <!-- Content Section -->
            <div class="w-full sm:w-1/2 p-8 sm:p-12 h-full flex flex-col">
              <div class="mb-4">
                <span class="text-xs font-bold text-brand-600 bg-brand-50 px-3 py-1 rounded-full uppercase tracking-widest">{{ product.category }}</span>
              </div>
              <h2 class="text-3xl font-black text-gray-900 leading-tight mb-4" id="modal-title">
                {{ product.name }}
              </h2>
              <div v-if="product.brand" class="mb-6 flex items-center gap-2">
                 <span class="text-sm font-medium text-gray-400">Marque:</span>
                 <span class="text-sm font-bold text-gray-900">{{ product.brand }}</span>
              </div>

              <div class="prose prose-sm text-gray-500 mb-8 line-clamp-4">
                {{ product.description || 'Aucune description disponible pour le moment.' }}
              </div>

              <!-- Pricing & Variants -->
              <div class="mb-8 p-6 bg-gray-50 rounded-2xl border border-gray-100">
                <div class="flex items-center justify-between mb-6">
                  <span class="text-sm font-bold text-gray-400">Prix unitaire (TTC)</span>
                  <span class="text-2xl font-black text-brand-900">
                    {{ displayPrice > 0 ? displayPrice.toFixed(2) + ' MAD' : 'Sur devis' }}
                  </span>
                </div>

                <div v-if="product.variants?.length > 1" class="space-y-3">
                  <label class="text-xs font-bold text-gray-400 uppercase tracking-widest">Choisir une variante</label>
                  <select v-model="selectedVariant" class="w-full p-3 bg-white border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-brand-500 font-medium text-sm">
                    <option v-for="v in product.variants" :key="v.id" :value="v">
                      {{ v.name_variation }} ({{ v.sku }})
                    </option>
                  </select>
                </div>
              </div>

              <!-- Actions -->
              <div class="mt-auto pt-6 flex flex-col gap-4">
                 <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                      <button @click="quantity > 1 && quantity--" class="px-4 py-2 hover:bg-gray-100 transition-colors">-</button>
                      <input type="number" v-model="quantity" class="w-12 text-center font-bold outline-none border-x border-gray-200" />
                      <button @click="quantity++" class="px-4 py-2 hover:bg-gray-100 transition-colors">+</button>
                    </div>
                    <button @click="handleAddToCart" class="flex-1 px-8 py-4 bg-brand-600 text-white rounded-2xl font-bold shadow-xl shadow-brand-200 hover:bg-brand-700 hover:-translate-y-1 transition-all active:translate-y-0">
                      Ajouter au devis
                    </button>
                 </div>
                 
                 <a :href="'https://wa.me/212765419754?text=Bonjour, je souhaite avoir plus d\'informations sur le produit: ' + product.name" target="_blank" class="w-full py-4 border-2 border-brand-100 text-brand-700 rounded-2xl font-bold text-center hover:bg-brand-50 transition-all flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                    Contacter via WhatsApp
                 </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Chrome, Safari, Edge, Opera */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

/* Firefox */
input[type=number] {
  -moz-appearance: textfield;
  appearance: textfield;
}
</style>
