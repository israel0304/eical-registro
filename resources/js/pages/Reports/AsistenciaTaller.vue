<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    workshops: any[];
}>();
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Reportes', href: '/admin/reportes' },
            {
                title: 'Asistencia por Taller',
                href: '/admin/reportes/asistencia-taller',
            },
        ]"
    >
        <Head title="Asistencia por Taller" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <div class="flex items-center justify-between">
                <Link
                    href="/admin/reportes"
                    class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                >
                    <ArrowLeft class="h-4 w-4" /> Volver
                </Link>
                <a
                    href="/admin/reportes/export/asistencia-taller"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    <Download class="h-4 w-4" /> Exportar CSV
                </a>
            </div>

            <h1
                class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Asistencia por Taller
            </h1>

            <div
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800"
                    >
                        <thead class="bg-gray-50 dark:bg-zinc-800">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase"
                                >
                                    Taller
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase"
                                >
                                    Capacidad
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase"
                                >
                                    Inscritos
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase"
                                >
                                    Asistieron
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase"
                                >
                                    Ocupación
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900"
                        >
                            <tr
                                v-for="workshop in workshops"
                                :key="workshop.id"
                                class="hover:bg-gray-50 dark:hover:bg-zinc-800"
                            >
                                <td
                                    class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{ workshop.name }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ workshop.capacity }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ workshop.total_inscritos }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ workshop.total_asistieron }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="h-2 w-24 rounded-full bg-gray-200 dark:bg-zinc-700"
                                        >
                                            <div
                                                class="h-2 rounded-full bg-indigo-600"
                                                :style="{
                                                    width:
                                                        Math.min(
                                                            (workshop.total_inscritos /
                                                                workshop.capacity) *
                                                                100,
                                                            100,
                                                        ) + '%',
                                                }"
                                            ></div>
                                        </div>
                                        <span class="text-xs text-gray-500"
                                            >{{
                                                Math.round(
                                                    (workshop.total_inscritos /
                                                        workshop.capacity) *
                                                        100,
                                                )
                                            }}%</span
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
