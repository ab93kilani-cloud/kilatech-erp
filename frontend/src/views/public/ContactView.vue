<template>
  <div class="bg-white min-h-screen">
    <!-- Page Header -->
    <section class="py-12 bg-gray-50 border-b border-gray-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-4xl font-extrabold text-gray-900 text-center uppercase tracking-tight">
          contactez-nous
        </h1>
      </div>
    </section>

    <!-- Content Section -->
    <section class="py-20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-20">
          
          <!-- Left: Contact Form -->
          <div>
            <h2 class="text-3xl font-bold text-gray-900 mb-6">Écrivez-nous</h2>
            <p class="text-gray-600 mb-10 leading-relaxed font-light">
              N’hésitez pas à nous contacter pour toute question ou préoccupation. Vous pouvez utiliser le formulaire sur notre site web ou nous envoyer un email directement. Nous vous remercions de l’intérêt que vous portez à notre société et serons ravis de vous lire.
            </p>

            <form @submit.prevent="handleSubmit" class="space-y-6 bg-gray-50 p-8 rounded-3xl border border-gray-100 shadow-sm">
              <div class="grid md:grid-cols-2 gap-6">
                 <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Votre prénom *</label>
                    <input v-model="form.first_name" type="text" placeholder="Entrez votre prénom ici" class="w-full px-5 py-3.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all outline-none" required />
                 </div>
                 <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Votre nom *</label>
                    <input v-model="form.last_name" type="text" placeholder="Entrez votre nom ici" class="w-full px-5 py-3.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all outline-none" required />
                 </div>
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Votre adresse e-mail *</label>
                <input v-model="form.email" type="email" placeholder="Entrez votre e-mail ici" class="w-full px-5 py-3.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all outline-none" required />
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Votre pays</label>
                <input v-model="form.country" type="text" class="w-full px-5 py-3.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all outline-none" />
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Votre Téléphone *</label>
                <input v-model="form.phone" type="tel" placeholder="+212" class="w-full px-5 py-3.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all outline-none" required />
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Votre message *</label>
                <textarea v-model="form.message" rows="4" placeholder="Écrivons votre message ici" class="w-full px-5 py-3.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all outline-none resize-none" required></textarea>
              </div>

              <button type="submit" :disabled="isSubmitting" class="w-full py-4 bg-[#0a1435] text-white rounded-full font-bold uppercase tracking-widest text-sm hover:bg-brand-700 transition-all shadow-lg hover:shadow-brand-200 active:scale-95 disabled:opacity-50">
                 <span v-if="isSubmitting">Envoi en cours...</span>
                 <span v-else>Envoyer votre message</span>
              </button>

              <div v-if="successMsg" class="mt-4 p-4 bg-green-50 text-green-700 rounded-xl border border-green-100 font-bold text-center">
                {{ successMsg }}
              </div>
              <div v-if="errorMsg" class="mt-4 p-4 bg-red-50 text-red-700 rounded-xl border border-red-100 font-bold text-center">
                {{ errorMsg }}
              </div>
            </form>
          </div>

          <!-- Right: Info & Map -->
          <div class="space-y-12">
            <div>
              <h2 class="text-3xl font-bold text-gray-900 mb-6">Visitez-nous</h2>
              <div class="rounded-3xl overflow-hidden shadow-xl border-4 border-white mb-6 group">
                 <!-- Interactive Google Map -->
                 <div class="relative w-full h-[400px]">
                   <iframe 
                     src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3324.4633!2d-7.52236!3d33.56246!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMzPCsDMzJzQ0LjkiTiA3wrAzMScxMS4yIlc!5e0!3m2!1sfr!2sma!4v1715000000000!5m2!1sfr!2sma" 
                     width="100%" 
                     height="100%" 
                     style="border:0;" 
                     allowfullscreen="true" 
                     loading="lazy" 
                     referrerpolicy="no-referrer-when-downgrade"
                     class="grayscale hover:grayscale-0 transition-all duration-700"
                   ></iframe>
                   <a href="https://www.google.com/maps/place/33%C2%B033'44.9%22N+7%C2%B031'11.2%22W/@33.5624685,-7.5197855,17z" target="_blank" class="absolute bottom-4 right-4 bg-white/90 backdrop-blur px-4 py-2 rounded-full font-bold text-[10px] text-gray-900 shadow-xl hover:bg-brand-600 hover:text-white transition-all uppercase tracking-widest">
                      Ouvrir dans Maps
                   </a>
                 </div>
              </div>
              <div class="space-y-4 text-gray-600 font-light">
                 <p class="flex items-start gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-brand-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Local 16 – Immeuble 02, Résidence Al Abrar, Rue Hay Baraka 2, <br/>Sidi Moumen, Casablanca 20300, Maroc.</span>
                 </p>
                 <p class="flex items-start gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-brand-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    <span>+212 765 419 754 / +212 522 726 673</span>
                 </p>
                 <p class="flex items-start gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-brand-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span>info@kilatech.ma</span>
                 </p>
              </div>
            </div>

            <div class="pt-8 border-t border-gray-100">
               <h3 class="text-xl font-bold text-gray-900 mb-6 uppercase tracking-widest text-sm">Follow us</h3>
               <div class="flex gap-6">
                  <a href="https://web.facebook.com/kilatech05/?_rdc=1&_rdr" target="_blank" class="group">
                     <div class="p-3 bg-brand-50 text-brand-600 rounded-full group-hover:bg-brand-600 group-hover:text-white transition-all transform group-hover:scale-110">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
                     </div>
                  </a>
                  <a href="https://www.linkedin.com/company/kilatech-maroc/" target="_blank" class="group">
                     <div class="p-3 bg-brand-50 text-brand-600 rounded-full group-hover:bg-brand-600 group-hover:text-white transition-all transform group-hover:scale-110">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                     </div>
                  </a>
               </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- Footer Simple CTA -->
    <section class="py-16 bg-[#0a1435]">
       <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-8">
          <div class="text-white">
             <h2 class="text-2xl font-bold mb-2">Besoin d'une réponse rapide ?</h2>
             <p class="text-gray-400">Notre équipe est disponible sur WhatsApp pour vous aider.</p>
          </div>
          <a href="https://wa.me/212765419754" target="_blank" class="px-10 py-4 bg-[#25D366] text-white rounded-full font-bold flex items-center gap-3 hover:scale-105 transition-transform">
             <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
             Chatter sur WhatsApp
          </a>
       </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'

const form = ref({
    first_name: '',
    last_name: '',
    email: '',
    country: 'Maroc',
    phone: '',
    message: ''
})

const isSubmitting = ref(false)
const successMsg = ref('')
const errorMsg = ref('')

const handleSubmit = async () => {
    isSubmitting.value = true
    successMsg.value = ''
    errorMsg.value = ''
    
    try {
        // Direct axios for Hostinger compatibility
        const response = await axios.post('/public/contact', form.value)
        successMsg.value = response.data.message
        
        // Reset form
        form.value = {
            first_name: '',
            last_name: '',
            email: '',
            country: 'Maroc',
            phone: '',
            message: ''
        }
    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || "Erreur lors de l'envoi du message."
    } finally {
        isSubmitting.value = false
    }
}
</script>

<style scoped>
.text-brand-600 { color: #26a69a; }
.bg-brand-600 { background-color: #26a69a; }
.bg-brand-50 { background-color: #e0f2f1; }
.focus\:ring-brand-500:focus { --tw-ring-color: #4db6ac; }
.shadow-brand-200 { --tw-shadow-color: rgba(38, 166, 154, 0.2); }
</style>
