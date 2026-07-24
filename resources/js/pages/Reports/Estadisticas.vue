<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    asistenciaPorDia: any[];
    ocupacionTalleres: any[];
    tasaCompletado: number;
}>();
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Reportes', href: '/admin/reportes' },
            { title: 'Estadísticas', href: '/admin/reportes/estadisticas' },
        ]"
    >
        <Head title="Estadísticas" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <Link
                href="/admin/reportes"
                class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4" /> Volver
            </Link>

            <h1
                class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Estadísticas
            </h1>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Asistencia por día -->
                <div
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Asistencia por Día
                    </h2>
                    <div class="space-y-3">
                        <div
                            v-for="day in asistenciaPorDia"
                            :key="day.event_day"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="w-16 text-sm text-gray-600 dark:text-gray-400"
                                >Día {{ day.event_day }}</span
                            >
                            <div
                                class="h-6 flex-1 rounded-full bg-gray-100 dark:bg-zinc-800"
                            >
                                <div
                                    class="flex h-6 items-center justify-end rounded-full bg-indigo-500 pr-2"
                                    :style="{
                                        width:
                                            Math.max(
                                                (day.total /
                                                    Math.max(
                                                        ...asistenciaPorDia.map(
                                                            (d: any) => d.total,
                                                        ),
                                                        1,
                                                    )) *
                                                    100,
                                                10,
                                            ) + '%',
                                    }"
                                >
                                    <span
                                        class="text-xs font-medium text-white"
                                        >{{ day.total }}</span
                                    >
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="
                                !asistenciaPorDia ||
                                asistenciaPorDia.length === 0
                            "
                            class="py-8 text-center text-gray-500"
                        >
                            No hay datos de asistencia
                        </div>
                    </div>
                </div>

                <!-- Ocupación talleres -->
                <div
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <h2
                        class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Ocupación de Talleres
                    </h2>
                    <div class="space-y-3">
                        <div
                            v-for="w in ocupacionTalleres"
                            :key="w.id"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="w-32 truncate text-sm text-gray-600 dark:text-gray-400"
                                :title="w.name"
                                >{{ w.name }}</span
                            >
                            <div
                                class="h-6 flex-1 rounded-full bg-gray-100 dark:bg-zinc-800"
                            >
                                <div
                                    class="flex h-6 items-center justify-end rounded-full pr-2"
                                    :class="
                                        w.capacity > 0 &&
                                        w.enrolled / w.capacity > 0.9
                                            ? 'bg-red-500'
                                            : 'bg-green-500'
                                    "
                                    :style="{
                                        width:
                                            Math.max(
                                                w.capacity > 0
                                                    ? (w.enrolled /
                                                          w.capacity) *
                                                          100
                                                    : 0,
                                                5,
                                            ) + '%',
                                    }"
                                >
                                    <span class="text-xs font-medium text-white"
                                        >{{ w.enrolled }}/{{ w.capacity }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tasa de completado -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <h2
                    class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Tasa de Inscripción
                </h2>
                <div class="flex items-center gap-6">
                    <div class="relative h-32 w-32">
                        <svg class="h-32 w-32 -rotate-90" viewBox="0 0 120 120">
                            <circle
                                cx="60"
                                cy="60"
                                r="54"
                                fill="none"
                                stroke="#e5e7eb"
                                stroke-width="12"
                                class="dark:stroke-zinc-700"
                            />
                            <circle
                                cx="60"
                                cy="60"
                                r="54"
                                fill="none"
                                stroke="#6366f1"
                                stroke-width="12"
                                stroke-linecap="round"
                                :stroke-dasharray="339.292"
                                :stroke-dashoffset="
                                    339.292 - (339.292 * tasaCompletado) / 100
                                "
                            />
                        </svg>
                        <div
                            class="absolute inset-0 flex items-center justify-center"
                        >
                            <span
                                class="text-2xl font-bold text-gray-900 dark:text-white"
                                >{{ tasaCompletado }}%</span
                            >
                        </div>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Porcentaje de asistentes inscritos en al menos un
                            taller
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
