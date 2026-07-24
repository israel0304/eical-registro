<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Download, Search } from 'lucide-vue-next';
import { watch } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    users: any;
    filters: any;
}>();

const formFilters = useForm({
    search: props.filters?.search || '',
});

let searchTimeout: ReturnType<typeof setTimeout>;
watch(
    () => formFilters.search,
    () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            formFilters.get('/admin/reportes/asistencia-general', {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);
    },
);
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Reportes', href: '/admin/reportes' },
            {
                title: 'Asistencia General',
                href: '/admin/reportes/asistencia-general',
            },
        ]"
    >
        <Head title="Asistencia General" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <div class="flex items-center justify-between">
                <Link
                    href="/admin/reportes"
                    class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                >
                    <ArrowLeft class="h-4 w-4" /> Volver
                </Link>
                <a
                    href="/admin/reportes/export/asistencia-general"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    <Download class="h-4 w-4" /> Exportar CSV
                </a>
            </div>

            <h1
                class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Asistencia General
            </h1>

            <div class="relative w-full sm:w-64">
                <Search
                    class="absolute top-[11px] left-3 h-4 w-4 text-gray-500"
                />
                <input
                    v-model="formFilters.search"
                    type="text"
                    class="w-full rounded-md border border-gray-300 py-2 pr-4 pl-9 shadow-sm focus:border-black focus:ring-1 focus:ring-black sm:text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    placeholder="Buscar por nombre, email o DNI"
                />
            </div>

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
                                    DNI
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase"
                                >
                                    Nombre
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase"
                                >
                                    Email
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500 uppercase"
                                >
                                    Días Asistidos
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900"
                        >
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="hover:bg-gray-50 dark:hover:bg-zinc-800"
                            >
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ user.dni }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{ user.first_name }} {{ user.last_name }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ user.email }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ user.attendances?.length || 0 }}
                                </td>
                            </tr>
                            <tr v-if="!users?.data || users.data.length === 0">
                                <td
                                    colspan="4"
                                    class="px-6 py-12 text-center text-gray-500"
                                >
                                    No se encontraron registros.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="flex justify-end gap-4 border-t border-gray-100 px-6 py-4 text-sm font-medium dark:border-zinc-800"
                    v-if="users.total > 0"
                >
                    <Link
                        v-if="users.prev_page_url"
                        :href="users.prev_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                        >Anterior</Link
                    >
                    <span v-else class="text-gray-400">Anterior</span>
                    <Link
                        v-if="users.next_page_url"
                        :href="users.next_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                        >Siguiente</Link
                    >
                    <span v-else class="text-gray-400">Siguiente</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
