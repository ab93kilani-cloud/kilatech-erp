<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'

const route = useRoute()
const router = useRouter()
const isEditing = computed(() => route.params.id !== undefined)
const isLoading = ref(false)
const isGenerating = ref(false)
const isSubmitting = ref(false)
const isUploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const products = ref<any[]>([])

const article = ref({
    title: '',
    slug: '',
    content: '',
    product_id: null as number | null,
    status: 'draft',
    category: 'blog',
    image: '',
    meta_title: '',
    meta_description: '',
})

const newsNotes = ref('')
const isGeneratingNews = ref(false)

const fetchProducts = async () => {
    const res = await api.get('/products')
    products.value = res.data
}

const fetchArticle = async () => {
    if (!isEditing.value) return
    isLoading.value = true
    try {
        const res = await api.get(`/articles/${route.params.id}`)
        article.value = res.data
    } catch (e) {
        alert('Erreur lors du chargement')
    } finally {
        isLoading.value = false
    }
}

const generateWithAi = async () => {
    if (!article.value.product_id) {
        alert('Veuillez sélectionner un produit pour guider l\'IA.')
        return
    }
    isGenerating.value = true
    try {
        const res = await api.post('/articles/generate', { product_id: article.value.product_id })
        const data = res.data
        article.value.title = data.title
        article.value.content = data.content
        article.value.meta_title = data.meta_title
        article.value.meta_description = data.meta_description
        article.value.slug = data.slug
    } catch (e) {
        alert('Erreur lors de la génération IA')
    } finally {
        isGenerating.value = false
    }
}

const handleFileUpload = async (event: any) => {
    const file = event.target.files[0]
    if (!file) return

    const formData = new FormData()
    formData.append('image', file)

    isUploading.value = true
    try {
        const res = await api.post('/upload', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        article.value.image = res.data.url
    } catch (e) {
        alert('Erreur lors de l\'upload de l\'image')
    } finally {
        isUploading.value = false
    }
}

const generateNewsWithAi = async () => {
    if (newsNotes.value.length < 10) {
        alert('Veuillez saisir plus de détails (min 10 caractères) pour l\'actualité.')
        return
    }
    isGeneratingNews.value = true
    try {
        const res = await api.post('/articles/generate-news', { notes: newsNotes.value })
        const data = res.data
        article.value.title = data.title
        article.value.content = data.content
        article.value.meta_title = data.meta_title
        article.value.meta_description = data.meta_description
        article.value.slug = data.slug
        article.value.category = 'news'
    } catch (e) {
        alert('Erreur lors de la génération de l\'actualité')
    } finally {
        isGeneratingNews.value = false
    }
}

    const saveArticle = async () => {
    isSubmitting.value = true
    try {
        if (isEditing.value) {
            await api.put(`/articles/${route.params.id}`, article.value)
        } else {
            await api.post('/articles', article.value)
        }
        router.push({ name: 'marketing' })
    } catch (e: any) {
        const msg = e.response?.data?.message || 'Erreur lors de l\'enregistrement'
        alert(msg)
        console.error(e)
    } finally {
        isSubmitting.value = false
    }
}

onMounted(() => {
    fetchProducts()
    fetchArticle()
    
    // Set default category from query param if creating new
    if (!isEditing.value && route.query.type) {
        article.value.category = route.query.type as string
    }
})
</script>

<template>
  <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ isEditing ? 'Modifier l\'Article' : 'Rédiger un Article' }}</h1>
        <p class="text-sm text-gray-500">Rédigez ou générez un article avec l'IA pour booster votre marketing.</p>
      </div>
      <router-link :to="{ name: 'marketing' }" class="text-sm font-medium text-gray-600 hover:text-gray-900">
        Annuler
      </router-link>
    </div>

    <div v-if="isLoading" class="text-center py-12 text-gray-500">Chargement...</div>

    <form v-else @submit.prevent="saveArticle" class="space-y-6">
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <!-- AI Helper Section (BLOG) -->
        <div v-if="article.category === 'blog'" class="p-6 bg-indigo-50 border-b border-indigo-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center">
                    ✨
                </div>
                <div>
                    <h3 class="text-sm font-bold text-indigo-900">Générateur d'Article IA (Produit)</h3>
                    <p class="text-xs text-indigo-700">Sélectionnez un produit et laissez l'IA écrire pour vous.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <select v-model="article.product_id" class="text-xs rounded-md border-indigo-200 focus:ring-indigo-500 py-1.5 min-w-[200px]">
                    <option :value="null">-- Choisir un produit --</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <button type="button" @click="generateWithAi" :disabled="isGenerating || !article.product_id" class="bg-indigo-600 text-white px-4 py-1.5 rounded-md text-xs font-bold hover:bg-indigo-700 shadow-sm disabled:opacity-50">
                    {{ isGenerating ? 'Génération...' : 'Lancer l\'IA Blog' }}
                </button>
            </div>
        </div>

        <!-- NEW: AI News Helper Section (ACTU) -->
        <div v-if="article.category === 'news'" class="p-6 bg-emerald-50 border-b border-emerald-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center">
                    📢
                </div>
                <div>
                    <h3 class="text-sm font-bold text-emerald-900">Générateur d'Actualités Société</h3>
                    <p class="text-xs text-emerald-700">Décrivez l'actu en 1 phrase et l'IA rédigera l'article pro.</p>
                </div>
            </div>
            <div class="flex items-center gap-3 flex-1 max-w-md ml-8">
                <input v-model="newsNotes" type="text" placeholder="Ex: On a signé un partenariat avec General Electric..." class="flex-1 text-xs rounded-md border-emerald-200 focus:ring-emerald-500 py-1.5">
                <button type="button" @click="generateNewsWithAi" :disabled="isGeneratingNews || newsNotes.length < 10" class="bg-emerald-600 text-white px-4 py-1.5 rounded-md text-xs font-bold hover:bg-emerald-700 shadow-sm disabled:opacity-50 whitespace-nowrap">
                    {{ isGeneratingNews ? 'RÉDACTION...' : 'GÉNÉRER ACTU' }}
                </button>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <!-- General Info -->
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Titre de l'Article *</label>
                    <input v-model="article.title" required type="text" class="w-full rounded-md border-gray-300 focus:border-brand-500 focus:ring-brand-500 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">URL (Slug)</label>
                    <input v-model="article.slug" type="text" placeholder="titre-de-l-article" class="w-full rounded-md border-gray-300 bg-gray-50 text-gray-500 text-sm italic" readonly>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Image de l'Article</label>
                    <div class="flex gap-4 items-start">
                        <div class="flex-1 space-y-3">
                            <!-- File Upload -->
                            <div class="flex items-center gap-2">
                                <button type="button" @click="fileInput?.click()" :disabled="isUploading" class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-all shadow-sm">
                                    <svg v-if="!isUploading" class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                    <span v-else class="animate-spin w-4 h-4 border-2 border-brand-500 border-t-transparent rounded-full"></span>
                                    {{ isUploading ? 'Téléversement...' : 'Choisir une photo locale' }}
                                </button>
                                <input ref="fileInput" type="file" @change="handleFileUpload" accept="image/*" class="hidden">
                                <span class="text-[10px] text-gray-400">ou</span>
                            </div>
                            
                            <!-- URL Input -->
                            <input v-model="article.image" type="text" placeholder="Coller l'URL d'une image (ex: https://images.unsplash.com/...)" class="w-full rounded-md border-gray-300 focus:border-brand-500 focus:ring-brand-500 shadow-sm text-sm">
                            <p class="text-[10px] text-gray-400">Laissez vide pour utiliser l'image par défaut.</p>
                        </div>
                        <div v-if="article.image" class="relative group w-24 h-20 rounded-lg border border-gray-200 overflow-hidden bg-gray-50 flex-shrink-0 shadow-md">
                            <img :src="article.image" class="w-full h-full object-cover" @error="(e: any) => e.target.src = '/images/blog_placeholder.png'">
                            <button type="button" @click="article.image = ''" class="absolute top-1 right-1 bg-red-500 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contenu (HTML)</label>
                    <p class="text-[10px] text-gray-400 mb-2">Supporte les balises standards (h2, p, strong, ul, li).</p>
                    <textarea v-model="article.content" required rows="12" class="w-full rounded-md border-gray-300 focus:border-brand-500 focus:ring-brand-500 shadow-sm font-mono text-sm"></textarea>
                </div>
                <div class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
                    <input v-model="article.category" type="hidden">
                </div>
            </div>

            <!-- SEO Config -->
            <div class="pt-6 border-t border-gray-100">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    🔍 Configuration SEO {{ article.category === 'blog' ? 'Blog' : 'Actualité' }}
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Meta Title {{ article.category === 'blog' ? 'Blog' : 'Actualité' }}</label>
                        <input v-model="article.meta_title" type="text" class="w-full rounded-md border-gray-300 focus:border-brand-500 focus:ring-brand-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-indigo-600 uppercase mb-1">Statut de Publication (CRITIQUE)</label>
                        <select v-model="article.status" class="w-full rounded-md border-indigo-200 bg-indigo-50/30 focus:border-brand-500 focus:ring-brand-500 text-sm font-bold">
                            <option value="draft">📁 Brouillon (Invisible pour les clients)</option>
                            <option value="published">🌐 Publié (Visible sur le site !)</option>
                        </select>
                        <p class="text-[10px] text-indigo-400 mt-1">L'article doit être "Publié" pour apparaître sur la page Actualités.</p>
                    </div>
                    <div class="col-span-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Meta Description {{ article.category === 'blog' ? 'Blog' : 'Actualité' }}</label>
                        <textarea v-model="article.meta_description" rows="2" class="w-full rounded-md border-gray-300 focus:border-brand-500 focus:ring-brand-500 text-sm"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
          <router-link :to="{ name: 'marketing' }" class="px-6 py-2 rounded-xl text-sm font-bold text-gray-600 hover:text-gray-900 transition-colors">Retour</router-link>
          <button type="submit" :disabled="isSubmitting" class="bg-brand-600 text-white px-8 py-2 rounded-xl text-sm font-bold shadow-lg shadow-brand-600/20 hover:bg-brand-700 transition-all">
            {{ isSubmitting ? 'Enregistrement...' : 'Confirmer / Enregistrer' }}
          </button>
        </div>
      </div>
    </form>
  </div>
</template>
