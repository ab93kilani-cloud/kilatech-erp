<script setup lang="ts">
import { ref, onMounted, computed, nextTick } from 'vue'
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

// Smart Layout Detector
const hasCustomLayout = computed(() => {
    if (!article.value || !article.value.content) return false
    const content = article.value.content
    return content.includes('blog-article') || content.includes('blog-hero') || content.includes('blog-content') || content.includes('blog-content-layout')
})

const processedContent = computed(() => {
    if (!article.value || !article.value.content) return ''
    let html = article.value.content

    // If an associated product exists, dynamically inject it into the custom layout
    if (article.value.product) {
        const prod = article.value.product
        const prodName = prod.name || ''
        const prodSlug = prod.slug || ''
        const prodBrand = prod.brand || 'KILATECH'
        const prodImageUrl = prod.image_path ? getStorageUrl(prod.image_path) : '/images/blog_placeholder.png'

        // Use DOMParser for safer structural replacements
        const parser = new DOMParser()
        const doc = parser.parseFromString(html, 'text/html')

        // 1. Locate the product card
        // First try to find a card explicitly marked with data-dynamic-product
        let targetCard = doc.querySelector('[data-dynamic-product="true"]')
        
        // Fallback: look for the specific AI placeholder product name
        if (!targetCard) {
            const allDivs = Array.from(doc.querySelectorAll('div'))
            const productCards = allDivs.filter(div => {
                const text = div.textContent || ''
                return text.includes('SONDE GASTRIQUE SILICONE STERILE') && div.querySelector('img')
            })
            if (productCards.length > 0) {
                productCards.sort((a, b) => (a.textContent || '').length - (b.textContent || '').length)
                targetCard = productCards[0]
            }
        }

        if (targetCard) {
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
                    const firstSvg = svgs.length > 0 ? svgs[0] : null
                    if (firstSvg) {
                        const btnOrSvgContainer = firstSvg.parentElement
                        const parent = btnOrSvgContainer ? btnOrSvgContainer.parentNode : null
                        if (btnOrSvgContainer && btnOrSvgContainer.tagName.toLowerCase() !== 'a' && parent) {
                            const wrapper = doc.createElement('a')
                            wrapper.setAttribute('href', `/produit/${prodSlug}`)
                            wrapper.setAttribute('class', 'block cursor-pointer')
                            // wrap the button
                            parent.insertBefore(wrapper, btnOrSvgContainer)
                            wrapper.appendChild(btnOrSvgContainer)
                        }
                    }
                }
            }
        }

        // Retrieve the modified HTML
        html = doc.body.innerHTML

        // 1. Fallback for the hardcoded sumbow product image URL if it was used elsewhere
        html = html.replace(/\/images\/sonde-produit-sumbow\.(png|jpg)/g, prodImageUrl)

        // 2. Replace the hardcoded product name in the bottom card & top header title
        html = html.replace(/SONDE GASTRIQUE SILICONE STERILE/gi, prodName.toUpperCase())
        html = html.replace(/Sonde Gastrique Silicone St[ée]rile/gi, prodName)

        // 3. Replace the brand names
        html = html.replace(/SUMBOW MEDICAL/gi, prodBrand.toUpperCase())
        html = html.replace(/SUMBOW/gi, prodBrand.toUpperCase())
        html = html.replace(/Marque Officielle Sumbow/gi, `Marque Officielle ${prodBrand}`)

        // 4. Replace generic placeholders from the new dynamic card template
        html = html.replace(/NOM DU PRODUIT/gi, prodName.toUpperCase())
        html = html.replace(/KILATECH BRAND/gi, `${prodBrand.toUpperCase()} BRAND`)
        html = html.replace(/R[ǸéE]F[ǸéE]RENCE KILATECH/gi, `RÉFÉRENCE ${prodBrand.toUpperCase()}`)
    }

    return html
})


// Native JS handler for accordion transitions inside dynamic injected content
const setupFaqAccordions = () => {
    nextTick(() => {
        document.querySelectorAll('.faq-question').forEach(btn => {
            // Remove existing listener to prevent double triggers
            const newBtn = btn.cloneNode(true);
            btn.parentNode?.replaceChild(newBtn, btn);
            
            newBtn.addEventListener('click', () => {
                const parent = newBtn.parentElement
                if (parent) {
                    const isActive = parent.classList.contains('active')
                    
                    // Collapse all others
                    document.querySelectorAll('.faq-item').forEach(item => {
                        item.classList.remove('active')
                        const ans = item.querySelector('.faq-answer') as HTMLElement
                        if (ans) ans.style.maxHeight = '0'
                    })

                    if (!isActive) {
                        parent.classList.add('active')
                        const ans = parent.querySelector('.faq-answer') as HTMLElement
                        if (ans) ans.style.maxHeight = ans.scrollHeight + 'px'
                    }
                }
            })
        })
    })
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
        
        // Setup FAQ click actions if custom layout exists
        setupFaqAccordions()
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
        <!-- 1. STANDARD HEADER BANNER (Only shows if NOT using the advanced blueprint structure) -->
        <div v-if="!hasCustomLayout" class="bg-gradient-to-br from-teal-50 via-teal-50/30 to-sky-50 border-b border-teal-100/40 py-12 md:py-16 relative overflow-hidden">
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

        <!-- 2. MAIN CONTENT WRAPPER (Broad max-w-6xl for beautiful grids, or broad px-0 if custom full-width blueprint layout) -->
        <div :class="hasCustomLayout ? 'max-w-[1240px] mx-auto px-4 sm:px-6 lg:px-8 py-10' : 'max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16'">
            
            <!-- Injected Rich HTML Content -->
            <div 
              class="prose prose-lg prose-indigo mx-auto text-slate-700 font-light leading-relaxed blog-content max-w-none" 
              v-html="processedContent"
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

/* --- BLUEPRINT DESIGN STYLES FOR DYNAMIC BLOGS --- */
.blog-article {
    background: #f8fafc;
    padding: 32px 0;
    color: #1f2937;
}

.blog-hero {
    background: #fff;
    border-radius: 16px;
    padding: 32px;
    display: grid;
    grid-template-columns: 1.2fr .8fr;
    gap: 32px;
    align-items: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
    margin-bottom: 32px;
    border: 1px solid #f1f5f9;
}

.blog-hero .badge {
    display: inline-block;
    background: #e0f2fe;
    color: #0369a1;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.blog-hero .blog-title {
    font-size: 30px;
    line-height: 1.25;
    margin: 16px 0 12px;
    font-weight: 800;
    color: #0f172a;
}

.blog-hero .meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 13px;
    color: #64748b;
}

.blog-hero .meta span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.blog-hero .blog-excerpt {
    color: #4b5563;
    font-size: 15px;
    line-height: 1.6;
    margin-top: 18px;
}

.blog-hero .hero-image {
    width: 100%;
    max-width: 320px;
    margin: 0 auto;
    display: block;
    object-fit: contain;
}

.blog-summary-container {
    margin: 24px 0 32px;
}

.blog-summary-container .summary-title {
    font-weight: 800;
    font-size: 16px;
    color: #0f172a;
    margin-bottom: 12px;
}

.blog-summary-container .summary-grid {
    background: #fff;
    border-radius: 16px;
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.02);
    border: 1px solid #f1f5f9;
}

.blog-summary-container .summary-grid a {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 8px;
    border-radius: 12px;
    color: #475569;
    text-decoration: none;
    font-weight: 700;
    font-size: 11px;
    transition: all 0.25s ease;
    border: 1px solid transparent;
    text-align: center;
}

.blog-summary-container .summary-grid a:hover {
    background: #f0fdf4;
    color: #065f46;
    border-color: #d1fae5;
    transform: translateY(-2px);
}

.blog-summary-container .summary-grid a i {
    font-size: 18px;
    color: #0d9488;
}

.blog-content-layout {
    display: grid;
    grid-template-columns: 2.2fr .8fr;
    gap: 32px;
    margin-top: 20px;
    align-items: start;
}

.content-section {
    background: #fff;
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 24px;
    display: grid;
    grid-template-columns: 1.2fr .8fr;
    gap: 28px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.015);
    border: 1px solid #f1f5f9;
    align-items: center;
}

.content-section:nth-child(even) {
    grid-template-columns: .8fr 1.2fr;
}

.content-text h2 {
    font-size: 22px !important;
    margin: 0 0 12px !important;
    font-weight: 800 !important;
    color: #0f172a !important;
}

.content-media img {
    width: 100%;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}

.table-wrapper {
    overflow-x: auto;
    margin: 20px 0;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.table-wrapper table {
    width: 100% !important;
    border-collapse: collapse !important;
    overflow: hidden !important;
}

.table-wrapper th, .table-wrapper td {
    padding: 12px 16px !important;
    border-bottom: 1px solid #e2e8f0 !important;
    text-align: left !important;
}

.table-wrapper th {
    background: #0d9488 !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    font-size: 13px !important;
}

.table-wrapper tr:last-child td {
    border-bottom: 0 !important;
}

.faq-container {
    margin-top: 24px;
}

.faq-item {
    border-bottom: 1px solid #e2e8f0;
}

.faq-item:last-child {
    border-bottom: 0;
}

.faq-question {
    width: 100% !important;
    background: #fff !important;
    border: 0 !important;
    padding: 18px 16px !important;
    text-align: left !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    font-weight: 700 !important;
    font-size: 14px !important;
    color: #1e293b !important;
    cursor: pointer !important;
    transition: color 0.2s;
}

.faq-question:hover {
    color: #0d9488;
}

.faq-answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
    color: #475569;
    font-size: 13px;
    line-height: 1.6;
}

.faq-item.active .faq-answer {
    padding: 0 16px 18px;
}

.blog-sidebar {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.sidebar-widget {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.015);
    border: 1px solid #f1f5f9;
}

.sidebar-title {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 16px;
    border-left: 4px solid #0d9488;
    padding-left: 10px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.widget-categories {
    list-style: none !important;
    padding: 0 !important;
    margin: 0 !important;
}

.widget-categories li a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    color: #475569;
    font-size: 13px;
    text-decoration: none;
    transition: all 0.2s;
    border-bottom: 1px solid #f1f5f9;
}

.widget-categories li:last-child a {
    border-bottom: 0;
}

.widget-categories li a:hover {
    color: #0d9488;
    padding-left: 4px;
}

.widget-recent-posts {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.recent-post-item {
    display: flex;
    gap: 12px;
    align-items: center;
    text-decoration: none;
}

.recent-post-item img {
    width: 60px;
    height: 45px;
    object-fit: cover;
    border-radius: 8px;
    background: #f1f5f9;
}

.recent-post-title {
    font-size: 12px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.4;
    transition: color 0.2s;
}

.recent-post-item:hover .recent-post-title {
    color: #0d9488;
}

.recent-post-date {
    font-size: 10px;
    color: #94a3b8;
    margin-top: 2px;
}

/* Sidebar Call to Action Banner */
.sidebar-cta {
    background: #007A78;
    color: #fff;
    border-radius: 16px;
    padding: 28px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
}

.sidebar-cta h4 {
    font-size: 16px;
    font-weight: 800;
    margin-bottom: 12px;
    color: #fff !important;
}

.sidebar-cta p {
    font-size: 12px;
    color: #ccfbf1;
    margin-bottom: 20px;
    line-height: 1.5;
}

.sidebar-cta .btn-cta {
    display: inline-block;
    background: #fff;
    color: #007A78;
    padding: 10px 24px;
    border-radius: 10px;
    font-weight: 800;
    font-size: 13px;
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    transition: all 0.2s;
}

.sidebar-cta .btn-cta:hover {
    background: #f0fdf4;
    transform: translateY(-1px);
}

.sidebar-cta .phone-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 16px;
    font-weight: 700;
    color: #fff;
    font-size: 14px;
    text-decoration: none;
}

/* Responsiveness overrides */
@media (max-width: 992px) {
    .blog-hero {
        grid-template-columns: 1fr !important;
        text-align: center;
    }
    .blog-hero .meta {
        justify-content: center;
    }
    .blog-hero .hero-image {
        max-width: 260px;
        order: -1;
    }
    .blog-summary-container .summary-grid {
        grid-template-columns: repeat(3, 1fr) !important;
    }
    .blog-content-layout {
        grid-template-columns: 1fr !important;
    }
    .content-section {
        grid-template-columns: 1fr !important;
    }
    .content-section:nth-child(even) {
        grid-template-columns: 1fr !important;
    }
    .content-media {
        order: -1;
    }
}

@media (max-width: 768px) {
    .blog-summary-container .summary-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 480px) {
    .blog-summary-container .summary-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>
