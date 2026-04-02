<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'
import { useConfig } from '@/composables/useConfig'

const { getImageUrl } = useConfig()

interface Catalogue {
    id: number;
    title: string;
    file_path: string;
    category: string | null;
    is_active: boolean;
    created_at: string;
}

const catalogues = ref<Catalogue[]>([])
const isLoading = ref(true)

const fetchCatalogues = async () => {
    isLoading.value = true
    try {
        const response = await api.get('/public/catalogues')
        catalogues.value = response.data
    } catch (e) {
        console.error('Failed to fetch catalogues')
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchCatalogues)
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="text-center mb-16">
        <span class="text-brand-600 font-bold tracking-widest text-xs uppercase">Ressources Clients</span>
        <h1 class="text-4xl md:text-5xl font-black text-gray-900 mt-4 uppercase tracking-tighter">Nos <span class="text-brand-600">Catalogues</span></h1>
        <p class="mt-4 text-gray-500 max-w-2xl mx-auto font-medium leading-relaxed">
          Téléchargez nos catalogues interactifs pour découvrir l'intégralité de nos solutions médicales, équipements et consommables.
        </p>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="flex justify-center py-20">
        <div class="w-16 h-16 border-4 border-brand-200 border-t-brand-600 rounded-full animate-spin"></div>
      </div>

      <!-- Catalogues Grid -->
      <div v-else-if="catalogues.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <div v-for="cat in catalogues" :key="cat.id" class="group bg-white rounded-[2.5rem] p-8 shadow-xl shadow-gray-100 border border-gray-50 hover:border-brand-500 transition-all duration-500 hover:-translate-y-2">
            <div class="flex items-start justify-between mb-6">
                <div class="p-4 bg-red-50 text-red-600 rounded-2xl group-hover:bg-red-600 group-hover:text-white transition-colors">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </div>
                <span class="px-4 py-1.5 bg-gray-100 text-gray-600 rounded-full text-[10px] font-black uppercase tracking-widest">
                    {{ cat.category || 'Général' }}
                </span>
            </div>
            
            <h3 class="text-xl font-black text-gray-900 group-hover:text-brand-600 transition-colors mb-2 line-clamp-2">{{ cat.title }}</h3>
            <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mb-8">Format PDF • {{ new Date(cat.created_at).toLocaleDateString() }}</p>
            
            <a :href="getImageUrl(cat.file_path)" target="_blank" class="w-full flex items-center justify-center gap-3 py-4 bg-gray-900 text-white rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-brand-600 hover:shadow-xl hover:shadow-brand-200 transition-all active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                Télécharger
            </a>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-24 bg-white rounded-[3rem] border-2 border-dashed border-gray-200 shadow-inner">
        <div class="inline-flex items-center justify-center w-24 h-24 bg-gray-50 rounded-full mb-6">
            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
        </div>
        <h3 class="text-2xl font-black text-gray-900 uppercase tracking-tighter">Aucun catalogue disponible</h3>
        <p class="mt-2 text-gray-500 font-medium">Revenez bientôt pour découvrir nos nouvelles brochures.</p>
        <router-link :to="{name: 'storefront'}" class="mt-8 inline-flex items-center px-8 py-4 bg-brand-600 text-white rounded-full font-black uppercase tracking-widest text-xs hover:bg-brand-700 shadow-lg shadow-brand-100 transition-all">
            Retour à l'accueil
        </router-link>
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-brand-600 { color: #00897b; }
.bg-brand-600 { background-color: #00897b; }
.shadow-brand-100 { --tw-shadow-color: rgba(0, 137, 123, 0.2); }
.border-brand-200 { border-color: #80cbc4; }
.border-t-brand-600 { border-top-color: #00897b; }
</style>
