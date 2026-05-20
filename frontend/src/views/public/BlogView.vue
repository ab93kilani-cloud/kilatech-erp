<script setup lang="ts">
import { ref, onMounted } from 'vue'
import publicApi from '@/plugins/axios-public'
import { useSEO } from '@/composables/useSEO'
import { useConfig } from '@/composables/useConfig'

const { getImageUrl: getStorageUrl } = useConfig()
const { updateSEO } = useSEO()

const articles = ref<any[]>([])
const isLoading = ref(true)

const getImageUrl = (article: any) => {
  if (article.product && article.product.image_path) {
    return getStorageUrl(article.product.image_path)
  }
  if (article.image) return getStorageUrl(article.image)
  return '/images/blog_placeholder.png'
}

const fetchArticles = async () => {
  try {
    const res = await publicApi.get('/seo-v2/blog-list?api_token=KILA-SEO-PROD-2026-X9&category=blog')
    articles.value = res.data
  } catch (e) {
    console.error('Failed to load blog posts')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchArticles()
  updateSEO({
    title: 'Blog Médical & Expertise | Kilatech',
    description: "Retrouvez nos guides d'experts, conseils et nouveautés sur le matériel médical au Maroc."
  })
})
</script>

<template>
  <div class="bg-white min-h-screen">
    <!-- Header Section -->
    <section class="py-20 bg-brand-50/30 border-b border-brand-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="text-brand-600 font-bold uppercase tracking-widest text-xs mb-4 block">Savoir & Expertise</span>
        <h1 class="text-4xl md:text-5xl font-black text-gray-900 tracking-tight mb-6 uppercase">
          Le Blog <span class="text-brand-600">Experts</span>
        </h1>
        <p class="text-lg text-gray-500 max-w-2xl mx-auto font-light leading-relaxed">
          Découvrez nos guides techniques, comparatifs et conseils d'experts pour optimiser votre parc médical.
        </p>
      </div>
    </section>

    <!-- Blog Grid -->
    <section class="py-20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div v-if="isLoading" class="flex justify-center py-20">
            <div class="animate-spin rounded-full h-12 w-12 border-4 border-brand-500 border-t-transparent"></div>
        </div>
        
        <div v-else-if="articles.length === 0" class="text-center py-20">
            <div class="text-gray-400 mb-4 text-6xl">📝</div>
            <h3 class="text-xl font-bold text-gray-900">Aucun article de blog pour le moment</h3>
            <p class="text-gray-500 mt-2">Revenez bientôt pour de nouveaux guides.</p>
        </div>

        <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div v-for="article in articles" :key="article.id" class="group flex flex-col bg-white rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-500 overflow-hidden">
             <!-- Article Image -->
              <div class="aspect-[16/10] overflow-hidden bg-gray-50 relative">
                <img 
                    :src="getImageUrl(article)" 
                    :alt="article.title" 
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-1000"
                    @error="(e: any) => e.target.src = '/images/blog_placeholder.png'"
                />
                <div v-if="article.product" class="absolute top-4 left-4 bg-white/90 backdrop-blur px-3 py-1 rounded-full text-[10px] font-bold text-brand-700 uppercase tracking-wider border border-brand-100 shadow-sm">
                    {{ article.product.name }}
                </div>
             </div>
             
             <div class="p-8 flex flex-col flex-grow">
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ new Date(article.created_at).toLocaleDateString('fr-FR', { month: 'long', day: 'numeric', year: 'numeric' }) }}</span>
                    <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                    <span class="text-[10px] font-bold text-brand-600 uppercase tracking-widest">{{ article.category === 'news' ? 'Actualité' : 'Blog' }}</span>
                </div>
                
                <h3 class="text-xl font-bold text-gray-900 mb-4 group-hover:text-brand-600 transition-colors line-clamp-2">
                    {{ article.title }}
                </h3>
                
                <p class="text-gray-500 text-sm leading-relaxed font-light mb-8 line-clamp-3">
                    {{ article.meta_description }}
                </p>
                
                <div class="mt-auto">
                    <router-link :to="{ name: 'blog-detail', params: { slug: article.slug } }" class="inline-flex items-center gap-2 text-sm font-bold text-gray-900 group-hover:text-brand-600 transition-colors uppercase tracking-widest">
                        Lire la suite
                        <svg class="w-4 h-4 transform group-hover:translate-x-2 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </router-link>
                </div>
             </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.text-brand-600 { color: #26a69a; }
.text-brand-700 { color: #00796b; }
.bg-brand-600 { background-color: #26a69a; }
.bg-brand-500 { background-color: #4db6ac; }
.bg-brand-100 { background-color: #e0f2f1; }
.bg-brand-50 { background-color: #f0f9f8; }
.border-brand-500 { border-color: #4db6ac; }
.border-brand-100 { border-color: #e0f2f1; }
</style>
