<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    stats: any;
}>();
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Reportes', href: '/admin/reportes' },
            { title: 'Resumen General', href: '/admin/reportes/resumen' },
        ]"
    >
        <Head title="Resumen General" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <div class="flex items-center justify-between">
                <Link
                    href="/admin/reportes"
                    class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                >
                    <ArrowLeft class="h-4 w-4" /> Volver
                </Link>
                <a
                    href="/admin/reportes/export/resumen"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    <Download class="h-4 w-4" /> Exportar CSV
                </a>
            </div>

            <h1
                class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Resumen General
            </h1>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div
                    v-for="(value, key) in stats"
                    :key="key"
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div
                        class="text-2xl font-bold text-gray-900 dark:text-white"
                    >
                        {{ value }}
                    </div>
                    <div
                        class="text-sm text-gray-500 capitalize dark:text-gray-400"
                    >
                        {{ key.replace(/_/g, ' ') }}
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
