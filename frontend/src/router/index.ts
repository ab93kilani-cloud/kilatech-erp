import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

// Domain detection for architecture separation
const isAppSubdomain = () => {
    const host = window.location.hostname;
    return host.startsWith('app.') || host.includes('localhost'); // localhost allowed for dev
};

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: () => import('../views/auth/LoginView.vue'),
            meta: { requiresGuest: true }
        },
        {
            path: '/',
            component: () => import('../layouts/public/PublicLayout.vue'),
            children: [
                {
                    path: '',
                    name: 'storefront',
                    component: () => import('../views/public/StorefrontView.vue'),
                    meta: { title: 'Kilatech - Vente Matériel Médical au Maroc' }
                },
                {
                    path: 'produit/:slug',
                    name: 'product-detail',
                    component: () => import('../views/public/ProductDetailView.vue'),
                    props: true
                },
                {
                    path: 'about',
                    name: 'about',
                    component: () => import('../views/public/AboutView.vue')
                },
                {
                    path: 'blog',
                    name: 'blog',
                    component: () => import('../views/public/BlogView.vue')
                },
                {
                    path: 'actualites',
                    name: 'news',
                    component: () => import('../views/public/NewsView.vue')
                },
                {
                    path: 'actualites/:slug',
                    name: 'article-detail',
                    component: () => import('../views/public/ArticleDetailView.vue')
                },
                {
                    path: 'contact',
                    name: 'contact',
                    component: () => import('../views/public/ContactView.vue')
                },
                {
                    path: 'catalogues',
                    name: 'catalogues',
                    component: () => import('../views/public/CataloguesView.vue')
                },
                {
                    path: 'devenir-partenaire',
                    name: 'become-partner',
                    component: () => import('../views/public/BecomePartnerView.vue'),
                    meta: { title: 'Devenir Partenaire | Kilatech' }
                }
            ]
        },
        {
            path: '/admin',
            component: () => import('../layouts/DashboardLayout.vue'),
            meta: { requiresAuth: true, requiresAppSubdomain: true },
            children: [
                {
                    path: '',
                    name: 'dashboard',
                    component: () => import('../views/DashboardView.vue'),
                    meta: { roles: ['Admin', 'Gérant'] }
                },
                {
                    path: 'vitrinerie',
                    name: 'vitrinerie',
                    component: () => import('../views/vitrinerie/VitrinerieHubView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Commercial'] }
                },
                // ... same routes as before ...
                {
                    path: 'catalog/create',
                    name: 'catalog.create',
                    component: () => import('../views/catalog/ProductFormView.vue')
                },
                {
                    path: 'catalog',
                    name: 'catalog',
                    component: () => import('../views/catalog/ProductListView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Commercial', 'Responsable d\'Achat', 'Responsable Marketing'] }
                },
                {
                    path: 'categories',
                    name: 'categories',
                    component: () => import('../views/catalog/CategoryListView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Commercial', 'Responsable d\'Achat', 'Responsable Marketing'] }
                },
                {
                    path: 'catalog/:id/edit',
                    name: 'catalog.edit',
                    component: () => import('../views/catalog/ProductFormView.vue')
                },
                {
                    path: 'stock/qa',
                    name: 'stock.qa',
                    component: () => import('../views/stock/StockQAValdationView.vue')
                },
                {
                    path: 'stock',
                    name: 'stock',
                    component: () => import('../views/stock/StockListView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Logisticien'] }
                },
                {
                    path: 'quotes/create',
                    name: 'quotes.create',
                    component: () => import('../views/sales/QuoteFormView.vue')
                },
                {
                    path: 'quotes/:id/edit',
                    name: 'quotes.edit',
                    component: () => import('../views/sales/QuoteFormView.vue')
                },
                {
                    path: 'quotes',
                    name: 'quotes',
                    component: () => import('../views/sales/QuoteListView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Commercial'] }
                },
                {
                    path: 'orders',
                    name: 'orders',
                    component: () => import('../views/sales/OrderListView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Commercial'] }
                },
                {
                    path: 'clients',
                    name: 'clients',
                    component: () => import('../views/clients/ClientListView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Commercial'] }
                },
                {
                    path: 'suppliers',
                    name: 'suppliers',
                    component: () => import('../views/suppliers/SupplierListView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Responsable d\'Achat'] }
                },
                {
                    path: 'purchases/create',
                    name: 'purchases.create',
                    component: () => import('../views/purchases/PurchaseOrderFormView.vue')
                },
                {
                    path: 'purchases/:id/edit',
                    name: 'purchases.edit',
                    component: () => import('../views/purchases/PurchaseOrderFormView.vue')
                },
                {
                    path: 'purchases',
                    name: 'purchases',
                    component: () => import('../views/purchases/PurchaseOrderListView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Responsable d\'Achat'] }
                },
                {
                    path: 'purchases/:id',
                    name: 'purchases.show',
                    component: () => import('../views/purchases/PurchaseOrderShow.vue')
                },
                {
                    path: 'receipts',
                    name: 'receipts',
                    component: () => import('../views/logistics/ReceiptListView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Responsable d\'Achat', 'Logisticien'] }
                },
                {
                    path: 'invoices',
                    name: 'invoices',
                    component: () => import('../views/finance/InvoiceListView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Commercial'] }
                },
                {
                    path: 'credit-notes/create',
                    name: 'credit-notes.create',
                    component: () => import('../views/finance/CreditNoteFormView.vue')
                },
                {
                    path: 'credit-notes',
                    name: 'credit-notes',
                    component: () => import('../views/finance/CreditNoteListView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Commercial'] }
                },
                {
                    path: 'payments',
                    name: 'payments',
                    component: () => import('../views/finance/PaymentListView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Commercial', 'Comptable'] }
                },
                {
                    path: 'users',
                    name: 'users',
                    component: () => import('../views/users/UserListView.vue'),
                    meta: { roles: ['Admin', 'Gérant'] }
                },
                {
                    path: 'marketing',
                    name: 'marketing',
                    component: () => import('../views/marketing/MarketingDashboardView.vue'),
                    meta: { roles: ['Admin', 'Responsable Marketing', 'Gérant'] }
                },
                {
                    path: 'marketing/blog/create',
                    name: 'article-create',
                    component: () => import('../views/marketing/ArticleFormView.vue'),
                    meta: { roles: ['Admin', 'Responsable Marketing', 'Gérant'] }
                },
                {
                    path: 'marketing/blog/:id/edit',
                    name: 'article-edit',
                    component: () => import('../views/marketing/ArticleFormView.vue'),
                    meta: { roles: ['Admin', 'Responsable Marketing', 'Gérant'] }
                },
                {
                    path: 'manager/dashboard',
                    name: 'manager.dashboard',
                    component: () => import('../views/manager/ManagerDashboardView.vue'),
                    meta: { roles: ['Admin', 'Gérant'] }
                },
                {
                    path: 'accounting/dashboard',
                    name: 'accounting.dashboard',
                    component: () => import('../views/accounting/AccountingDashboardView.vue'),
                    meta: { roles: ['Admin', 'Gérant', 'Comptable'] }
                }
            ]
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'NotFound',
            component: () => import('../views/NotFoundView.vue')
        }
    ]
})

// Role-based redirection logic
const getHomeRouteByRole = (role: string | null) => {
    switch (role) {
        case 'Admin':
        case 'Gérant':
            return { name: 'dashboard' }
        case 'Commercial':
            return { name: 'quotes' }
        case 'Responsable d\'Achat':
            return { name: 'purchases' }
        case 'Comptable':
            return { name: 'accounting.dashboard' }
        case 'Responsable Marketing':
            return { name: 'marketing' }
        case 'Logisticien':
            return { name: 'receipts' }
        default:
            return { name: 'storefront' }
    }
}

// Navigation Guard
router.beforeEach(async (to) => {
    const authStore = useAuthStore()
    
    // 1. If we have a token but no user, fetch the profile immediately to avoid role-routing loops
    if (authStore.token && !authStore.user) {
        await authStore.fetchProfile()
    }

    const isAuthenticated = authStore.isAuthenticated
    const userRole = authStore.role

    // 2. On app subdomain: redirect root (/) directly to login or dashboard
    if (isAppSubdomain() && (to.name === 'storefront' || to.path === '/')) {
        if (isAuthenticated) {
            const target = getHomeRouteByRole(userRole)
            // Safety: Never redirect to storefront on app subdomain
            return target.name === 'storefront' ? { name: 'dashboard' } : target
        } else {
            return { name: 'login' }
        }
    }

    // 3. Security check: Block admin routes on the main public domain
    if (to.meta.requiresAppSubdomain && !isAppSubdomain()) {
        console.warn('Access denied: Admin routes are only available on app subdomain.');
        return { name: 'storefront' }
    }

    // 4. Protected routes
    if (to.meta.requiresAuth && !isAuthenticated) {
        return { name: 'login' }
    }
    
    // 5. Role-specific route protection
    if (to.meta.requiresAuth && to.meta.roles) {
        const allowedRoles = to.meta.roles as string[]
        if (userRole && !allowedRoles.includes(userRole)) {
            return getHomeRouteByRole(userRole)
        }
    }

    // 6. Guest routes (like login)
    if (to.meta.requiresGuest && isAuthenticated) {
        const target = getHomeRouteByRole(userRole)
        // Safety for subdomain: if we catch login while authenticated, go to dashboard not storefront
        if (isAppSubdomain() && target.name === 'storefront') {
            return { name: 'dashboard' }
        }
        return target
    }
})

// SEO: Manage document.title and meta description
router.afterEach((to) => {
    // Basic title management
    const baseTitle = 'Kilatech Medical';
    const pageTitle = to.meta.title ? `${to.meta.title} | ${baseTitle}` : baseTitle;
    document.title = pageTitle as string;

    // Robots meta management: noindex on app subdomain or admin routes
    let robotsTag = document.querySelector('meta[name="robots"]');
    if (!robotsTag) {
        robotsTag = document.createElement('meta');
        robotsTag.setAttribute('name', 'robots');
        document.head.appendChild(robotsTag);
    }

    if (isAppSubdomain() || to.path.startsWith('/admin')) {
        robotsTag.setAttribute('content', 'noindex, nofollow, noarchive');
    } else {
        robotsTag.setAttribute('content', 'index, follow');
    }
})

export default router
