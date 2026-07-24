<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Search, Eye } from 'lucide-vue-next';
import { watch } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    presentations: {
        data: any[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
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
            formFilters.get('/presentations', {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);
    },
);
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Ponencias', href: '/presentations' }]">
        <Head title="Ponencias" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1
                class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Ponencias
            </h1>

            <div
                class="mt-6 flex flex-col justify-between gap-4 xl:flex-row xl:items-end"
            >
                <div class="flex flex-1 flex-col gap-4 sm:flex-row">
                    <div class="flex flex-col">
                        <label
                            class="text-[11px] font-medium tracking-wide text-gray-600 uppercase dark:text-gray-400"
                        >
                            Buscar
                        </label>
                        <div class="relative w-full sm:w-64">
                            <Search
                                class="absolute top-[11px] left-3 h-4 w-4 text-gray-500"
                            />
                            <input
                                v-model="formFilters.search"
                                type="text"
                                class="w-full rounded-md border border-gray-300 py-2 pr-4 pl-9 shadow-sm focus:border-black focus:ring-1 focus:ring-black sm:text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                placeholder="Título o disciplina"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="mt-6 rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800"
                    >
                        <thead
                            class="border-b bg-white dark:border-zinc-800 dark:bg-zinc-900"
                        >
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Título
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Autores
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Disciplina
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Horario
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Lugar
                                </th>
                                <th scope="col" class="relative px-6 py-4">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900"
                        >
                            <tr
                                v-for="presentation in presentations.data"
                                :key="presentation.id"
                                class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-zinc-800"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="max-w-xs truncate text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ presentation.title }}
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    <div
                                        v-for="author in presentation.authors?.slice(
                                            0,
                                            2,
                                        )"
                                        :key="author.id"
                                        class="text-xs"
                                    >
                                        {{ author.first_name }}
                                        {{ author.last_name }}
                                    </div>
                                    <div
                                        v-if="presentation.authors?.length > 2"
                                        class="text-xs text-gray-400"
                                    >
                                        +{{ presentation.authors.length - 2 }}
                                        más
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ presentation.discipline || '-' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    <div v-if="presentation.day">
                                        {{ presentation.day }}
                                        <div class="text-xs text-gray-400">
                                            {{ presentation.start_time }} -
                                            {{ presentation.end_time }}
                                        </div>
                                    </div>
                                    <span v-else class="text-gray-400"
                                        >Sin asignar</span
                                    >
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ presentation.location || 'Sin asignar' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Link
                                            :href="
                                                '/presentations/' +
                                                presentation.id
                                            "
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-if="
                                    !presentations?.data ||
                                    presentations.data.length === 0
                                "
                            >
                                <td
                                    colspan="6"
                                    class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
                                >
                                    No se encontraron ponencias.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex justify-end gap-4 border-t border-gray-100 px-6 py-4 text-sm font-medium dark:border-zinc-800"
                    v-if="presentations.total > 0"
                >
                    <Link
                        v-if="presentations.prev_page_url"
                        :href="presentations.prev_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                        >Anterior</Link
                    >
                    <span v-else class="text-gray-400 dark:text-zinc-600"
                        >Anterior</span
                    >
                    <Link
                        v-if="presentations.next_page_url"
                        :href="presentations.next_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                        >Siguiente</Link
                    >
                    <span v-else class="text-gray-400 dark:text-zinc-600"
                        >Siguiente</span
                    >
                </div>
            </div>
        </div>
    </AppLayout>
</template>
