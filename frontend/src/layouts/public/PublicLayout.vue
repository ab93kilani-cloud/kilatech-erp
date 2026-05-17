
<script setup lang="ts">
import { ref } from 'vue'

const isMenuOpen = ref(false)
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col pt-16 md:pt-28">
    <!-- Navbar Premium Publique -->
    <header class="fixed w-full top-0 z-[60] bg-white border-b border-gray-100 shadow-sm transition-all duration-300">
      <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16 md:h-28">
        <!-- Logo -->
        <div class="flex-shrink-0 flex items-center">
          <router-link :to="{ name: 'storefront' }" class="flex items-center">
            <img class="h-10 md:h-20 w-auto object-contain hover:scale-105 transition-transform duration-500" src="/logo.png" alt="Kilatech" />
          </router-link>
        </div>

        <!-- Desktop Navigation -->
        <div class="hidden md:flex items-center space-x-8">
          <router-link :to="{ name: 'storefront' }" class="text-sm font-bold text-gray-700 hover:text-brand-600 transition-colors uppercase tracking-widest" active-class="text-brand-600 border-b-2 border-brand-600 pb-1">Accueil</router-link>
            <router-link :to="{ name: 'blog' }" class="text-sm font-bold text-gray-700 hover:text-brand-600 transition-colors uppercase tracking-widest" active-class="text-brand-600 border-b-2 border-brand-600 pb-1">Blog</router-link>
            <router-link :to="{ name: 'news' }" class="text-sm font-bold text-gray-700 hover:text-brand-600 transition-colors uppercase tracking-widest" active-class="text-brand-600 border-b-2 border-brand-600 pb-1">Actualités</router-link>
            
            <router-link :to="{ name: 'about' }" class="text-sm font-bold text-gray-700 hover:text-brand-600 transition-colors uppercase tracking-widest" active-class="text-brand-600 border-b-2 border-brand-600 pb-1">Qui sommes-nous</router-link>
            <router-link :to="{ name: 'become-partner' }" class="text-sm font-bold text-gray-700 hover:text-brand-600 transition-colors uppercase tracking-widest text-nowrap" active-class="text-brand-600 border-b-2 border-brand-600 pb-1">Devenir Partenaire</router-link>
            <router-link :to="{ name: 'contact' }" class="text-sm font-bold text-gray-700 hover:text-brand-600 transition-colors uppercase tracking-widest" active-class="text-brand-600 border-b-2 border-brand-600 pb-1">Contact</router-link>
        </div>

        <!-- Icons / Mobile Menu Button -->
        <div class="flex items-center space-x-4">
           <!-- Mobile Hamburger -->
           <button @click="isMenuOpen = !isMenuOpen" class="md:hidden text-gray-600 hover:text-brand-600 focus:outline-none">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path v-if="!isMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
               <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
             </svg>
           </button>
           
           <!-- Shop Icon (Hidden on mobile for now as we have a floating one) -->
           <button class="hidden sm:block text-gray-600 hover:text-brand-600 transition-colors relative">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
             </svg>
           </button>
        </div>
      </nav>

      <!-- Mobile Navigation Drawer -->
      <transition 
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="transform -translate-y-full"
        enter-to-class="transform translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="transform translate-y-0"
        leave-to-class="transform -translate-y-full"
      >
        <div v-if="isMenuOpen" class="md:hidden bg-white border-b border-gray-100 shadow-xl overflow-hidden absolute w-full top-16 left-0 z-40">
          <div class="px-4 pt-4 pb-8 space-y-4">
            <router-link @click="isMenuOpen = false" :to="{ name: 'storefront' }" class="block text-lg font-black text-gray-900 uppercase tracking-widest border-l-4 border-transparent pl-4 py-2" active-class="text-brand-600 border-brand-600 bg-brand-50">Accueil</router-link>
            <router-link @click="isMenuOpen = false" :to="{ name: 'blog' }" class="block text-lg font-black text-gray-900 uppercase tracking-widest border-l-4 border-transparent pl-4 py-2" active-class="text-brand-600 border-brand-600 bg-brand-50">Blog</router-link>
            <router-link @click="isMenuOpen = false" :to="{ name: 'news' }" class="block text-lg font-black text-gray-900 uppercase tracking-widest border-l-4 border-transparent pl-4 py-2" active-class="text-brand-600 border-brand-600 bg-brand-50">Actualités</router-link>
            <router-link @click="isMenuOpen = false" :to="{ name: 'about' }" class="block text-lg font-black text-gray-900 uppercase tracking-widest border-l-4 border-transparent pl-4 py-2" active-class="text-brand-600 border-brand-600 bg-brand-50">Qui sommes-nous</router-link>
            <router-link @click="isMenuOpen = false" :to="{ name: 'become-partner' }" class="block text-lg font-black text-gray-900 uppercase tracking-widest border-l-4 border-transparent pl-4 py-2" active-class="text-brand-600 border-brand-600 bg-brand-50">Devenir Partenaire</router-link>
            <router-link @click="isMenuOpen = false" :to="{ name: 'contact' }" class="block text-lg font-black text-gray-900 uppercase tracking-widest border-l-4 border-transparent pl-4 py-2" active-class="text-brand-600 border-brand-600 bg-brand-50">Contact</router-link>
          </div>
        </div>
      </transition>
    </header>

    <!-- Page Content (Storefront) -->
    <main class="flex-grow">
       <router-view />
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
      <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 md:flex md:items-center md:justify-between lg:px-8">
        <div class="flex justify-center space-x-6 md:order-2">
           <span class="text-gray-400 text-sm">Fournisseur d'équipements médicaux certifiés</span>
        </div>
        <div class="mt-8 md:mt-0 md:order-1">
          <p class="text-center text-base text-gray-400">
            &copy; 2026 Kilatech Commerciale V3. Tous droits réservés.
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>
