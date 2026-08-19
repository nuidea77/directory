import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';

const routes = [
    // Нийтийн
    { path: '/', name: 'home', component: () => import('./pages/HomePage.vue') },
    { path: '/search', name: 'search', component: () => import('./pages/CategoryPage.vue') },
    { path: '/categories', name: 'categories', component: () => import('./pages/CategoriesPage.vue') },
    { path: '/c/:slug', name: 'category', component: () => import('./pages/CategoryPage.vue') },
    { path: '/b/:slug', name: 'business', component: () => import('./pages/BusinessPage.vue') },
    { path: '/nearby', name: 'nearby', component: () => import('./pages/NearbyPage.vue') },
    { path: '/pricing', name: 'pricing', component: () => import('./pages/PricingPage.vue') },

    // Auth
    { path: '/register', name: 'register', component: () => import('./pages/auth/RegisterPage.vue'), meta: { guest: true } },
    { path: '/login', name: 'login', component: () => import('./pages/auth/LoginPage.vue'), meta: { guest: true } },
    { path: '/forgot-password', name: 'forgot', component: () => import('./pages/auth/ForgotPasswordPage.vue'), meta: { guest: true } },
    { path: '/verify', name: 'verify', component: () => import('./pages/auth/VerifyPage.vue'), meta: { auth: true } },

    // Бизнес нэмэх + onboarding
    { path: '/add-business', name: 'add-business', component: () => import('./pages/onboarding/BusinessWizardPage.vue'), meta: { auth: true } },
    { path: '/onboarding/:orgId/plan', name: 'plan-select', component: () => import('./pages/onboarding/PlanSelectPage.vue'), meta: { auth: true } },
    { path: '/orders/:id/pay', name: 'order-pay', component: () => import('./pages/onboarding/PaymentPage.vue'), meta: { auth: true } },
    { path: '/orders/:id/success', name: 'order-success', component: () => import('./pages/onboarding/SuccessPage.vue'), meta: { auth: true } },

    // Хэрэглэгчийн дашбоард
    { path: '/account', name: 'account', component: () => import('./pages/AccountPage.vue'), meta: { auth: true } },

    // Бизнес зөвлөл
    {
        path: '/console',
        component: () => import('./pages/console/ConsoleLayout.vue'),
        meta: { auth: true },
        children: [
            { path: '', name: 'console', component: () => import('./pages/console/BranchesTab.vue') },
            { path: 'stats', name: 'console-stats', component: () => import('./pages/console/StatsTab.vue') },
            { path: 'messages', name: 'console-messages', component: () => import('./pages/console/MessagesTab.vue') },
            { path: 'reviews', name: 'console-reviews', component: () => import('./pages/console/ReviewsTab.vue') },
            { path: 'plan', name: 'console-plan', component: () => import('./pages/console/PlanTab.vue') },
            { path: 'invoices', name: 'console-invoices', component: () => import('./pages/console/InvoicesTab.vue') },
            { path: 'settings', name: 'console-settings', component: () => import('./pages/console/SettingsTab.vue') },
        ],
    },
    { path: '/console/branches/:id', name: 'branch-edit', component: () => import('./pages/console/BranchEditPage.vue'), meta: { auth: true } },
    { path: '/console/ads/new', name: 'ad-purchase', component: () => import('./pages/console/AdPurchasePage.vue'), meta: { auth: true } },

    // Админ
    {
        path: '/admin',
        component: () => import('./pages/admin/AdminLayout.vue'),
        meta: { auth: true, admin: true },
        children: [
            { path: '', name: 'admin', component: () => import('./pages/admin/ModerationTab.vue') },
            { path: 'revenue', name: 'admin-revenue', component: () => import('./pages/admin/RevenueTab.vue') },
            { path: 'businesses', name: 'admin-businesses', component: () => import('./pages/admin/BusinessesTab.vue') },
        ],
    },

    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('./pages/NotFoundPage.vue') },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    await auth.load();

    if (to.meta.auth && !auth.isLoggedIn) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.admin && !auth.user?.is_admin) {
        return { name: 'home' };
    }

    if (to.meta.guest && auth.isLoggedIn) {
        return { name: 'home' };
    }
});
