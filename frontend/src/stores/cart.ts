import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'

export interface CartItem {
  id: number;
  product_id: number;
  variant_id: number;
  name: string;
  image_path: string | null;
  price: number;
  quantity: number;
  category: string;
}

export const useCartStore = defineStore('cart', () => {
  const items = ref<CartItem[]>([])
  
  // Load from local storage on init
  const savedCart = localStorage.getItem('kilatech_cart')
  if (savedCart) {
    try {
      items.value = JSON.parse(savedCart)
    } catch (e) {
      console.error('Failed to parse cart from local storage', e)
    }
  }

  // Persist to local storage on change
  watch(items, (newItems) => {
    localStorage.setItem('kilatech_cart', JSON.stringify(newItems))
  }, { deep: true })

  const totalItems = computed(() => items.value.reduce((acc, item) => acc + item.quantity, 0))
  const totalPrice = computed(() => items.value.reduce((acc, item) => acc + (item.price * item.quantity), 0))

  function addItem(product: any, variant: any, quantity: number = 1) {
    const existingItem = items.value.find(item => item.variant_id === variant.id)
    
    if (existingItem) {
      existingItem.quantity += quantity
    } else {
      const price_ttc = parseFloat(variant.public_price || 0)
      
      items.value.push({
        id: Math.random(), // local id
        product_id: product.id,
        variant_id: variant.id,
        name: `${product.name} ${variant.name_variation || ''}`.trim(),
        image_path: product.image_path,
        price: price_ttc,
        quantity: quantity,
        category: product.category
      })
    }
  }

  function removeItem(variantId: number) {
    items.value = items.value.filter(item => item.variant_id !== variantId)
  }

  function updateQuantity(variantId: number, quantity: number) {
    const item = items.value.find(item => item.variant_id === variantId)
    if (item) {
      item.quantity = Math.max(1, quantity)
    }
  }

  function clearCart() {
    items.value = []
  }

  return {
    items,
    totalItems,
    totalPrice,
    addItem,
    removeItem,
    updateQuantity,
    clearCart
  }
})
