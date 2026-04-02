import { computed } from 'vue';

export function useConfig() {
    const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

    // Extract base URL without /api suffix
    const storageBaseUrl = computed(() => {
        return apiBaseUrl.replace('/api', '') + '/storage';
    });

    const getImageUrl = (path: string | null) => {
        if (!path) return '/logo.png';
        // If it's already an absolute URL, return it
        if (path.startsWith('http')) return path;
        return `${storageBaseUrl.value}/${path}`;
    };

    return {
        getImageUrl,
        storageBaseUrl,
        apiBaseUrl
    };
}
