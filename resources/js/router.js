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
    { path: '/terms', name: 'terms', component: () => import('./pages/TermsPage.vue') },
    { path: '/privacy', name: 'privacy', component: () => import('./pages/PrivacyPage.vue') },

    // Auth
    { path: '/register', name: 'register', component: () => import('./pages/auth/RegisterPage.vue'), meta: { guest: true } },
    { path: '/login', name: 'login', component: () => import('./pages/auth/LoginPage.vue'), meta: { guest: true } },
    { path: '/forgot-password', name: 'forgot', component: () => import('./pages/auth/ForgotPasswordPage.vue'), meta: { guest: true } },
    { path: '/verify', name: 'verify', component: () => import('./pages/auth/VerifyPage.vue'), meta: { auth: true } },

    // Бизнес нэмэх + onboarding
    { path: '/add-business', name: 'add-business', component: () => import('./pages/onboarding/BusinessWizardPage.vue'), meta: { auth: true, verified: true, chrome: false } },
    { path: '/onboarding/:orgId/plan', name: 'plan-select', component: () => import('./pages/onboarding/PlanSelectPage.vue'), meta: { auth: true } },
    { path: '/orders/:id/pay', name: 'order-pay', component: () => import('./pages/onboarding/PaymentPage.vue'), meta: { auth: true, chrome: false } },
    { path: '/orders/:id/success', name: 'order-success', component: () => import('./pages/onboarding/SuccessPage.vue'), meta: { auth: true } },

    // Хэрэглэгчийн дашбоард
    { path: '/account', name: 'account', component: () => import('./pages/AccountPage.vue'), meta: { auth: true } },

    // Бизнес зөвлөл
    {
        path: '/console',
        component: () => import('./pages/console/ConsoleLayout.vue'),
        meta: { auth: true, verified: true },
        children: [
            { path: '', name: 'console', component: () => import('./pages/console/BranchesTab.vue') },
            { path: 'stats', name: 'console-stats', component: () => import('./pages/console/StatsTab.vue') },
            { path: 'reviews', name: 'console-reviews', component: () => import('./pages/console/ReviewsTab.vue') },
            { path: 'plan', name: 'console-plan', component: () => import('./pages/console/PlanTab.vue') },
            { path: 'invoices', name: 'console-invoices', component: () => import('./pages/console/InvoicesTab.vue') },
            { path: 'settings', name: 'console-settings', component: () => import('./pages/console/SettingsTab.vue') },
        ],
    },
    { path: '/console/branches/:id', name: 'branch-edit', component: () => import('./pages/console/BranchEditPage.vue'), meta: { auth: true, verified: true, chrome: false } },
    { path: '/console/ads/new', name: 'ad-purchase', component: () => import('./pages/console/AdPurchasePage.vue'), meta: { auth: true, verified: true, chrome: false } },

    // Админ
    {
        path: '/admin',
        component: () => import('./pages/admin/AdminLayout.vue'),
        // Админ өөрийн толгой, sidebar-тай — нийтийн header/footer давхарлахгүй
        meta: { auth: true, admin: true, chrome: false },
        children: [
            { path: '', name: 'admin', component: () => import('./pages/admin/ModerationTab.vue') },
            { path: 'revenue', name: 'admin-revenue', component: () => import('./pages/admin/RevenueTab.vue') },
            { path: 'businesses', name: 'admin-businesses', component: () => import('./pages/admin/BusinessesTab.vue') },
            { path: 'reviews', name: 'admin-reviews', component: () => import('./pages/admin/ReviewsModTab.vue') },
            { path: 'ads', name: 'admin-ads', component: () => import('./pages/admin/AdsTab.vue') },
            { path: 'plans', name: 'admin-plans', component: () => import('./pages/admin/PlansTab.vue') },
            { path: 'categories', name: 'admin-categories', component: () => import('./pages/admin/CategoriesAdminTab.vue') },
        ],
    },

    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('./pages/NotFoundPage.vue') },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

const routeTitles = {
    home: 'Хаана.mn — Монголын бизнес лавлах',
    categories: 'Бүх ангилал | Хаана.mn',
    search: 'Хайлт | Хаана.mn',
    nearby: 'Миний ойролцоо | Хаана.mn',
    pricing: 'Бизнест — үнэ, эрхийн бичиг | Хаана.mn',
    terms: 'Үйлчилгээний нөхцөл | Хаана.mn',
    privacy: 'Нууцлалын журам | Хаана.mn',
    login: 'Нэвтрэх | Хаана.mn',
    register: 'Бүртгүүлэх | Хаана.mn',
    account: 'Миний булан | Хаана.mn',
    console: 'Бизнес зөвлөл | Хаана.mn',
    admin: 'Админ | Хаана.mn',
};

router.afterEach((to) => {
    // Динамик хуудсууд (business/category) title-аа өөрөө тавина
    if (routeTitles[to.name]) document.title = routeTitles[to.name];
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

    if (to.meta.verified && auth.isLoggedIn && !auth.user?.phone_verified) {
        return { name: 'verify' };
    }

    if (to.meta.guest && auth.isLoggedIn) {
        return { name: 'home' };
    }
});
