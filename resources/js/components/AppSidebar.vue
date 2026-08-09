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
    Presentation,
    Tags,
    ShieldCheck,
    BadgeCheck,
    ScanLine,
    CalendarDays,
    LayoutTemplate,
    ClipboardCheck,
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

const page = usePage();

const can = (permission: string) =>
    (page.props.auth.permissions as string[] | undefined)?.includes(
        permission,
    ) ?? false;

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (can('dashboard.view')) {
        items.push({ title: 'Inicio', href: dashboard(), icon: Home });
    }

    if (can('users.view')) {
        items.push({ title: 'Usuarios', href: '/users', icon: Users });
    }

    if (can('workshops.view')) {
        items.push({ title: 'Talleres', href: '/workshops', icon: BookOpen });
    }

    if (can('presentations.view')) {
        items.push({
            title: can('presentations.my') ? 'Mis Ponencias' : 'Ponencias',
            href: '/presentations',
            icon: Mic,
        });
    }

    if (can('conferences.view')) {
        items.push({
            title: 'Conferencias',
            href: '/conferences',
            icon: Presentation,
        });
    }

    if (can('workshops.my')) {
        items.push({
            title: 'Mis Talleres',
            href: '/my-workshops',
            icon: CalendarCheck,
        });
    }

    if (can('reportes.view')) {
        items.push({
            title: 'Reportes',
            href: '/admin/reportes',
            icon: BarChart3,
        });
    }

    if (can('constancias.view')) {
        items.push({
            title: 'Mis Constancias',
            href: '/constancias',
            icon: Award,
        });
    }

    if (can('gafete.view')) {
        items.push({ title: 'Mi Gafete', href: '/gafete', icon: BadgeCheck });
    }

    if (can('asignaciones.view')) {
        items.push({
            title: 'Mis Asignaciones',
            href: '/mis-asignaciones',
            icon: ClipboardCheck,
        });
    }

    if (can('checkin.scan')) {
        items.push({ title: 'Check-in', href: '/checkin', icon: ScanLine });
    }

    if (can('constancias.evento.manage')) {
        items.push({
            title: 'Evento',
            href: '/admin/evento',
            icon: CalendarDays,
        });
    }

    if (
        can('gafete.templates.manage') ||
        can('constancias.templates.manage') ||
        can('correos.templates.manage')
    ) {
        items.push({
            title: 'Plantillas',
            href: '/admin/plantillas',
            icon: LayoutTemplate,
        });
    }

    if (can('constancias.types.manage')) {
        items.push({
            title: 'Tipos',
            href: '/admin/constancias/tipos',
            icon: Tags,
        });
    }

    if (can('roles.manage')) {
        items.push({ title: 'Roles', href: '/admin/roles', icon: ShieldCheck });
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
                            <img
                                src="/images/logo-eical.png"
                                class="h-8 w-auto"
                                alt="Registro EICAL"
                            />
                            <span class="truncate text-sm font-semibold">
                                Registro EICAL
                            </span>
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
