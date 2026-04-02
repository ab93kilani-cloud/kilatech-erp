<script setup lang="ts">
import { useAuthStore } from '../stores/auth'
import { computed, ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const authStore = useAuthStore()
const user = computed(() => authStore.user)

const blogPendingCount = ref(0)

const fetchStats = async () => {
    try {
        const res = await api.get('/articles')
        if (Array.isArray(res.data)) {
            blogPendingCount.value = res.data.filter((a: any) => a.status === 'draft').length
        }
    } catch (e) {
        console.error('Failed to fetch blog stats')
    }
}

onMounted(fetchStats)
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900">Bonjour, {{ user?.name || 'Admin' }}</h1>
      <p class="text-gray-600 mt-1">Voici le resume de l'activite B2B Kilatech de la journee.</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
      
      <!-- Card 1 -->
      <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0 bg-brand-100 rounded-md p-3">
              <svg class="h-6 w-6 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">Ventes du jour</dt>
                <dd class="text-lg font-semibold text-gray-900">4 500.00 MAD</dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 2 -->
      <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
              <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">Alertes Stock/DLC</dt>
                <dd class="text-lg font-semibold text-gray-900">3</dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 3 -->
      <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
              <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
              </svg>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">Devis en attente</dt>
                <dd class="text-lg font-semibold text-gray-900">12</dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

      <!-- Card 4 -->
      <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
              <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
              </svg>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">Receptions QA</dt>
                <dd class="text-lg font-semibold text-gray-900">5 Lots</dd>
              </dl>
            </div>
          </div>
        </div>
      </div>

       <!-- NEW: Card 5 - Marketing -->
      <div class="bg-white overflow-hidden shadow rounded-lg border-2 border-indigo-50">
        <div class="p-5">
          <div class="flex items-center">
            <div class="flex-shrink-0 bg-indigo-100 rounded-md p-3">
              <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </div>
            <div class="ml-5 w-0 flex-1">
              <dl>
                <dt class="text-sm font-medium text-gray-500 truncate">Marketing a valider</dt>
                <dd class="text-lg font-semibold text-indigo-700 font-black">{{ blogPendingCount }} Articles</dd>
              </dl>
            </div>
          </div>
          <div class="mt-4">
            <router-link :to="{ name: 'marketing' }" class="text-xs font-bold text-indigo-600 hover:text-indigo-900 uppercase tracking-widest">Voir le Marketing →</router-link>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
