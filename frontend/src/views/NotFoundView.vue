<template>
  <div class="min-h-screen bg-white flex flex-col justify-center py-12 sm:px-6 lg:px-8 items-center text-center">
    <h1 class="text-9xl font-extrabold text-brand-600">404</h1>
    <h2 class="mt-4 text-3xl font-bold text-gray-900 tracking-tight sm:text-5xl">Page introuvable</h2>
    <p class="mt-4 text-base text-gray-500">Désolé, nous n'avons pas pu trouver la page que vous recherchez.</p>
    <div class="mt-6">
      <router-link to="/" class="text-base font-medium text-brand-600 hover:text-brand-500">
        Retourner à l'accueil<span aria-hidden="true"> &rarr;</span>
      </router-link>
    </div>
  </div>
</template>
