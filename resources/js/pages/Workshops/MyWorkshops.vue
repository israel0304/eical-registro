<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Calendar, MapPin, Clock, User } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    workshops: any[];
}>();

const canCancel = (workshop: any) => {
    if (workshop.has_attendance) return false;
    const start = new Date(workshop.day + 'T' + workshop.start_time);
    const now = new Date();
    const diffMs = start.getTime() - now.getTime();
    if (diffMs <= 0) return false;
    const diffMin = diffMs / 60000;
    return diffMin > 10;
};

const cancelEnrollment = (workshopId: number) => {
    if (confirm('¿Estás seguro de cancelar tu inscripción en este taller?')) {
        router.delete('/workshops/' + workshopId + '/unenroll');
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Mis Talleres', href: '/my-workshops' }]">
        <Head title="Mis Talleres" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1 class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white">
                Mis Talleres Inscritos
            </h1>

            <div v-if="workshops && workshops.length > 0" class="grid gap-4">
                <div
                    v-for="workshop in workshops"
                    :key="workshop.id"
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ workshop.name }}
                            </h3>
                            <p v-if="workshop.description" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ workshop.description }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400">
                                <div class="flex items-center gap-1.5">
                                    <User class="h-4 w-4" />
                                    {{ workshop.instructor_name }}
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <MapPin class="h-4 w-4" />
                                    {{ workshop.location }}
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <Calendar class="h-4 w-4" />
                                    {{ workshop.day }}
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <Clock class="h-4 w-4" />
                                    {{ workshop.start_time }} - {{ workshop.end_time }}
                                </div>
                            </div>
                        </div>
                        <button
                            v-if="canCancel(workshop)"
                            @click="cancelEnrollment(workshop.id)"
                            class="shrink-0 rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-100 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-400"
                        >
                            Cancelar inscripción
                        </button>
                        <span
                            v-else
                            class="shrink-0 rounded-md bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-500 dark:bg-zinc-800 dark:text-zinc-400"
                        >
                            {{ workshop.has_attendance ? 'Asistencia confirmada' : 'No se puede cancelar' }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-gray-500 dark:text-gray-400">No estás inscrito en ningún taller.</p>
                <router-link
                    href="/workshops"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    Ver talleres disponibles
                </router-link>
            </div>
        </div>
    </AppLayout>
</template>
