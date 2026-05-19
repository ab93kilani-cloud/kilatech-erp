<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import publicApi from '@/plugins/axios-public'
import { useSEO } from '@/composables/useSEO'
import { useConfig } from '@/composables/useConfig'

const { getImageUrl: getStorageUrl } = useConfig()
const { updateSEO } = useSEO()

const route = useRoute()
const article = ref<any>(null)
const isLoading = ref(true)

const getImageUrl = (item: any) => {
    if (!item) return '/images/blog_placeholder.png'
    if (item.product && item.product.image_path) {
        return getStorageUrl(item.product.image_path)
    }
    if (item.image) return getStorageUrl(item.image)
    return '/images/blog_placeholder.png'
}

const fetchArticle = async () => {
    try {
        const res = await publicApi.get(`/seo-v2/blog-detail/${route.params.slug}?api_token=KILA-SEO-PROD-2026-X9`)
        article.value = res.data
        // Update document metadata for SEO
        updateSEO({
            title: article.value.meta_title || article.value.title,
            description: article.value.meta_description || article.value.content.substring(0, 160).replace(/<[^>]*>/g, ''),
            image: getImageUrl(article.value)
        })
    } catch (e) {
        console.error('Failed to load article')
    } finally {
        isLoading.value = false
    }
}

onMounted(fetchArticle)
</script>

<template>
  <div class="bg-slate-50/50 min-h-screen pb-20 font-sans antialiased text-slate-700">
    <div v-if="isLoading" class="flex justify-center py-40">
        <div class="animate-spin rounded-full h-12 w-12 border-4 border-teal-600 border-t-transparent"></div>
    </div>

    <div v-else-if="!article" class="text-center py-40">
        <h2 class="text-2xl font-bold text-gray-900">Article non trouvé</h2>
        <router-link :to="{ name: 'news' }" class="text-teal-600 mt-4 inline-block font-bold">Retour aux actualités</router-link>
    </div>

    <article v-else class="w-full">
        <!-- 1. PREMIUM HEADER BANNER (Soft Teal & Indigo Gradient) -->
        <div class="bg-gradient-to-br from-teal-50 via-teal-50/30 to-sky-50 border-b border-teal-100/40 py-12 md:py-16 relative overflow-hidden">
            <div class="absolute -right-20 -top-20 w-80 h-80 bg-teal-200/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-20 -bottom-20 w-80 h-80 bg-sky-200/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row items-center justify-between gap-8 md:gap-16">
                    
                    <!-- Left Content Deck -->
                    <div class="flex-1 space-y-4 text-left">
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] font-black text-white bg-teal-700 uppercase tracking-widest px-3 py-1.5 rounded-lg shadow-sm">
                                {{ article.category === 'blog' ? 'Blog Kilatech' : 'Actualités' }}
                            </span>
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">
                                {{ new Date(article.created_at).toLocaleDateString('fr-FR', { month: 'long', day: 'numeric', year: 'numeric' }) }}
                            </span>
                        </div>
                        
                        <h1 class="text-3xl md:text-4xl lg:text-5xl font-black text-slate-950 leading-tight">
                            {{ article.title }}
                        </h1>
                        
                        <div v-if="article.product" class="inline-flex items-center gap-2 p-2 px-4 bg-white/90 backdrop-blur-sm rounded-xl border border-teal-100 shadow-sm text-xs transition-colors hover:bg-white">
                            <span class="text-gray-400 font-medium">Matériel Médical Associé :</span>
                            <span class="font-extrabold text-teal-800">{{ article.product.name }}</span>
                        </div>
                    </div>

                    <!-- Right Floated Premium Circular Image Frame -->
                    <div class="w-48 h-48 md:w-64 md:h-64 rounded-full overflow-hidden shadow-2xl border-4 border-white bg-white/70 flex-shrink-0 flex items-center justify-center relative transform hover:scale-[1.03] transition-all duration-300">
                        <img 
                            :src="getImageUrl(article)" 
                            :alt="article.title" 
                            class="w-full h-full object-cover"
                            @error="(e: any) => e.target.src = '/images/blog_placeholder.png'"
                        >
                    </div>

                </div>
            </div>
        </div>

        <!-- 2. MAIN CONTENT WRAPPER (Broad Max-w-6xl for beautiful grids) -->
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            
            <!-- Injected Rich HTML Content -->
            <div 
              class="prose prose-lg prose-indigo mx-auto text-slate-700 font-light leading-relaxed blog-content max-w-none" 
              v-html="article.content"
            ></div>

            <hr class="my-16 border-slate-100">

            <!-- Premium Footer and Sharing Panel -->
            <div class="flex flex-col md:flex-row justify-between items-center gap-8 bg-white p-6 rounded-3xl border border-slate-200/60 shadow-sm">
                <router-link :to="{ name: 'news' }" class="flex items-center gap-2 text-slate-500 hover:text-slate-900 transition-colors font-bold uppercase tracking-widest text-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"></path></svg>
                    Retour aux articles
                </router-link>
                
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">Partager cet article :</span>
                    <button class="p-2.5 rounded-xl bg-slate-50 hover:bg-teal-50 text-slate-400 hover:text-teal-600 transition-all border border-slate-100" title="Partager sur LinkedIn">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.761 0 5-2.239 5-5v-14c0-2.761-2.239-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" /></svg>
                    </button>
                </div>
            </div>
            
        </div>
    </article>
  </div>
</template>

<style>
/* Reset Prose Max Width on our custom blog contents to let grids expand */
.prose.blog-content {
    max-width: none !important;
}

/* Custom typographic margins and weights matching live design */
.blog-content h2 {
    font-size: 1.75rem !important;
    font-weight: 800 !important;
    color: #0f172a !important;
    margin-top: 3rem !important;
    margin-bottom: 1.25rem !important;
    line-height: 1.35 !important;
}

.blog-content h3 {
    font-size: 1.35rem !important;
    font-weight: 800 !important;
    color: #1e293b !important;
    margin-top: 2.25rem !important;
    margin-bottom: 1rem !important;
    line-height: 1.4 !important;
}

.blog-content p {
    margin-bottom: 1.25rem !important;
    line-height: 1.75 !important;
    color: #334155 !important;
}

.blog-content ul {
    list-style-type: disc !important;
    padding-left: 1.5rem !important;
    margin-bottom: 1.5rem !important;
}

.blog-content li {
    margin-bottom: 0.5rem !important;
    color: #475569 !important;
}

.blog-content strong {
    font-weight: 700 !important;
    color: #0f172a !important;
}
</style>
