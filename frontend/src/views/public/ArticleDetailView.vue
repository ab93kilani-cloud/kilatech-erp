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
    if (item.image) return item.image
    if (item.product && item.product.image_path) {
        return getStorageUrl(item.product.image_path)
    }
    return '/images/blog_placeholder.png'
}

const fetchArticle = async () => {
    try {
        const res = await publicApi.get(`/public/articles/${route.params.slug}`)
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
  <div class="bg-white min-h-screen pb-20">
    <div v-if="isLoading" class="flex justify-center py-40">
        <div class="animate-spin rounded-full h-12 w-12 border-4 border-brand-500 border-t-transparent"></div>
    </div>

    <div v-else-if="!article" class="text-center py-40">
        <h2 class="text-2xl font-bold text-gray-900">Article non trouvé</h2>
        <router-link :to="{ name: 'news' }" class="text-brand-600 mt-4 inline-block font-bold">Retour aux actualités</router-link>
    </div>

    <article v-else class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <!-- Header -->
        <header class="mb-12 text-center">
            <div class="flex items-center justify-center gap-3 mb-6">
                <span class="text-xs font-bold text-brand-600 uppercase tracking-widest bg-brand-50 px-3 py-1 rounded-full">Blog Kilatech</span>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">{{ new Date(article.created_at).toLocaleDateString('fr-FR', { month: 'long', day: 'numeric', year: 'numeric' }) }}</span>
            </div>
            <h1 class="text-3xl md:text-5xl font-black text-gray-900 leading-tight mb-8">
                {{ article.title }}
            </h1>
            <div v-if="article.product" class="inline-flex items-center gap-3 p-4 bg-gray-50 rounded-2xl border border-gray-100">
                <span class="text-sm text-gray-500">Produit associé :</span>
                <span class="text-sm font-bold text-gray-900">{{ article.product.name }}</span>
            </div>
        </header>

        <!-- Image -->
        <div class="rounded-3xl overflow-hidden shadow-2xl mb-16 border border-gray-100 aspect-video bg-gray-50">
            <img 
                :src="getImageUrl(article)" 
                :alt="article.title" 
                class="w-full h-full object-cover"
                @error="(e: any) => e.target.src = '/images/blog_placeholder.png'"
            >
        </div>

        <!-- Content -->
        <div class="prose prose-lg prose-indigo mx-auto text-gray-700 font-light leading-relaxed blog-content" v-html="article.content">
        </div>

        <hr class="my-16 border-gray-100">

        <!-- Footer / Share / Back -->
        <div class="flex flex-col md:flex-row justify-between items-center gap-8">
            <router-link :to="{ name: 'news' }" class="flex items-center gap-2 text-gray-500 hover:text-gray-900 transition-colors font-bold uppercase tracking-widest text-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"></path></svg>
                Retour aux articles
            </router-link>
            
            <div class="flex gap-4">
                <button class="p-3 rounded-full bg-gray-50 hover:bg-indigo-50 text-gray-400 hover:text-indigo-600 transition-all border border-gray-100">
                    <span class="sr-only">LinkedIn</span>
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.761 0 5-2.239 5-5v-14c0-2.761-2.239-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" /></svg>
                </button>
            </div>
        </div>
    </article>
  </div>
</template>

<style>
/* Styling for the HTML content injected via v-html */
.blog-content h2 {
    font-size: 1.875rem;
    font-weight: 800;
    color: #111827;
    margin-top: 2.5rem;
    margin-bottom: 1.25rem;
}
.blog-content h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    margin-top: 2rem;
    margin-bottom: 1rem;
}
.blog-content p {
    margin-bottom: 1.5rem;
}
.blog-content ul {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin-bottom: 1.5rem;
}
.blog-content li {
    margin-bottom: 0.5rem;
}
.blog-content strong {
    font-weight: 700;
    color: #111827;
}
</style>
