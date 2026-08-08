<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calendar, Clock, MapPin, Eye, CheckCircle2 } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    assignments: {
        id: number;
        title: string;
        type: string;
        location: string | null;
        day: string | null;
        start_time: string | null;
        end_time: string | null;
        url: string;
    }[];
}>();

const formatDate = (dateStr: string | null) => {
    if (!dateStr) return 'Fecha por definir';
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const badgeColor = (type: string) => {
    switch (type) {
        case 'Taller':
            return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300';
        case 'Ponencia':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'Conferencia':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-zinc-800 dark:text-gray-300';
    }
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Mis Asignaciones', href: '/mis-asignaciones' },
        ]"
    >
        <Head title="Mis Asignaciones" />

        <div
            class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-4 py-6 sm:space-y-8 sm:px-8 sm:py-8"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <h1
                        class="text-2xl font-normal tracking-tight text-gray-900 sm:text-3xl dark:text-white"
                    >
                        Mis Asignaciones
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Listado de actividades en las que has sido asignado como
                        moderador.
                    </p>
                </div>
            </div>

            <div v-if="assignments.length > 0" class="space-y-4">
                <div
                    v-for="item in assignments"
                    :key="item.type + '-' + item.id"
                    class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span
                                :class="[
                                    'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    badgeColor(item.type),
                                ]"
                            >
                                {{ item.type }}
                            </span>
                            <span
                                class="text-xs text-gray-400 dark:text-gray-500"
                            >
                                ID: {{ item.id }}
                            </span>
                        </div>
                        <h2
                            class="text-base font-semibold text-gray-900 dark:text-white"
                        >
                            {{ item.title }}
                        </h2>
                        <div
                            class="flex flex-wrap items-center gap-x-6 gap-y-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            <div class="flex items-center gap-1.5">
                                <Calendar class="h-3.5 w-3.5 text-gray-400" />
                                {{ formatDate(item.day) }}
                            </div>
                            <div
                                v-if="item.start_time"
                                class="flex items-center gap-1.5"
                            >
                                <Clock class="h-3.5 w-3.5 text-gray-400" />
                                {{ item.start_time
                                }}{{
                                    item.end_time ? ' - ' + item.end_time : ''
                                }}
                            </div>
                            <div
                                v-if="item.location"
                                class="flex items-center gap-1.5"
                            >
                                <MapPin class="h-3.5 w-3.5 text-gray-400" />
                                {{ item.location }}
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Link
                            :href="item.url"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-700"
                        >
                            <Eye class="h-4 w-4" />
                            Ver actividad
                        </Link>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <CheckCircle2
                    class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600"
                />
                <p class="mt-4 text-gray-500 dark:text-gray-400">
                    No tienes actividades asignadas como moderador actualmente.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
