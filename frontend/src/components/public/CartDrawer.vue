<script setup lang="ts">
import { ref } from 'vue'
import { useCartStore } from '@/stores/cart'
import api from '@/plugins/axios'
import { useConfig } from '@/composables/useConfig'

const { getImageUrl: getStorageUrl, apiBaseUrl } = useConfig()

const props = defineProps<{
  show: boolean;
}>()

const emit = defineEmits(['close'])
const cartStore = useCartStore()

const form = ref({
  client_type: 'company', // 'company' or 'individual'
  client_name: '',
  client_email: '',
  client_phone: '',
  company_name: '',
  ice: '',
  address: '',
  city: ''
})

const isSubmitting = ref(false)
const quoteResult = ref<{ id: number; number: string } | null>(null)
const errors = ref<Record<string, string[]>>({})

const getImageUrl = (path: string | null) => {
  if (!path) return '/logo.png'
  return getStorageUrl(path)
}

const isValidEmail = (email: string) => {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

const handleSubmit = async () => {
  errors.value = {}
  if (cartStore.items.length === 0) return
  
  // Basic frontend validation
  if (!form.value.client_email || !isValidEmail(form.value.client_email)) {
    errors.value.client_email = ['Veuillez entrer une adresse email valide.']
  }
  
  if (!form.value.client_phone) {
    errors.value.client_phone = ['Le numéro de téléphone est obligatoire pour valider votre demande.']
  }

  if (Object.keys(errors.value).length > 0) {
    return
  }
  
  isSubmitting.value = true
  try {
    const payload = {
      client_type: form.value.client_type,
      client_name: form.value.client_name,
      client_email: form.value.client_email,
      client_phone: form.value.client_phone,
      company_name: form.value.company_name,
      ice: form.value.ice,
      address: form.value.address,
      city: form.value.city,
      lines: cartStore.items.map(item => ({
        variant_id: item.variant_id,
        quantity: item.quantity
      }))
    }
    
    const response = await api.post('/public/orders', payload)
    quoteResult.value = {
      id: response.data.quote_id,
      number: response.data.quote_number
    }
    
    // Clear cart on success
    cartStore.clearCart()
    } catch (e: any) {
    console.error('Failed to submit quote request', e)
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors
    } else {
      // Force display raw response for debugging Maya.
      const rawError = e.response?.data ? JSON.stringify(e.response.data, null, 2) : (e.response?.statusText || e.message)
      alert('DEBUG MAYA - Erreur Serveur :\n' + rawError)
    }
  } finally {
    isSubmitting.value = false
  }
}

const downloadQuote = () => {
  if (!quoteResult.value) return
  window.open(`${apiBaseUrl}/public/quotes/${quoteResult.value.id}/pdf`, '_blank')
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-[110] overflow-hidden">
    <!-- Overlay -->
    <div @click="emit('close')" class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity"></div>
    
    <div class="absolute inset-y-0 right-0 max-w-full flex pl-10">
      <div class="w-screen max-w-md transform transition-all duration-500">
        <div class="h-full flex flex-col bg-white shadow-2xl rounded-l-[40px] overflow-hidden">
          
          <!-- Header -->
          <div class="p-8 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-2xl font-black text-gray-900">Demande de Devis</h2>
            <button @click="emit('close')" class="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-400">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
            
            <div v-if="quoteResult" class="text-center py-12 animate-fade-in">
                <div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Devis Prêt !</h3>
                <p class="text-gray-500 mb-8 font-medium">Votre devis <span class="text-brand-600 font-bold">{{ quoteResult.number }}</span> a été généré automatiquement.</p>
                <div class="space-y-4">
                  <button @click="downloadQuote" class="w-full py-4 bg-brand-600 text-white rounded-2xl font-bold shadow-xl shadow-brand-100 flex items-center justify-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Télécharger le PDF
                  </button>
                  <button @click="quoteResult = null; emit('close')" class="w-full py-4 text-gray-400 font-bold hover:text-gray-600 transition-colors">
                    Fermer
                  </button>
                </div>
            </div>

            <div v-else-if="cartStore.items.length > 0" class="space-y-8">
              <!-- Item List -->
              <div class="space-y-6">
                <div v-for="item in cartStore.items" :key="item.variant_id" class="flex gap-4 group">
                  <div class="w-20 h-20 bg-gray-50 rounded-2xl overflow-hidden shadow-inner border border-gray-100 flex-shrink-0">
                    <img :src="getImageUrl(item.image_path)" class="w-full h-full object-cover" />
                  </div>
                  <div class="flex-1">
                    <div class="flex justify-between items-start">
                      <h4 class="font-bold text-gray-900 line-clamp-2 leading-tight">{{ item.name }}</h4>
                      <button @click="cartStore.removeItem(item.variant_id)" class="text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                    <div class="flex items-center justify-between mt-3 text-sm">
                      <div class="flex items-center gap-3 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">
                        <button @click="cartStore.updateQuantity(item.variant_id, item.quantity - 1)" class="text-gray-400 hover:text-brand-600">-</button>
                        <span class="font-bold">{{ item.quantity }}</span>
                        <button @click="cartStore.updateQuantity(item.variant_id, item.quantity + 1)" class="text-gray-400 hover:text-brand-600">+</button>
                      </div>
                      <span class="font-black text-brand-900">{{ (item.price * item.quantity).toFixed(2) }} MAD (TTC)</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Form -->
              <div class="pt-8 border-t border-gray-100">
                 <h4 class="text-lg font-bold text-gray-900 mb-6 underline decoration-brand-500 decoration-4 underline-offset-4">Vos Coordonnées</h4>
                 
                 <!-- Type Selector -->
                 <div class="flex p-1 bg-gray-50 rounded-2xl mb-8 border border-gray-100">
                    <button 
                      @click="form.client_type = 'individual'"
                      :class="form.client_type === 'individual' ? 'bg-white shadow-md text-brand-600' : 'text-gray-400'"
                      class="flex-1 py-3 font-bold rounded-xl transition-all"
                    >
                      Particulier
                    </button>
                    <button 
                      @click="form.client_type = 'company'"
                      :class="form.client_type === 'company' ? 'bg-white shadow-md text-brand-600' : 'text-gray-400'"
                      class="flex-1 py-3 font-bold rounded-xl transition-all"
                    >
                      Société
                    </button>
                 </div>

                 <div class="space-y-5">
                    <div v-if="form.client_type === 'company'">
                      <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Raison Sociale</label>
                      <input v-model="form.company_name" type="text" placeholder="Ex: Kilatech SARL" :class="errors.company_name ? 'border-red-500 bg-red-50' : 'bg-gray-50 border-transparent'" class="w-full p-4 border rounded-2xl focus:bg-white focus:border-brand-500 focus:ring-4 focus:ring-brand-50 outline-none transition-all font-medium" />
                      <p v-if="errors.company_name" class="mt-1 text-xs text-red-500 font-bold">{{ errors.company_name[0] }}</p>
                    </div>
                    
                    <div v-if="form.client_type === 'company'">
                      <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">ICE</label>
                      <input v-model="form.ice" type="text" placeholder="Identifiant Commun de l'Entreprise" :class="errors.ice ? 'border-red-500 bg-red-50' : 'bg-gray-50 border-transparent'" class="w-full p-4 border rounded-2xl focus:bg-white focus:border-brand-500 focus:ring-4 focus:ring-brand-50 outline-none transition-all font-medium" />
                      <p v-if="errors.ice" class="mt-1 text-xs text-red-500 font-bold">{{ errors.ice[0] }}</p>
                    </div>

                    <div>
                      <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Nom du Contact</label>
                      <input v-model="form.client_name" type="text" placeholder="Ex: Jean Dupont" :class="errors.client_name ? 'border-red-500 bg-red-50' : 'bg-gray-50 border-transparent'" class="w-full p-4 border rounded-2xl focus:bg-white focus:border-brand-500 focus:ring-4 focus:ring-brand-50 outline-none transition-all font-medium" />
                      <p v-if="errors.client_name" class="mt-1 text-xs text-red-500 font-bold">{{ errors.client_name[0] }}</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                      <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Email</label>
                        <input v-model="form.client_email" type="email" placeholder="nom@entreprise.com" :class="errors.client_email ? 'border-red-500 bg-red-50' : 'bg-gray-50 border-transparent'" class="w-full p-4 border rounded-2xl focus:bg-white focus:border-brand-500 focus:ring-4 focus:ring-brand-50 outline-none transition-all font-medium" />
                        <p v-if="errors.client_email" class="mt-1 text-xs text-red-500 font-bold">{{ errors.client_email[0] }}</p>
                      </div>
                      <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Téléphone</label>
                        <input v-model="form.client_phone" type="tel" placeholder="+212 ..." :class="errors.client_phone ? 'border-red-500 bg-red-50' : 'bg-gray-50 border-transparent'" class="w-full p-4 border rounded-2xl focus:bg-white focus:border-brand-500 focus:ring-4 focus:ring-brand-50 outline-none transition-all font-medium" />
                        <p v-if="errors.client_phone" class="mt-1 text-xs text-red-500 font-bold">{{ errors.client_phone[0] }}</p>
                      </div>
                    </div>

                    <div>
                      <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Adresse de Livraison</label>
                      <input v-model="form.address" type="text" placeholder="N°, Rue, Quartier..." :class="errors.address ? 'border-red-500 bg-red-50' : 'bg-gray-50 border-transparent'" class="w-full p-4 border rounded-2xl focus:bg-white focus:border-brand-500 focus:ring-4 focus:ring-brand-50 outline-none transition-all font-medium" />
                      <p v-if="errors.address" class="mt-1 text-xs text-red-500 font-bold">{{ errors.address[0] }}</p>
                    </div>

                    <div>
                      <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Ville</label>
                      <input v-model="form.city" type="text" placeholder="Ex: Casablanca" :class="errors.city ? 'border-red-500 bg-red-50' : 'bg-gray-50 border-transparent'" class="w-full p-4 border rounded-2xl focus:bg-white focus:border-brand-500 focus:ring-4 focus:ring-brand-50 outline-none transition-all font-medium" />
                      <p v-if="errors.city" class="mt-1 text-xs text-red-500 font-bold">{{ errors.city[0] }}</p>
                    </div>
                 </div>
              </div>
            </div>

            <div v-else class="h-full flex flex-col items-center justify-center text-center py-20">
               <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mb-6">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
               </div>
               <h3 class="text-xl font-bold text-gray-900">Votre panier est vide</h3>
               <p class="mt-4 text-gray-400 font-medium">Parcourez notre catalogue et ajoutez des produits pour obtenir un devis personnalisé.</p>
               <button @click="emit('close')" class="mt-8 px-8 py-3 bg-brand-600 text-white rounded-full font-bold shadow-lg shadow-brand-100 hover:scale-105 transition-transform">
                 Voir les produits
               </button>
            </div>
          </div>

          <!-- Footer -->
          <div v-if="!quoteResult && cartStore.items.length > 0" class="p-8 bg-gray-50 border-t border-gray-100">
            <div class="flex items-center justify-between mb-6">
              <span class="font-bold text-gray-500">Estimation Totale (TTC)</span>
              <span class="text-2xl font-black text-brand-900">{{ cartStore.totalPrice.toFixed(2) }} MAD</span>
            </div>
            <button 
              @click="handleSubmit" 
              :disabled="isSubmitting || !form.client_name || !form.client_email || (form.client_type === 'company' && (!form.company_name || !form.ice))"
              class="w-full py-5 bg-brand-600 text-white rounded-[24px] font-black text-lg shadow-2xl shadow-brand-200 hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed hover:-translate-y-1 transition-all active:translate-y-0"
            >
              <span v-if="isSubmitting" class="flex items-center justify-center gap-2">
                <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Génération en cours...
              </span>
              <span v-else>VALIDER MA DEMANDE DE DEVIS</span>
            </button>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #e5e7eb;
  border-radius: 10px;
}
.animate-fade-in {
  animation: fadeIn 0.5s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
