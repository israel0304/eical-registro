<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Users, Mic, BookOpen, ClipboardCheck, BarChart3, FileText } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    stats: any;
}>();

const reportLinks = [
    { title: 'Asistencia por Taller', href: '/admin/reportes/asistencia-taller', icon: BookOpen, description: 'Lista de asistentes por taller con estado' },
    { title: 'Asistencia General', href: '/admin/reportes/asistencia-general', icon: Users, description: 'Listado completo de asistentes' },
    { title: 'Asistencia a Ponencias', href: '/admin/reportes/asistencia-ponencias', icon: Mic, description: 'Quién asistió a cada ponencia' },
    { title: 'Resumen General', href: '/admin/reportes/resumen', icon: FileText, description: 'Totales e indicadores clave' },
    { title: 'Ocupación de Talleres', href: '/admin/reportes/ocupacion', icon: BarChart3, description: 'Inscritos vs capacidad' },
    { title: 'Estadísticas', href: '/admin/reportes/estadisticas', icon: ClipboardCheck, description: 'Gráficas y métricas' },
];
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reportes', href: '/admin/reportes' }]">
        <Head title="Reportes" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1 class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white">
                Reportes y Estadísticas
            </h1>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_asistentes }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Asistentes</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_ponentes }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Ponentes</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_talleres }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Talleres</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_ponencias }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Ponencias</div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="report in reportLinks"
                    :key="report.href"
                    :href="report.href"
                    class="group rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-700"
                >
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-indigo-100 transition-colors group-hover:bg-indigo-200 dark:bg-indigo-900/30 dark:group-hover:bg-indigo-900/50">
                            <component :is="report.icon" class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ report.title }}</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ report.description }}</p>
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
