<script setup lang="ts">
import { useColorMode } from '@vueuse/core';
import { Monitor, Moon, ShieldCheck, Sun, Users } from 'lucide-vue-next';
import { computed } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarRail,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';

import 'vue-sonner/style.css';

const colorMode = useColorMode({
    emitAuto: true,
    modes: {
        light: 'light',
        dark: 'dark',
        auto: 'auto',
    },
});

const modes = ['light', 'dark', 'auto'] as const;

const cycleMode = () => {
    const currentIndex = modes.indexOf(colorMode.value as (typeof modes)[number]);
    const nextIndex = (currentIndex + 1) % modes.length;
    colorMode.value = modes[nextIndex];
};

const currentIcon = computed(() => {
    switch (colorMode.value) {
        case 'light':
            return Sun;
        case 'dark':
            return Moon;
        default:
            return Monitor;
    }
});

const modeLabel = computed(() => {
    switch (colorMode.value) {
        case 'light':
            return 'Light';
        case 'dark':
            return 'Dark';
        default:
            return 'Auto';
    }
});
</script>

<template>
    <SidebarProvider>
        <Sidebar>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupLabel>Permission Manager</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child>
                                    <router-link :to="{ name: 'roles' }">
                                        <ShieldCheck class="size-4" />
                                        <span>Roles</span>
                                    </router-link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child>
                                    <router-link :to="{ name: 'users' }">
                                        <Users class="size-4" />
                                        <span>Users</span>
                                    </router-link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>
            <SidebarFooter />
            <SidebarRail />
        </Sidebar>
        <SidebarInset>
            <header
                class="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12"
            >
                <div class="flex items-center gap-2 px-4">
                    <SidebarTrigger class="-ml-1" />
                </div>
                <div class="ml-auto px-4">
                    <Button variant="ghost" size="icon" @click="cycleMode" :title="`Theme: ${modeLabel}`">
                        <component :is="currentIcon" class="size-4" />
                        <span class="sr-only"> Toggle theme ({{ modeLabel }}) </span>
                    </Button>
                </div>
            </header>
            <div class="container mx-auto flex min-h-[100vh] flex-1 flex-col gap-4 p-4 pt-0">
                <router-view />
            </div>
            <Toaster position="top-right" />
        </SidebarInset>
    </SidebarProvider>
</template>
