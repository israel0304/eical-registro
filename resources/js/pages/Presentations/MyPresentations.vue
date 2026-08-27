<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Pencil } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    presentations: any[];
}>();

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[{ title: 'Mis Ponencias', href: '/my-presentations' }]"
    >
        <Head title="Mis Ponencias" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1
                class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Mis Ponencias
            </h1>

            <div
                v-if="presentations && presentations.length > 0"
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
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
                                    Titulo
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
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Moderador
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
                                v-for="presentation in presentations"
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
                                    <div v-if="presentation.day">
                                        {{ formatDate(presentation.day) }}
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
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    <div
                                        v-if="presentation.moderators?.length"
                                        class="flex items-center -space-x-2"
                                    >
                                        <span
                                            v-for="mod in presentation.moderators.slice(
                                                0,
                                                3,
                                            )"
                                            :key="mod.id"
                                            :title="
                                                mod.name +
                                                (mod.affiliation
                                                    ? ' — ' + mod.affiliation
                                                    : '')
                                            "
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-xs font-medium text-amber-700 ring-2 ring-white dark:bg-amber-900 dark:text-amber-300 dark:ring-zinc-900"
                                        >
                                            {{ mod.first_name?.[0]
                                            }}{{ mod.last_name?.[0] }}
                                        </span>
                                        <span
                                            v-if="
                                                presentation.moderators.length >
                                                3
                                            "
                                            :title="
                                                presentation.moderators
                                                    .map((m: any) => m.name)
                                                    .join(', ')
                                            "
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-xs font-medium text-gray-600 ring-2 ring-white dark:bg-zinc-700 dark:text-gray-300 dark:ring-zinc-900"
                                        >
                                            +{{
                                                presentation.moderators.length -
                                                3
                                            }}
                                        </span>
                                    </div>
                                    <span v-else class="text-gray-400">—</span>
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
                                        <Link
                                            :href="
                                                '/presentations/' +
                                                presentation.id
                                            "
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-else
                class="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <p class="text-gray-500 dark:text-gray-400">
                    No tienes ponencias asignadas.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
