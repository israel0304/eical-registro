<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Mic, Users } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

interface Moderator {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string;
    affiliation: string | null;
    activated: boolean;
    activated_at: string | null;
    conference_count: number;
    conference_titles: string[];
    folio: string | null;
}

defineProps<{
    moderators: Moderator[];
}>();

const toggle = (moderator: Moderator) => {
    router.post(
        '/admin/constancias/moderadores/' + moderator.id + '/activar',
        {},
        {
            preserveScroll: true,
        },
    );
};

const download = (moderator: Moderator) => {
    window.open(
        '/admin/constancias/moderadores/' + moderator.id + '/constancia',
        '_blank',
    );
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Constancias', href: '/constancias' },
            { title: 'Moderadores', href: '/admin/constancias/moderadores' },
        ]"
    >
        <Head title="Moderadores" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <div>
                <h1
                    class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                >
                    Moderadores
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Activa la constancia única de moderador. Cada moderador
                    recibe una sola constancia que lista todas las conferencias
                    que modera, sin importar cuántas sean.
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800"
                    >
                        <thead class="bg-gray-50 dark:bg-zinc-800/50">
                            <tr>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Moderador
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Conferencias
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Folio
                                </th>
                                <th
                                    class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300"
                                >
                                    Constancia
                                </th>
                                <th class="relative px-5 py-3">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-zinc-800"
                        >
                            <tr
                                v-for="moderator in moderators"
                                :key="moderator.id"
                            >
                                <td class="px-5 py-3">
                                    <div
                                        class="flex items-start gap-3"
                                    >
                                        <div
                                            class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-amber-100 text-sm font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                        >
                                            <Mic class="h-4 w-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <div
                                                class="text-sm font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ moderator.full_name }}
                                            </div>
                                            <div
                                                class="text-xs text-gray-400"
                                            >
                                                {{ moderator.email }}
                                            </div>
                                            <div
                                                v-if="
                                                    moderator.affiliation
                                                "
                                                class="text-xs text-gray-400"
                                            >
                                                {{
                                                    moderator.affiliation
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <span
                                        class="text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{ moderator.conference_count }}
                                    </span>
                                    <div
                                        v-if="
                                            moderator.conference_titles.length
                                        "
                                        class="mt-1 max-w-xs text-xs text-gray-400"
                                    >
                                        {{
                                            moderator.conference_titles.join(
                                                ' · ',
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <span
                                        v-if="moderator.folio"
                                        class="font-mono text-[11px] text-gray-500 dark:text-gray-400"
                                    >
                                        {{ moderator.folio }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400"
                                        >—</span
                                    >
                                </td>
                                <td class="px-5 py-3">
                                    <label
                                        class="inline-flex cursor-pointer items-center"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="moderator.activated"
                                            @change="toggle(moderator)"
                                            class="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                        />
                                        <span
                                            v-if="moderator.activated"
                                            class="ml-2 text-xs font-medium text-green-600 dark:text-green-400"
                                        >
                                            Activada
                                        </span>
                                        <span
                                            v-else
                                            class="ml-2 text-xs text-gray-400"
                                        >
                                            Pendiente
                                        </span>
                                    </label>
                                </td>
                                <td
                                    class="px-5 py-3 text-right whitespace-nowrap"
                                >
                                    <button
                                        @click="download(moderator)"
                                        class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-amber-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-amber-400"
                                        title="Descargar constancia"
                                    >
                                        <Download class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="moderators.length === 0">
                                <td
                                    colspan="5"
                                    class="px-5 py-12 text-center"
                                >
                                    <Users
                                        class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600"
                                    />
                                    <p
                                        class="mt-3 text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        Aún no hay moderadores asignados a
                                        conferencias.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>