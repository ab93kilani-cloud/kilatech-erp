<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '@/plugins/axios'

interface PurchaseOrder {
  id: number;
  po_number: string;
  order_date: string;
  status: string;
  supplier: { name: string };
  expected_delivery_date: string | null;
  receipts?: any[];
}

const pos = ref<PurchaseOrder[]>([])

const isConverting = ref(false)
const isPreviewModalOpen = ref(false)
const previewImage = ref<string | null>(null)
const activeReceiptId = ref<number | null>(null)
const scannedPages = ref<string[]>([]) // Stocke les images traitées (DataURLs)

let cropper: any = null

const handleArchiveInvoice = async (e: any, receiptId: number) => {
    const file = e.target.files[0]
    if (!file || !receiptId) return
    
    if (file.type.startsWith('image/')) {
        activeReceiptId.value = receiptId
        const reader = new FileReader()
        reader.onload = (ev) => {
            previewImage.value = ev.target?.result as string
            isPreviewModalOpen.value = true
            nextTick(() => initCropper())
        }
        reader.readAsDataURL(file)
    } else {
        await uploadInvoice(file, receiptId)
    }
}

import { nextTick } from 'vue'

const initCropper = () => {
    if (cropper) cropper.destroy()
    const image = document.getElementById('cropper-target') as HTMLImageElement
    if (!image) return
    
    const CropperClass = (window as any).Cropper
    cropper = new CropperClass(image, {
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 0.9,
        restore: false,
        guides: true,
        center: true,
        highlight: false,
        cropBoxMovable: true,
        cropBoxResizable: true,
        toggleDragModeOnDblclick: false,
    })
}

const activeFilter = ref('scan') // 'original', 'scan', 'bw'

const applyFilterToCanvas = (ctx: CanvasRenderingContext2D, width: number, height: number, filter: string) => {
    if (filter === 'original') return
    
    const imgData = ctx.getImageData(0, 0, width, height)
    const d = imgData.data
    for (let i = 0; i < d.length; i += 4) {
        const r = d[i] ?? 0, g = d[i+1] ?? 0, b = d[i+2] ?? 0
        const gray = r * 0.3 + g * 0.59 + b * 0.11
        
        if (filter === 'scan') {
            // Filtre Scanner Pro (Blanchit le papier)
            const val = gray > 150 ? 255 : (gray < 70 ? 0 : gray * 0.9)
            d[i] = d[i+1] = d[i+2] = val
        } else if (filter === 'bw') {
            // Filtre Noir et Blanc pur (Eco)
            const val = gray > 128 ? 255 : 0
            d[i] = d[i+1] = d[i+2] = val
        }
    }
    ctx.putImageData(imgData, 0, 0)
}

const addPageAndContinue = () => {
    if (!cropper) return
    
    const canvas = cropper.getCroppedCanvas({ maxWidth: 2000, maxHeight: 2000 })
    const ctx = canvas.getContext('2d')!
    applyFilterToCanvas(ctx, canvas.width, canvas.height, activeFilter.value)
    
    scannedPages.value.push(canvas.toDataURL('image/jpeg', 0.85))
    
    // Reset pour la page suivante
    previewImage.value = null
    cropper.destroy()
    cropper = null
    
    // On ré-ouvre l'input pour la page suivante
    const inputId = 'inv-upload-' + (pos.value.find(p => p.receipts?.[0]?.id === activeReceiptId.value)?.id)
    const el = document.getElementById(inputId)
    if (el) el.click()
}

const finishAndUpload = async () => {
    if (!activeReceiptId.value) return
    isConverting.value = true
    
    try {
        // Si une image est en cours de recadrage mais non ajoutée, on l'ajoute
        if (cropper && previewImage.value) {
            const canvas = cropper.getCroppedCanvas({ maxWidth: 2000, maxHeight: 2000 })
            const ctx = canvas.getContext('2d')!
            applyFilterToCanvas(ctx, canvas.width, canvas.height, activeFilter.value)
            scannedPages.value.push(canvas.toDataURL('image/jpeg', 0.85))
        }

        if (scannedPages.value.length === 0) return

        const { jsPDF } = (window as any).jspdf
        const pdf = new jsPDF()

        for (let i = 0; i < scannedPages.value.length; i++) {
            if (i > 0) pdf.addPage()
            
            const imgData = scannedPages.value[i]!
            // Calcul de l'aspect ratio pour remplir la page A4
            const img = new Image()
            img.src = imgData
            await new Promise(r => img.onload = r)
            
            const pageWidth = 210, pageHeight = 297
            const ratio = Math.min(pageWidth / img.width, pageHeight / img.height)
            const finalW = img.width * ratio
            const finalH = img.height * ratio
            
            pdf.addImage(imgData, 'JPEG', (pageWidth - finalW)/2, (pageHeight - finalH)/2, finalW, finalH)
        }

        const blob = pdf.output('blob')
        const finalFile = new File([blob], `scan_${Date.now()}.pdf`, { type: 'application/pdf' })
        
        await uploadInvoice(finalFile, activeReceiptId.value)
        
        // Reset global
        isPreviewModalOpen.value = false
        scannedPages.value = []
        previewImage.value = null
    } catch (e) {
        alert("Erreur lors de la génération du PDF.")
    } finally {
        isConverting.value = false
    }
}

const uploadInvoice = async (file: File, receiptId: number) => {
    const formData = new FormData()
    formData.append('invoice_file', file)
    try {
        await api.post(`/receipts/${receiptId}/upload-invoice`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        alert("Document PDF sauvegardé avec succès.")
        fetchPOs()
    } catch (err) {
        alert("Erreur lors de l'envoi.")
    }
}

const downloadInvoice = async (receiptId: number) => {
    try {
        const response = await api.get(`/receipts/${receiptId}/download-invoice`, { responseType: 'blob' })
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
        window.open(url, '_blank')
    } catch (e: any) {
        alert('Erreur lors du téléchargement de la facture.')
    }
}
const isLoading = ref(true)
const error = ref('')
const activeTab = ref('active') // 'active' ou 'archived'

const fetchPOs = async () => {
    isLoading.value = true
    try {
        const url = activeTab.value === 'archived' ? '/purchase-orders?archived=1' : '/purchase-orders'
        const response = await api.get(url)
        pos.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des commandes.'
    } finally {
        isLoading.value = false
    }
}

import { watch } from 'vue'
watch(activeTab, () => {
    fetchPOs()
})

const confirmPO = async (id: number) => {
    try {
        await api.post(`/purchase-orders/${id}/confirm`)
        fetchPOs() // refresh list
    } catch (e: any) {
        alert(e.response?.data?.message || 'Erreur lors de la confirmation.')
    }
}

const archivePO = async (id: number) => {
    if (!confirm('Voulez-vous archiver manuellement cette commande ? Elle sera déplacée dans l\'onglet Archives.')) return;
    try {
        await api.post(`/purchase-orders/${id}/archive`)
        fetchPOs()
    } catch (e: any) {
        alert(e.response?.data?.message || 'Erreur lors de l\'archivage.')
    }
}

const deletePO = async (id: number) => {
    if (!confirm('Êtes-vous sûr de vouloir supprimer définitivement ce bon de commande ?')) return;
    try {
        await api.delete(`/purchase-orders/${id}`)
        fetchPOs()
    } catch (e: any) {
        alert(e.response?.data?.message || 'Erreur lors de la suppression.')
    }
}

const filteredPOs = computed(() => pos.value)

const downloadPDF = async (id: number) => {
    try {
        const response = await api.get(`/purchase-orders/${id}/pdf`, { responseType: 'blob' })
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
        window.open(url, '_blank')
    } catch (e: any) {
        alert('Erreur lors de la génération du PDF.')
    }
}

const isReceiveModalOpen = ref(false)
const selectedPO = ref<any>(null)
const receiveForm = ref({
    receipt_date: '',
    notes: '',
    lines: [] as {po_line_id: number, ordered_quantity: number, received_quantity: number, product_name: string, lot_number: string, expiry_date: string}[]
})
const isReceiving = ref(false)

const openReceiveModal = async (id: number) => {
    try {
        const response = await api.get(`/purchase-orders/${id}`)
        selectedPO.value = response.data
        receiveForm.value.receipt_date = new Date().toISOString().split('T')[0] || ''
        receiveForm.value.notes = ''
        receiveForm.value.lines = response.data.lines.map((l: any) => ({
            po_line_id: l.id,
            ordered_quantity: l.ordered_quantity,
            received_quantity: l.ordered_quantity - l.received_quantity, // Reste à recevoir
            lot_number: '',
            expiry_date: '',
            product_name: l.variant ? (l.variant.product?.brand ? `[${l.variant.product.brand}] ` : '') + `${l.variant.product?.name || ''} - ${l.variant.name_variation || l.variant.sku}` : 'Inconnu'
        })).filter((l: any) => l.received_quantity > 0)
        isReceiveModalOpen.value = true
    } catch (e: any) {
        alert("Erreur lors du chargement des détails.")
    }
}

const submitReceive = async () => {
    isReceiving.value = true
    try {
        await api.post('/receipts', {
            purchase_order_id: selectedPO.value.id,
            receipt_date: receiveForm.value.receipt_date,
            notes: receiveForm.value.notes,
            lines: receiveForm.value.lines.filter(l => l.received_quantity > 0).map(l => ({
                po_line_id: l.po_line_id,
                received_quantity: l.received_quantity,
                actual_purchase_price: null,
                lot_number: l.lot_number || null,
                expiry_date: l.expiry_date || null
            }))
        })
        isReceiveModalOpen.value = false
        fetchPOs()
        alert("Réception réussie : Les produits sont en attente de Contrôle Qualité (QA) pour affectation des lots.")
    } catch (e: any) {
        const detail = e.response?.data?.error || e.response?.data?.message || "";
        alert("Erreur lors de la réception : " + detail);
    } finally {
        isReceiving.value = false
    }
}

onMounted(() => {
    fetchPOs()
})
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Bons de Commande (Achats)</h1>
        <p class="mt-2 text-sm text-gray-700">Gérez vos réapprovisionnements auprès de vos fournisseurs.</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <router-link :to="{ name: 'purchases.create' }" class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 sm:w-auto">
          Nouvelle Commande
        </router-link>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 p-4 mb-4"><p class="text-sm text-red-700">{{ error }}</p></div>
    <div v-else-if="isLoading" class="text-center py-8">Chargement...</div>

    <!-- Interface des Achats -->
    <div v-else>
      <!-- Tabs -->
      <div class="mb-4 border-b border-gray-200">
      <nav class="-mb-px flex space-x-8" aria-label="Tabs">
        <button @click="activeTab = 'active'" :class="[activeTab === 'active' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
          Commandes en cours
        </button>
        <button @click="activeTab = 'archived'" :class="[activeTab === 'archived' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300', 'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm']">
          Archives (Livrés)
        </button>
      </nav>
    </div>

      <!-- Data Table -->
      <div class="mt-4 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
          <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
              <table class="min-w-full divide-y divide-gray-300">
                <thead class="bg-gray-50">
                <tr>
                   <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">N° Commande</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Fournisseur</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date Prévue</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Statut</th>
                   <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="po in filteredPOs" :key="po.id" :class="{'bg-gray-50': po.status === 'draft'}">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">{{ po.po_number }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ po.supplier?.name }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ po.expected_delivery_date || '-' }}</td>
                  
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                     <span v-if="po.status === 'draft'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 uppercase">Brouillon</span>
                     <span v-else-if="po.status === 'ordered'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 tracking-wider font-black">COMMANDÉ</span>
                     <span v-else-if="po.status === 'sent'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">ENVOYÉE</span>
                     <span v-else-if="po.status === 'partially_received'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Partiellement Reçu</span>
                     <span v-else-if="po.status === 'received'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Réception Terminée</span>
                     <span v-else class="text-gray-500 uppercase text-xs">{{ po.status }}</span>
                  </td>
                  
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <div v-if="(po.status === 'received' || po.status === 'completed') && po.receipts?.length" class="flex items-center gap-2">
                        <span v-if="po.receipts[0]?.invoice_status === 'received'" class="text-green-600 font-bold bg-green-50 px-2 py-0.5 rounded text-[10px]">FACTURE OK</span>
                        <span v-else class="text-orange-600 font-bold bg-orange-50 px-2 py-0.5 rounded text-[10px]">FACTURE À RÉGLER</span>
                        
                        <input type="file" :id="'inv-upload-'+po.id" class="hidden" accept="application/pdf,image/*" @change="(e) => handleArchiveInvoice(e, po.receipts![0]?.id)">
                        <label v-if="!po.receipts[0]?.invoice_path" :for="'inv-upload-'+po.id" class="cursor-pointer text-[10px] bg-brand-600 text-white px-2 py-0.5 rounded hover:bg-brand-700 font-bold uppercase transition-transform active:scale-95 shadow-lg">
                           {{ isConverting ? '📦 SCAN EN COURS...' : 'Uploader PDF / Photo' }}
                        </label>
                      </div>
                  </td>
                  
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 space-x-3">
                     <button @click="downloadPDF(po.id)" class="text-gray-600 hover:text-gray-900 font-medium">📥 Commande</button>
                     <button v-if="po.receipts?.length && po.receipts[0]?.invoice_path" @click="downloadInvoice(po.receipts[0].id)" class="text-blue-600 hover:text-blue-900 font-medium">🧾 Facture Fourn.</button>
                     <button v-if="po.status === 'draft'" @click="confirmPO(po.id)" class="text-brand-600 hover:text-brand-900 font-medium">Confirmer la Commande</button>
                     <button v-if="po.status === 'ordered' || po.status === 'partially_received'" @click="openReceiveModal(po.id)" class="text-green-600 hover:text-green-900 font-bold">Réceptionner</button>
                     <button v-if="po.status === 'ordered' || po.status === 'partially_received'" @click="archivePO(po.id)" class="text-gray-400 hover:text-gray-600 bg-gray-50 px-2 py-0.5 rounded border border-gray-100 text-[10px] font-bold uppercase transition-colors" title="Archiver la commande">Archiver</button>
                     <router-link v-if="po.status === 'draft'" :to="{ name: 'purchases.edit', params: { id: po.id } }" class="text-blue-600 hover:text-blue-900 font-medium">Modifier</router-link>
                     <router-link v-else :to="{ name: 'purchases.show', params: { id: po.id } }" class="text-blue-600 hover:text-blue-900 font-medium tracking-tight">Ouvrir</router-link>
                     <button v-if="po.status === 'draft' || po.status === 'ordered' || po.status === 'sent'" @click="deletePO(po.id)" class="text-red-400 hover:text-red-600 flex items-center gap-1 inline-flex ml-4 group">
                        <svg class="h-4 w-4 opacity-70 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        <span class="text-[10px] uppercase font-bold tracking-widest">Supprimer</span>
                     </button>
                  </td>
                </tr>
                <tr v-if="filteredPOs.length === 0">
                    <td colspan="5" class="py-8 text-center text-gray-500">Aucun bon de commande dans cette vue.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    </div>

    <!-- Modal de Réception -->
    <div v-if="isReceiveModalOpen" class="relative z-10" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
      <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-2" id="modal-title">
              Réception de la Commande N° {{ selectedPO?.po_number }}
            </h3>
            <p class="text-sm text-gray-500 mb-4">Saisissez les quantités reçues. Les produits iront ensuite en Entrée QA pour le contrôle des lots.</p>
            
            <form @submit.prevent="submitReceive" class="space-y-6">
              <div class="grid grid-cols-2 gap-4 border-b pb-4">
                  <div>
                      <label class="block text-sm font-medium text-gray-700">Date de Réception *</label>
                      <input v-model="receiveForm.receipt_date" required type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                  </div>
                  <div>
                      <label class="block text-sm font-medium text-gray-700">Notes du bon de livraison (Facultatif)</label>
                      <input v-model="receiveForm.notes" type="text" placeholder="N° du BL papier, Observations..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                  </div>
              </div>

              <div>
                  <h4 class="text-sm font-medium text-gray-900 mb-3">Produits à recevoir</h4>
                  <p v-if="receiveForm.lines.length === 0" class="text-gray-500 text-sm">Tous les produits de cette commande ont déjà été réceptionnés.</p>
                  <table v-else class="min-w-full divide-y divide-gray-200">
                      <thead class="bg-gray-50">
                          <tr>
                              <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Produit (SKU)</th>
                              <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-32">Lot N°</th>
                              <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 w-32">Péremption</th>
                              <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 w-24">Qte Reçue</th>
                          </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-200">
                          <tr v-for="line in receiveForm.lines" :key="line.po_line_id">
                              <td class="px-3 py-2 text-sm text-gray-900">
                                  <div class="font-bold">{{ line.product_name }}</div>
                                  <div class="text-[10px] text-gray-500">Commande: {{ line.ordered_quantity }} unités</div>
                              </td>
                              <td class="px-3 py-2">
                                  <input type="text" v-model="line.lot_number" placeholder="LOT..." class="w-full text-xs rounded border-gray-300 px-2 py-1 shadow-sm focus:ring-brand-500 focus:border-brand-500 border uppercase">
                              </td>
                              <td class="px-3 py-2">
                                  <input type="date" v-model="line.expiry_date" class="w-full text-xs rounded border-gray-300 px-2 py-1 shadow-sm focus:ring-brand-500 focus:border-brand-500 border">
                              </td>
                              <td class="px-3 py-2 text-right">
                                  <input type="number" v-model.number="line.received_quantity" min="0" :max="line.ordered_quantity" class="w-full text-right font-bold text-brand-700 rounded border-brand-300 px-2 py-1 text-sm shadow-sm ring-1 ring-inset ring-brand-300 focus:ring-brand-600 focus:ring-2">
                              </td>
                          </tr>
                      </tbody>
                  </table>
              </div>

              <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                <button type="submit" :disabled="isReceiving || receiveForm.lines.length === 0" class="inline-flex w-full justify-center rounded-md border border-transparent bg-green-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 sm:col-start-2 sm:text-sm disabled:opacity-50">
                  {{ isReceiving ? 'Réception en cours...' : 'Enregistrer la Réception' }}
                </button>
                <button @click="isReceiveModalOpen = false" type="button" class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:col-start-1 sm:mt-0 sm:text-sm">
                  Annuler
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Scanner Ultra-Complet -->
    <div v-if="isPreviewModalOpen" class="fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-90 transition-opacity" @click="isPreviewModalOpen = false"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="relative inline-block align-middle bg-white rounded-2xl text-left shadow-2xl transform transition-all sm:my-8 sm:max-w-4xl w-full border border-gray-100 overflow-hidden">
          
          <!-- Header -->
          <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-b">
            <div class="flex items-center gap-3">
              <span class="bg-brand-600 text-white p-2 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg></span>
              <h3 class="text-lg font-black text-gray-900">NUMÉRISATION PROFESSIONNELLE</h3>
            </div>
            <div class="flex items-center gap-4">
              <span class="text-xs font-bold text-gray-500 bg-gray-200 px-3 py-1 rounded-full uppercase">{{ scannedPages.length }} PAGE(S)</span>
              <button @click="isPreviewModalOpen = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
            </div>
          </div>

          <!-- Body -->
          <div class="flex flex-col md:flex-row h-[600px]">
            <!-- Zone Cropper -->
            <div class="flex-1 bg-black relative flex items-center justify-center p-4">
               <div v-if="previewImage" class="w-full h-full max-h-[500px]">
                  <img id="cropper-target" :src="previewImage" class="max-w-full hidden" />
               </div>
               <div v-else class="text-white text-center">
                  <p class="text-gray-500 mb-4">Prêt pour la page suivante</p>
                  <button @click="addPageAndContinue" class="bg-brand-600 px-6 py-2 rounded-lg font-bold">Prendre la photo</button>
               </div>
            </div>

            <!-- Toolbar Sider -->
            <div class="w-full md:w-64 bg-white border-l p-6 space-y-8 overflow-y-auto">
              <!-- Filtres -->
              <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Filtres de rendu</label>
                <div class="grid grid-cols-1 gap-2">
                  <button @click="activeFilter = 'scan'" :class="[activeFilter === 'scan' ? 'ring-2 ring-brand-600 bg-brand-50 text-brand-700' : 'bg-gray-50 text-gray-600', 'px-4 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2']">
                     <span class="w-2 h-2 rounded-full bg-blue-500"></span> Scanner Pro
                  </button>
                  <button @click="activeFilter = 'bw'" :class="[activeFilter === 'bw' ? 'ring-2 ring-brand-600 bg-brand-50 text-brand-700' : 'bg-gray-50 text-gray-600', 'px-4 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2']">
                     <span class="w-2 h-2 rounded-full bg-black"></span> Eco N&B
                  </button>
                  <button @click="activeFilter = 'original'" :class="[activeFilter === 'original' ? 'ring-2 ring-brand-600 bg-brand-50 text-brand-700' : 'bg-gray-50 text-gray-600', 'px-4 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2']">
                     <span class="w-2 h-2 rounded-full bg-orange-500"></span> Original
                  </button>
                </div>
              </div>

              <!-- Page Action -->
              <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Actions de page</label>
                <button @click="addPageAndContinue" class="w-full px-4 py-3 bg-gray-900 text-white rounded-xl font-bold text-sm mb-3 hover:bg-black transition-all">
                  + Continuer (Page suiv.)
                </button>
                <p class="text-[10px] text-gray-400 italic">Recadre l'image actuelle et demande la suivante.</p>
              </div>

              <!-- Preview du document final -->
              <div v-if="scannedPages.length > 0">
                 <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Pages déjà scannées</label>
                 <div class="flex gap-2 overflow-x-auto py-2">
                    <img v-for="(img, idx) in scannedPages" :key="idx" :src="img" class="h-16 w-12 object-cover border rounded shadow-sm" />
                 </div>
              </div>
            </div>
          </div>

          <!-- Footer Actions -->
          <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row gap-3 border-t">
            <button @click="isPreviewModalOpen = false" class="px-6 py-2 text-gray-600 font-bold">Annuler tout</button>
            <div class="flex-1"></div>
            <button @click="finishAndUpload" :disabled="isConverting" class="px-10 py-3 bg-emerald-600 text-white rounded-xl font-black shadow-xl shadow-emerald-100 hover:bg-emerald-700 transition-all flex items-center justify-center gap-2">
               <span v-if="isConverting" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
               TERMINER & ENVOYER LE PDF ({{ scannedPages.length + (previewImage ? 1 : 0) }} pages)
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
