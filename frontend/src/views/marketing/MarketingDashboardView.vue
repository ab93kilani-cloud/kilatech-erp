<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import api from '@/plugins/axios'
import { useConfig } from '@/composables/useConfig'

const { getImageUrl: getStorageUrl } = useConfig()

const products = ref<any[]>([])
const isLoading = ref(true)
const isGenerating = ref<number | null>(null)
const diagnostic = ref<any | null>(null)
const isDiagnosing = ref(false)
const isEditing = ref(false)
const editForm = ref<any>({})
const activeTab = ref('seo') // 'seo', 'social', 'blog', 'news', 'opportunities'
const activeSocialSubTab = ref('linkedin') // 'linkedin', 'facebook', 'instagram'
const showCopySuccess = ref(false)
const articles = ref<any[]>([])
const isLoadingArticles = ref(false)
const missingSearches = ref<any[]>([])

const getImageUrl = (path: string | null) => {
  if (!path) return undefined
  return getStorageUrl(path)
}

const publishToSocial = async (text: string, platform: string) => {
  if (!text) return
  
  try {
    await navigator.clipboard.writeText(text)
    showCopySuccess.value = true
    setTimeout(() => { showCopySuccess.value = false }, 3000)
    
    const urls: any = {
      linkedin: 'https://www.linkedin.com/company/106067139/admin/page-posts/published/?share=true',
      facebook: 'https://www.facebook.com/profile.php?id=61578878744600',
      instagram: 'https://www.instagram.com/'
    }
    
    window.open(urls[platform], '_blank')
  } catch (err) {
    console.error('Failed to copy text', err)
  }
}

const calculateSocialScore = (product: any) => {
  const checks = []
  let score = 100

  const posts = {
    linkedin: product.social_linkedin_post || '',
    facebook: product.social_facebook_post || '',
    instagram: product.social_instagram_post || ''
  }

  // LinkedIn Checks
  if (posts.linkedin.length < 100) {
    checks.push({ id: 'li_short', status: 'warning', label: 'LinkedIn trop court', advice: 'Visez 200-400 caractères pour un impact pro.' })
    score -= 10
  }
  if (!(posts.linkedin.includes('#'))) {
    checks.push({ id: 'li_no_hash', status: 'warning', label: 'LI manque de hashtags', advice: 'Ajoutez 3-5 hashtags métiers.' })
    score -= 5
  }

  // FB Checks
  if (posts.facebook.length > 0 && !(posts.facebook.match(/[\uD800-\uDBFF][\uDC00-\uDFFF]/))) {
    checks.push({ id: 'fb_no_emoji', status: 'warning', label: 'FB manque d\'emojis', advice: 'Les emojis augmentent l\'engagement sur Facebook !' })
    score -= 5
  }

  // IG Checks
  const igHashtags = (posts.instagram.match(/#/g) || []).length
  if (posts.instagram.length > 0 && igHashtags < 10) {
    checks.push({ id: 'ig_low_hash', status: 'warning', label: 'IG manque de hashtags', advice: 'Visez 15-20 hashtags sur Instagram pour exploser !' })
    score -= 10
  }

  return {
    score: Math.max(0, score),
    health: score > 80 ? 'Excellent' : (score > 50 ? 'Améliorable' : 'Critique'),
    checks
  }
}

const calculateSeoScore = (product: any) => {
  // Prioritize Backend Agent Score if available
  if (product.seo_score !== undefined && product.seo_score !== null && product.seo_audit) {
    const audit = typeof product.seo_audit === 'string' ? JSON.parse(product.seo_audit) : product.seo_audit;
    return {
      score: product.seo_score,
      health: product.seo_score > 80 ? 'Excellent' : (product.seo_score > 50 ? 'Améliorable' : 'Critique'),
      checks: audit.warnings?.map((w: string, i: number) => ({ id: 'warn_'+i, status: 'warning', label: 'Alerte Agent', advice: w })) || []
    }
  }

  const checks = []
  let score = 100

  // Title Check (Using seo_title or legacy meta_title)
  const title = product.seo_title || product.meta_title || ''
  if (title.length === 0) {
    checks.push({ id: 'title_missing', status: 'error', label: 'Titre SEO manquant', advice: 'Ajoutez un titre pour le référencement Google.' })
    score -= 30
  } else if (title.length < 40) {
    checks.push({ id: 'title_short', status: 'warning', label: 'Titre trop court', advice: 'Visez entre 50 et 60 caractères pour plus de visibilité.' })
    score -= 10
  } else if (title.length > 60) {
    checks.push({ id: 'title_long', status: 'warning', label: 'Titre trop long', advice: 'Le titre sera coupé sur Google. Réduisez à 60 caractères.' })
    score -= 10
  } else {
    checks.push({ id: 'title_ok', status: 'success', label: 'Longueur du titre idéale', advice: 'Parfait pour l\'affichage Google.' })
  }

  // Description Check
  const desc = product.seo_description || product.meta_description || ''
  if (desc.length === 0) {
    checks.push({ id: 'desc_missing', status: 'error', label: 'Description manquante', advice: 'Indispensable pour encourager le clic sur Google-Maroc.' })
    score -= 30
  } else if (desc.length < 120) {
    checks.push({ id: 'desc_short', status: 'warning', label: 'Description trop courte', advice: 'Ajoutez plus de détails sur les bénéfices du produit.' })
    score -= 10
  } else if (desc.length > 160) {
    checks.push({ id: 'desc_long', status: 'warning', label: 'Description trop longue', advice: 'Elle sera tronquée. Restez sous 160 caractères.' })
    score -= 10
  } else {
    checks.push({ id: 'desc_ok', status: 'success', label: 'Description optimisée', advice: 'Excellente longueur pour le CTR.' })
  }

  // Slug Check
  const slug = product.slug || ''
  const nameSlug = (product.name || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')
  if (!slug) {
    checks.push({ id: 'slug_missing', status: 'error', label: 'URL non optimisée', advice: 'Générez un slug pour une URL propre.' })
    score -= 10
  } else if (!slug.includes(nameSlug)) {
    checks.push({ id: 'slug_mismatch', status: 'warning', label: 'URL peu lisible', advice: 'Le nom du produit devrait apparaître dans l\'URL.' })
    score -= 5
  } else {
    checks.push({ id: 'slug_ok', status: 'success', label: 'Structure d\'URL sémantique', advice: 'Aide les moteurs de recherche à comprendre le contenu.' })
  }

  // Local SEO (GEO)
  const geo = product.local_geo_context || ''
  if (!geo) {
    checks.push({ id: 'geo_missing', status: 'warning', label: 'Géo-référencement absent', advice: 'Ajoutez des villes (Casablanca, Rabat) pour le SEO local.' })
    score -= 5
  } else {
    checks.push({ id: 'geo_ok', status: 'success', label: 'Ciblage local détecté', advice: 'Bon positionnement pour Google Maps et recherches locales.' })
  }

  score = Math.max(0, score)
  return {
    score,
    health: score > 80 ? 'Excellent' : (score > 50 ? 'Améliorable' : 'Critique'),
    checks
  }
}

const cleanedSeoContent = computed(() => {
  let content = editForm.value.seo_content || ''
  if (!content) return ''

  // 1. Fix Image URLs (redirect to storage proxy because of missing symbolic link)
  content = content.replace(/https:\/\/app\.kilatech\.ma\/storage\//g, 'https://app.kilatech.ma/api/storage_proxy.php?path=')
  
  // 2. Fix SEO Hierarchy: h1 -> h2 (since the page already has an H1)
  content = content.replace(/<h1/g, '<h2').replace(/<\/h1>/g, '</h2>')

  return content
})

const currentDiagnostic = computed(() => {
  if (isEditing.value) {
    return activeTab.value === 'seo' ? calculateSeoScore(editForm.value) : calculateSocialScore(editForm.value)
  }
  return diagnostic.value
})

const fetchArticles = async () => {
  isLoadingArticles.value = true
  try {
    const res = await api.get('/articles')
    articles.value = res.data
  } catch (e) {
    console.error('Failed to load articles')
  } finally {
    isLoadingArticles.value = false
  }
}

const deleteArticle = async (id: number) => {
  if (!confirm('Supprimer cet article ?')) return
  try {
    await api.delete(`/articles/${id}`)
    articles.value = articles.value.filter(a => a.id !== id)
  } catch (e) {
    alert('Erreur lors de la suppression')
  }
}

const fetchProducts = async () => {
  isLoading.value = true
  try {
    const res = await api.get('/seo/products', { params: { per_page: 200 } })
    // Secure extraction for paginated or direct array data
    if (res.data && res.data.data) {
        products.value = res.data.data
    } else {
        products.value = Array.isArray(res.data) ? res.data : []
    }
  } catch (e) {
    console.error('SEO fetch failed, using generic products')
    const fallback = await api.get('/products')
    products.value = Array.isArray(fallback.data) ? fallback.data : (fallback.data?.data || [])
  } finally {
    isLoading.value = false
  }
}

const fetchDiagnostic = async (productId: number) => {
  isDiagnosing.value = true
  try {
    const endpoint = activeTab.value === 'seo' ? 'seo-diagnostic' : 'social-diagnostic'
    const res = await api.get(`/products/${productId}/${endpoint}`)
    diagnostic.value = res.data
  } catch (e) {
    console.error('Failed to fetch diagnostic')
  } finally {
    isDiagnosing.value = false
  }
}

const generateMarketing = async (productId: number) => {
  isGenerating.value = productId
  try {
    const res = await api.post(`/products/${productId}/generate-marketing`)
    // Refresh products list to show the "Pending" state if needed
    await fetchProducts() 
    alert(res.data.message || 'Produit envoyé aux agents n8n !')
  } catch (e) {
    alert('Erreur lors de la mise en attente')
  } finally {
    isGenerating.value = null
  }
}

const isRepairing = ref(false)
const repairError = ref<string | null>(null)

const fetchMissingSearches = async () => {
    repairError.value = null
    try {
        const res = await api.get('/missing-searches')
        missingSearches.value = res.data
    } catch (e: any) {
        console.error('Failed to fetch missing searches')
        if (e.response?.status === 500) {
            repairError.value = "La base de données n'est pas à jour (Table manquante)."
        }
    }
}

const runDbRepair = async () => {
    isRepairing.value = true
    try {
        await api.post('/marketing/repair-db')
        alert('Base de données réparée avec succès !')
        fetchMissingSearches()
    } catch (e: any) {
        alert('Erreur lors de la réparation : ' + (e.response?.data?.message || e.message))
    } finally {
        isRepairing.value = false
    }
}

const deleteMissingSearch = async (id: number) => {
    if (!confirm('Marquer cette opportunité comme traitée ?')) return
    try {
        await api.delete(`/missing-searches/${id}`)
        missingSearches.value = missingSearches.value.filter(s => s.id !== id)
    } catch (e) {
        alert('Erreur lors de la suppression')
    }
}

const isAiConfigured = ref(true)

const fetchConfigStatus = async () => {
    try {
        const res = await api.get('/marketing/config-status')
        isAiConfigured.value = res.data.is_configured
    } catch (e) {
        console.error('Failed to fetch config status')
    }
}

onMounted(() => {
    fetchProducts()
    fetchArticles()
    fetchMissingSearches()
    fetchConfigStatus()
})

const counts = computed(() => {
  const total = products.value.length
  const optimized = products.value.filter(p => p.seo_status === 'optimized' || (p.seo_score > 50)).length
  const elite = products.value.filter(p => p.seo_score >= 90).length
  const pending = total - optimized
  return { total, optimized, elite, pending }
})

const selectedProduct = ref<any | null>(null)

const openPreview = async (product: any, socialTab = 'linkedin') => {
  selectedProduct.value = product
  editForm.value = { ...product }
  isEditing.value = false
  diagnostic.value = null
  activeSocialSubTab.value = socialTab
  
  // Fetch full product details to get seo_content if not in list
  try {
    const res = await api.get(`/products/${product.id}`)
    selectedProduct.value = res.data
    editForm.value = { ...res.data }
  } catch (e) {
    console.error('Failed to fetch full product details')
  }
  
  fetchDiagnostic(product.id)
}

watch(activeTab, () => {
  if (selectedProduct.value) {
    fetchDiagnostic(selectedProduct.value.id)
  }
})

const closePreview = () => {
  selectedProduct.value = null
  isEditing.value = false
}

const saveMarketing = async () => {
  if (!selectedProduct.value) return
  
  try {
    const res = await api.put(`/products/${selectedProduct.value.id}`, editForm.value)
    selectedProduct.value = res.data
    fetchProducts()
    fetchDiagnostic(selectedProduct.value.id)
    isEditing.value = false
  } catch (e) {
    alert('Erreur lors de la sauvegarde')
  }
}
</script>

<template>
  <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <!-- Header -->
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
      <div class="mb-4 sm:mb-0">
        <h1 class="text-2xl md:text-3xl text-gray-800 font-bold">Marketing & SEO</h1>
        <p class="text-sm text-gray-500 mt-1">Générez et optimisez automatiquement le contenu marketing de votre catalogue.</p>
      </div>
    </div>

    <!-- AI Config Warning -->
    <div v-if="!isAiConfigured" class="mb-8 p-4 bg-amber-50 border-2 border-amber-200 rounded-2xl flex items-start gap-4 animate-pulse-slow">
        <div class="bg-amber-100 p-2 rounded-lg text-amber-600">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <div>
            <h4 class="text-sm font-black text-amber-900 uppercase">Configuration IA incomplète</h4>
            <p class="text-xs text-amber-700 mt-1 font-medium">La clé <code class="bg-amber-100 px-1 rounded">MARKETING_AI_API_KEY</code> est manquante dans votre fichier <code class="bg-amber-100 px-1 rounded">.env</code>. Le système utilise actuellement un <strong>générateur de démonstration</strong> premium.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
        <div class="text-sm font-medium text-gray-500 mb-1 text-uppercase tracking-wider">Catalogue Total</div>
        <div class="text-3xl font-bold text-gray-900">{{ counts.total }}</div>
      </div>
      <div class="bg-white p-6 rounded-xl shadow-sm border border-brand-100 bg-brand-50/10">
        <div class="text-sm font-medium text-brand-600 mb-1 text-uppercase tracking-wider">⭐ SEO ÉLITE (90%+)</div>
        <div class="text-3xl font-bold text-brand-600">{{ counts.elite }}</div>
      </div>
      <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
        <div class="text-sm font-medium text-emerald-600 mb-1 text-uppercase tracking-wider">Optimisés (>50%)</div>
        <div class="text-3xl font-bold text-emerald-600">{{ counts.optimized }}</div>
      </div>
      <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
        <div class="text-sm font-medium text-amber-600 mb-1 text-uppercase tracking-wider">En attente / Bruts</div>
        <div class="text-3xl font-bold text-amber-600">{{ counts.pending }}</div>
      </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="flex border-b border-gray-200 mb-6 overflow-x-auto">
      <button 
        @click="activeTab = 'seo'"
        :class="activeTab === 'seo' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
        class="whitespace-nowrap py-4 px-6 border-b-2 font-bold text-sm transition-all text-sm"
      >
        Optimisation SEO
      </button>
      <button 
        @click="activeTab = 'social'"
        :class="activeTab === 'social' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
        class="whitespace-nowrap py-4 px-6 border-b-2 font-bold text-sm transition-all text-sm"
      >
        Réseaux Sociaux
      </button>
      <button 
        @click="activeTab = 'blog'"
        :class="activeTab === 'blog' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
        class="whitespace-nowrap py-4 px-6 border-b-2 font-bold text-sm transition-all text-sm"
      >
        Blog
      </button>
      <button 
        @click="activeTab = 'news'"
        :class="activeTab === 'news' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
        class="whitespace-nowrap py-4 px-6 border-b-2 font-bold text-sm transition-all text-sm"
      >
        Actualités
      </button>
      <button 
        @click="activeTab = 'opportunities'"
        :class="activeTab === 'opportunities' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
        class="whitespace-nowrap py-4 px-6 border-b-2 font-bold text-sm transition-all flex items-center gap-2 text-sm"
      >
        Opportunités
        <span v-if="missingSearches.length > 0" class="bg-orange-100 text-orange-600 px-1.5 py-0.5 rounded-full text-[10px] font-bold">{{ missingSearches.length }}</span>
      </button>
    </div>

    <!-- Main Content Area -->
    <div class="bg-white shadow-sm border border-gray-200 rounded-xl overflow-hidden">
      <!-- SEO/Social Header -->
      <div v-if="activeTab === 'seo' || activeTab === 'social'" class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
        <h2 class="text-lg font-semibold text-gray-800">
            {{ activeTab === 'seo' ? 'Gestion des Métadonnées Google' : 'Gestion des Publications Sociales' }}
        </h2>
        <button @click="fetchProducts" class="text-sm text-brand-600 hover:text-brand-900 flex items-center gap-1">
          Actualiser
        </button>
      </div>

      <!-- Blog/News Header -->
      <div v-if="activeTab === 'blog' || activeTab === 'news'" class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
        <h2 class="text-lg font-semibold text-gray-800">
            {{ activeTab === 'blog' ? 'Gestion du Blog' : 'Gestion des Actualités' }}
        </h2>
        <div class="flex items-center gap-4">
            <button @click="fetchArticles" class="text-sm text-brand-600 hover:text-brand-900 flex items-center gap-1">
                Actualiser
            </button>
            <router-link :to="{ name: 'article-create', query: { type: activeTab === 'blog' ? 'blog' : 'news' } }" class="bg-brand-600 text-white px-4 py-1.5 rounded-lg text-xs font-bold hover:bg-brand-700 transition-all flex items-center gap-2">
                Nouvel Article {{ activeTab === 'blog' ? 'Blog' : 'Actu' }}
            </router-link>
        </div>
      </div>

      <div class="overflow-x-auto">
        <!-- Products Table (SEO / Social) -->
        <table v-if="activeTab === 'seo' || activeTab === 'social'" class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
              <th v-if="activeTab === 'seo'" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qualité SEO</th>
              <th v-if="activeTab === 'seo'" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance GSC</th>
              <th v-if="activeTab === 'social'" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider text-sm">Santé Social</th>
              <th v-if="activeTab === 'social'" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider text-sm">Réseaux Sociaux</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider text-sm">Actions IA</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="isLoading" v-for="i in 3" :key="'loader-'+i" class="animate-pulse">
              <td class="px-6 py-4 whitespace-nowrap"><div class="h-4 bg-gray-200 rounded w-48"></div></td>
              <td class="px-6 py-4 whitespace-nowrap"><div class="h-4 bg-gray-200 rounded w-24"></div></td>
              <td class="px-6 py-4 whitespace-nowrap"><div class="h-4 bg-gray-200 rounded w-32"></div></td>
              <td class="px-6 py-4 whitespace-nowrap text-right"><div class="h-8 bg-gray-200 rounded w-24 ml-auto"></div></td>
            </tr>
            <tr v-else v-for="product in products" :key="product.id" @click="openPreview(product)" class="hover:bg-gray-50/80 transition-colors cursor-pointer">
              <td class="px-6 py-4">
                <div class="flex flex-col">
                  <span class="text-sm font-bold text-gray-900">{{ product.name }}</span>
                  <span class="text-xs text-gray-500">{{ product.category }}</span>
                </div>
              </td>
              <td v-if="activeTab === 'seo'" class="px-6 py-4 whitespace-nowrap">
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-[10px] font-black text-gray-400 uppercase">Score Agent</span>
                        <div class="flex items-center gap-1">
                            <span v-if="(product.seo_score || 0) >= 90" class="text-[10px] bg-brand-100 text-brand-700 px-1.5 py-0.5 rounded font-black">ELITE</span>
                            <span class="text-[10px] font-black" :class="{
                                'text-emerald-600': (product.seo_score || 0) > 80,
                                'text-amber-600': (product.seo_score || 0) <= 80 && (product.seo_score || 0) > 50,
                                'text-red-600': (product.seo_score || 0) <= 50
                            }">
                                {{ product.seo_score || 0 }}%
                            </span>
                        </div>
                    </div>
                </div>
              </td>
              <td v-if="activeTab === 'seo'" class="px-6 py-4 whitespace-nowrap">
                <div v-if="product.gsc_position" class="flex flex-col gap-1">
                    <span class="text-[11px] font-black text-gray-700">Pos. {{ product.gsc_position }}</span>
                    <span class="text-[9px] font-bold text-gray-400 uppercase">CTR: {{ product.gsc_ctr }}% | {{ product.gsc_impressions }} Imp.</span>
                </div>
                <div v-else class="text-[10px] text-gray-300 italic">Pas de données GSC</div>
              </td>
              <td v-if="activeTab === 'social'" class="px-6 py-4 whitespace-nowrap">
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center justify-between gap-4 text-sm">
                        <span class="text-[10px] font-bold text-gray-400 uppercase">Qualite Sociale</span>
                        <span class="text-[10px] font-black" :class="{
                            'text-emerald-600': calculateSocialScore(product).score > 80,
                            'text-amber-600': calculateSocialScore(product).score <= 80 && calculateSocialScore(product).score > 50,
                            'text-red-600': calculateSocialScore(product).score <= 50
                        }">
                            {{ calculateSocialScore(product).score }}%
                        </span>
                    </div>
                </div>
              </td>
              <td v-if="activeTab === 'social'" class="px-6 py-4 whitespace-nowrap text-sm">
                <div class="flex gap-2" @click.stop>
                  <button @click="openPreview(product, 'linkedin')" class="w-6 h-6 flex items-center justify-center rounded bg-blue-50 text-blue-600 text-[10px] font-black">in</button>
                  <button @click="openPreview(product, 'facebook')" class="w-6 h-6 flex items-center justify-center rounded bg-blue-100 text-blue-700 text-[10px] font-black">f</button>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button @click.stop="generateMarketing(product.id)" :disabled="isGenerating === product.id" class="bg-brand-50 text-brand-700 px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-brand-100 transition-colors">
                  {{ isGenerating === product.id ? 'IA...' : 'Optimiser' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Articles Table (Blog / News) -->
        <table v-if="activeTab === 'blog' || activeTab === 'news'" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider text-sm">Titre / URL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider text-center text-sm">Section</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider text-right text-sm">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="isLoadingArticles">
                    <td colspan="3" class="px-6 py-12 text-center text-gray-400 font-medium">Chargement...</td>
                </tr>
                <tr v-else-if="articles.filter(a => a.category === (activeTab === 'blog' ? 'blog' : 'news')).length === 0">
                    <td colspan="3" class="px-6 py-12 text-center text-gray-400 font-medium">Aucun article.</td>
                </tr>
                <tr v-for="article in articles.filter(a => a.category === (activeTab === 'blog' ? 'blog' : 'news'))" :key="article.id" class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-gray-900">{{ article.title }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border">
                            {{ article.category === 'news' ? 'ACTUALITE' : 'BLOG' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                        <div class="flex justify-end gap-2">
                             <button @click="deleteArticle(article.id)" class="text-red-600 hover:text-red-900 bg-red-50 p-2 rounded-lg border border-red-100 transition-colors text-xs font-bold">
                                SUPPRIMER
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
      </div>

      <!-- TAB: OPPORTUNITIES (Missing Searches) -->
      <div v-if="activeTab === 'opportunities'" class="p-8 animate-fade-in bg-white">
          <div class="mb-8 border-b border-gray-100 pb-6 flex justify-between items-center">
              <div>
                  <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">Opportunites de Catalogue</h3>
                  <p class="text-sm text-gray-500 mt-1">Produits recherches par vos clients sur la vitrine mais absents de votre inventaire actuel.</p>
              </div>
              <button @click="fetchMissingSearches" class="flex items-center gap-2 text-xs font-bold text-brand-600 hover:text-brand-900 bg-brand-50 px-3 py-1.5 rounded-lg transition-colors">
                  Actualiser
              </button>
          </div>

          <div v-if="repairError" class="text-center py-20 bg-red-50 rounded-[2rem] border-2 border-dashed border-red-200">
              <div class="max-w-md mx-auto">
                  <div class="text-red-500 mb-4">
                      <svg class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                  </div>
                  <h4 class="text-lg font-black text-red-900 uppercase mb-2">Structure de données manquante</h4>
                  <p class="text-sm text-red-700 mb-6 font-medium">{{ repairError }} Voulez-vous que Kilatech tente une réparation automatique ?</p>
                  <button @click="runDbRepair" :disabled="isRepairing" class="bg-red-600 text-white font-black px-8 py-3 rounded-2xl shadow-xl hover:bg-red-700 transition-all flex items-center gap-3 mx-auto disabled:opacity-50">
                      <span v-if="isRepairing" class="animate-spin text-lg">⚙️</span>
                      {{ isRepairing ? 'Réparation en cours...' : 'RÉPARER LA BASE DE DONNÉES' }}
                  </button>
              </div>
          </div>

          <div v-else-if="missingSearches.length === 0" class="text-center py-20 bg-gray-50/50 rounded-[2rem] border-2 border-dashed border-gray-200">
              <p class="text-gray-400 font-bold italic">Aucune opportunite detectee pour le moment.</p>
          </div>

          <div v-else class="grid grid-cols-1 gap-6">
              <div class="overflow-hidden border border-gray-100 rounded-2xl shadow-sm">
                  <table class="min-w-full divide-y divide-gray-200">
                      <thead class="bg-gray-50/80 text-sm">
                          <tr>
                              <th class="px-8 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Expression recherchee</th>
                              <th class="px-8 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Volume</th>
                              <th class="px-8 py-4 text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">Dernier signalement</th>
                              <th class="px-8 py-4 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Gestion</th>
                          </tr>
                      </thead>
                      <tbody class="bg-white divide-y divide-100 text-sm font-medium">
                          <tr v-for="search in missingSearches" :key="search.id" class="hover:bg-orange-50/40 group">
                              <td class="px-8 py-5">
                                  <div class="text-base font-black text-gray-800 uppercase italic">"{{ search.query }}"</div>
                              </td>
                              <td class="px-8 py-5 text-center">
                                  <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-black bg-orange-100 text-orange-700">
                                      {{ search.count }} REQUETES
                                  </span>
                              </td>
                              <td class="px-8 py-5 text-sm font-bold text-gray-500">
                                  {{ search.last_seen_at }}
                              </td>
                              <td class="px-8 py-5 text-right text-sm">
                                  <button @click="deleteMissingSearch(search.id)" class="text-red-300 hover:text-red-600 transition-colors p-2 text-xs font-bold" title="Marquer comme traite">
                                      Traitee
                                  </button>
                              </td>
                          </tr>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>
    </div>

    <!-- Modal de Preview -->
    <div v-if="selectedProduct" class="fixed inset-0 z-[99] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
          <div class="flex items-center gap-4">
             <div class="w-10 h-10 rounded-full bg-brand-50 flex items-center justify-center text-brand-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
             </div>
             <div>
                <h3 class="text-xl font-black text-gray-900">{{ selectedProduct.name }}</h3>
                <p class="text-xs text-gray-500 uppercase tracking-widest font-bold">{{ selectedProduct.category }}</p>
             </div>
          </div>
          <button @click="closePreview" class="text-gray-400 hover:text-gray-900 transition-colors bg-gray-100 p-2 rounded-full">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        
        <div class="flex-1 overflow-y-auto p-8">
            <div class="flex flex-col lg:flex-row gap-10">
                <!-- Preview Canvas -->
                <div class="lg:w-1/2 space-y-6">
                    <div class="flex justify-between items-end">
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest">Apercu {{ activeTab === 'seo' ? 'Google' : activeSocialSubTab }}</h4>
                        <!-- Score Badge -->
                        <div v-if="currentDiagnostic" class="flex items-center gap-2">
                            <span class="text-[10px] font-black text-gray-500 uppercase">Qualite</span>
                            <span class="px-3 py-1 rounded-full text-[10px] font-black border-2" :class="{
                                'bg-emerald-50 text-emerald-700 border-emerald-100': currentDiagnostic.score > 80,
                                'bg-amber-50 text-amber-700 border-amber-100': currentDiagnostic.score <= 80 && currentDiagnostic.score > 50,
                                'bg-red-50 text-red-700 border-red-100': currentDiagnostic.score <= 50
                            }">
                                {{ currentDiagnostic.score }}% {{ currentDiagnostic.health }}
                            </span>
                        </div>
                    </div>

                    <!-- SOCIAL PREVIEW CARD -->
                    <div v-if="activeTab === 'social'" class="social-preview p-6 bg-slate-50 border-2 border-slate-100 rounded-3xl relative min-h-[400px]">
                        <!-- SUB-TABS Social -->
                         <div class="flex gap-2 mb-6">
                            <button @click="activeSocialSubTab = 'linkedin'" :class="activeSocialSubTab === 'linkedin' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 border border-gray-100'" class="px-4 py-1.5 rounded-full text-[10px] font-bold transition-all shadow-sm">LinkedIn</button>
                            <button @click="activeSocialSubTab = 'facebook'" :class="activeSocialSubTab === 'facebook' ? 'bg-blue-800 text-white' : 'bg-white text-gray-500 border border-gray-100'" class="px-4 py-1.5 rounded-full text-[10px] font-bold transition-all shadow-sm">Facebook</button>
                         </div>

                         <!-- Content Simulator -->
                         <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
                             <div class="p-4 border-b border-gray-50 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-brand-500 flex items-center justify-center text-white text-[10px] font-black">K</div>
                                <div>
                                    <p class="text-[11px] font-black text-gray-900">Kilatech Equipements Medicaux</p>
                                    <p class="text-[9px] text-gray-400 italic">Maintenant</p>
                                </div>
                             </div>
                             <div class="p-4">
                                <p class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{{ editForm['social_' + activeSocialSubTab + '_post'] || 'Aucun contenu genere.' }}</p>
                             </div>
                             <div v-if="selectedProduct.image" class="w-full aspect-video bg-gray-50">
                                <img :src="getImageUrl(selectedProduct.image)" class="w-full h-full object-cover">
                             </div>
                         </div>

                         <!-- Quick Action -->
                         <div v-if="editForm['social_' + activeSocialSubTab + '_post']" class="absolute bottom-6 right-6 flex gap-3">
                            <button @click="publishToSocial(editForm['social_' + activeSocialSubTab + '_post'], activeSocialSubTab)" class="bg-white text-brand-600 font-black px-6 py-2 rounded-2xl shadow-xl border border-brand-50 hover:bg-brand-50 transition-all text-xs flex items-center gap-2">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                COPIER & PUBLIER
                            </button>
                         </div>
                    </div>

                    <!-- SEO PREVIEW CARD (Google) -->
                    <div v-if="activeTab === 'seo'" class="p-8 bg-white border-2 border-gray-100 rounded-3xl shadow-sm space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 text-xs font-bold">G</div>
                            <span class="text-xs text-gray-500">https://kilatech.ma > produit > {{ editForm.slug }}</span>
                        </div>
                        <h5 class="text-xl text-blue-800 font-medium hover:underline cursor-pointer leading-tight">{{ editForm.seo_title || editForm.meta_title }}</h5>
                        <p class="text-sm text-gray-600 leading-relaxed">{{ editForm.seo_description || editForm.meta_description }}</p>
                        
                        <div v-if="editForm.seo_content" class="mt-6 pt-6 border-t border-gray-100">
                            <span class="text-[10px] font-black text-brand-600 uppercase tracking-widest block mb-4 italic">Aperçu Réel de l'Article</span>
                            <div class="bg-white border border-gray-100 rounded-2xl p-6 shadow-inner max-h-[500px] overflow-y-auto">
                                <div class="prose prose-sm max-w-none preview-article" v-html="cleanedSeoContent"></div>
                            </div>
                        </div>
                    </div>

                    <!-- DIAGNOSTIC ADVICE -->
                     <div v-if="currentDiagnostic" class="space-y-3">
                        <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Conseils de l'IA Kilatech</h4>
                        <div v-for="check in currentDiagnostic.checks" :key="check.id" class="flex gap-4 p-4 rounded-2xl border transition-all" :class="{
                            'bg-emerald-50/30 border-emerald-100': check.status === 'success',
                            'bg-amber-50/30 border-amber-100': check.status === 'warning',
                            'bg-red-50/30 border-red-100': check.status === 'error'
                        }">
                            <div class="text-lg">{{ check.status === 'success' ? '✅' : (check.status === 'warning' ? '⚠️' : '❌') }}</div>
                            <div>
                                <p class="text-xs font-black text-gray-900">{{ check.label }}</p>
                                <p class="text-[11px] text-gray-500 mt-1 italic">{{ check.advice }}</p>
                            </div>
                        </div>
                     </div>
                </div>

                <!-- Editor Side -->
                <div class="lg:w-1/2 bg-gray-50/50 rounded-3xl p-8 border border-gray-100">
                    <div class="flex justify-between items-center mb-6">
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest">Edition Directe</h4>
                        <button v-if="!isEditing" @click="isEditing = true" class="text-xs font-black text-brand-600 uppercase italic">Modifier Manuellement</button>
                        <div v-else class="flex gap-3">
                            <button @click="isEditing = false" class="text-xs font-bold text-gray-400 uppercase">Annuler</button>
                            <button @click="saveMarketing" class="text-xs font-black text-emerald-600 uppercase border-b-2 border-emerald-600 pb-1">Enregistrer</button>
                        </div>
                    </div>

                    <!-- SEO FORM -->
                    <div v-if="activeTab === 'seo'" class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Titre H1 (Page)</label>
                            <input v-model="editForm.seo_h1" :disabled="!isEditing" placeholder="Titre principal du produit" class="w-full bg-white border border-gray-100 rounded-xl p-4 text-sm font-bold focus:ring-2 focus:ring-brand-500 focus:border-brand-500 disabled:opacity-70 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Meta Title (Google Search)</label>
                            <input v-model="editForm.seo_title" :disabled="!isEditing" class="w-full bg-white border border-gray-100 rounded-xl p-4 text-sm font-bold focus:ring-2 focus:ring-brand-500 focus:border-brand-500 disabled:opacity-70 transition-all">
                            <div class="flex justify-between mt-2 px-1">
                                <span class="text-[10px] text-gray-400 italic">Ideal: 50-60 car.</span>
                                <span class="text-[10px] font-bold" :class="editForm.seo_title?.length > 60 || editForm.seo_title?.length < 40 ? 'text-amber-500' : 'text-emerald-500'">{{ editForm.seo_title?.length || 0 }} car.</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Meta Description</label>
                            <textarea v-model="editForm.seo_description" :disabled="!isEditing" rows="3" class="w-full bg-white border border-gray-100 rounded-xl p-4 text-sm font-medium focus:ring-2 focus:ring-brand-500 focus:border-brand-500 disabled:opacity-70 transition-all"></textarea>
                            <div class="flex justify-between mt-2 px-1">
                                <span class="text-[10px] text-gray-400 italic">Ideal: 120-160 car.</span>
                                <span class="text-[10px] font-bold" :class="editForm.seo_description?.length > 160 || editForm.seo_description?.length < 100 ? 'text-amber-500' : 'text-emerald-500'">{{ editForm.seo_description?.length || 0 }} car.</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Contenu SEO (Article Long)</label>
                            <textarea v-model="editForm.seo_content" :disabled="!isEditing" rows="6" placeholder="Contenu structuré généré par n8n..." class="w-full bg-white border border-gray-100 rounded-xl p-4 text-sm font-medium focus:ring-2 focus:ring-brand-500 focus:border-brand-500 disabled:opacity-70 transition-all"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Context Geographique (Local SEO)</label>
                            <input v-model="editForm.local_geo_context" :disabled="!isEditing" placeholder="Ex: Casablanca, Maarif, Bouskoura" class="w-full bg-white border border-gray-100 rounded-xl p-4 text-sm font-bold focus:ring-2 focus:ring-brand-500 focus:border-brand-500 disabled:opacity-70 transition-all">
                        </div>
                    </div>

                    <!-- SOCIAL FORM -->
                    <div v-if="activeTab === 'social'" class="space-y-6 animate-fade-in">
                        <div class="bg-white border-2 border-brand-50 p-4 rounded-2xl mb-6">
                            <p class="text-[10px] font-black text-brand-600 uppercase tracking-widest mb-1 italic">Strategie Social Media</p>
                            <p class="text-xs text-gray-500 leading-relaxed font-bold">Adaptez chaque post a son audience. LinkedIn = Professionnel & Expertise. FB/IG = Emotionnel & Usage.</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Post LinkedIn</label>
                            <textarea v-model="editForm.social_linkedin_post" :disabled="!isEditing" rows="4" class="w-full bg-white border border-gray-100 rounded-xl p-4 text-sm font-medium focus:ring-2 focus:ring-brand-500 focus:border-brand-500 disabled:opacity-70 transition-all"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Post Facebook</label>
                            <textarea v-model="editForm.social_facebook_post" :disabled="!isEditing" rows="4" class="w-full bg-white border border-gray-100 rounded-xl p-4 text-sm font-medium focus:ring-2 focus:ring-brand-500 focus:border-brand-500 disabled:opacity-70 transition-all"></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-10 pt-6 border-t border-gray-100 flex justify-center">
                         <button @click="generateMarketing(selectedProduct.id)" :disabled="isGenerating === selectedProduct.id" class="bg-brand-600 text-white font-black px-10 py-3 rounded-2xl shadow-2xl shadow-brand-200 hover:scale-105 active:scale-95 transition-all flex items-center gap-3 disabled:opacity-50">
                            <span v-if="isGenerating === selectedProduct.id" class="animate-spin text-xl">✨</span>
                            <span v-else class="text-xl">⚡</span>
                            {{ isGenerating === selectedProduct.id ? 'Optimisation IA en cours...' : 'RE-GENERER TOUT PAR IA' }}
                         </button>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </div>

    <!-- Alert Success Copy -->
    <div v-if="showCopySuccess" class="fixed bottom-10 left-1/2 -translate-x-1/2 z-[100] bg-gray-900 text-white px-8 py-4 rounded-2xl font-black text-xs shadow-2xl animate-bounce">
       TEXTE COPIE ! REDIRECTION VERS LE RESEAU SOCIAL...
    </div>

  </div>
</template>

<style scoped>
.social-preview {
    @apply bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden transition-all;
}

/* Styles pour l'aperçu SEO (car Tailwind Typography n'est pas installé) */
.preview-article :deep(h2) {
    @apply text-2xl font-bold text-gray-900 mt-8 mb-4 leading-tight;
}
.preview-article :deep(h3) {
    @apply text-xl font-semibold text-gray-800 mt-6 mb-3 leading-snug;
}
.preview-article :deep(p) {
    @apply text-base text-gray-600 mb-4 leading-relaxed;
}
.preview-article :deep(ul) {
    @apply list-disc list-inside mb-4 space-y-2 text-gray-600;
}
.preview-article :deep(ol) {
    @apply list-decimal list-inside mb-4 space-y-2 text-gray-600;
}
.preview-article :deep(img) {
    @apply rounded-xl my-6 w-full max-h-[400px] object-contain bg-gray-50 border border-gray-100;
}
.preview-article :deep(a) {
    @apply text-brand-600 hover:text-brand-700 underline font-medium;
}

@keyframes fade-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fade-in 0.4s ease-out forwards;
}
</style>
