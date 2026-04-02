/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#f0fbfb',
          100: '#ccf2f2',
          200: '#99e6e6',
          300: '#66d9d9',
          400: '#33cccc',
          500: '#00a6a6', // Couleur exacte du logo original Kilatech
          600: '#008585',
          700: '#006363',
          800: '#004242',
          900: '#002121',
        },
      }
    },
  },
  plugins: [],
}
