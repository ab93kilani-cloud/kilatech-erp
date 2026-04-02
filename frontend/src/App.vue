<script setup lang="ts">
import { useAuthStore } from './stores/auth'
import { onMounted } from 'vue'

const authStore = useAuthStore()

onMounted(async () => {
    // Attempt to automatically re-fetch the user details if a token exists in LocalStorage
    if (authStore.token && !authStore.user) {
        await authStore.fetchProfile()
    }
})
</script>

<template>
  <router-view /> <!-- This renders the Login OR the Dashboard Layout -->
</template>

<style>
/* Any ultra global CSS tweaks can go here, but usually tailwind handles this. */
</style>
