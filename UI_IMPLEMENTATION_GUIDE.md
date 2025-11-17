# Permissions Manager UI Implementation Guide

This guide provides step-by-step instructions to complete the Vue 3 + shadcn-vue UI for the Permissions Manager.

## ✅ What's Already Implemented

### Backend (Complete)
- ✅ API Controllers (`RoleController`, `PermissionController`, `UserController`, `DashboardController`)
- ✅ API Routes (`routes/web.php`)
- ✅ Role model with `users()` relationship
- ✅ Service Provider with route and view loading
- ✅ Configuration file with UI settings
- ✅ Base Blade view (`resources/views/dashboard.blade.php`)

### API Endpoints Available
```
GET    /permissions-manager                 # Dashboard view
GET    /permissions-manager/api/roles       # List roles
POST   /permissions-manager/api/roles       # Create role
GET    /permissions-manager/api/roles/{id}  # Show role
PUT    /permissions-manager/api/roles/{id}  # Update role
DELETE /permissions-manager/api/roles/{id}  # Delete role
GET    /permissions-manager/api/permissions # List permissions
POST   /permissions-manager/api/permissions # Create permission
PUT    /permissions-manager/api/permissions/{id} # Update permission
DELETE /permissions-manager/api/permissions/{id} # Delete permission
GET    /permissions-manager/api/users       # List users
PUT    /permissions-manager/api/users/{id}/roles # Update user roles
PUT    /permissions-manager/api/users/{id}/permissions # Update user permissions
```

---

## 🚀 Frontend Implementation Steps

### Step 1: Initialize Package Dependencies

Create `package.json` in the package root:

```json
{
  "name": "laravel-user-permissions",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview"
  },
  "dependencies": {
    "@radix-icons/vue": "^1.0.0",
    "@vueuse/core": "^11.0.0",
    "axios": "^1.7.0",
    "class-variance-authority": "^0.7.0",
    "clsx": "^2.1.0",
    "radix-vue": "^1.9.0",
    "tailwind-merge": "^2.5.0",
    "tailwindcss-animate": "^1.0.7",
    "vue": "^3.4.0",
    "vue-router": "^4.4.0"
  },
  "devDependencies": {
    "@types/node": "^22.0.0",
    "@vitejs/plugin-vue": "^5.0.0",
    "autoprefixer": "^10.4.0",
    "laravel-vite-plugin": "^1.0.0",
    "postcss": "^8.4.0",
    "tailwindcss": "^3.4.0",
    "typescript": "^5.5.0",
    "vite": "^5.4.0",
    "vue-tsc": "^2.0.0"
  }
}
```

Install dependencies:
```bash
npm install
```

### Step 2: Configure Vite

Create `vite.config.js`:

```javascript
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: 'public',
        manifest: true,
        rollupOptions: {
            input: 'resources/js/app.ts',
            output: {
                entryFileNames: 'app.js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name.endsWith('.css')) {
                        return 'app.css';
                    }
                    return 'assets/[name]-[hash][extname]';
                },
            },
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, './resources/js'),
        },
    },
});
```

### Step 3: Configure Tailwind CSS

Create `tailwind.config.js`:

```javascript
/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: ['class'],
    content: [
        './resources/**/*.{vue,js,ts,jsx,tsx}',
        './resources/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',
                primary: {
                    DEFAULT: 'hsl(var(--primary))',
                    foreground: 'hsl(var(--primary-foreground))',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive))',
                    foreground: 'hsl(var(--destructive-foreground))',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted))',
                    foreground: 'hsl(var(--muted-foreground))',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent))',
                    foreground: 'hsl(var(--accent-foreground))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover))',
                    foreground: 'hsl(var(--popover-foreground))',
                },
                card: {
                    DEFAULT: 'hsl(var(--card))',
                    foreground: 'hsl(var(--card-foreground))',
                },
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },
        },
    },
    plugins: [require('tailwindcss-animate')],
};
```

Create `postcss.config.js`:

```javascript
module.exports = {
    plugins: {
        tailwindcss: {},
        autoprefixer: {},
    },
};
```

### Step 4: Set Up shadcn-vue

Install shadcn-vue CLI:
```bash
npx shadcn-vue@latest init
```

Answer the prompts:
- TypeScript: Yes
- Framework: Vite
- Style: Default
- Base color: Slate
- CSS variables: Yes
- Tailwind config: tailwind.config.js
- Components location: resources/js/components
- Utils location: resources/js/lib/utils.ts

Install essential components:
```bash
npx shadcn-vue@latest add button
npx shadcn-vue@latest add card
npx shadcn-vue@latest add table
npx shadcn-vue@latest add dialog
npx shadcn-vue@latest add input
npx shadcn-vue@latest add label
npx shadcn-vue@latest add select
npx shadcn-vue@latest add badge
npx shadcn-vue@latest add alert
npx shadcn-vue@latest add toast
npx shadcn-vue@latest add dropdown-menu
```

### Step 5: Create Directory Structure

```
resources/
├── js/
│   ├── app.ts
│   ├── router/
│   │   └── index.ts
│   ├── views/
│   │   ├── Dashboard.vue
│   │   ├── Roles/
│   │   │   ├── RolesList.vue
│   │   │   ├── RoleForm.vue
│   │   │   └── RolePermissions.vue
│   │   ├── Permissions/
│   │   │   ├── PermissionsList.vue
│   │   │   └── PermissionForm.vue
│   │   └── Users/
│   │       ├── UsersList.vue
│   │       └── UserAssignments.vue
│   ├── components/
│   │   ├── Layout.vue
│   │   ├── Sidebar.vue
│   │   ├── Header.vue
│   │   └── ui/  # shadcn-vue components
│   ├── composables/
│   │   ├── useRoles.ts
│   │   ├── usePermissions.ts
│   │   └── useUsers.ts
│   ├── lib/
│   │   ├── utils.ts
│   │   └── api.ts
│   └── types/
│       └── index.ts
└── css/
    └── app.css
```

### Step 6: Core Files

#### `resources/css/app.css`
```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
  :root {
    --background: 0 0% 100%;
    --foreground: 222.2 84% 4.9%;
    --card: 0 0% 100%;
    --card-foreground: 222.2 84% 4.9%;
    --popover: 0 0% 100%;
    --popover-foreground: 222.2 84% 4.9%;
    --primary: 222.2 47.4% 11.2%;
    --primary-foreground: 210 40% 98%;
    --secondary: 210 40% 96.1%;
    --secondary-foreground: 222.2 47.4% 11.2%;
    --muted: 210 40% 96.1%;
    --muted-foreground: 215.4 16.3% 46.9%;
    --accent: 210 40% 96.1%;
    --accent-foreground: 222.2 47.4% 11.2%;
    --destructive: 0 84.2% 60.2%;
    --destructive-foreground: 210 40% 98%;
    --border: 214.3 31.8% 91.4%;
    --input: 214.3 31.8% 91.4%;
    --ring: 222.2 84% 4.9%;
    --radius: 0.5rem;
  }

  .dark {
    --background: 222.2 84% 4.9%;
    --foreground: 210 40% 98%;
    --card: 222.2 84% 4.9%;
    --card-foreground: 210 40% 98%;
    --popover: 222.2 84% 4.9%;
    --popover-foreground: 210 40% 98%;
    --primary: 210 40% 98%;
    --primary-foreground: 222.2 47.4% 11.2%;
    --secondary: 217.2 32.6% 17.5%;
    --secondary-foreground: 210 40% 98%;
    --muted: 217.2 32.6% 17.5%;
    --muted-foreground: 215 20.2% 65.1%;
    --accent: 217.2 32.6% 17.5%;
    --accent-foreground: 210 40% 98%;
    --destructive: 0 62.8% 30.6%;
    --destructive-foreground: 210 40% 98%;
    --border: 217.2 32.6% 17.5%;
    --input: 217.2 32.6% 17.5%;
    --ring: 212.7 26.8% 83.9%;
  }
}

@layer base {
  * {
    @apply border-border;
  }
  body {
    @apply bg-background text-foreground;
  }
}
```

#### `resources/js/app.ts`
```typescript
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './components/Layout.vue';
import routes from './router';
import '../css/app.css';

const router = createRouter({
    history: createWebHistory('/permissions-manager'),
    routes,
});

const app = createApp(App);
app.use(router);
app.mount('#app');
```

#### `resources/js/lib/api.ts`
```typescript
import axios from 'axios';

const api = axios.create({
    baseURL: '/permissions-manager/api',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
});

// Add CSRF token to requests
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    api.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
}

export default api;
```

#### `resources/js/types/index.ts`
```typescript
export interface Role {
    id: number;
    name: string;
    display_name: string | null;
    created_at: string;
    updated_at: string;
    permissions?: Permission[];
    users_count?: number;
}

export interface Permission {
    id: number;
    name: string;
    display_name: string | null;
    created_at: string;
    updated_at: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
    updated_at: string;
    roles?: Role[];
    direct_permissions?: Permission[];
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
```

#### `resources/js/router/index.ts`
```typescript
import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        name: 'dashboard',
        component: () => import('../views/Dashboard.vue'),
    },
    {
        path: '/roles',
        name: 'roles',
        component: () => import('../views/Roles/RolesList.vue'),
    },
    {
        path: '/permissions',
        name: 'permissions',
        component: () => import('../views/Permissions/PermissionsList.vue'),
    },
    {
        path: '/users',
        name: 'users',
        component: () => import('../views/Users/UsersList.vue'),
    },
];

export default routes;
```

### Step 7: Create Vue Components

Refer to Laravel Telescope's Vue components for inspiration. Key components needed:

1. **Layout.vue** - Main app wrapper with sidebar navigation
2. **Dashboard.vue** - Overview with stats cards
3. **RolesList.vue** - Table of roles with CRUD actions
4. **RoleForm.vue** - Form for creating/editing roles
5. **PermissionsList.vue** - Table of permissions
6. **UsersList.vue** - Table of users with role/permission assignment

### Step 8: Build Assets

```bash
# Development
npm run dev

# Production
npm run build
```

### Step 9: Publish Assets

```bash
php artisan vendor:publish --tag=permissions-assets
```

---

## 📚 Reference Resources

- **Laravel Telescope**: https://github.com/laravel/telescope
  - See `resources/js` for Vue component structure
  - Check `vite.config.js` for build configuration

- **shadcn-vue Documentation**: https://www.shadcn-vue.com
  - Component library usage
  - Theme customization

- **Filament Shield**: https://github.com/bezhanSalleh/filament-shield
  - UI patterns for permission management
  - Role assignment workflows

---

## 🎨 UI Features to Implement

### Roles Management
- [ ] List all roles with permissions count
- [ ] Create new role with permission selection
- [ ] Edit role and its permissions
- [ ] Delete role (with confirmation)
- [ ] View users assigned to role

### Permissions Management
- [ ] List all permissions
- [ ] Create new permission
- [ ] Edit permission
- [ ] Delete permission (with confirmation)
- [ ] View which roles have this permission

### Users Management
- [ ] List all users with their roles
- [ ] Assign/remove roles from users
- [ ] Assign/remove direct permissions to users
- [ ] View all permissions for a user (direct + via roles)

### Dashboard
- [ ] Total roles count card
- [ ] Total permissions count card
- [ ] Total users count card
- [ ] Recent activity feed
- [ ] Quick actions

---

## 🔒 Security Considerations

1. Add middleware to protect routes (already configured)
2. Add authorization checks in controllers
3. Validate all inputs on both frontend and backend
4. Use CSRF protection (already included)
5. Consider adding rate limiting to API endpoints

---

## 📦 Final Steps

1. Complete all Vue components
2. Run `npm run build`
3. Publish assets: `php artisan vendor:publish --tag=permissions-assets`
4. Access UI at: `http://yourapp.test/permissions-manager`

---

## Need Help?

This is a complex implementation. Consider:
- Breaking it into smaller tasks
- Starting with one section (e.g., Roles) and completing it fully
- Using Vue Devtools for debugging
- Testing API endpoints with Postman first
