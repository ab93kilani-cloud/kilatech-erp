
<template>
  <div class="min-h-screen bg-gray-50 flex flex-col pt-28">
    <!-- Navbar Premium Publique -->
    <header class="fixed w-full top-0 z-50 bg-white border-b border-gray-100 shadow-sm transition-all duration-300">
      <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex items-center justify-between h-28">
        <!-- Logo -->
        <div class="flex-shrink-0 flex items-center">
          <router-link :to="{ name: 'storefront' }" class="flex items-center">
            <img class="h-20 w-auto object-contain hover:scale-105 transition-transform duration-500" src="/logo.png" alt="Kilatech" />
          </router-link>
        </div>

        <!-- Desktop Navigation -->
        <div class="hidden md:flex items-center space-x-8">
          <router-link :to="{ name: 'storefront' }" class="text-sm font-medium text-gray-700 hover:text-brand-600 border-b-2 border-brand-600 pb-1">Accueil</router-link>
            <router-link :to="{ name: 'blog' }" class="text-sm font-bold text-gray-700 hover:text-brand-600 transition-colors uppercase tracking-widest">Blog</router-link>
            <router-link :to="{ name: 'news' }" class="text-sm font-bold text-gray-700 hover:text-brand-600 transition-colors uppercase tracking-widest">Actualités</router-link>
            
            <!-- Dropdown Style -->
            <div class="relative group">
              <button class="flex items-center gap-1 text-sm font-medium text-gray-600 group-hover:text-brand-600 transition-colors uppercase tracking-widest font-bold">
                Solutions
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <div class="absolute left-0 mt-2 w-48 bg-white border border-gray-100 rounded-md shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-10 font-sans">
                 <div class="py-1">
                   <a href="#" class="block px-4 py-2 text-xs text-gray-700 hover:bg-brand-50">Matériel Médical</a>
                   <a href="#" class="block px-4 py-2 text-xs text-gray-700 hover:bg-brand-50">Solutions B2B</a>
                 </div>
              </div>
            </div>

            <router-link :to="{ name: 'about' }" class="text-sm font-bold text-gray-700 hover:text-brand-600 transition-colors uppercase tracking-widest">Qui sommes-nous</router-link>
            <router-link :to="{ name: 'contact' }" class="text-sm font-bold text-gray-700 hover:text-brand-600 transition-colors uppercase tracking-widest">Contact</router-link>
        </div>

        <!-- Icons / Right side -->
        <div class="flex items-center space-x-5">
           <button class="text-gray-600 hover:text-brand-600 transition-colors relative">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
             </svg>
           </button>
           
           <!-- Accès Admin masqué Maya -->
        </div>
      </nav>
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
