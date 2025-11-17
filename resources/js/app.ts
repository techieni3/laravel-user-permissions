import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import Layout from './components/Layout.vue';
import routes from './router';
import '../css/app.css';

const router = createRouter({
    history: createWebHistory('/permissions-manager'),
    routes,
});

// Set page title from route meta
router.beforeEach((to, from, next) => {
    document.title = to.meta.title ? `${to.meta.title} - Permissions Manager` : 'Permissions Manager';
    next();
});

const app = createApp(Layout);
app.use(router);
app.mount('#app');
