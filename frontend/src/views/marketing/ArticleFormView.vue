<script setup lang="ts">
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { useConfig } from '@/composables/useConfig'

const { getImageUrl: getStorageUrl } = useConfig()

const route = useRoute()
const router = useRouter()
const isEditing = computed(() => route.params.id !== undefined)
const isLoading = ref(false)
const isSubmitting = ref(false)
const isUploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const products = ref<any[]>([])
const activeTab = ref<'visual' | 'code'>('visual')
const previewDevice = ref<'desktop' | 'mobile'>('desktop')

// AI Assistant States
const isGenerating = ref(false)
const isGeneratingNews = ref(false)
const isRephrasing = ref(false)
const isAuditing = ref(false)
const newsNotes = ref('')

const aiTone = ref<'professional' | 'educational' | 'marketing' | 'simple'>('professional')
const aiAction = ref<'rephrase' | 'extend' | 'shorten' | 'improve_seo'>('rephrase')
const auditResult = ref<{ score: number; advice: string; tips: string[] } | null>(null)

// AI Rephrase comparison modal
const showAiCompare = ref(false)
const originalTextForCompare = ref('')
const rephrasedTextForCompare = ref('')

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

// Visual Editor Integration
const editorRef = ref<HTMLDivElement | null>(null)

const fetchProducts = async () => {
    try {
        const res = await api.get('/products')
        products.value = res.data
    } catch (e) {
        console.error('Failed to load products')
    }
}

const fetchArticle = async () => {
    if (!isEditing.value) return
    isLoading.value = true
    try {
        const res = await api.get(`/articles/${route.params.id}`)
        article.value = res.data
        // Sync to visual editor div with nextTick fallback
        nextTick(() => {
            syncEditorContent(article.value.content || '')
        })
    } catch (e) {
        alert('Erreur lors du chargement de l\'article')
    } finally {
        isLoading.value = false
    }
}

// Watchers for slug auto-generation
watch(() => article.value.title, (newTitle) => {
    if (!isEditing.value) {
        article.value.slug = newTitle
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '')
    }
})

const syncEditorContent = (content: string) => {
    if (editorRef.value && editorRef.value.innerHTML !== content) {
        editorRef.value.innerHTML = content
    }
}

// Watch article.content and use nextTick if editorRef is not yet mounted to ensure sync
watch(() => article.value.content, async (newContent) => {
    if (!editorRef.value) {
        await nextTick()
    }
    if (editorRef.value && editorRef.value.innerHTML !== newContent) {
        editorRef.value.innerHTML = newContent || ''
    }
}, { immediate: true })

const onEditorInput = (e: Event) => {
    const target = e.target as HTMLDivElement
    article.value.content = target.innerHTML
}

// Visual Rich Text Actions using standard execCommand
const format = (command: string, value: string | undefined = undefined) => {
    document.execCommand(command, false, value)
    if (editorRef.value) {
        article.value.content = editorRef.value.innerHTML
    }
}

const insertLink = () => {
    const url = prompt('Entrez l\'URL du lien :')
    if (url) {
        format('createLink', url)
    }
}

const insertCtaButton = () => {
    const text = prompt('Texte du bouton CTA :', 'Demander un devis')
    const link = prompt('Lien ou Ancre du bouton (ex: /contact ou #devis) :', '/contact')
    if (text && link) {
        const ctaHtml = `<a href="${link}" class="inline-block bg-emerald-600 text-white font-bold px-6 py-2.5 rounded-xl hover:bg-emerald-700 transition-all my-4 no-underline cursor-pointer" target="_blank">${text}</a>`
        document.execCommand('insertHTML', false, ctaHtml)
        if (editorRef.value) {
            article.value.content = editorRef.value.innerHTML
        }
    }
}

// Sync visual and code tabs
watch(activeTab, (newTab) => {
    if (newTab === 'visual') {
        setTimeout(() => {
            syncEditorContent(article.value.content)
        }, 50)
    }
})

// AI Core Helper - Generate full article
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
        
        syncEditorContent(data.content)
    } catch (e) {
        alert('Erreur lors de la génération IA')
    } finally {
        isGenerating.value = false
    }
}

// AI Core Helper - Generate News from simple Notes
const generateNewsWithAi = async () => {
    if (newsNotes.value.length < 10) {
        alert('Veuillez saisir plus de détails (min 10 caractères) pour guider l\'actualité.')
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
        
        syncEditorContent(data.content)
    } catch (e) {
        alert('Erreur lors de la génération de l\'actualité')
    } finally {
        isGeneratingNews.value = false
    }
}

// Upload local photo
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

// AI Interactive Editor Tools (Rephrase / Optimize)
const triggerAiRephrase = async () => {
    // Get selection or fallback to full content
    let selectedText = ''
    if (window.getSelection) {
        selectedText = window.getSelection()?.toString() || ''
    }
    
    const textToProcess = selectedText.trim() || article.value.content || ''
    
    if (!textToProcess.trim() || textToProcess === '<br>') {
        alert('Veuillez saisir du contenu ou sélectionner une partie de texte à optimiser.')
        return
    }

    isRephrasing.value = true
    try {
        const res = await api.post('/articles/rephrase', {
            text: textToProcess,
            tone: aiTone.value,
            action: aiAction.value
        })
        
        originalTextForCompare.value = textToProcess
        rephrasedTextForCompare.value = res.data.rephrased
        showAiCompare.value = true
    } catch (e) {
        alert('Erreur lors de l\'optimisation de l\'IA')
    } finally {
        isRephrasing.value = false
    }
}

// Apply rephrased content into the editor
const applyAiOptimisation = () => {
    if (!rephrasedTextForCompare.value) return
    
    // If we originally selected a text segment, replace the selection
    let selection = window.getSelection()
    if (selection && selection.toString().trim() !== '') {
        const range = selection.getRangeAt(0)
        range.deleteContents()
        const el = document.createElement('div')
        el.innerHTML = rephrasedTextForCompare.value
        const frag = document.createDocumentFragment()
        let node
        while ((node = el.firstChild)) {
            frag.appendChild(node)
        }
        range.insertNode(frag)
        if (editorRef.value) {
            article.value.content = editorRef.value.innerHTML
        }
    } else {
        // Otherwise, replace the entire document content
        article.value.content = rephrasedTextForCompare.value
        syncEditorContent(rephrasedTextForCompare.value)
    }
    
    showAiCompare.value = false
    alert('Optimisation appliquée avec succès !')
}

// AI Anti-Repetitive Quality Audit
const runAntiRepetitionAudit = async () => {
    if (!article.value.content || article.value.content === '<br>') {
        alert('Veuillez d\'abord saisir ou générer du contenu pour lancer l\'audit.')
        return
    }
    
    isAuditing.value = true
    auditResult.value = null
    try {
        const res = await api.post('/articles/rephrase', {
            text: article.value.content,
            action: 'check_repetition'
        })
        auditResult.value = res.data
    } catch (e) {
        alert('Erreur lors de l\'analyse sémantique')
    } finally {
        isAuditing.value = false
    }
}

const saveArticle = async () => {
    if (!article.value.title.trim()) {
        alert('Veuillez saisir un titre d\'article.')
        return
    }
    
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

// Image helper for preview
const getPreviewImage = computed(() => {
    if (!article.value.image) return '/images/blog_placeholder.png'
    return article.value.image
})

// Storefront matching processed content for Live Preview
const processedContentPreview = computed(() => {
    let html = article.value.content || '<p class="text-gray-300 italic">Aucun contenu rǸdigǸ pour le moment.</p>'
    if (!article.value.product_id) return html

    const prod = products.value.find(p => p.id === article.value.product_id)
    if (!prod) return html

    const prodName = prod.name || ''
    const prodSlug = prod.slug || ''
    const prodBrand = prod.brand || 'KILATECH'
    const prodImageUrl = prod.image_path ? getStorageUrl(prod.image_path) : '/images/blog_placeholder.png'

    // Use DOMParser for safer structural replacements
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, 'text/html')

    // 1. Locate the product card by looking for the specific AI placeholder product name in uppercase
    const allDivs = Array.from(doc.querySelectorAll('div'))
    const productCards = allDivs.filter(div => {
        const text = div.textContent || ''
        return text.includes('SONDE GASTRIQUE SILICONE STERILE') && div.querySelector('img')
    })

    if (productCards.length > 0) {
        // Sort by text length to find the innermost container
        productCards.sort((a, b) => (a.textContent || '').length - (b.textContent || '').length)
        const targetCard = productCards[0]

        // Replace image(s) in the card
        const imgs = targetCard.querySelectorAll('img')
        imgs.forEach(img => img.setAttribute('src', prodImageUrl))

        // Replace link(s) in the card
        const links = targetCard.querySelectorAll('a')
        if (links.length > 0) {
            links.forEach(link => link.setAttribute('href', `/produit/${prodSlug}`))
        } else {
            // If the AI didn't generate an <a> tag, find the button (teal circle with arrow) and wrap it
            const svgs = targetCard.querySelectorAll('svg')
            if (svgs.length > 0) {
                const btnOrSvgContainer = svgs[0].parentElement
                if (btnOrSvgContainer && btnOrSvgContainer.tagName.toLowerCase() !== 'a') {
                    const wrapper = doc.createElement('a')
                    wrapper.setAttribute('href', `/produit/${prodSlug}`)
                    wrapper.setAttribute('class', 'block cursor-pointer')
                    // wrap the button
                    btnOrSvgContainer.parentNode?.insertBefore(wrapper, btnOrSvgContainer)
                    wrapper.appendChild(btnOrSvgContainer)
                }
            }
        }
    }

    // Retrieve the modified HTML
    html = doc.body.innerHTML

    // 2. Perform text replacements for the associated product placeholders
    html = html.replace(/\/images\/sonde-produit-sumbow\.(png|jpg)/g, prodImageUrl)
    html = html.replace(/SONDE GASTRIQUE SILICONE STERILE/gi, prodName.toUpperCase())
    html = html.replace(/Sonde Gastrique Silicone St[ée]rile/gi, prodName)
    html = html.replace(/SUMBOW MEDICAL/gi, prodBrand.toUpperCase())
    html = html.replace(/SUMBOW/gi, prodBrand.toUpperCase())
    html = html.replace(/Marque Officielle Sumbow/gi, `Marque Officielle ${prodBrand}`)

    return html
})
</script>

<template>
  <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-[1700px] mx-auto min-h-screen">
    <!-- Header Backlink -->
    <div class="flex items-center justify-between mb-8 border-b border-gray-100 pb-6">
      <div class="flex items-center gap-4">
        <router-link :to="{ name: 'marketing' }" class="p-2.5 rounded-xl hover:bg-gray-100 transition-colors border border-gray-200 shadow-sm text-gray-500">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        </router-link>
        <div>
          <span class="text-xs font-black text-brand-600 uppercase tracking-widest bg-brand-50 px-2.5 py-1 rounded-md">
            {{ article.category === 'blog' ? 'Espace Blog' : 'Espace Actualités' }}
          </span>
          <h1 class="text-2xl md:text-3xl font-black text-gray-900 mt-1">
            {{ isEditing ? 'Modifier l\'Article' : 'Rédiger un Nouvel Article' }}
          </h1>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <router-link :to="{ name: 'marketing' }" class="px-5 py-2.5 rounded-xl text-sm font-bold text-gray-500 hover:bg-gray-100 transition-all border border-transparent">
          Annuler
        </router-link>
        <button 
          @click="saveArticle" 
          :disabled="isSubmitting"
          class="bg-brand-600 text-white px-8 py-2.5 rounded-xl text-sm font-black shadow-lg shadow-brand-600/10 hover:bg-brand-700 transition-all flex items-center gap-2"
        >
          <span v-if="isSubmitting" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
          {{ isSubmitting ? 'Enregistrement...' : 'Enregistrer les Modifications' }}
        </button>
      </div>
    </div>

    <!-- MAIN TWO COLUMN WORKSPACE -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      
      <!-- LEFT COLUMN: EDITOR SUITE (7 Cols) -->
      <div class="lg:col-span-7 space-y-6">
        
        <!-- AI Assistants Accordions -->
        <div class="bg-gradient-to-r from-brand-600 to-indigo-600 rounded-3xl p-6 text-white shadow-xl shadow-brand-600/10 relative overflow-hidden">
            <!-- decorative circles -->
            <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/5 rounded-full blur-xl pointer-events-none"></div>
            <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-white/5 rounded-full blur-xl pointer-events-none"></div>

            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center text-xl flex-shrink-0">
                    ✨
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-lg leading-tight">Générateur de Rédaction Express IA</h3>
                    <p class="text-xs text-white/80 mt-1">Automatisez la rédaction pour booster votre référencement Google au Maroc.</p>
                    
                    <!-- AI Tools depending on category -->
                    <div v-if="article.category === 'blog'" class="mt-4 flex flex-wrap items-center gap-3">
                        <select v-model="article.product_id" class="text-xs rounded-xl bg-white/10 border-white/20 text-white focus:ring-brand-500 py-2 px-3 min-w-[200px] placeholder-white/50">
                            <option :value="null" class="text-gray-900">-- Lier un produit --</option>
                            <option v-for="p in products" :key="p.id" :value="p.id" class="text-gray-900">{{ p.name }}</option>
                        </select>
                        <button type="button" @click="generateWithAi" :disabled="isGenerating || !article.product_id" class="bg-white text-brand-700 px-5 py-2 rounded-xl text-xs font-black hover:bg-brand-50 transition-all flex items-center gap-2 disabled:opacity-50">
                            <span v-if="isGenerating" class="animate-spin text-sm">⚙️</span>
                            {{ isGenerating ? 'Génération...' : 'Lancer l\'IA Blog' }}
                        </button>
                    </div>

                    <div v-if="article.category === 'news'" class="mt-4 flex items-center gap-3 w-full">
                        <input v-model="newsNotes" type="text" placeholder="Ex: Signature de contrat exclusif de distribution avec GE Healthcare..." class="flex-1 text-xs rounded-xl bg-white/10 border-white/20 text-white focus:ring-brand-500 py-2 px-4 placeholder-white/50">
                        <button type="button" @click="generateNewsWithAi" :disabled="isGeneratingNews || newsNotes.length < 10" class="bg-white text-emerald-700 px-5 py-2 rounded-xl text-xs font-black hover:bg-brand-50 transition-all flex items-center gap-2 whitespace-nowrap disabled:opacity-50">
                            <span v-if="isGeneratingNews" class="animate-spin text-sm">📰</span>
                            {{ isGeneratingNews ? 'Rédaction...' : 'Générer Actu' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rich-Text Visual Editor Suite -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-200/80 overflow-hidden">
            <!-- Header Tabs -->
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <div class="flex bg-gray-200/60 p-1 rounded-xl">
                    <button 
                      @click="activeTab = 'visual'"
                      :class="activeTab === 'visual' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'"
                      class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                    >
                        Éditeur Visuel
                    </button>
                    <button 
                      @click="activeTab = 'code'"
                      :class="activeTab === 'code' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'"
                      class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                    >
                        Code HTML Raw
                    </button>
                </div>
                <div class="text-[10px] text-gray-400 font-mono">
                    {{ article.content ? article.content.length : 0 }} caractères
                </div>
            </div>

            <!-- Formatting Toolbar (for Visual editor only) -->
            <div v-show="activeTab === 'visual'" class="px-6 py-3 border-b border-gray-100 bg-white flex flex-wrap items-center gap-1">
                <button @click="format('bold')" title="Gras" class="p-2 rounded-lg hover:bg-gray-100 text-gray-600 font-black text-xs min-w-[28px]">G</button>
                <button @click="format('italic')" title="Italique" class="p-2 rounded-lg hover:bg-gray-100 text-gray-600 italic font-black text-xs min-w-[28px]">I</button>
                <div class="w-px h-5 bg-gray-200 mx-1"></div>
                <button @click="format('formatBlock', '<h2>')" title="Titre 2" class="px-2.5 py-1 rounded-lg hover:bg-gray-100 text-gray-700 text-xs font-bold">H2</button>
                <button @click="format('formatBlock', '<h3>')" title="Titre 3" class="px-2.5 py-1 rounded-lg hover:bg-gray-100 text-gray-700 text-xs font-bold">H3</button>
                <button @click="format('formatBlock', '<p>')" title="Paragraphe" class="px-2.5 py-1 rounded-lg hover:bg-gray-100 text-gray-700 text-xs font-medium">Paragraphe</button>
                <div class="w-px h-5 bg-gray-200 mx-1"></div>
                <button @click="format('insertUnorderedList')" title="Liste à puces" class="p-2 rounded-lg hover:bg-gray-100 text-gray-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <button @click="format('insertOrderedList')" title="Liste numérotée" class="p-2 rounded-lg hover:bg-gray-100 text-gray-600 text-[10px] font-bold">1.</button>
                <div class="w-px h-5 bg-gray-200 mx-1"></div>
                <button @click="insertLink" title="Insérer un lien" class="p-2 rounded-lg hover:bg-gray-100 text-gray-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                </button>
                <button @click="insertCtaButton" title="Insérer un bouton B2B Devis" class="px-3 py-1 rounded-xl bg-brand-50 hover:bg-brand-100 text-brand-700 text-[10px] font-black border border-brand-100 flex items-center gap-1 transition-colors">
                    ✨ Bouton CTA Devis
                </button>
                <div class="w-px h-5 bg-gray-200 mx-1"></div>
                <button @click="format('removeFormat')" title="Nettoyer le style" class="p-2 rounded-lg hover:bg-gray-100 text-red-500">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
            </div>

            <!-- Content Inputs -->
            <div class="p-6 space-y-6">
                <!-- Title & Slug inside editor for cohesion -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Titre de l'Article *</label>
                        <input v-model="article.title" required type="text" class="w-full rounded-xl border-gray-200 focus:border-brand-500 focus:ring-brand-500 shadow-sm text-sm" placeholder="Saisir le titre...">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">URL (Slug)</label>
                        <input v-model="article.slug" type="text" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:border-brand-500 focus:ring-brand-500 shadow-sm text-sm font-mono text-gray-500" placeholder="slug-de-l-article">
                    </div>
                </div>

                <!-- Main Content Areas -->
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Corps de l'Article</label>
                    
                    <!-- Visual Contenteditable Container (Medium-style) -->
                    <div v-show="activeTab === 'visual'" class="relative">
                        <div
                          ref="editorRef"
                          contenteditable="true"
                          @input="onEditorInput"
                          class="w-full min-h-[400px] p-6 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 overflow-y-auto blog-content transition-all shadow-inner"
                        ></div>
                        <div v-if="!article.content" class="absolute top-6 left-6 text-gray-300 pointer-events-none text-sm italic">
                            Commencez à rédiger votre contenu ici ou cliquez sur "Lancer l'IA" ci-dessus...
                        </div>
                    </div>

                    <!-- Code Source Textarea -->
                    <div v-show="activeTab === 'code'">
                        <textarea 
                          v-model="article.content" 
                          rows="18" 
                          class="w-full rounded-2xl border-gray-200 focus:border-brand-500 focus:ring-brand-500 shadow-inner font-mono text-xs p-4 bg-gray-900 text-green-400 leading-normal"
                        ></textarea>
                    </div>
                </div>

                <!-- Product Association and status -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-100">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Produit Associé (Vitrine)</label>
                        <select v-model="article.product_id" class="w-full rounded-xl border-gray-200 focus:ring-brand-500 text-sm">
                            <option :value="null">-- Aucun produit --</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Statut de Publication (Site Public)</label>
                        <select v-model="article.status" class="w-full rounded-xl border-gray-200 text-sm font-bold bg-indigo-50/50 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="draft">📁 Brouillon (Privé / Édition)</option>
                            <option value="published">🌐 Publié (Visible en ligne immédiat !)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Image Management Deck -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-200/80 space-y-4">
            <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Image de Présentation</h3>
            
            <div class="flex flex-col sm:flex-row gap-4 items-start">
                <div class="flex-1 space-y-3 w-full">
                    <!-- File upload trigger -->
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="fileInput?.click()" :disabled="isUploading" class="flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-gray-50 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 transition-all shadow-sm">
                            <svg v-if="!isUploading" class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                            <span v-else class="animate-spin w-4 h-4 border-2 border-brand-500 border-t-transparent rounded-full"></span>
                            {{ isUploading ? 'Téléversement...' : 'Importer une image locale' }}
                        </button>
                        <input ref="fileInput" type="file" @change="handleFileUpload" accept="image/*" class="hidden">
                        <span class="text-xs text-gray-400">ou coller une URL absolue</span>
                    </div>

                    <!-- Direct image url input -->
                    <input v-model="article.image" type="text" placeholder="https://images.unsplash.com/... (ex: Unsplash ou CDN)" class="w-full rounded-xl border-gray-200 focus:border-brand-500 focus:ring-brand-500 shadow-sm text-xs py-2 px-3">
                </div>

                <!-- Preview thumbnail -->
                <div v-if="article.image" class="relative group w-32 h-24 rounded-2xl border border-gray-200 overflow-hidden bg-gray-50 flex-shrink-0 shadow-md">
                    <img :src="article.image" class="w-full h-full object-cover" @error="(e: any) => e.target.src = '/images/blog_placeholder.png'">
                    <button type="button" @click="article.image = ''" class="absolute top-1.5 right-1.5 bg-red-500 hover:bg-red-600 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition-all shadow-md">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"></path></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- SEO Metadata Card -->
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-200/80">
            <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider mb-4 flex items-center gap-2">
                🔍 Balises Métadonnées SEO
            </h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Titre de l'onglet (Meta Title) - Max 60 chars</label>
                    <input v-model="article.meta_title" type="text" class="w-full rounded-xl border-gray-200 focus:border-brand-500 focus:ring-brand-500 text-sm py-2 px-3" placeholder="Saisir le meta title...">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Résumé Google (Meta Description) - Max 155 chars</label>
                    <textarea v-model="article.meta_description" rows="3" class="w-full rounded-xl border-gray-200 focus:border-brand-500 focus:ring-brand-500 text-sm p-3" placeholder="Saisir le résumé d'accroche..."></textarea>
                </div>
            </div>
        </div>

      </div>

      <!-- RIGHT COLUMN: INTERACTIVE LIVE PREVIEW & AI PANEL (5 Cols) -->
      <div class="lg:col-span-5 space-y-6 lg:sticky lg:top-8">
        
        <!-- Smart AI Copy Assistant Sidebar -->
        <div class="bg-white rounded-3xl border border-gray-200/80 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-100 flex items-center gap-2">
                <span class="text-xl">🤖</span>
                <div>
                    <h3 class="font-bold text-indigo-900 text-sm">Assistant Anti-Répétition IA</h3>
                    <p class="text-[10px] text-indigo-700 font-medium">Améliorez l'accroche, le vocabulaire ou la structure.</p>
                </div>
            </div>

            <div class="p-6 space-y-5">
                <!-- Action selection -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Action IA</label>
                        <select v-model="aiAction" class="w-full rounded-xl border-gray-200 text-xs py-1.5 focus:ring-indigo-500">
                            <option value="rephrase">✍️ Reformuler</option>
                            <option value="extend">📈 Développer</option>
                            <option value="shorten">✂️ Raccourcir</option>
                            <option value="improve_seo">🚀 Optimiser le SEO</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Ton Recommandé</label>
                        <select v-model="aiTone" class="w-full rounded-xl border-gray-200 text-xs py-1.5 focus:ring-indigo-500">
                            <option value="professional">🎓 Professionnel</option>
                            <option value="educational">💡 Éducatif / Médical</option>
                            <option value="marketing">🔥 Persuasif / Vente</option>
                            <option value="simple">🌟 Simple / Accessible</option>
                        </select>
                    </div>
                </div>

                <div class="bg-indigo-50/30 p-3 rounded-xl border border-indigo-100/50">
                    <p class="text-[10px] text-indigo-800 leading-normal">
                        <strong>Conseil de Rédaction :</strong> Sélectionnez une portion de texte dans l'éditeur visuel puis cliquez sur lancer ci-dessous pour ne reformuler que ce passage ! Sinon, l'IA traitera l'article entier.
                    </p>
                </div>

                <!-- Launch action -->
                <button 
                  @click="triggerAiRephrase" 
                  :disabled="isRephrasing"
                  class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs py-3 px-4 rounded-xl transition-all shadow-md shadow-indigo-600/10 flex items-center justify-center gap-2"
                >
                    <span v-if="isRephrasing" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                    {{ isRephrasing ? 'Optimisation en cours...' : 'Optimiser le Texte par l\'IA' }}
                </button>

                <div class="h-px bg-gray-100 my-4"></div>

                <!-- Anti-Duplication Auditor -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Diagnostic Anti-Répétition</h4>
                        <button 
                          @click="runAntiRepetitionAudit" 
                          :disabled="isAuditing"
                          class="text-[10px] font-black text-brand-600 hover:text-brand-900 flex items-center gap-1 uppercase disabled:opacity-50"
                        >
                            <span v-if="isAuditing" class="animate-spin inline-block">⚙️</span>
                            {{ isAuditing ? 'Analyse...' : 'Lancer l\'Audit' }}
                        </button>
                    </div>

                    <!-- Audit Result details -->
                    <div v-if="auditResult" class="bg-gray-50 rounded-2xl border border-gray-100 p-4 space-y-3 animate-fade-in">
                        <div class="flex items-center gap-3">
                            <!-- Circular Score -->
                            <div class="w-12 h-12 rounded-full border-4 flex items-center justify-center text-xs font-black" :class="{
                                'border-emerald-500 text-emerald-600 bg-emerald-50': auditResult.score >= 80,
                                'border-amber-500 text-amber-600 bg-amber-50': auditResult.score < 80 && auditResult.score >= 60,
                                'border-red-500 text-red-600 bg-red-50': auditResult.score < 60
                            }">
                                {{ auditResult.score }}%
                            </div>
                            <div>
                                <span class="text-[9px] font-black text-gray-400 uppercase">Score d'Originalité</span>
                                <h5 class="font-bold text-xs" :class="{
                                    'text-emerald-700': auditResult.score >= 80,
                                    'text-amber-700': auditResult.score < 80 && auditResult.score >= 60,
                                    'text-red-700': auditResult.score < 60
                                }">
                                    {{ auditResult.score >= 80 ? 'Originalité Excellente' : (auditResult.score >= 60 ? 'Moyennement Répétitif' : 'Contenu à Personnaliser d\'Urgence') }}
                                </h5>
                            </div>
                        </div>

                        <p class="text-[11px] text-gray-600 italic leading-relaxed">
                            "{{ auditResult.advice }}"
                        </p>

                        <div class="space-y-2 pt-2 border-t border-gray-200">
                            <span class="text-[9px] font-black text-indigo-600 uppercase tracking-widest block">3 Actions Recommandées :</span>
                            <ul class="space-y-1.5">
                                <li v-for="(tip, idx) in auditResult.tips" :key="idx" class="text-[10px] text-gray-700 flex items-start gap-1.5 leading-relaxed">
                                    <span class="text-indigo-500 mt-0.5">•</span>
                                    <span>{{ tip }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- REAL-TIME LIVE STOREFRONT PREVIEW MOCKUP -->
        <div class="bg-white rounded-3xl border border-gray-200/80 shadow-sm overflow-hidden flex flex-col">
            <!-- Mockup Header Controls -->
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-red-400"></span>
                    <span class="w-3 h-3 rounded-full bg-yellow-400"></span>
                    <span class="w-3 h-3 rounded-full bg-green-400"></span>
                    <span class="text-[10px] text-gray-400 font-bold ml-2 font-mono">Prévisualisation Vitrine</span>
                </div>
                
                <!-- Desktop / Mobile Toggle -->
                <div class="flex bg-gray-200 p-0.5 rounded-lg">
                    <button 
                      @click="previewDevice = 'desktop'" 
                      :class="previewDevice === 'desktop' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-400 hover:text-gray-600'"
                      class="p-1 rounded text-xs transition-all"
                      title="Ordinateur"
                    >
                        🖥️
                    </button>
                    <button 
                      @click="previewDevice = 'mobile'" 
                      :class="previewDevice === 'mobile' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-400 hover:text-gray-600'"
                      class="p-1 rounded text-xs transition-all"
                      title="Smartphone"
                    >
                        📱
                    </button>
                </div>
            </div>

            <!-- Virtual Browser Shell -->
            <div class="bg-gray-100 p-4 flex-1">
                <div 
                  :class="previewDevice === 'mobile' ? 'max-w-[360px] rounded-3xl border-[8px] border-gray-800 aspect-[9/19] h-[640px]' : 'w-full rounded-2xl border border-gray-200'"
                  class="mx-auto bg-white overflow-y-auto transition-all duration-300 shadow-xl relative scrollbar-thin"
                >
                    <!-- Virtual Mobile Speaker/Camera -->
                    <div v-if="previewDevice === 'mobile'" class="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-4 bg-gray-800 rounded-b-xl z-20"></div>

                    <!-- Storefront detail page emulation -->
                    <div class="p-6 bg-white min-h-full font-sans text-gray-700">
                        <header class="mb-6 text-center pt-2">
                            <div class="flex items-center justify-center gap-2 mb-4">
                                <span class="text-[9px] font-black text-brand-600 uppercase tracking-widest bg-brand-50 px-2 py-0.5 rounded-full">
                                    {{ article.category === 'blog' ? 'Blog Kilatech' : 'Actualités' }}
                                </span>
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">
                                    {{ new Date().toLocaleDateString('fr-FR', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                                </span>
                            </div>
                            <h1 class="text-lg md:text-xl font-black text-gray-900 leading-tight">
                                {{ article.title || 'Titre de votre article de presse...' }}
                            </h1>
                            
                            <div v-if="article.product_id" class="inline-flex items-center gap-1.5 mt-3 p-1.5 px-3 bg-gray-50 rounded-xl border border-gray-100/60 text-[10px]">
                                <span class="text-gray-400">Produit associé :</span>
                                <span class="font-bold text-gray-800 truncate max-w-[150px]">
                                    {{ products.find(p => p.id === article.product_id)?.name || 'Matériel Associé' }}
                                </span>
                            </div>
                        </header>

                        <!-- Main Image -->
                        <div class="rounded-2xl overflow-hidden shadow-md mb-6 aspect-video bg-gray-50 border border-gray-100">
                            <img 
                              :src="getPreviewImage" 
                              class="w-full h-full object-cover" 
                              @error="(e: any) => e.target.src = '/images/blog_placeholder.png'"
                            >
                        </div>

                        <!-- Rendered HTML Content -->
                        <div 
                          class="prose prose-sm prose-indigo blog-content text-gray-600 font-light leading-relaxed max-w-none text-xs break-words" 
                          v-html="processedContentPreview"
                        ></div>
                        
                        <div class="mt-8 pt-4 border-t border-gray-100 flex items-center justify-between text-[9px] text-gray-400 font-bold uppercase tracking-wider">
                            <span>← Retour</span>
                            <span>Partager 🔗</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

      </div>

    </div>

    <!-- AI COMPARE / BEFORE & AFTER DRAFT MERGE MODAL -->
    <div v-if="showAiCompare" class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-4xl w-full shadow-2xl overflow-hidden border border-gray-200 animate-scale-up">
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xl">⚔️</span>
                    <div>
                        <h3 class="font-bold text-indigo-900 text-sm">Fusionner l'Optimisation de l'IA</h3>
                        <p class="text-[10px] text-indigo-700 font-medium">Comparez le texte original et la formulation proposée par l'IA.</p>
                    </div>
                </div>
                <button @click="showAiCompare = false" class="text-gray-400 hover:text-gray-600 text-sm">✕</button>
            </div>

            <!-- Modal Content (Side by side) -->
            <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100 h-[450px] overflow-hidden">
                <!-- BEFORE Content -->
                <div class="p-6 flex flex-col h-full overflow-y-auto bg-red-50/10">
                    <span class="text-[10px] font-black text-red-600 bg-red-100/50 px-2 py-1 rounded self-start uppercase tracking-wider mb-3">Texte d'Origine</span>
                    <div class="text-xs text-gray-600 leading-relaxed font-light whitespace-pre-wrap break-words flex-1" v-html="originalTextForCompare"></div>
                </div>
                
                <!-- AFTER Content -->
                <div class="p-6 flex flex-col h-full overflow-y-auto bg-emerald-50/10">
                    <span class="text-[10px] font-black text-emerald-600 bg-emerald-100/50 px-2 py-1 rounded self-start uppercase tracking-wider mb-3">Texte Reformulé par l'IA</span>
                    <div class="text-xs text-gray-800 leading-relaxed font-normal whitespace-pre-wrap break-words flex-1 blog-content" v-html="rephrasedTextForCompare"></div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                <button @click="showAiCompare = false" class="px-5 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-100 transition-all">
                    Rejeter les Modifications
                </button>
                <button @click="applyAiOptimisation" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-xl text-xs font-bold shadow-md shadow-indigo-600/10 transition-all">
                    Appliquer & Intégrer dans l'Éditeur
                </button>
            </div>
        </div>
    </div>

  </div>
</template>

<style>
/* Smooth micro-animations */
.animate-fade-in {
  animation: fadeIn 0.25s ease-out forwards;
}
.animate-scale-up {
  animation: scaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes scaleUp {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}

/* Custom visual scrollbar */
.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 20px;
}

/* Emulated Storefront Blog Typography styling rules inside preview and visual editor */
.blog-content h2 {
    font-size: 1.25rem !important;
    font-weight: 800 !important;
    color: #111827 !important;
    margin-top: 1.5rem !important;
    margin-bottom: 0.75rem !important;
    line-height: 1.4 !important;
}
.blog-content h3 {
    font-size: 1.1rem !important;
    font-weight: 700 !important;
    color: #1f2937 !important;
    margin-top: 1.25rem !important;
    margin-bottom: 0.5rem !important;
    line-height: 1.4 !important;
}
.blog-content p {
    margin-bottom: 1rem !important;
    line-height: 1.6 !important;
}
.blog-content ul {
    list-style-type: disc !important;
    padding-left: 1.25rem !important;
    margin-bottom: 1rem !important;
}
.blog-content li {
    margin-bottom: 0.35rem !important;
}
.blog-content strong {
    font-weight: 700 !important;
    color: #111827 !important;
}
</style>
