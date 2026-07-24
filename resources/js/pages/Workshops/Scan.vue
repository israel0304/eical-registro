<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle, XCircle, ArrowLeft } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    success: boolean;
    message: string;
    workshop: {
        name: string;
    };
}>();
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Talleres', href: '/workshops' },
            { title: 'Asistencia', href: '#' },
        ]"
    >
        <Head title="Asistencia - Taller" />

        <div class="mx-auto min-h-screen w-full max-w-lg px-8 py-16">
            <div
                class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div v-if="success" class="flex flex-col items-center gap-4">
                    <CheckCircle class="h-16 w-16 text-green-500" />
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        Asistencia registrada
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ message }}
                    </p>
                    <div
                        class="mt-2 rounded-lg bg-gray-50 px-4 py-3 dark:bg-zinc-800"
                    >
                        <p
                            class="text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{ workshop.name }}
                        </p>
                    </div>
                </div>

                <div v-else class="flex flex-col items-center gap-4">
                    <XCircle class="h-16 w-16 text-red-500" />
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        No se pudo registrar
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ message }}
                    </p>
                    <div
                        class="mt-2 rounded-lg bg-gray-50 px-4 py-3 dark:bg-zinc-800"
                    >
                        <p
                            class="text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{ workshop.name }}
                        </p>
                    </div>
                </div>

                <Link
                    href="/workshops"
                    class="mt-6 inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                >
                    <ArrowLeft class="h-4 w-4" /> Volver a talleres
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
