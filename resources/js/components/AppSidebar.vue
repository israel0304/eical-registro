<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Home, Users, Store, Package, ClipboardList, CalendarCheck, ScanBarcode, LayoutTemplate, FileDown, BarChart, Settings, Folder, BookOpen } from 'lucide-vue-next';
import { computed } from 'vue';
import NavFooter from '@/components/NavFooter.vue';
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

const role_id = computed(() => page.props.auth.user?.role_id || 1); // fallback to 1 (Admin) for dev testing if missing

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Inicio', href: dashboard(), icon: Home },
    ];

    if (role_id.value === 1) { // Administrador
        items.push(
            { title: 'Usuarios', href: '/users', icon: Users },
            { title: 'Stands', href: '#', icon: Store },
            { title: 'Registro de inventario', href: '#', icon: Package },
            { title: 'Solicitud de material', href: '#', icon: ClipboardList },
            { title: 'Registro de Asistencia', href: '#', icon: CalendarCheck },
            { title: 'Entrega de material', href: '#', icon: ScanBarcode },
            { title: 'Plantillas', href: '#', icon: LayoutTemplate },
            { title: 'Descargas', href: '#', icon: FileDown },
            { title: 'Reportes', href: '#', icon: BarChart },
            { title: 'Ajustes', href: '#', icon: Settings }
        );
    } else if (role_id.value === 2) { // Staff
        items.push(
            { title: 'Registro de Asistencia', href: '#', icon: CalendarCheck },
            { title: 'Entrega de material', href: '#', icon: ScanBarcode }
        );
    } else if (role_id.value === 3) { // Tallerista
        items.push(
            { title: 'Stands', href: '#', icon: Store },
            { title: 'Solicitud de material', href: '#', icon: ClipboardList },
            { title: 'Descargas', href: '#', icon: FileDown }
        );
    } else if (role_id.value === 4) { // Participante
        items.push(
            { title: 'Stands', href: '#', icon: Store },
            { title: 'Descargas', href: '#', icon: FileDown }
        );
    }

    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
