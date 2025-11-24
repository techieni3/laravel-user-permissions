import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';

import { routes } from '@/router';

import './bootstrap';

import './../css/app.css';

import Dashboard from '@/pages/Dashboard.vue';

const routerBasePath = window.Permissions.path + '/';

const router = createRouter({
  history: createWebHistory(routerBasePath),
  routes,
});

const app = createApp(Dashboard);
app.use(router);
app.mount('#app');
