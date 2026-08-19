import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';

const routes = [
    { path: '/', name: 'home', component: () => import('./pages/HomePage.vue') },
    { path: '/search', name: 'search', component: () => import('./pages/SearchPage.vue') },
    { path: '/c/:slug', name: 'category', component: () => import('./pages/SearchPage.vue') },
    { path: '/l/:slug', name: 'listing', component: () => import('./pages/ListingDetailPage.vue') },
    { path: '/login', name: 'login', component: () => import('./pages/LoginPage.vue'), meta: { guest: true } },
    { path: '/register', name: 'register', component: () => import('./pages/RegisterPage.vue'), meta: { guest: true } },
    { path: '/forgot-password', name: 'forgot', component: () => import('./pages/ForgotPasswordPage.vue'), meta: { guest: true } },
    { path: '/dashboard', name: 'dashboard', component: () => import('./pages/DashboardPage.vue'), meta: { auth: true } },
    { path: '/dashboard/listings/new', name: 'listing-create', component: () => import('./pages/ListingFormPage.vue'), meta: { auth: true } },
    { path: '/dashboard/listings/:id/edit', name: 'listing-edit', component: () => import('./pages/ListingFormPage.vue'), meta: { auth: true } },
    { path: '/dashboard/listings/:id/feature', name: 'listing-feature', component: () => import('./pages/FeaturePage.vue'), meta: { auth: true } },
    { path: '/dashboard/payments', name: 'payments', component: () => import('./pages/PaymentsPage.vue'), meta: { auth: true } },
    { path: '/favorites', name: 'favorites', component: () => import('./pages/FavoritesPage.vue'), meta: { auth: true } },
    { path: '/profile', name: 'profile', component: () => import('./pages/ProfilePage.vue'), meta: { auth: true } },
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

    if (to.meta.guest && auth.isLoggedIn) {
        return { name: 'dashboard' };
    }
});
