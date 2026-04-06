<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const errorMsg = ref('')
const isLoading = ref(false)
const showPassword = ref(false)

const handleLogin = async () => {
    errorMsg.value = ''
    isLoading.value = true
    try {
        const success = await authStore.login({
            email: email.value,
            password: password.value
        })
        
        if (success) {
            router.push({ name: 'dashboard' })
        } else {
            errorMsg.value = 'Echec de la connexion. Verifiez vos identifiants.'
        }
    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || 'Identifiants incorrects ou serveur indisponible.'
    } finally {
        isLoading.value = false
    }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
      <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
        Kilatech B2B
      </h2>
      <p class="mt-2 text-center text-sm text-gray-600">
        Connectez-vous a votre espace securise
      </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
        <form class="space-y-6" @submit.prevent="handleLogin">
          
          <div v-if="errorMsg" class="bg-red-50 text-red-700 p-3 rounded text-sm mb-4">
            {{ errorMsg }}
          </div>

          <div>
            <label for="email" class="block text-sm font-medium text-gray-700">
              Adresse e-mail
            </label>
            <div class="mt-1">
              <input id="email" v-model="email" name="email" type="email" required
                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-brand-500 focus:border-brand-500 sm:text-sm" />
            </div>
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">
              Mot de passe
            </label>
            <div class="mt-1 relative">
              <input id="password" v-model="password" name="password" :type="showPassword ? 'text' : 'password'" required
                class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-brand-500 focus:border-brand-500 sm:text-sm pr-10" />
              <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                <svg v-if="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                </svg>
              </button>
            </div>
          </div>

          <div>
            <button type="submit" :disabled="isLoading"
              class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 disabled:opacity-50">
              {{ isLoading ? 'Connexion en cours...' : 'Se connecter' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
