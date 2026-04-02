<script setup lang="ts">
import { ref, onMounted } from 'vue'
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

const handleFileUpload = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);

    try {
        isLoading.value = true;
        await api.post('/suppliers/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        alert('Import Excel terminé avec succès !');
        fetchSuppliers();
    } catch (e: any) {
        alert('Erreur: Le fichier Excel est invalide ou mal formaté.');
    } finally {
        isLoading.value = false;
        // Reset l'input file
        target.value = '';
    }
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
        <p class="mt-2 text-sm text-gray-700">Gérez vos fabricants locaux ou internationaux. Vous pouvez importer des listes Excel.</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex items-center gap-3">
        <!-- Bouton Upload caché, déclenché via label -->
        <label class="cursor-pointer inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
          <span>Importer Excel</span>
          <input type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="handleFileUpload" />
        </label>
        
        <button @click="closeModal(); isModalOpen = true" type="button" class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:w-auto">
          Nouveau Fournisseur
        </button>
      </div>
    </div>

    <div v-if="error" class="bg-red-50 p-4 mb-4"><p class="text-sm text-red-700">{{ error }}</p></div>
    <div v-if="isLoading" class="text-center py-8">Chargement...</div>

    <div v-else class="mt-8 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                   <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">Fabricant / Marque</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Contact</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Adresse Fiscale</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Matricules B2B</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Attestation Fiscale</th>
                   <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="supplier in suppliers" :key="supplier.id">
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
                    <div v-if="supplier.tax_certificate_path" class="flex flex-col gap-1">
                      <span :class="[
                        'px-2 py-0.5 rounded-full text-[10px] font-bold w-fit uppercase',
                        new Date(supplier.tax_certificate_expires_at!) < new Date() ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'
                      ]">
                        {{ new Date(supplier.tax_certificate_expires_at!) < new Date() ? 'Expirée' : 'Valide' }}
                      </span>
                      <span class="text-[9px] text-gray-400">Exp: {{ new Date(supplier.tax_certificate_expires_at!).toLocaleDateString() }}</span>
                    </div>
                    <div v-else class="text-xs text-red-500 font-bold italic">Manquante</div>
                    <div class="flex gap-3">
                        <label class="mt-2 text-[10px] font-black text-brand-600 hover:text-brand-900 cursor-pointer uppercase underline">
                        {{ supplier.tax_certificate_path ? 'Actualiser' : 'Uploader' }}
                        <input type="file" class="hidden" accept=".pdf" @change="(e) => uploadCertificate(e, supplier.id)" />
                        </label>
                        <button v-if="supplier.tax_certificate_path" @click="downloadFile(supplier.tax_certificate_path, `Cert-Fis-${supplier.name}`)" class="mt-2 text-[10px] font-black text-blue-600 hover:text-blue-900 uppercase underline">
                            Télécharger
                        </button>
                    </div>
                  </td>
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 gap-2 flex justify-end">
                    <button @click="editSupplier(supplier)" class="text-brand-600 hover:text-brand-900 px-2 py-1 bg-brand-50 rounded">Modifier</button>
                    <button @click="deleteSupplier(supplier.id)" class="text-red-600 hover:text-red-900 px-2 py-1 bg-red-50 rounded">Supprimer</button>
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
    </div>

    <!-- Modal d'ajout -->
    <div v-if="isModalOpen" class="relative z-10" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
      <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4" id="modal-title">
              {{ editingId ? 'Modifier Fournisseur' : 'Ajouter un Fournisseur' }}
            </h3>
            <form @submit.prevent="saveSupplier" class="space-y-4">
               <div>
                  <label class="block text-sm font-medium text-gray-700">Nom / Raison Sociale *</label>
                  <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
               </div>
               <div class="grid grid-cols-2 gap-4">
                   <div>
                      <label class="block text-sm font-medium text-gray-700">Email Contact</label>
                      <input v-model="form.email" type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                   </div>
                   <div>
                      <label class="block text-sm font-medium text-gray-700">Téléphone</label>
                      <input v-model="form.phone" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                   </div>
               </div>
               <div>
                  <label class="block text-sm font-medium text-gray-700">Adresse / Pays</label>
                  <input v-model="form.address" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
               </div>

               <div class="border-t border-gray-200 block pt-4 mt-2">
                 <h4 class="text-sm font-medium text-gray-900 mb-3">Informations Légales & Fiscales</h4>
                 <div class="grid grid-cols-2 gap-4">
                     <div>
                        <label class="block text-xs font-medium text-gray-700">ICE</label>
                        <input v-model="form.ice" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                     </div>
                     <div>
                        <label class="block text-xs font-medium text-gray-700">Registre du Commerce (RC)</label>
                        <input v-model="form.rc" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                     </div>
                     <div>
                        <label class="block text-xs font-medium text-gray-700">Id. Fiscal (IF)</label>
                        <input v-model="form.if" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                     </div>
                     <div>
                        <label class="block text-xs font-medium text-gray-700">Patente</label>
                        <input v-model="form.patente" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                     </div>
                 </div>
               </div>
               <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                 <button type="submit" :disabled="isSubmitting" class="inline-flex w-full justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-brand-700 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:col-start-2 sm:text-sm">
                   {{ isSubmitting ? 'Enregistrement...' : 'Enregistrer' }}
                 </button>
                 <button @click="closeModal" type="button" class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:col-start-1 sm:mt-0 sm:text-sm">
                   Annuler
                 </button>
               </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
