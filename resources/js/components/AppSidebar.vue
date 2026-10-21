<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ClipboardCheck,
    Crosshair,
    LayoutGrid,
    Library,
    MapPin,
    ScanSearch,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as analysesIndex } from '@/routes/analyses';
import { index as findingsIndex } from '@/routes/findings';
import { index as referencesIndex } from '@/routes/reference-sources';
import { index as targetsIndex } from '@/routes/targets';
import { index as vectorsIndex } from '@/routes/vectors';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Vectors',
        href: vectorsIndex(),
        icon: Crosshair,
    },
    {
        title: 'Targets',
        href: targetsIndex(),
        icon: MapPin,
    },
    {
        title: 'Reference data',
        href: referencesIndex(),
        icon: Library,
    },
    ...(page.props.auth.user.is_admin
        ? [
              {
                  title: 'Analyses',
                  href: analysesIndex(),
                  icon: ScanSearch,
              },
              {
                  title: 'Review queue',
                  href: findingsIndex(),
                  icon: ClipboardCheck,
              },
          ]
        : []),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
