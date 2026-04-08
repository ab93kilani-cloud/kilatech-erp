<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '@/plugins/axios'

// Liste des bons de réceptions contenant au moins une ligne en attente (Logistique Fournisseur)
const pendingReceipts = ref<any[]>([])
const selectedReceiptId = ref<number | null>(null)

const invoiceFile = ref<File | null>(null)
const linesQA = ref<any[]>([])

const handleInvoiceChange = (e: any) => {
    invoiceFile.value = e.target.files[0]
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
        errorMsg.value = e.response?.data?.message || 'Erreur lors du contrôle qualité sur une des lignes.'
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
            
            <!-- Global Invoice Upload (once per receipt) -->
            <div class="sm:col-span-full bg-brand-50 p-4 rounded-lg border border-brand-100 flex items-center justify-between mb-4">
                <div>
                    <label class="block text-sm font-bold leading-6 text-brand-900 uppercase">Facture Fournisseur (Physique PDF) *</label>
                    <p class="text-xs text-brand-600 mb-2">Fournie par {{ supplierName }} (Valable pour toute la réception).</p>
                </div>
                <div class="flex items-center gap-4">
                    <input type="file" @change="handleInvoiceChange" accept="application/pdf" class="block text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-white file:text-brand-700 hover:file:bg-brand-50" />
                    <div v-if="selectedReceiptData.invoice_path" class="text-[10px] bg-green-100 text-green-700 px-2 py-1 rounded border border-green-200 font-bold tracking-widest uppercase shadow-sm">✓ Déjà uploadé</div>
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
                    <div class="flex items-center gap-6 pt-6 mb-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" value="dlc" v-model="line.dateType" class="text-brand-600 focus:ring-brand-500">
                            <span class="text-[10px] font-bold text-gray-700 uppercase tracking-widest">Consommable (DLC)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" value="warranty" v-model="line.dateType" class="text-brand-600 focus:ring-brand-500">
                            <span class="text-[10px] font-bold text-gray-700 uppercase tracking-widest">Matériel (Garantie)</span>
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
                            <label class="block text-xs font-bold leading-6 text-gray-700 uppercase tracking-wide">{{ line.dateType === 'dlc' ? "Date Limite (DLC) *" : "Fin de Garantie *" }}</label>
                            <div class="mt-1">
                                <input v-model="line.expiration_date" required type="date" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-gray-600 sm:text-sm font-semibold px-3 bg-gray-50">
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
</template>
