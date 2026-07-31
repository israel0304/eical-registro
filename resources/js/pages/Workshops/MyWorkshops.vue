<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    workshops: any[];
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
        :breadcrumbs="[{ title: 'Mis Talleres', href: '/my-workshops' }]"
    >
        <Head title="Mis Talleres" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1
                class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Mis Talleres Inscritos
            </h1>

            <div
                v-if="workshops && workshops.length > 0"
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
                                    Taller
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 dark:text-gray-200"
                                >
                                    Instructor
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
                                v-for="workshop in workshops"
                                :key="workshop.id"
                                class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-zinc-800"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ workshop.name }}
                                    </div>
                                    <div
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ formatDate(workshop.day) }}
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    <div
                                        v-for="(
                                            instructor, idx
                                        ) in workshop.instructors"
                                        :key="instructor.id"
                                    >
                                        <div>{{ instructor.name }}</div>
                                        <div
                                            v-if="instructor.institution"
                                            class="text-xs text-gray-400"
                                        >
                                            {{ instructor.institution }}
                                        </div>
                                        <div
                                            v-if="
                                                idx <
                                                (workshop.instructors?.length ??
                                                    0) -
                                                    1
                                            "
                                            class="my-1 border-b border-gray-100 dark:border-zinc-800"
                                        ></div>
                                    </div>
                                    <div
                                        v-if="!workshop.instructors?.length"
                                        class="text-gray-400"
                                    >
                                        —
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ workshop.start_time }} -
                                    {{ workshop.end_time }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ workshop.location }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Link
                                            :href="
                                                '/workshops/' + workshop.id
                                            "
                                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Users class="h-4 w-4" />
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
                    No estás inscrito en ningún taller.
                </p>
                <Link
                    href="/workshops"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    Ver talleres disponibles
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
