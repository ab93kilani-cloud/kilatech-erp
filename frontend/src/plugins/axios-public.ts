import axios from 'axios'

const publicApi = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: false // IMPORTANT : On n'envoie pas les cookies de session
})

export default publicApi
