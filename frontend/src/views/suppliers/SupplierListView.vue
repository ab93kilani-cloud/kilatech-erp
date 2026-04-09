<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '@/plugins/axios'

interface Supplier {
  id: number;
  name: string;
  email: string;
  phone: string;
  address: string;
  ice?: string;
  rc?: string;
  if?: string;
  patente?: string;
  tax_certificate_path?: string;
  tax_certificate_expires_at?: string;
}

const suppliers = ref<Supplier[]>([])
const isLoading = ref(true)
const error = ref('')
const searchQuery = ref('')

const filteredSuppliers = computed(() => {
    if (!searchQuery.value) return suppliers.value
    const query = searchQuery.value.toLowerCase()
    return suppliers.value.filter(s => 
        s.name.toLowerCase().includes(query) || 
        (s.email && s.email.toLowerCase().includes(query)) ||
        (s.phone && s.phone.includes(query))
    )
})

const isModalOpen = ref(false)
const isSubmitting = ref(false)
const editingId = ref<number | null>(null)
const form = ref({
    name: '',
    email: '',
    phone: '',
    address: '',
    ice: '',
    rc: '',
    if: '',
    patente: ''
})

const fetchSuppliers = async () => {
    try {
        const response = await api.get('/suppliers')
        suppliers.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des fournisseurs.'
    } finally {
        isLoading.value = false
    }
}

const saveSupplier = async () => {
    isSubmitting.value = true
    try {
        if (editingId.value) {
            const response = await api.put(`/suppliers/${editingId.value}`, form.value)
            const index = suppliers.value.findIndex(s => s.id === editingId.value)
            if (index !== -1) suppliers.value[index] = response.data
        } else {
            const response = await api.post('/suppliers', form.value)
            suppliers.value.unshift(response.data)
        }
        closeModal()
    } catch (e: any) {
        alert("Erreur lors de l'enregistrement du fournisseur.")
    } finally {
        isSubmitting.value = false
    }
}

const editSupplier = (supplier: Supplier) => {
    editingId.value = supplier.id
    form.value = { 
        name: supplier.name, 
        email: supplier.email, 
        phone: supplier.phone, 
        address: supplier.address, 
        ice: supplier.ice || '', 
        rc: supplier.rc || '', 
        if: supplier.if || '', 
        patente: supplier.patente || '' 
    }
    isModalOpen.value = true
}

const deleteSupplier = async (id: number) => {
    if (!confirm('Voulez-vous vraiment supprimer ce fournisseur ?')) return
    try {
        await api.delete(`/suppliers/${id}`)
        suppliers.value = suppliers.value.filter(s => s.id !== id)
    } catch (e: any) {
        alert("Erreur lors de la suppression.")
    }
}

const closeModal = () => {
    isModalOpen.value = false
    editingId.value = null
    form.value = { name: '', email: '', phone: '', address: '', ice: '', rc: '', if: '', patente: '' }
}


const uploadCertificate = async (event: Event, supplierId: number) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    const expiresAt = prompt("Date d'expiration (AAAA-MM-JJ) :", new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toISOString().split('T')[0]);
    if (!expiresAt) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('expires_at', expiresAt);

    try {
        isLoading.value = true;
        await api.post(`/suppliers/${supplierId}/tax-certificate`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        alert('Attestation uploadée avec succès !');
        fetchSuppliers();
    } catch (e: any) {
        alert("Erreur lors de l'upload de l'attestation.");
    } finally {
        isLoading.value = false;
        target.value = '';
    }
}

const downloadFile = async (path: string, fileName: string = 'document.pdf') => {
    try {
        const url = `/documents/${path.startsWith('/') ? path.slice(1) : path}`;
        const response = await api.get(url, { responseType: 'blob' });
        const blob = new Blob([response.data], { type: 'application/pdf' });
        const blobUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.setAttribute('download', fileName.endsWith('.pdf') ? fileName : `${fileName}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(blobUrl);
    } catch (e) {
        console.error('Download failed', e);
        alert('Erreur: Impossible de télécharger le document depuis le serveur sécurisé.');
    }
}

onMounted(() => {
    fetchSuppliers()
})
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Fournisseurs Matériel B2B</h1>
        <p class="mt-2 text-sm text-gray-700">Gérez vos fabricants locaux ou internationaux.</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex items-center gap-3">
        <!-- Barre de recherche Maya -->
        <div class="relative rounded-md shadow-sm max-w-xs">
          <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
            </svg>
          </div>
          <input v-model="searchQuery" type="text" class="block w-full rounded-md border-gray-300 pl-10 focus:border-brand-500 focus:ring-brand-500 sm:text-sm border py-2" placeholder="Rechercher...">
        </div>
        
        <button @click="closeModal(); isModalOpen = true" type="button" class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:w-auto">
          Nouveau Fournisseur
        </button>
      </div>
    </div>

    <div v-if="error" class="bg-red-50 p-4 mb-4"><p class="text-sm text-red-700">{{ error }}</p></div>
    <div v-if="isLoading" class="text-center py-8">Chargement...</div>

    <div v-else class="mt-8 flex flex-col">
      <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-auto max-h-[75vh] shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50/80 backdrop-blur sticky top-0 z-20 border-b border-gray-200">
                <tr>
                   <th scope="col" class="py-4 pl-4 pr-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Fabricant / Marque</th>
                   <th scope="col" class="px-3 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Contact</th>
                   <th scope="col" class="px-3 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Adresse Fiscale</th>
                   <th scope="col" class="px-3 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Matricules B2B</th>
                   <th scope="col" class="px-3 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Attestation Fiscale</th>
                   <th scope="col" class="relative py-4 pl-3 pr-4 sm:pr-6 sticky right-0 bg-gray-50/80 backdrop-blur z-30 w-24 border-l border-gray-100"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="supplier in filteredSuppliers" :key="supplier.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">{{ supplier.name }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    <div>{{ supplier.phone || '-' }}</div>
                    <div class="text-xs text-gray-400">{{ supplier.email || '-' }}</div>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ supplier.address || '-' }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                    <div class="text-xs"><span class="font-medium text-gray-700">ICE :</span> {{ supplier.ice || '-' }}</div>
                    <div class="text-xs"><span class="font-medium text-gray-700">RC :</span> {{ supplier.rc || '-' }}</div>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm">
                    <div v-if="supplier.tax_certificate_path" class="flex flex-col gap-1.5">
                      <span :class="[
                        'px-2.5 py-0.5 rounded-full text-[10px] font-bold w-fit uppercase border shadow-sm',
                        new Date(supplier.tax_certificate_expires_at!) < new Date() ? 'bg-red-50 text-red-700 border-red-100' : 'bg-green-50 text-green-700 border-green-100'
                      ]">
                        {{ new Date(supplier.tax_certificate_expires_at!) < new Date() ? 'Expirée' : 'Valide' }}
                      </span>
                      <span class="text-[10px] text-gray-400 font-medium">Expire le : {{ new Date(supplier.tax_certificate_expires_at!).toLocaleDateString() }}</span>
                    </div>
                    <div v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100 uppercase italic">Manquante</div>
                    <div class="flex gap-4 mt-2">
                        <label class="text-[11px] font-bold text-brand-600 hover:text-brand-800 cursor-pointer uppercase tracking-tight flex items-center gap-1 transition-colors">
                          <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                          {{ supplier.tax_certificate_path ? 'Actualiser' : 'Uploader' }}
                          <input type="file" class="hidden" accept=".pdf" @change="(e) => uploadCertificate(e, supplier.id)" />
                        </label>
                        <button v-if="supplier.tax_certificate_path" @click="downloadFile(supplier.tax_certificate_path, `Cert-Fis-${supplier.name}`)" class="text-[11px] font-bold text-blue-600 hover:text-blue-800 uppercase tracking-tight flex items-center gap-1 transition-colors">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            Télécharger
                        </button>
                    </div>
                  </td>
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 gap-3 flex justify-end sticky right-0 bg-white/90 backdrop-blur z-10 w-24 border-l border-gray-100">
                    <button @click="editSupplier(supplier)" class="text-brand-600 hover:text-brand-900 font-bold text-xs p-2 hover:bg-brand-50 rounded-lg transition-all flex items-center gap-1">
                      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                      Modifier
                    </button>
                    <button @click="deleteSupplier(supplier.id)" class="text-red-500 hover:text-red-700 font-bold text-xs p-2 hover:bg-red-50 rounded-lg transition-all flex items-center gap-1">
                      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                      Supprimer
                    </button>
                  </td>
                </tr>
                <tr v-if="suppliers.length === 0">
                    <td colspan="5" class="py-8 text-center text-gray-500">Aucun fournisseur trouvé.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <!-- Modal d'ajout Premium -->
    <div v-if="isModalOpen" class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <!-- Backdrop with blur -->
      <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" @click="closeModal"></div>
      
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-xl">
          
          <!-- Header with Gradient -->
          <div class="bg-gradient-to-r from-brand-600 to-brand-800 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-white/10 rounded-lg">
                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
              </div>
              <h3 class="text-xl font-bold text-white" id="modal-title">
                {{ editingId ? 'Modifier le Fournisseur' : 'Nouveau Fournisseur' }}
              </h3>
            </div>
            <button @click="closeModal" class="text-white/80 hover:text-white transition-colors">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <form @submit.prevent="saveSupplier" class="p-6 space-y-6">
            <!-- Section: Primary Info -->
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nom / Raison Sociale *</label>
                <div class="relative">
                  <input v-model="form.name" type="text" required placeholder="Ex: NS MEDICAL" 
                    class="block w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-gray-900 focus:border-brand-500 focus:ring-brand-500 transition-all border duration-200">
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                 <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email Contact</label>
                    <input v-model="form.email" type="email" placeholder="contact@example.com"
                      class="block w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-gray-900 focus:border-brand-500 focus:ring-brand-500 transition-all border duration-200">
                 </div>
                 <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Téléphone</label>
                    <input v-model="form.phone" type="text" placeholder="05XXXXXXXX"
                      class="block w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-gray-900 focus:border-brand-500 focus:ring-brand-500 transition-all border duration-200">
                 </div>
              </div>

              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Adresse / Pays</label>
                <input v-model="form.address" type="text" placeholder="Ex: Casablanca, Maroc"
                  class="block w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-gray-900 focus:border-brand-500 focus:ring-brand-500 transition-all border duration-200">
              </div>
            </div>

            <!-- Section: Fiscal Info with Label Background -->
            <div class="bg-brand-50/50 rounded-2xl p-5 border border-brand-100">
              <div class="flex items-center gap-2 mb-4">
                <svg class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <h4 class="text-sm font-bold text-brand-900 uppercase tracking-wider">Identifiants Fiscaux</h4>
              </div>
              
              <div class="grid grid-cols-2 gap-x-4 gap-y-4">
                  <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">ICE (Identifiant Commun)</label>
                     <input v-model="form.ice" type="text" class="block w-full rounded-lg border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 border">
                  </div>
                  <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Registre du Commerce (RC)</label>
                     <input v-model="form.rc" type="text" class="block w-full rounded-lg border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 border">
                  </div>
                  <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Identifiant Fiscal (IF)</label>
                     <input v-model="form.if" type="text" class="block w-full rounded-lg border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 border">
                  </div>
                  <div>
                     <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Patente</label>
                     <input v-model="form.patente" type="text" class="block w-full rounded-lg border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 border">
                  </div>
              </div>
            </div>

            <!-- Footer Buttons -->
            <div class="flex gap-3 pt-2">
              <button @click="closeModal" type="button" 
                class="flex-1 rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all">
                Annuler
              </button>
              <button type="submit" :disabled="isSubmitting" 
                class="flex-1 rounded-xl border border-transparent bg-brand-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all">
                {{ isSubmitting ? 'Traitement...' : 'Enregistrer' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>iv>
    </div>
  </div>
</template>
