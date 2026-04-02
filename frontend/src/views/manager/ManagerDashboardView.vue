<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'
import { useConfig } from '@/composables/useConfig'

const { getImageUrl: getStorageUrl } = useConfig()

// Stats & Performance Data
const stats = ref<any>({
    revenue: 0,
    purchases: 0,
    gross_margin: 0,
    stock_worth: 0,
    collection_rate: 0,
    total_due: 0
})
const performance = ref<any[]>([])
const isLoading = ref(true)

// Upload State
const isUploading = ref(false)
const uploadError = ref('')
const uploadSuccess = ref(false)
const showUpload = ref(false)

const uploadForm = ref({
    month: new Date().getMonth() + 1,
    year: new Date().getFullYear(),
    file: null as File | null
})

// Catalogue Management
const catalogues = ref<any[]>([])
const isUploadingCat = ref(false)
const catForm = ref({
    title: '',
    category: '',
    file: null as File | null
})
const showCatUpload = ref(false)
const catUploadError = ref('')
const catUploadSuccess = ref(false)

const fetchData = async () => {
    isLoading.value = true
    try {
        const [statsRes, perfRes, catRes] = await Promise.all([
            api.get('/manager/stats'),
            api.get('/manager/performance'),
            api.get('/manager/catalogues')
        ])
        stats.value = statsRes.data
        performance.value = perfRes.data
        catalogues.value = catRes.data
    } catch (e) {
        console.error('Failed to load manager data')
    } finally {
        isLoading.value = false
    }
}

const handleFileChange = (e: any) => {
    uploadForm.value.file = e.target.files[0]
}

const submitUpload = async () => {
    if (!uploadForm.value.file) {
        uploadError.value = 'Veuillez sélectionner un fichier PDF.'
        return
    }

    isUploading.value = true
    uploadError.value = ''
    uploadSuccess.value = false

    const formData = new FormData()
    formData.append('file', uploadForm.value.file)
    formData.append('month', uploadForm.value.month.toString())
    formData.append('year', uploadForm.value.year.toString())

    try {
        await api.post('/accounting/bank-statements', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        uploadSuccess.value = true
        uploadForm.value.file = null
        setTimeout(() => { uploadSuccess.value = false; showUpload.value = false; }, 3000)
    } catch (e: any) {
        uploadError.value = e.response?.data?.message || 'Erreur lors de l\'envoi.'
    } finally {
        isUploading.value = false
    }
}

const handleCatFileChange = (e: any) => {
    catForm.value.file = e.target.files[0]
}

const submitCatUpload = async () => {
    if (!catForm.value.file || !catForm.value.title) return
    
    isUploadingCat.value = true
    catUploadError.value = ''
    catUploadSuccess.value = false

    const formData = new FormData()
    formData.append('file', catForm.value.file)
    formData.append('title', catForm.value.title)
    if (catForm.value.category) {
        formData.append('category', catForm.value.category)
    }

    try {
        await api.post('/manager/catalogues', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        catForm.value = { title: '', category: '', file: null }
        catUploadSuccess.value = true
        setTimeout(() => { 
            catUploadSuccess.value = false; 
            showCatUpload.value = false; 
        }, 2000)
        const res = await api.get('/manager/catalogues')
        catalogues.value = res.data
    } catch (e: any) {
        console.error('Upload failed', e)
        catUploadError.value = e.response?.data?.message || 'Erreur lors de l\'envoi du catalogue.'
    } finally {
        isUploadingCat.value = false
    }
}

const deleteCatalogue = async (id: number) => {
    if (!confirm('Supprimer ce catalogue ?')) return
    try {
        await api.delete(`/manager/catalogues/${id}`)
        catalogues.value = catalogues.value.filter(c => c.id !== id)
    } catch (e) {
        console.error('Delete failed')
    }
}

const toggleCatStatus = async (cat: any) => {
    try {
        const res = await api.patch(`/manager/catalogues/${cat.id}/toggle`)
        const index = catalogues.value.findIndex(c => c.id === cat.id)
        if (index !== -1) catalogues.value[index] = res.data
    } catch (e) {
        console.error('Toggle failed')
    }
}

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'MAD' }).format(val)
}

onMounted(fetchData)
</script>

<template>
  <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <!-- HEADER -->
    <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div>
            <h1 class="text-4xl font-black text-gray-900 uppercase tracking-tighter">Espace <span class="text-indigo-600">Stratégique</span></h1>
            <p class="mt-2 text-sm text-gray-500 font-medium bg-indigo-50 w-fit px-3 py-1 rounded-full border border-indigo-100 italic">Décisionnel & Performance Opérationnelle</p>
        </div>
        <div class="flex gap-3">
            <button @click="showCatUpload = !showCatUpload" class="px-6 py-3 bg-emerald-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100">
                {{ showCatUpload ? 'Fermer' : 'Nouveau Catalogue' }}
            </button>
            <button @click="showUpload = !showUpload" class="px-6 py-3 bg-white border border-gray-200 rounded-2xl text-xs font-black uppercase tracking-widest text-gray-600 hover:border-indigo-500 hover:text-indigo-600 transition-all shadow-sm">
                {{ showUpload ? 'Fermer Upload' : 'Déposer Relevé' }}
            </button>
            <button @click="fetchData" class="p-3 bg-indigo-600 text-white rounded-2xl shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all">
                <svg class="w-5 h-5" :class="{'animate-spin': isLoading}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </button>
        </div>
    </div>

    <!-- CATALOGUE UPLOAD PANEL -->
    <div v-if="showCatUpload" class="mb-10 bg-white p-8 rounded-[2.5rem] border-2 border-dashed border-emerald-100 animate-in slide-in-from-top duration-500">
        <h3 class="text-lg font-black text-gray-900 mb-6 uppercase tracking-tighter">Ajouter un Catalogue PDF</h3>
        <form @submit.prevent="submitCatUpload" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input v-model="catForm.title" type="text" placeholder="Titre du Catalogue (ex: Gamme Chirurgie 2026)" class="md:col-span-2 bg-gray-50 border-0 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-emerald-500" required />
            <input v-model="catForm.category" type="text" placeholder="Catégorie" class="bg-gray-50 border-0 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-emerald-500" />
            <div class="flex items-center gap-4 bg-gray-50 rounded-xl px-4">
                <input type="file" @change="handleCatFileChange" accept="application/pdf" class="flex-1 text-[10px] file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-black file:bg-emerald-50 file:text-emerald-700" required />
            </div>
            <div class="md:col-span-4 flex justify-end mt-4">
                <button type="submit" :disabled="isUploadingCat || !catForm.file" class="px-10 py-3 bg-emerald-600 text-white rounded-xl text-xs font-black uppercase tracking-widest hover:bg-emerald-700 disabled:opacity-50">
                    {{ isUploadingCat ? 'Envoi...' : 'Publier le Catalogue' }}
                </button>
            </div>
        </form>
        <div v-if="catUploadError" class="mt-4 text-xs text-red-500 font-bold border-l-2 border-red-500 pl-3">
            {{ catUploadError }}
        </div>
        <div v-if="catUploadSuccess" class="mt-4 text-xs text-emerald-500 font-bold border-l-2 border-emerald-500 pl-3">
            Catalogue publié avec succès !
        </div>
    </div>

    <!-- UPLOAD PANEL (CONDITIONAL) -->
    <div v-if="showUpload" class="mb-10 bg-white p-8 rounded-[2.5rem] border-2 border-dashed border-indigo-100 animate-in slide-in-from-top duration-500">
        <form @submit.prevent="submitUpload" class="flex flex-col md:flex-row items-center gap-6">
            <div class="flex-1 flex gap-4">
                <select v-model="uploadForm.month" class="bg-gray-50 border-0 rounded-xl px-4 py-2 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                    <option v-for="m in 12" :key="m" :value="m">{{ m }}</option>
                </select>
                <select v-model="uploadForm.year" class="bg-gray-50 border-0 rounded-xl px-4 py-2 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                    <option :value="2026">2026</option>
                    <option :value="2025">2025</option>
                </select>
                <input type="file" @change="handleFileChange" accept="application/pdf" class="flex-1 text-xs file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
            </div>
            <button type="submit" :disabled="isUploading || !uploadForm.file" class="px-8 py-3 bg-indigo-600 text-white rounded-xl text-xs font-black uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50">
                {{ isUploading ? 'Envoi...' : 'Valider Relevé' }}
            </button>
        </form>
        <div v-if="uploadError" class="mt-4 text-xs text-red-500 font-bold">{{ uploadError }}</div>
        <div v-if="uploadSuccess" class="mt-4 text-xs text-green-500 font-bold">Document transmis !</div>
    </div>

    <!-- KPI GRID -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-10">
        <div class="bg-white p-6 rounded-[2rem] shadow-xl shadow-gray-100/50 border border-gray-50 group hover:scale-[1.02] transition-transform">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </div>
                <div class="text-[10px] font-black text-blue-600 uppercase tracking-widest">Revenus 2026</div>
            </div>
            <div class="text-2xl font-black text-gray-900">{{ formatCurrency(stats.revenue) }}</div>
            <div class="mt-2 text-[10px] text-gray-400 font-bold uppercase">Facturation Brute Annuelle</div>
        </div>

        <div class="bg-white p-6 rounded-[2rem] shadow-xl shadow-gray-100/50 border border-gray-50 group hover:scale-[1.02] transition-transform">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Marge Brute</div>
            </div>
            <div class="text-2xl font-black text-gray-900">{{ formatCurrency(stats.gross_margin) }}</div>
            <div class="mt-1 text-[10px] font-black text-emerald-500">{{ Math.round((stats.gross_margin / stats.revenue) * 100) || 0 }}% de rentabilité</div>
        </div>

        <div class="bg-white p-6 rounded-[2rem] shadow-xl shadow-gray-100/50 border border-gray-50 group hover:scale-[1.02] transition-transform text-indigo-900">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                </div>
                <div class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">Valeur Stock</div>
            </div>
            <div class="text-2xl font-black text-gray-900">{{ formatCurrency(stats.stock_worth) }}</div>
            <div class="mt-2 text-[10px] text-gray-400 font-bold uppercase">Actifs en Entrepôt</div>
        </div>

        <div class="bg-indigo-900 p-6 rounded-[2rem] shadow-xl shadow-indigo-200/50 text-white overflow-hidden relative group">
            <div class="absolute -right-4 -bottom-4 w-32 h-32 bg-indigo-800 rounded-full opacity-50 group-hover:scale-150 transition-transform"></div>
            <div class="flex items-center justify-between mb-4">
                <div class="text-[10px] font-black text-indigo-300 uppercase tracking-widest relative z-10">Taux Recouvrement</div>
                <div class="relative z-10 text-xs font-black bg-indigo-800 px-2 py-1 rounded-lg">{{ stats.collection_rate }}%</div>
            </div>
            <div class="text-2xl font-black relative z-10">{{ formatCurrency(stats.total_due) }}</div>
            <div class="mt-2 text-[10px] text-indigo-400 font-bold uppercase relative z-10">Créances Clients Totales</div>
        </div>
    </div>

    <!-- MAIN DASHBOARD CONTENT -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
        <!-- PERFORMANCE TABLE -->
        <div class="bg-white p-10 rounded-[3rem] shadow-xl shadow-gray-100/50 border border-gray-100">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-xl font-bold text-gray-900">Performance Équipe</h2>
                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Filtre : 30 derniers jours</span>
            </div>
            <div class="overflow-hidden">
                <table class="w-full text-left">
                    <thead>
                        <tr>
                            <th class="pb-6 text-[10px] font-black text-gray-400 uppercase tracking-widest">Collaborateur</th>
                            <th class="pb-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Devis</th>
                            <th class="pb-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Actions</th>
                            <th class="pb-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Indice</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="user in performance" :key="user.id" class="group hover:bg-gray-50/50 transition-colors">
                            <td class="py-4">
                                <div class="text-sm font-black text-gray-900">{{ user.name }}</div>
                                <div class="text-[9px] text-indigo-500 font-bold uppercase tracking-widest">{{ user.role }}</div>
                            </td>
                            <td class="py-4 text-center">
                                <div class="text-sm font-black text-gray-700">{{ user.quotes_locked }}</div>
                            </td>
                            <td class="py-4 text-center">
                                <div class="text-[10px] font-bold text-gray-500">{{ user.orders_processed }} Ord. / {{ user.receipts_handled }} Rec.</div>
                            </td>
                            <td class="py-4 text-right">
                                <span :class="[
                                    'px-3 py-1 rounded-full text-[10px] font-black',
                                    user.activity_index > 50 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
                                ]">
                                    {{ user.activity_index }} pts
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RECENT ACTIVITY / DECISION AID -->
        <div class="bg-white p-10 rounded-[3rem] shadow-xl shadow-gray-100/50 border border-gray-100">
            <h2 class="text-xl font-bold text-gray-900 mb-8">Alertes & Aide à la Décision</h2>
            <div class="space-y-6">
                <div v-if="stats.collection_rate < 80" class="p-6 rounded-3xl bg-amber-50 border border-amber-100 flex items-start gap-4">
                    <div class="p-3 bg-amber-500 text-white rounded-2xl shadow-lg shadow-amber-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-amber-900 uppercase">Attention au Recouvrement</h4>
                        <p class="mt-1 text-xs text-amber-700 leading-relaxed font-medium">Votre taux de cash-flow est de {{ stats.collection_rate }}%. {{ formatCurrency(stats.total_due) }} sont toujours dehors. Priorité : Relance Clients.</p>
                    </div>
                </div>

                <div v-if="stats.gross_margin < (stats.revenue * 0.2)" class="p-6 rounded-3xl bg-red-50 border border-red-100 flex items-start gap-4">
                    <div class="p-3 bg-red-500 text-white rounded-2xl shadow-lg shadow-red-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-red-900 uppercase">Alerte Profitabilité</h4>
                        <p class="mt-1 text-xs text-red-700 leading-relaxed font-medium">La marge brute est anormalement basse. Vérifiez les prix d'achat fournisseurs ou renégociez les tarifs d'approvisionnement.</p>
                    </div>
                </div>

                <div class="p-6 rounded-3xl bg-indigo-50 border border-indigo-100 flex items-start gap-4">
                    <div class="p-3 bg-indigo-600 text-white rounded-2xl shadow-lg shadow-indigo-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-black text-indigo-900 uppercase">Capacité d'Investissement</h4>
                        <p class="mt-1 text-xs text-indigo-700 leading-relaxed font-medium">Votre stock est valorisé à {{ formatCurrency(stats.stock_worth) }}. Pensez à déstocker les produits à rotation lente pour libérer de la trésorerie.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CATALOGUE MANAGEMENT TABLE -->
        <div class="lg:col-span-2 bg-white p-10 rounded-[3rem] shadow-xl shadow-gray-100/50 border border-gray-100 mt-10">
            <div class="flex items-center justify-between mb-8">
                <div>
                   <h2 class="text-xl font-bold text-gray-900 uppercase tracking-tighter">Catalogues <span class="text-emerald-600">Interactifs</span></h2>
                   <p class="text-[10px] text-gray-500 font-bold mt-1 uppercase tracking-widest bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100 w-fit">Publicité & Brochures Clients</p>
                </div>
                <div class="text-[10px] font-black bg-gray-900 text-white px-4 py-2 rounded-xl">
                    {{ catalogues.length }} DOCUMENTS
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr>
                            <th class="pb-6 text-[10px] font-black text-gray-400 uppercase tracking-widest">Document</th>
                            <th class="pb-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Catégorie</th>
                            <th class="pb-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Statut</th>
                            <th class="pb-6 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="cat in catalogues" :key="cat.id" class="group hover:bg-gray-50/50 transition-colors">
                            <td class="py-5">
                                <div class="flex items-center gap-4">
                                    <div class="p-3 bg-red-50 text-red-600 rounded-2xl group-hover:bg-red-600 group-hover:text-white transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-black text-gray-900 group-hover:text-indigo-600 transition-colors">{{ cat.title }}</div>
                                        <div class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">PDF • Ajouté le {{ new Date(cat.created_at).toLocaleDateString() }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-5 text-center">
                                <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-lg text-[9px] font-black uppercase tracking-widest">{{ cat.category || 'GÉNÉRAL' }}</span>
                            </td>
                            <td class="py-5 text-center">
                                <button @click="toggleCatStatus(cat)" :class="[
                                    'px-4 py-1.5 rounded-full text-[9px] font-black uppercase tracking-widest transition-all shadow-sm',
                                    cat.is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-400 hover:bg-gray-200'
                                ]">
                                    {{ cat.is_active ? 'En ligne' : 'Brouillon' }}
                                </button>
                            </td>
                            <td class="py-5 text-right">
                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a :href="getStorageUrl(cat.file_path)" target="_blank" class="p-2.5 bg-brand-50 text-brand-600 rounded-xl hover:bg-brand-600 hover:text-white transition-all shadow-sm border border-brand-100">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    <button @click="deleteCatalogue(cat.id)" class="p-2.5 bg-red-50 text-red-600 rounded-xl hover:bg-red-600 hover:text-white transition-all shadow-sm border border-red-100">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </div>
</template>

<style scoped>
.text-indigo-600 { color: #5c6bc0; }
.bg-indigo-600 { background-color: #5c6bc0; }
.shadow-indigo-200 { --tw-shadow-color: rgba(92, 107, 192, 0.4); }
</style>
