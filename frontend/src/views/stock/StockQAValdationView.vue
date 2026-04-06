<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

// Liste des lignes de réceptions en attente (Logistique Fournisseur)
const pendingReceipts = ref<any[]>([])
const selectedReceiptLineId = ref<number | null>(null)
const selectedReceiptQuantity = ref<number>(0)

const form = ref({
    lot_number: '',
    expiration_date: '',
    serial_numbers: [] as string[],
    invoice_file: null as File | null
})

const handleInvoiceChange = (e: any) => {
    form.value.invoice_file = e.target.files[0]
}

const isLoadingReceipts = ref(true)
const isSubmitting = ref(false)
const errorMsg = ref('')
const successMsg = ref('')
const dateType = ref('dlc')

// Charger les bons de réceptions passés (Mockup de flux)
const fetchPendingQA = async () => {
    try {
        const response = await api.get('/receipts')
        // Filtrons en JS pour trouver les lignes 'pending_qa'
        const pending: any[] = []
        response.data.forEach((receipt: any) => {
            receipt.lines.forEach((line: any) => {
                if (line.status === 'pending_qa') {
                    pending.push({
                        ...line,
                        receipt_number: receipt.receipt_number,
                        receipt_date: receipt.receipt_date,
                        receipt_id: receipt.id
                    })
                }
            })
        })
        pendingReceipts.value = pending
    } catch (e) {
        // Optionnel : Gérer l'erreur silencieuse si pas de data
    } finally {
        isLoadingReceipts.value = false
    }
}

const isReverting = ref(false)
const revertToPO = async (receiptId: number) => {
    if (!confirm("Voulez-vous annuler cette réception et retourner au Bon de Commande ? Le BC sera restauré et cette ligne disparaîtra du QA.")) return;
    
    isReverting.value = true
    try {
        await api.post(`/receipts/${receiptId}/revert`)
        successMsg.value = "Réception annulée. Le Bon de Commande a été restauré dans la liste active."
        selectedReceiptLineId.value = null
        selectedLineData.value = null
        fetchPendingQA()
    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || "Erreur lors du retour au BC."
    } finally {
        isReverting.value = false
    }
}

const selectedLineData = ref<any>(null)

const onSelectLine = () => {
    const line = pendingReceipts.value.find(l => l.id === selectedReceiptLineId.value)
    if (line) {
        selectedLineData.value = line
        selectedReceiptQuantity.value = line.received_quantity
        // Init serial numbers array with empty strings
        form.value.serial_numbers = Array(line.received_quantity).fill('')
        successMsg.value = ''
    } else {
        selectedLineData.value = null
    }
}

// Fonction de simulation de scan via Douchette / Scanner
const scanInput = ref('')
const handleScan = () => {
    const code = scanInput.value.trim()
    if (!code) return

    if (!selectedReceiptLineId.value) {
        errorMsg.value = "Veuillez d'abord sélectionner une ligne à contrôler avant de scanner."
        scanInput.value = ''
        return
    }

    // Pseudo-logique de scan: si ça ressemble à une date (ex: EXP202612), on parse.
    // Si ça a 'LOT', on le met dans Lot. Sinon dans le premier Numéro de série vide.
    const upperCode = code.toUpperCase()
    if (upperCode.includes('LOT') || upperCode.startsWith('L')) {
        form.value.lot_number = code
        successMsg.value = `Lot ${code} scanné.`
    } else if (upperCode.startsWith('202') && upperCode.length >= 8) {
        // Ex: 2026-12-31
        form.value.expiration_date = code
        successMsg.value = `Date d'expiration ${code} scannée.`
    } else {
        // Trouver le premier SN vide
        const firstEmptyIndex = form.value.serial_numbers.findIndex(sn => !sn)
        if (firstEmptyIndex !== -1) {
            form.value.serial_numbers[firstEmptyIndex] = code
            successMsg.value = `Numéro de série ${code} scanné (Unité ${firstEmptyIndex + 1}).`
        } else {
            errorMsg.value = "Toutes les unités ont déjà un numéro de série scanné."
        }
    }
    scanInput.value = ''
    
    // Clear success/error after 3s
    setTimeout(() => { successMsg.value = ''; errorMsg.value = ''; }, 3000)
}

const validateQA = async () => {
    isSubmitting.value = true
    errorMsg.value = ''
    successMsg.value = ''

    const formData = new FormData()
    formData.append('receipt_line_id', selectedReceiptLineId.value!.toString())
    formData.append('lot_number', form.value.lot_number)
    formData.append('expiration_date', form.value.expiration_date)
    if (form.value.invoice_file) {
        formData.append('invoice_file', form.value.invoice_file)
    }
    form.value.serial_numbers.forEach((sn, index) => {
        if (sn) formData.append(`serial_numbers[${index}]`, sn)
    })

    try {
        await api.post('/stock-units/validate-qa', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
        
        successMsg.value = 'Contrôle QA validé ! Unités de stock ajoutées avec succès (Traçabilité au SN générée).'
        selectedReceiptLineId.value = null
        selectedReceiptQuantity.value = 0
        form.value.lot_number = ''
        form.value.expiration_date = ''
        form.value.serial_numbers = []
        form.value.invoice_file = null
        
        fetchPendingQA() // Recharge la liste
    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || 'Erreur lors du contrôle qualité.'
    } finally {
        isSubmitting.value = false
    }
}

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
            <label class="block text-sm font-medium leading-6 text-gray-900">Ligne de réception à valider *</label>
            <div class="mt-2">
                <select v-model="selectedReceiptLineId" @change="onSelectLine" required class="block w-full rounded-md border-0 py-2 pl-3 pr-10 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6">
                    <option :value="null" disabled>Sélectionner une ligne ({{ pendingReceipts.length }} en attente)</option>
                    <option v-for="line in pendingReceipts" :key="line.id" :value="line.id">
                        Bon N° {{ line.receipt_number }} - {{ line.poLine?.variant?.product?.name || 'Produit Inconnu' }} 
                        [ {{ line.poLine?.variant?.product?.brand || 'Sans Marque' }} ] (Qté: {{ line.received_quantity }})
                    </option>
                </select>
            </div>
            <p class="text-xs text-gray-500 mt-1">Sélectionnez la marchandise que vous venez d'inspecter physiquement.</p>
        </div>

        <template v-if="selectedLineData">
            <!-- CARTE PRODUIT ET DETAILS -->
            <div class="bg-brand-50 rounded-lg p-4 border border-brand-200 flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-bold text-brand-900 uppercase">{{ selectedLineData.poLine?.variant?.product?.name }}</h2>
                    <div v-if="selectedLineData.poLine?.variant?.product?.brand" class="text-xs font-black text-brand-600 uppercase tracking-widest mt-0.5 mb-2">Marque: {{ selectedLineData.poLine?.variant?.product?.brand }}</div>
                    <p class="text-sm text-brand-700 font-mono mt-1">SKU: {{ selectedLineData.poLine?.variant?.sku }}</p>
                    <p class="text-sm text-gray-600 mt-2">Origine: {{ selectedLineData.poLine?.purchase_order?.supplier?.name }} (BC {{ selectedLineData.poLine?.purchase_order?.po_number }})</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500 uppercase tracking-widest">Quantité à Contrôler</div>
                    <div class="text-4xl font-black text-brand-600">{{ selectedReceiptQuantity }}</div>
                    <div class="text-xs text-gray-500 mt-1">unités physiques</div>
                </div>
            </div>

            <!-- MODULE DE SCAN (DOUCHETTE) -->
            <div class="bg-gray-800 rounded-lg p-4 text-white shadow-inner flex items-center gap-4">
                <div class="bg-gray-700 p-3 rounded-full">
                    <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-300 mb-1 uppercase tracking-wider">Douchette / Scanner Code-Barres</label>
                    <input v-model="scanInput" @keyup.enter="handleScan" type="text" placeholder="Scannez un Lot, une Péremption ou un N° de Série ici... (puis Entrée)" class="block w-full rounded-md border-0 py-2.5 px-4 bg-gray-900 text-white shadow-sm ring-1 ring-inset ring-gray-600 focus:ring-2 focus:ring-inset focus:ring-brand-500 sm:text-sm font-mono placeholder:text-gray-500">
                </div>
                <button type="button" @click="handleScan" class="bg-brand-600 hover:bg-brand-500 text-white px-4 py-2.5 rounded shadow mt-5 text-sm font-medium">Scanner</button>
            </div>
            <!-- FIN MODULE DE SCAN -->
            <div class="flex items-center gap-6 pt-4 border-t border-gray-100">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" value="dlc" v-model="dateType" class="text-brand-600 focus:ring-brand-500">
                    <span class="text-xs font-bold text-gray-700 uppercase">Consommable (DLC/Péremption)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" value="warranty" v-model="dateType" class="text-brand-600 focus:ring-brand-500">
                    <span class="text-xs font-bold text-gray-700 uppercase">Matériel (Fin de Garantie)</span>
                </label>
            </div>
            
            <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 pt-4">
                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900">Numéro de Lot Fournisseur *</label>
                    <div class="mt-2">
                        <input v-model="form.lot_number" required type="text" placeholder="Ex: LOT-MX22" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium leading-6 text-gray-900">{{ dateType === 'dlc' ? "Date Limite d'Utilisation (DLC) *" : "Fin de Garantie Constructeur *" }}</label>
                    <div class="mt-2">
                        <input v-model="form.expiration_date" required type="date" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-sm sm:leading-6 px-3">
                    </div>
                </div>

                <div class="sm:col-span-full border-t border-gray-100 pt-6 mt-4">
                    <label class="block text-sm font-bold leading-6 text-gray-900 uppercase">Facture Fournisseur (Physique PDF) *</label>
                    <p class="text-xs text-gray-500 mb-2">Uploadez le document PDF reçu avec la marchandise pour la comptabilité.</p>
                    <div class="mt-2 flex items-center gap-4">
                        <input type="file" @change="handleInvoiceChange" accept="application/pdf" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" />
                        <div v-if="selectedLineData?.receipt?.invoice_path" class="text-[10px] bg-green-50 text-green-700 px-2 py-1 rounded border border-green-100 font-bold">✓ DÉJÀ UPLOADÉ</div>
                    </div>
                </div>
            </div>

            <div class="pt-6">
                <h4 class="text-sm font-medium text-gray-900 mb-4">Saisie des Numéros de Séries ({{ selectedReceiptQuantity }} unités)</h4>
                <p class="text-xs text-brand-600 mb-3 bg-brand-50 p-2 rounded">
                    Laissez vide pour générer des unités anonymées (traçables uniquement par le numéro de lot pour les consommables). Remplissez pour le gros matériel médical.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div v-for="i in Math.min(selectedReceiptQuantity, 50)" :key="i">
                        <label class="block text-xs text-gray-500 mb-1">Unité #{{ i }} - N° Série (SN)</label>
                        <input v-model="form.serial_numbers[i-1]" type="text" placeholder="Code barres / SN" class="block w-full rounded-md border-0 py-1 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-600 sm:text-xs px-2">
                    </div>
                </div>
                <!-- Limite l'affichage à 50 virtuellement pour le frontend si on recoit 1000 gants -->
            </div>
        </template>
      </div>
      
      <div class="flex items-center justify-end gap-x-4 border-t border-gray-900/10 px-4 py-4 sm:px-8 bg-gray-50">
        <button v-if="selectedLineData" type="button" @click="revertToPO(selectedLineData.receipt_id)" :disabled="isSubmitting || isReverting" class="rounded-md bg-white px-3 py-2 text-xs font-bold text-red-600 shadow-sm ring-1 ring-inset ring-red-200 hover:bg-red-50 disabled:opacity-50 uppercase tracking-widest">
           Retour au Bon de Commande
        </button>
        <button type="submit" :disabled="isSubmitting || !selectedReceiptLineId || isReverting" class="rounded-md bg-green-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 disabled:opacity-50">
           {{ isSubmitting ? 'Validation...' : 'Valider le Contrôle & Créer Stock' }}
        </button>
      </div>
    </form>
  </div>
</template>
