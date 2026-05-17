<template>
  <transition
    enter-active-class="transition duration-500 ease-out"
    enter-from-class="transform translate-y-full opacity-0"
    enter-to-class="transform translate-y-0 opacity-100"
    leave-active-class="transition duration-300 ease-in"
    leave-from-class="transform translate-y-0 opacity-100"
    leave-to-class="transform translate-y-full opacity-0"
  >
    <div v-if="isVisible" class="fixed bottom-0 left-0 w-full z-[100] p-4 sm:p-6 pointer-events-none">
      <div class="max-w-5xl mx-auto bg-white/90 backdrop-blur-md border border-gray-200 shadow-2xl rounded-3xl p-6 sm:p-8 flex flex-col md:flex-row items-center gap-6 pointer-events-auto">
        <!-- Content -->
        <div class="flex-1">
          <div class="flex items-center gap-3 mb-3">
             <div class="w-10 h-10 bg-brand-50 text-brand-600 rounded-full flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" /></svg>
             </div>
             <h3 class="text-xl font-black text-gray-900">Respect de votre vie privée</h3>
          </div>
          <p class="text-sm text-gray-600 font-medium leading-relaxed">
            Pour vous offrir la meilleure expérience sur notre plateforme B2B, Kilatech utilise des cookies. 
            Certains sont indispensables au fonctionnement du site, d'autres nous aident à analyser le trafic et à vous proposer des offres adaptées. 
            Vous avez le contrôle total sur vos données.
          </p>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row md:flex-col lg:flex-row gap-3 w-full md:w-auto shrink-0">
          <button @click="rejectAll" class="px-6 py-3 border border-gray-200 text-gray-600 font-bold rounded-full hover:bg-gray-50 transition-colors text-sm w-full sm:w-auto">
            Continuer sans accepter
          </button>
          <button @click="acceptAll" class="px-8 py-3 bg-brand-600 text-white font-bold rounded-full shadow-lg shadow-brand-100 hover:bg-brand-700 hover:-translate-y-0.5 transition-all active:scale-95 text-sm w-full sm:w-auto">
            Tout Accepter
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { initAnalytics } from '@/utils/analytics'

const isVisible = ref(false)

onMounted(() => {
  const consent = localStorage.getItem('kilatech_cookie_consent')
  if (!consent) {
    setTimeout(() => {
      isVisible.value = true
    }, 1500)
  } else if (consent === 'accepted') {
    // Already accepted, initialize silently
    initAnalytics()
  }
})

const acceptAll = () => {
  localStorage.setItem('kilatech_cookie_consent', 'accepted')
  isVisible.value = false
  initAnalytics()
}

const rejectAll = () => {
  localStorage.setItem('kilatech_cookie_consent', 'rejected')
  isVisible.value = false
}
</script>
