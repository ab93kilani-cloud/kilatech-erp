<script setup lang="ts">
import { ref, onMounted, computed, nextTick } from 'vue'
import api from '@/plugins/axios'

// Liste des bons de réceptions contenant au moins une ligne en attente (Logistique Fournisseur)
const pendingReceipts = ref<any[]>([])
const selectedReceiptId = ref<number | null>(null)

const linesQA = ref<any[]>([])
const invoiceFile = ref<File | null>(null)
const isConverting = ref(false)
const isPreviewModalOpen = ref(false)
const previewImage = ref<string | null>(null)
const scannedPages = ref<string[]>([]) 
const activeFilter = ref('scan')

let cropper: any = null

const handleInvoiceChange = (e: any) => {
    const file = e.target.files[0]
    if (!file) return
    
    if (file.type.startsWith('image/')) {
        const reader = new FileReader()
        reader.onload = (ev) => {
            previewImage.value = ev.target?.result as string
            isPreviewModalOpen.value = true
            nextTick(() => initCropper())
        }
        reader.readAsDataURL(file)
    } else {
        invoiceFile.value = file
    }
}

const initCropper = () => {
    if (cropper) cropper.destroy()
    const image = document.getElementById('cropper-target') as HTMLImageElement
    if (!image) return
    
    const CropperClass = (window as any).Cropper
    cropper = new CropperClass(image, {
        viewMode: 1, dragMode: 'move', autoCropArea: 0.9, restore: false,
        guides: true, center: true, highlight: false,
        cropBoxMovable: true, cropBoxResizable: true, toggleDragModeOnDblclick: false,
    })
}

const rotateInCropper = () => { if (cropper) cropper.rotate(90) }

const applyFilterToCanvas = (ctx: CanvasRenderingContext2D, width: number, height: number, filter: string) => {
    if (filter === 'original') return
    const imgData = ctx.getImageData(0, 0, width, height)
    const d = imgData.data
    let totalLum = 0;
    for (let i = 0; i < d.length; i += 4) {
        totalLum += ((d[i]??0) * 0.3 + (d[i+1]??0) * 0.59 + (d[i+2]??0) * 0.11);
    }
    const avgLum = totalLum / (d.length / 4);
    const boost = avgLum < 120 ? 1.2 : 1.0;

    for (let i = 0; i < d.length; i += 4) {
        const r = (d[i] ?? 0) * boost, g = (d[i+1] ?? 0) * boost, b = (d[i+2] ?? 0) * boost
        const gray = r * 0.3 + g * 0.59 + b * 0.11
        if (filter === 'scan') {
            const threshold = avgLum > 180 ? 160 : 140; 
            const val = gray > threshold ? 255 : (gray < 60 ? 0 : gray * 0.8)
            d[i] = d[i+1] = d[i+2] = val
        } else if (filter === 'bw') {
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
    previewImage.value = null
    cropper.destroy(); cropper = null
    document.getElementById('receipt-inv-upload')?.click()
}

const finishScan = async () => {
    isConverting.value = true
    try {
        if (cropper && previewImage.value) {
            const canvas = cropper.getCroppedCanvas({ maxWidth: 2000, maxHeight: 2000 })
            const ctx = canvas.getContext('2d')!
            applyFilterToCanvas(ctx, canvas.width, canvas.height, activeFilter.value)
            scannedPages.value.push(canvas.toDataURL('image/jpeg', 0.85))
        }
        if (scannedPages.value.length === 0) { isPreviewModalOpen.value = false; return; }

        const { jsPDF } = (window as any).jspdf
        const pdf = new jsPDF()
        for (let i = 0; i < scannedPages.value.length; i++) {
            if (i > 0) pdf.addPage()
            const imgData = scannedPages.value[i]!
            const img = new Image(); img.src = imgData;
            await new Promise(r => img.onload = r)
            const pageWidth = 210, pageHeight = 297
            const ratio = Math.min(pageWidth / img.width, pageHeight / img.height)
            const finalW = img.width * ratio, finalH = img.height * ratio
            pdf.addImage(imgData, 'JPEG', (pageWidth - finalW)/2, (pageHeight - finalH)/2, finalW, finalH)
        }
        const blob = pdf.output('blob')
        invoiceFile.value = new File([blob], `scan_reception_${Date.now()}.pdf`, { type: 'application/pdf' })
        isPreviewModalOpen.value = false
        scannedPages.value = []
        previewImage.value = null
    } catch (e) {
        alert("Erreur scan")
    } finally {
        isConverting.value = false
    }
}

const isLoadingReceipts = ref(true)
const isSubmitting = ref(false)
const errorMsg = ref('')
const successMsg = ref('')

// Charger les bons de réceptions
const fetchPendingQA = async () => {
    try {
        // Obtenir aussi le supplier (via purchaseOrder.supplier que l'on va forcer ou qui est là)
        const response = await api.get('/receipts')
        // Ne garder que les receipts ayant au moins une ligne "pending_qa"
        const pending: any[] = []
        response.data.forEach((receipt: any) => {
            const pendingLines = receipt.lines.filter((line: any) => line.status === 'pending_qa')
            if (pendingLines.length > 0) {
                // On attache les infos pour affichage
                receipt.pending_lines = pendingLines
                pending.push(receipt)
            }
        })
        pendingReceipts.value = pending
    } catch (e) {
        // Optionnel
    } finally {
        isLoadingReceipts.value = false
    }
}

const isReverting = ref(false)
const revertToPO = async (receiptId: number) => {
    if (!confirm("Voulez-vous annuler cette réception et retourner au Bon de Commande ? Le BC sera restauré et cette réception disparaîtra du QA.")) return;
    
    isReverting.value = true
    try {
        await api.post(`/receipts/${receiptId}/revert`)
        successMsg.value = "Réception annulée. Le Bon de Commande a été restauré dans la liste active."
        selectedReceiptId.value = null
        selectedReceiptData.value = null
        fetchPendingQA()
    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || "Erreur lors du retour au BC."
    } finally {
        isReverting.value = false
    }
}

const selectedReceiptData = ref<any>(null)

const onSelectReceipt = () => {
    const receipt = pendingReceipts.value.find(r => r.id === selectedReceiptId.value)
    if (receipt) {
        selectedReceiptData.value = receipt
        // Prepare forms for each line
        linesQA.value = receipt.pending_lines.map((l: any) => ({
            id: l.id,
            po_line: l.po_line,
            // Fallback for snake case
            productName: l.po_line?.variant?.product?.name || l.poLine?.variant?.product?.name || 'Produit Inconnu',
            brandName: l.po_line?.variant?.product?.brand || l.poLine?.variant?.product?.brand || '',
            sku: l.po_line?.variant?.sku || l.poLine?.variant?.sku || '-',
            quantity: l.received_quantity,
            dateType: 'dlc',
            lot_number: '',
            expiration_date: '',
            serial_numbers: Array(l.received_quantity).fill(''),
             // Local scan logic state
            scanInput: ''
        }))
        successMsg.value = ''
        invoiceFile.value = null
    } else {
        selectedReceiptData.value = null
        linesQA.value = []
    }
}

// Fonction de simulation de scan via Douchette
const handleScan = (lineIndex: number) => {
    const line = linesQA.value[lineIndex]
    const code = line.scanInput.trim()
    if (!code) return

    const upperCode = code.toUpperCase()
    if (upperCode.includes('LOT') || upperCode.startsWith('L')) {
        line.lot_number = code
        successMsg.value = `Lot ${code} scanné pour la ligne ${lineIndex + 1}.`
    } else if (upperCode.startsWith('202') && upperCode.length >= 8) {
        line.expiration_date = code
        successMsg.value = `Date d'expiration ${code} scannée pour la ligne ${lineIndex + 1}.`
    } else {
        // Trouver le premier SN vide
        const firstEmptyIndex = line.serial_numbers.findIndex((sn: string) => !sn)
        if (firstEmptyIndex !== -1) {
            line.serial_numbers[firstEmptyIndex] = code
            successMsg.value = `Numéro de série scanné (Unité ${firstEmptyIndex + 1}).`
        } else {
            errorMsg.value = "Toutes les unités de cette ligne ont déjà un SN."
        }
    }
    line.scanInput = ''
    setTimeout(() => { successMsg.value = ''; errorMsg.value = ''; }, 3000)
}

const validateQA = async () => {
    isSubmitting.value = true
    errorMsg.value = ''
    successMsg.value = ''

    try {
        let firstLinePassed = false;
        
        // Process each line separately
        for (const line of linesQA.value) {
            const formData = new FormData()
            formData.append('receipt_line_id', line.id.toString())
            formData.append('lot_number', line.lot_number)
            formData.append('expiration_date', line.expiration_date)
            
            // On the first line ONLY, append the invoice to upload it with the receipt!
            if (!firstLinePassed && invoiceFile.value) {
                formData.append('invoice_file', invoiceFile.value)
            }
            firstLinePassed = true;

            line.serial_numbers.forEach((sn: string, index: number) => {
                if (sn) formData.append(`serial_numbers[${index}]`, sn)
            })

            await api.post('/stock-units/validate-qa', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            })
        }
        
        successMsg.value = 'Contrôle QA validé ! Unités de stock ajoutées avec succès pour toutes les lignes.'
        selectedReceiptId.value = null
        selectedReceiptData.value = null
        linesQA.value = []
        invoiceFile.value = null
        
        fetchPendingQA() // Recharge la liste
    } catch (e: any) {
        if (e.response?.data?.errors) {
            const errors = e.response.data.errors
            const firstError = Object.values(errors)[0] as string[]
            errorMsg.value = `${e.response.data.message}: ${firstError[0]}`
        } else {
            const backendMsg = e.response?.data?.message
            const detailMsg = e.response?.data?.error
            errorMsg.value = detailMsg ? `${backendMsg} : ${detailMsg}` : (backendMsg || 'Erreur lors du contrôle qualité sur une des lignes.')
        }
    } finally {
        isSubmitting.value = false
    }
}

const supplierName = computed(() => {
    return selectedReceiptData.value?.purchase_order?.supplier?.name || selectedReceiptData.value?.purchaseOrder?.supplier?.name || "Fournisseur Inconnu"
})

onMounted(() => {
    fetchPendingQA()
})
</script>

<template>
  <div class="max-w-4xl mx-auto py-8">
    <div class="mb-8 flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-gray-900">Validation Contrôle Qualité (QA)</h1>
      <router-link :to="{ name: 'stock' }" class="text-sm font-medium text-gray-600 hover:text-gray-900 border px-3 py-1.5 rounded bg-white">
        Retour Inventaire
      </router-link>
    </div>

    <!-- Error/Sucess States -->
    <div v-if="successMsg" class="bg-green-50 border-l-4 border-green-400 p-4 mb-4">
      <p class="text-sm text-green-700 font-bold">{{ successMsg }}</p>
    </div>
    <div v-if="errorMsg" class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
      <p class="text-sm text-red-700">{{ errorMsg }}</p>
    </div>

    <div v-if="isLoadingReceipts" class="text-center py-4 text-gray-500">
        Recherche des lignes en attente de QA...
    </div>

    <div v-else-if="pendingReceipts.length === 0" class="bg-white px-4 py-12 shadow sm:rounded-lg text-center border-2 border-dashed border-gray-300">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun produit en attente</h3>
        <p class="mt-1 text-sm text-gray-500">Toutes les réceptions fournisseurs ont été contrôlées et rangées.</p>
    </div>

    <form v-else @submit.prevent="validateQA" class="bg-white shadow-sm ring-1 ring-gray-200 sm:rounded-xl">
      <div class="px-4 py-6 sm:p-8 space-y-6">
        
        <div class="sm:col-span-full">
            <label class="block text-sm font-medium leading-6 text-gray-900">Bon de Réception Fournisseur à valider *</label>
            <div class="mt-2">
                <select v-model="selectedReceiptId" @change="onSelectReceipt" required class="block w-full rounded-md border-0 py-2 pl-3 pr-10 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                    <option :value="null" disabled>Sélectionner un Bon ({{ pendingReceipts.length }} en attente)</option>
                    <option v-for="receipt in pendingReceipts" :key="receipt.id" :value="receipt.id">
                        Bon N° {{ receipt.receipt_number }} - Fournisseur: {{ receipt.purchase_order?.supplier?.name || receipt.purchaseOrder?.supplier?.name || 'Inconnu' }} 
                        ({{ receipt.pending_lines.length }} produit(s) à contrôler)
                    </option>
                </select>
            </div>
            <p class="text-xs text-gray-500 mt-1">Sélectionnez la réception pour valider physiquement l'ensemble de la marchandise.</p>
        </div>

        <template v-if="selectedReceiptData">
            
            <!-- Global Invoice Upload (Scanner Pro) -->
            <div class="sm:col-span-full bg-brand-50 p-6 rounded-2xl border-2 border-brand-100 flex flex-col md:flex-row items-center justify-between mb-8 gap-6 shadow-sm">
                <div class="flex items-center gap-4">
                    <span class="bg-brand-600 text-white p-3 rounded-xl shadow-lg shadow-brand-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
                    </span>
                    <div>
                        <label class="block text-sm font-black leading-tight text-brand-900 uppercase tracking-tight">Scanner la Facture / BL *</label>
                        <p class="text-[10px] text-brand-600 uppercase font-bold tracking-widest mt-1">Fournisseur : {{ supplierName }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <input type="file" id="receipt-inv-upload" @change="handleInvoiceChange" accept="application/pdf,image/*" class="hidden" />
                    <label for="receipt-inv-upload" class="flex-1 md:flex-none flex items-center justify-center gap-2 px-6 py-3 bg-brand-600 text-white rounded-xl font-black text-xs uppercase tracking-widest shadow-xl shadow-brand-100 hover:bg-brand-700 cursor-pointer active:scale-95 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
                        Fichier / Photo
                    </label>
                    <div v-if="invoiceFile" class="flex items-center gap-2 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-xl border border-emerald-100 font-bold text-[10px] uppercase shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Document OK
                    </div>
                </div>
            </div>

            <!-- Loop over all lines for this receipt -->
            <div v-for="(line, lineIndex) in linesQA" :key="line.id" class="border border-gray-200 rounded-xl overflow-hidden mb-6 shadow-sm">
                
                <!-- ENTETE LIGNE -->
                <div class="bg-gray-100 px-6 py-4 flex items-start justify-between border-b border-gray-200">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 uppercase">{{ line.productName }}</h2>
                        <div v-if="line.brandName" class="text-[10px] font-black text-gray-500 uppercase tracking-widest mt-0.5 mb-1">Marque: {{ line.brandName }}</div>
                        <p class="text-sm text-gray-600 font-mono mt-1">SKU: {{ line.sku }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Quantité à Contrôler</div>
                        <div class="text-3xl font-black text-gray-900">{{ line.quantity }}</div>
                        <div class="text-[9px] text-gray-400 mt-1 uppercase tracking-widest">unités</div>
                    </div>
                </div>

                <div class="p-6">
                    <!-- MODULE DE SCAN -->
                    <div class="bg-gray-800 rounded-lg p-4 text-white shadow-inner flex items-center gap-4">
                        <div class="bg-gray-700 p-3 rounded-full">
                            <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                        </div>
                        <div class="flex-1">
                            <input v-model="line.scanInput" @keyup.enter="handleScan(lineIndex)" type="text" placeholder="Scanner Lot, DLC ou code-barres..." class="block w-full rounded-md border-0 py-2.5 px-4 bg-gray-900 text-white shadow-sm ring-1 ring-inset ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:text-sm font-mono placeholder:text-gray-500">
                        </div>
                        <button type="button" @click="handleScan(lineIndex)" class="bg-gray-700 hover:bg-gray-600 border border-gray-600 text-white px-4 py-2.5 rounded shadow text-sm font-medium transition-colors">Go</button>
                    </div>

                    <!-- INFOS CLES -->
                    <div class="flex flex-wrap items-center gap-6 pt-6 mb-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" value="dlc" v-model="line.dateType" class="text-brand-600 focus:ring-brand-500">
                            <span class="text-[10px] font-bold text-gray-700 uppercase tracking-widest">Consommable (DLC)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" value="warranty" v-model="line.dateType" class="text-brand-600 focus:ring-brand-500">
                            <span class="text-[10px] font-bold text-gray-700 uppercase tracking-widest">Matériel (Garantie)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" value="none" v-model="line.dateType" class="text-brand-600 focus:ring-brand-500">
                            <span class="text-[10px] font-bold text-gray-700 uppercase tracking-widest text-indigo-600">Sans Péremption</span>
                        </label>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-bold leading-6 text-gray-700 uppercase tracking-wide">Numéro de Lot Fournisseur *</label>
                            <div class="mt-1">
                                <input v-model="line.lot_number" required type="text" placeholder="Ex: LOT-MX22" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-gray-600 sm:text-sm font-semibold px-3 bg-gray-50">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold leading-6 text-gray-700 uppercase tracking-wide">
                                {{ line.dateType === 'none' ? "Date (Ignorée)" : (line.dateType === 'dlc' ? "Date Limite (DLC) *" : "Fin de Garantie *") }}
                            </label>
                            <div class="mt-1">
                                <input v-model="line.expiration_date" :required="line.dateType !== 'none'" :disabled="line.dateType === 'none'" type="date" :class="[line.dateType === 'none' ? 'bg-gray-200 opacity-50 cursor-not-allowed' : 'bg-gray-50', 'block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-gray-600 sm:text-sm font-semibold px-3']">
                            </div>
                        </div>
                    </div>

                    <!-- SN -->
                    <div class="pt-6 border-t border-gray-100 mt-6">
                        <h4 class="text-sm font-bold text-gray-900 mb-2">Numéros de Séries physiques (Optionnel)</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div v-for="i in Math.min(line.quantity, 40)" :key="i">
                                <label class="block text-[10px] text-gray-400 mb-1 uppercase tracking-widest">Unité #{{ i }}</label>
                                <input v-model="line.serial_numbers[i-1]" type="text" placeholder="SN (si dispo)" class="block w-full rounded border-gray-200 py-1 text-gray-900 shadow-sm focus:ring-gray-600 sm:text-xs px-2 font-mono" />
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </template>
      </div>
      
      <div class="flex items-center justify-end gap-x-4 border-t border-gray-900/10 px-4 py-4 sm:px-8 bg-gray-50">
        <button v-if="selectedReceiptData" type="button" @click="revertToPO(selectedReceiptData.id)" :disabled="isSubmitting || isReverting" class="rounded-md bg-white px-3 py-2 text-xs font-bold text-red-600 shadow-sm ring-1 ring-inset ring-red-200 hover:bg-red-50 disabled:opacity-50 uppercase tracking-widest">
           Retour au Bon de Commande
        </button>
        <button type="submit" :disabled="isSubmitting || !selectedReceiptId || isReverting" class="rounded-md bg-green-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 disabled:opacity-50 uppercase tracking-widest">
           {{ isSubmitting ? 'Validation Globale...' : 'Valider toute la Réception' }}
        </button>
      </div>
    </form>
  </div>

  <!-- Modal Scanner Professionnel -->
  <div v-if="isPreviewModalOpen" class="fixed inset-0 z-[60] bg-black sm:bg-gray-900 sm:bg-opacity-90 flex flex-col overflow-hidden h-[100dvh]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="bg-black/40 backdrop-blur-md px-6 py-4 flex items-center justify-between border-b border-white/10 text-white z-10">
      <div class="flex items-center gap-2">
        <span class="text-xs font-black tracking-[0.2em] uppercase text-brand-400">Scanner Réception</span>
        <span class="bg-brand-600 text-[10px] px-2 py-0.5 rounded-full">{{ scannedPages.length + (previewImage ? 1 : 0) }} PG</span>
      </div>
      <button @click="isPreviewModalOpen = false" class="p-2 -mr-2 text-white/60 hover:text-white transition-colors">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
      </button>
    </div>

    <div class="flex-1 relative flex items-center justify-center p-0 overflow-hidden">
      <div v-if="previewImage" class="w-full h-full">
        <img id="cropper-target" :src="previewImage" class="max-w-full hidden" />
      </div>
      <div v-if="isConverting" class="absolute inset-0 bg-black/80 flex flex-col items-center justify-center z-20">
        <div class="w-12 h-12 border-4 border-white/10 border-t-brand-500 rounded-full animate-spin"></div>
        <p class="mt-4 text-xs font-black text-white uppercase tracking-widest">Génération du PDF...</p>
      </div>
    </div>

    <div class="bg-gray-950 border-t border-white/10 p-4 pb-12 sm:pb-6 space-y-4 z-10 shadow-[0_-20px_50px_rgba(0,0,0,0.5)]">
      <div class="grid grid-cols-3 gap-2">
        <button @click="activeFilter = 'scan'" :class="[activeFilter === 'scan' ? 'bg-brand-600' : 'bg-gray-800 text-gray-400', 'py-4 rounded-2xl text-[10px] font-black uppercase tracking-tight transition-all']">SCAN PRO</button>
        <button @click="activeFilter = 'bw'" :class="[activeFilter === 'bw' ? 'bg-white text-black' : 'bg-gray-800 text-gray-400', 'py-4 rounded-2xl text-[10px] font-black uppercase tracking-tight transition-all']">N&B ECO</button>
        <button @click="activeFilter = 'original'" :class="[activeFilter === 'original' ? 'bg-indigo-600' : 'bg-gray-800 text-gray-400', 'py-4 rounded-2xl text-[10px] font-black uppercase tracking-tight transition-all']">PHOTO</button>
      </div>

      <div class="flex items-center justify-between gap-4">
        <button @click="isPreviewModalOpen = false" class="flex-1 flex items-center justify-center gap-2 p-4 bg-red-900/40 text-red-500 rounded-2xl border border-red-900/50">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          <span class="text-[10px] font-black uppercase">Abandon</span>
        </button>
        <button @click="rotateInCropper" class="flex-1 flex items-center justify-center gap-2 p-4 bg-gray-800 text-brand-400 rounded-2xl border border-gray-700">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          <span class="text-[10px] font-black uppercase">Pivoter</span>
        </button>
      </div>

      <div class="flex items-center gap-3">
        <button @click="addPageAndContinue" class="flex-1 bg-white text-black h-16 rounded-2xl font-black text-xs uppercase tracking-widest shadow-2xl flex items-center justify-center gap-2 active:scale-95 transition-transform border-2 border-gray-100">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
          <span>+ Page</span>
        </button>
        <button @click="finishScan" :disabled="isConverting" class="flex-[1.5] bg-emerald-600 text-white h-16 rounded-2xl font-black text-xs uppercase tracking-widest shadow-2xl active:scale-95 transition-transform disabled:opacity-50">
          VALIDER ({{ scannedPages.length + (previewImage ? 1 : 0) }} PG)
        </button>
      </div>
    </div>
  </div>
</template>

<style>
.cropper-point {
  width: 32px !important; height: 32px !important;
  background-color: #f97316 !important;
  border-radius: 50% !important; opacity: 1 !important;
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.5), 0 0 0 4px white !important;
  margin-top: -16px !important; margin-left: -16px !important;
}
.cropper-point.point-e, .cropper-point.point-n, .cropper-point.point-w, .cropper-point.point-s { display: none !important; }
.cropper-point.point-ne, .cropper-point.point-nw, .cropper-point.point-se, .cropper-point.point-sw {
  width: 44px !important; height: 44px !important;
  margin-top: -22px !important; margin-left: -22px !important;
  border: 4px solid white !important;
}
.cropper-line { background-color: #f97316 !important; height: 3px !important; }
.cropper-view-box { outline: 3px solid #f97316 !important; }
</style>
