<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

interface Role {
  id: number;
  name: string;
}

interface User {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
  role_id: number;
  role?: Role;
  last_login: string | null;
}

const users = ref<User[]>([])
const roles = ref<Role[]>([])
const isLoading = ref(true)
const error = ref('')

const isModalOpen = ref(false)
const isSubmitting = ref(false)
const isEditing = ref(false)
const editingId = ref<number | null>(null)

const form = ref({
    name: '',
    email: '',
    password: '',
    role_id: '',
    is_active: true
})

const isAdmin = () => {
   return authStore.user?.role?.name === 'Admin';
}

const openCreateModal = () => {
    isEditing.value = false
    editingId.value = null
    form.value = { name: '', email: '', password: '', role_id: '', is_active: true }
    isModalOpen.value = true
}

const openEditModal = (user: User) => {
    isEditing.value = true
    editingId.value = user.id
    form.value = { 
        name: user.name,
        email: user.email,
        password: '', // Leave empty unless changing
        role_id: user.role_id.toString(),
        is_active: user.is_active
    }
    isModalOpen.value = true
}

const fetchData = async () => {
    try {
        const [usersRes, rolesRes] = await Promise.all([
            api.get('/users'),
            api.get('/roles')
        ])
        users.value = usersRes.data
        roles.value = rolesRes.data
    } catch (e: any) {
        error.value = 'Erreur lors du chargement des données.'
    } finally {
        isLoading.value = false
    }
}

const saveUser = async () => {
    isSubmitting.value = true
    try {
        if (isEditing.value && editingId.value) {
            const response = await api.put(`/users/${editingId.value}`, form.value)
            const index = users.value.findIndex(u => u.id === editingId.value)
            if (index !== -1) users.value[index] = response.data
        } else {
            const response = await api.post('/users', form.value)
            users.value.unshift(response.data)
        }
        isModalOpen.value = false
    } catch (e: any) {
        alert(e.response?.data?.message || "Erreur lors de l'enregistrement.")
    } finally {
        isSubmitting.value = false
    }
}

const deleteUser = async (id: number) => {
    if (!confirm('Voulez-vous vraiment supprimer ce compte ? Cette action est irréversible.')) return;
    try {
        await api.delete(`/users/${id}`)
        users.value = users.value.filter(u => u.id !== id)
    } catch (e: any) {
        alert(e.response?.data?.message || "Impossible de supprimer ce compte.")
    }
}

const formatDate = (dateString: string | null) => {
  if (!dateString) return 'Jamais'
  return new Date(dateString).toLocaleString('fr-FR')
}

onMounted(() => {
    fetchData()
})
</script>

<template>
  <div>
    <div class="sm:flex sm:items-center mb-8">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Équipe & Utilisateurs</h1>
        <p class="mt-2 text-sm text-gray-700">Gérez les accès personnels de votre équipe et assignez les rôles métiers.</p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <button v-if="isAdmin()" @click="openCreateModal" type="button" class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:focus:ring-brand-500 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 sm:w-auto">
          Nouvel Utilisateur
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 p-4 mb-4"><p class="text-sm text-red-700">{{ error }}</p></div>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-8">Chargement...</div>

    <!-- Data Table -->
    <div v-else class="mt-8 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                   <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">Nom / Email</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Rôle</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Dernier Accès</th>
                   <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Statut</th>
                   <th v-if="isAdmin()" scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="user in users" :key="user.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">
                     <p>{{ user.name }}</p>
                     <p class="text-xs text-gray-500 font-normal">{{ user.email }}</p>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ user.role?.name }}
                      </span>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ formatDate(user.last_login) }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <span v-if="user.is_active" class="text-green-600 font-medium">Actif</span>
                      <span v-else class="text-red-500 font-medium">Bloqué</span>
                  </td>
                  <td v-if="isAdmin()" class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 space-x-3">
                     <button @click="openEditModal(user)" class="text-brand-600 hover:text-brand-900">Modifier</button>
                     <button v-if="user.id !== authStore.user?.id" @click="deleteUser(user.id)" class="text-red-500 hover:text-red-900 ml-4">Supprimer</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <div v-if="isModalOpen" class="relative z-10" aria-labelledby="modal-title" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
      <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4" id="modal-title">{{ isEditing ? 'Modifier Utilisateur' : 'Nouvel Utilisateur' }}</h3>
            <form @submit.prevent="saveUser" class="space-y-4">
               <div>
                  <label class="block text-sm font-medium text-gray-700">Nom de l'employé *</label>
                  <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
               </div>
               <div>
                  <label class="block text-sm font-medium text-gray-700">Email professionnel *</label>
                  <input v-model="form.email" type="email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
               </div>
               <div>
                  <label class="block text-sm font-medium text-gray-700">Mot de passe {{ isEditing ? '(Laissez vide pour garder l\'actuel)' : '*' }}</label>
                  <input v-model="form.password" type="password" :required="!isEditing" minlength="6" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border">
               </div>
               <div>
                  <label class="block text-sm font-medium text-gray-700">Rôle Méteir *</label>
                  <select v-model="form.role_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm px-3 py-2 border bg-white">
                      <option disabled value="">Sélectionnez un rôle</option>
                      <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                  </select>
               </div>
               <div>
                  <label class="flex items-center mt-4">
                     <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                     <span class="ml-2 text-sm text-gray-700">Compte Actif (Décocher pour bloquer l'accès)</span>
                  </label>
               </div>
               <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                 <button type="submit" :disabled="isSubmitting" class="inline-flex w-full justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-brand-700 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:col-start-2 sm:text-sm">
                   {{ isSubmitting ? 'Enregistrement...' : 'Enregistrer' }}
                 </button>
                 <button @click="isModalOpen = false" type="button" class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:col-start-1 sm:mt-0 sm:text-sm">
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
