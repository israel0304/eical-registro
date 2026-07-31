<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Download, Award, BookOpen } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const page = usePage();

defineProps<{
    completedWorkshops: any[];
    presentationCertificates?: any[];
    user: any;
}>();

const hasRole = (name: string) =>
    page.props.auth.user?.roles?.some((r: any) => r.name === name) ?? false;
const isAdmin = computed(() => hasRole('Administrator'));
const isPonente = computed(() => hasRole('Ponente'));
const canSeePonencias = computed(() => isAdmin.value || isPonente.value);

const downloadCertificate = (workshopId: number) => {
    window.open('/constancias/' + workshopId + '/download', '_blank');
};

const downloadPonencia = (presentationId: number) => {
    window.open(
        '/constancias/ponencia/' + presentationId + '/download',
        '_blank',
    );
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[{ title: 'Mis Constancias', href: '/constancias' }]"
    >
        <Head title="Mis Constancias" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1
                class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Mis Constancias
            </h1>

            <!-- Talleres -->
            <div
                v-if="completedWorkshops && completedWorkshops.length > 0"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="workshop in completedWorkshops"
                    :key="workshop.id"
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30"
                        >
                            <Award
                                class="h-6 w-6 text-indigo-600 dark:text-indigo-400"
                            />
                        </div>
                        <div class="flex-1">
                            <h3
                                class="text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{ workshop.name }}
                            </h3>
                            <p
                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ workshop.instructor_name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ workshop.day }} | {{ workshop.location }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button
                            @click="downloadCertificate(workshop.id)"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 transition-colors hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300"
                        >
                            <Download class="h-4 w-4" /> Descargar Constancia
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ponencias (solo ponente y admin) -->
            <div v-if="canSeePonencias && presentationCertificates?.length">
                <h2
                    class="mb-4 text-xl font-normal tracking-tight text-gray-800 dark:text-gray-200"
                >
                    Constancias de Ponencia
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="presentation in presentationCertificates"
                        :key="presentation.id"
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30"
                            >
                                <BookOpen
                                    class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                                />
                            </div>
                            <div class="flex-1">
                                <h3
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ presentation.title }}
                                </h3>
                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ presentation.discipline || '—' }}
                                </p>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ presentation.day || '—' }}
                                    {{
                                        presentation.day &&
                                        presentation.location
                                            ? '|'
                                            : ''
                                    }}
                                    {{ presentation.location || '' }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button
                                @click="downloadPonencia(presentation.id)"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 transition-colors hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300"
                            >
                                <Download class="h-4 w-4" /> Descargar
                                Constancia
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="
                    (!completedWorkshops || completedWorkshops.length === 0) &&
                    (!canSeePonencias || !presentationCertificates?.length)
                "
                class="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <Award
                    class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600"
                />
                <p class="mt-4 text-gray-500 dark:text-gray-400">
                    Aún no tienes constancias disponibles.
                </p>
                <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
                    Las constancias se generan cuando completas un taller
                    (asistencia verificada) o presentas una ponencia.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
