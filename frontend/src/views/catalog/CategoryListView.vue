<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

interface Category {
  id: number;
  name: string;
  slug: string;
}

const categories = ref<Category[]>([])
const isLoading = ref(true)
const error = ref('')
const isModalOpen = ref(false)
const editingCategory = ref<Category | null>(null)
const categoryForm = ref({ name: '' })
const isSubmitting = ref(false)

const fetchCategories = async () => {
    try {
        const response = await api.get('/categories')
        categories.value = response.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des catégories.'
    } finally {
        isLoading.value = false
    }
}

const openModal = (category: Category | null = null) => {
    editingCategory.value = category
    categoryForm.value.name = category ? category.name : ''
    isModalOpen.value = true
}

const saveCategory = async () => {
    if (!categoryForm.value.name) return
    isSubmitting.value = true
    try {
        if (editingCategory.value) {
            const res = await api.put(`/categories/${editingCategory.value.id}`, categoryForm.value)
            const index = categories.value.findIndex(c => c.id === editingCategory.value?.id)
            if (index !== -1) categories.value[index] = res.data
        } else {
            const res = await api.post('/categories', categoryForm.value)
            categories.value.push(res.data)
        }
        isModalOpen.value = false
        editingCategory.value = null
        categoryForm.value.name = ''
    } catch (e: any) {
        alert(e.response?.data?.message || 'Erreur lors de la sauvegarde.')
    } finally {
        isSubmitting.value = false
    }
}

const deleteCategory = async (id: number) => {
    console.log('Tentative de suppression de la catégorie ID:', id);
    if (!window.confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ? Cette action est irréversible.')) return
    
    try {
        const response = await api.delete(`/categories/${id}`)
        console.log('Suppression réussie:', response.data);
        categories.value = categories.value.filter(c => c.id !== id)
    } catch (e: any) {
        console.error('Erreur lors de la suppression:', e.response?.data || e);
        const errorMsg = e.response?.data?.message || 'Erreur lors de la suppression. Vérifiez si des produits sont liés à cette catégorie.';
        alert(errorMsg)
    }
}

onMounted(fetchCategories)
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Familles / Catégories</h1>
        <p class="mt-2 text-sm text-gray-700">Gérez les catégories de produits du catalogue. Modifier une catégorie mettra à jour tous les produits liés.</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <button @click="openModal()" class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:w-auto">
          Nouvelle Catégorie
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="flex justify-center p-8">
      <svg class="animate-spin h-8 w-8 text-brand-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <div v-else class="mt-8 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Nom</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Slug</th>
                  <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                    <span class="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="cat in categories" :key="cat.id" class="hover:bg-gray-50">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">{{ cat.name }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ cat.slug }}</td>
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                    <div class="flex items-center justify-end gap-3">
                        <button @click="openModal(cat)" class="text-brand-600 hover:text-brand-900 font-bold">Modifier</button>
                        <button @click="deleteCategory(cat.id)" class="text-red-400 hover:text-red-600">Supprimer</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="categories.length === 0">
                    <td colspan="3" class="py-12 text-center text-gray-500">Aucune catégorie trouvée.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="isModalOpen = false"></div>
            <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-sm sm:p-6">
                <div>
                    <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">{{ editingCategory ? 'Modifier la Catégorie' : 'Nouvelle Catégorie' }}</h3>
                    <input v-model="categoryForm.name" type="text" placeholder="Nom de la catégorie..." class="block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
                </div>
                <div class="mt-5 sm:mt-6 flex gap-3">
                    <button @click="saveCategory" :disabled="isSubmitting" class="inline-flex w-full justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-brand-700 sm:text-sm">
                        {{ isSubmitting ? '...' : (editingCategory ? 'Enregistrer' : 'Ajouter') }}
                    </button>
                    <button @click="isModalOpen = false" class="inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:text-sm">
                        Annuler
                    </button>
                </div>
            </div>
        </div>
    </div>
  </div>
</template>
