import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '/api', // Relative path for universal use (Local or Production) Maya.
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
    withCredentials: true, // For Sanctum CSRF / Cookies
});

// Interceptor to handle globally 401 Unauthenticated
api.interceptors.response.use(
    response => response,
    error => {
        if (error.response && error.response.status === 401) {
            // Optional: Handle logout automatically if unauthorized
            // window.location.href = '/login'; 
            localStorage.removeItem('token'); // Fallback if using tokens instead of strictly cookies
        }
        return Promise.reject(error);
    }
);

export default api;
