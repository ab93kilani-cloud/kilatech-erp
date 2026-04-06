<script setup lang="ts">
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'
import { computed, ref, onMounted } from 'vue'
import api from '@/plugins/axios'
import logoUrl from '@/assets/logo.png'

const authStore = useAuthStore()
const router = useRouter()

const user = computed(() => authStore.user)
const notifications = ref<any[]>([])
const showNotifications = ref(false)

const fetchNotifications = async () => {
    try {
        const res = await api.get('/notifications')
        notifications.value = res.data
    } catch (e) {
        console.error('Failed to fetch notifications')
    }
}

const markAsRead = async (id: number, url: string) => {
    try {
        await api.post(`/notifications/${id}/read`)
        notifications.value = notifications.value.filter(n => n.id !== id)
        if (url) router.push(url) 
    } catch (e) {
        console.error('Failed to mark notification as read')
    }
}

onMounted(() => {
    fetchNotifications()
    // Refresh every 2 minutes
    setInterval(fetchNotifications, 120000)
})

const logout = async () => {
    await authStore.logout()
    router.push({ name: 'login' })
}

const navigation = computed(() => {
  const roleName = user.value?.role?.name;
  const menu: any[] = [];

  // Define permissions mapping for convenience
  const hasAccess = (name: string) => {
    if (roleName === 'Admin' || roleName === 'Gérant') return true;
    
    switch (roleName) {
      case 'Commercial':
        return ['Clients (B2B)', 'Catalogue', 'Devis (Ventes)', 'Bons de Livraison (Ventes)', 'Factures', 'Avoirs', 'Règlements'].includes(name);
      case 'Responsable d\'Achat':
        return ['Catalogue', 'Fournisseurs', 'Achats / Bons Commande'].includes(name);
      case 'Comptable':
        return ['Comptabilité'].includes(name);
      case 'Responsable Marketing':
        return ['Catalogue', 'Espace Marketing (SEO)'].includes(name);
      case 'Logisticien':
        return ['Stocks & Lots'].includes(name);
      default:
        return false;
    }
  };

  // Dashboard is visible for everyone except maybe specialized roles if you want a clean break
  // For now, let's keep Dashboard for everyone except Accountant who is "uniquement" Accounting Space
  if (roleName !== 'Comptable') {
    menu.push({ name: 'Tableau de bord', to: { name: 'dashboard' }, icon: 'HomeIcon' });
  }

  // Admin section
  if (roleName === 'Admin' || roleName === 'Gérant') {
    menu.push({ name: 'Équipe / Utilisateurs', to: { name: 'users' }, icon: 'UsersIcon' });
  }

  // All other modules
  const modules: any[] = [
    { name: 'Clients (B2B)', to: { name: 'clients' } },
    { name: 'Catalogue', to: { name: 'catalog' } },
    { name: 'Fournisseurs', to: { name: 'suppliers' } },
    { name: 'Achats / Bons Commande', to: { name: 'purchases' } },
    { name: 'Stocks & Lots', to: { name: 'stock' } },
    { name: 'Devis (Ventes)', to: { name: 'quotes' } },
    { name: 'Bons de Livraison (Ventes)', to: { name: 'orders' } },
    { name: 'Factures', to: { name: 'invoices' } },
    { name: 'Avoirs', to: { name: 'credit-notes' } },
    { name: 'Règlements', to: { name: 'payments' } }
  ];

  if (roleName === 'Admin' || roleName === 'Gérant') {
    modules.push({ name: 'Espace Manager', to: { name: 'manager.dashboard' } });
  }

  modules.push({ name: 'Espace Marketing (SEO)', to: { name: 'marketing' } });
  modules.push({ name: 'Comptabilité', to: { name: 'accounting.dashboard' } });

  modules.forEach(m => {
    if (hasAccess(m.name)) menu.push(m);
  });

  return menu;
});
</script>

<template>
  <div class="min-h-screen bg-gray-100 flex">
    
    <!-- Sidebar / Menu de navigation -->
    <div class="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 bg-white border-r border-gray-200">
      <div class="flex-1 flex flex-col min-h-0">
        <router-link :to="{ name: 'storefront' }" class="flex items-center h-48 flex-shrink-0 bg-white border-b border-gray-100 overflow-hidden px-4 group">
          <img :src="logoUrl" alt="Kilatech Logo" class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500" />
        </router-link>
        <div class="flex-1 flex flex-col overflow-y-auto">
          <nav class="flex-1 px-2 py-4 space-y-1">
            <router-link v-for="item in navigation" :key="item.name" :to="item.to" 
               active-class="bg-brand-50 text-brand-700"
               class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 group flex items-center px-2 py-2 text-sm font-medium rounded-md">
              {{ item.name }}
            </router-link>
          </nav>
        </div>
      </div>
    </div>

    <!-- Main content area -->
    <div class="md:pl-64 flex flex-col flex-1">
      <!-- Top Navbar -->
      <div class="sticky top-0 z-10 flex-shrink-0 flex h-16 bg-white shadow">
        <div class="flex-1 px-4 flex justify-between">
          <div class="flex-1 flex items-center">
            <h1 class="text-2xl font-semibold text-gray-900">Espace Sécurisé</h1>
          </div>
          <div class="ml-4 flex items-center md:ml-6 gap-4">
            
            <!-- Notifications Dropdown -->
            <div class="relative">
              <button @click="showNotifications = !showNotifications" class="bg-white p-1 rounded-full text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 relative">
                <span class="sr-only">Voir les notifications</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span v-if="notifications.length > 0" class="absolute top-0 right-0 block h-2.5 w-2.5 rounded-full bg-red-500 border-2 border-white"></span>
              </button>

              <!-- Dropdown Panel -->
              <div v-if="showNotifications" class="origin-top-right absolute right-0 mt-2 w-80 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                <div class="py-1">
                  <div class="px-4 py-2 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-gray-900">Notifications</h3>
                    <span class="text-[10px] bg-brand-100 text-brand-700 px-2 py-0.5 rounded-full">{{ notifications.length }} nouvelles</span>
                  </div>
                  <div class="max-h-60 overflow-y-auto">
                    <div v-if="notifications.length === 0" class="px-4 py-8 text-center text-gray-400 text-sm">
                      Aucune notification
                    </div>
                    <button 
                      v-for="n in notifications" :key="n.id" 
                      @click="markAsRead(n.id, n.action_url); showNotifications = false"
                      class="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0"
                    >
                      <p class="text-xs font-bold text-gray-900">{{ n.title }}</p>
                      <p class="text-[11px] text-gray-500 mt-1 line-clamp-2">{{ n.message }}</p>
                      <p class="text-[9px] text-gray-400 mt-2 italic">{{ new Date(n.created_at).toLocaleTimeString() }}</p>
                    </button>
                  </div>
                  <div class="px-4 py-2 bg-gray-50 text-center">
                    <router-link :to="{ name: 'dashboard' }" @click="showNotifications = false" class="text-[10px] font-bold text-brand-600 hover:text-brand-700 uppercase tracking-widest">Voir tout</router-link>
                  </div>
                </div>
              </div>
            </div>

            <!-- User Profile Dropdown Simulator -->
            <div class="ml-3 relative flex items-center gap-4">
              <div class="flex flex-col text-right hidden sm:block">
                 <span class="text-sm font-medium text-gray-700">{{ user?.name || 'Administrateur' }}</span>
                 <span class="text-xs text-gray-500">{{ user?.role?.name || 'Admin' }}</span>
              </div>
              <button @click="logout" class="bg-gray-100 p-2 rounded-md text-sm text-gray-600 hover:bg-gray-200 focus:outline-none">
                Déconnexion
              </button>
            </div>
            
          </div>
        </div>
      </div>

      <!-- Main Page Content -->
      <main class="flex-1">
        <div class="py-6">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
            <router-view /> <!-- Sub-routes will be rendered here -->
          </div>
        </div>
      </main>

    </div>
  </div>
</template>
