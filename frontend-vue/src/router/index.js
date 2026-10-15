import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

// Views
import HomeView from '../views/HomeView.vue';
import CatalogView from '../views/CatalogView.vue';
import ProductDetailView from '../views/ProductDetailView.vue';
import TrackingView from '../views/TrackingView.vue';
import ArticlesView from '../views/ArticlesView.vue';
import ArticleDetailView from '../views/ArticleDetailView.vue';
import LoginView from '../views/LoginView.vue';
import RegisterView from '../views/RegisterView.vue';

// Role Dashboards
import PetaniDashboard from '../views/petani/PetaniDashboard.vue';
import TengkulakDashboard from '../views/tengkulak/TengkulakDashboard.vue';
import BuyerDashboard from '../views/buyer/BuyerDashboard.vue';
import SuperAdminDashboard from '../views/super/SuperAdminDashboard.vue';

const routes = [
    // 1. Guest / Public
    { path: '/', name: 'home', component: HomeView },
    { path: '/catalog', name: 'catalog', component: CatalogView },
    { path: '/catalog/:slug', name: 'product-detail', component: ProductDetailView },
    { path: '/track/:code?', name: 'tracking', component: TrackingView },
    { path: '/articles', name: 'articles', component: ArticlesView },
    { path: '/articles/:slug', name: 'article-detail', component: ArticleDetailView },
    { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
    { path: '/register', name: 'register', component: RegisterView, meta: { guestOnly: true } },

    // 2. Petani Portal (Mobile-First)
    {
        path: '/petani',
        name: 'petani-dashboard',
        component: PetaniDashboard,
        meta: { requiresAuth: true, roles: ['petani'] }
    },

    // 3. Tengkulak / Regional Distributor Portal
    {
        path: '/tengkulak',
        name: 'tengkulak-dashboard',
        component: TengkulakDashboard,
        meta: { requiresAuth: true, roles: ['admin_tengkulak', 'super_admin'] }
    },

    // 4. Buyer Workspace (B2B / B2C)
    {
        path: '/buyer',
        name: 'buyer-dashboard',
        component: BuyerDashboard,
        meta: { requiresAuth: true, roles: ['pembeli'] }
    },

    // 5. Super Admin & Admin Support Executive Center
    {
        path: '/super',
        name: 'super-admin-dashboard',
        component: SuperAdminDashboard,
        meta: { requiresAuth: true, roles: ['super_admin', 'admin'] }
    },

    // Catch-all
    { path: '/:pathMatch(.*)*', redirect: '/' }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    }
});

// 5-Level RBAC Navigation Guard
router.beforeEach(async (to, from, next) => {
    const auth = useAuthStore();

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return next(auth.roleDashboardRoute);
    }

    if (to.meta.requiresAuth) {
        if (!auth.isAuthenticated) {
            return next({ path: '/login', query: { redirect: to.fullPath } });
        }

        const allowedRoles = to.meta.roles || [];
        if (allowedRoles.length && !allowedRoles.includes(auth.role)) {
            // Unauthorized role access -> redirect to their designated dashboard
            return next(auth.roleDashboardRoute);
        }
    }

    next();
});

export default router;
