<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Home,
    Users,
    BookOpen,
    Mic,
    CalendarCheck,
    Award,
    BarChart3,
} from 'lucide-vue-next';
import { computed } from 'vue';
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
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

const page = usePage();

const hasRole = (name: string) =>
    page.props.auth.user?.roles?.some((r: any) => r.name === name) ?? false;

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Inicio', href: dashboard(), icon: Home },
    ];

    if (hasRole('Administrator')) {
        items.push(
            { title: 'Usuarios', href: '/users', icon: Users },
            { title: 'Talleres', href: '/workshops', icon: BookOpen },
            { title: 'Ponencias', href: '/presentations', icon: Mic },
            { title: 'Reportes', href: '/admin/reportes', icon: BarChart3 },
        );
    }

    if (hasRole('Ponente')) {
        items.push({
            title: 'Mis Ponencias',
            href: '/presentations',
            icon: Mic,
        });
    }

    const canSeeWorkshops =
        hasRole('Ponente') || hasRole('Asistente') || hasRole('Instructor');
    if (canSeeWorkshops) {
        items.push(
            { title: 'Talleres', href: '/workshops', icon: BookOpen },
            {
                title: 'Mis Talleres',
                href: '/my-workshops',
                icon: CalendarCheck,
            },
            { title: 'Mis Constancias', href: '/constancias', icon: Award },
        );
    }

    return items;
});
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
