<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Users, BookOpen, Mic, ClipboardCheck, Award, BarChart3 } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

defineProps<{
    stats: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const user = usePage().props.auth.user;
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1 class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white">
                Bienvenido, {{ user.name }}
            </h1>

            <!-- Admin Dashboard -->
            <template v-if="user.role_id === 1">
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                <Users class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_users }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Total Usuarios</div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                                <Users class="h-6 w-6 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.asistentes }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Asistentes</div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                                <Mic class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.ponentes }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Ponentes</div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                                <BookOpen class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.talleres }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Talleres</div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                                <ClipboardCheck class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.ponencias }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Ponencias</div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-rose-100 dark:bg-rose-900/30">
                                <Award class="h-6 w-6 text-rose-600 dark:text-rose-400" />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.inscripciones }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Inscripciones</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link href="/users" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-3">
                            <Users class="h-5 w-5 text-gray-400" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Gestionar Usuarios</span>
                        </div>
                    </Link>
                    <Link href="/workshops" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-3">
                            <BookOpen class="h-5 w-5 text-gray-400" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Gestionar Talleres</span>
                        </div>
                    </Link>
                    <Link href="/admin/reportes" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-3">
                            <BarChart3 class="h-5 w-5 text-gray-400" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Ver Reportes</span>
                        </div>
                    </Link>
                    <Link href="/admin/constancias" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-3">
                            <Award class="h-5 w-5 text-gray-400" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Constancias</span>
                        </div>
                    </Link>
                </div>
            </template>

            <!-- Ponente Dashboard -->
            <template v-else-if="user.role_id === 2">
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.mis_ponencias }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Mis Ponencias</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.talleres_inscritos }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Talleres Inscritos</div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Link href="/presentations" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-3">
                            <Mic class="h-5 w-5 text-gray-400" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Ver Mis Ponencias</span>
                        </div>
                    </Link>
                    <Link href="/my-workshops" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-3">
                            <BookOpen class="h-5 w-5 text-gray-400" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Mis Talleres</span>
                        </div>
                    </Link>
                </div>
            </template>

            <!-- Asistente Dashboard -->
            <template v-else>
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.talleres_inscritos }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Talleres Inscritos</div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Link href="/workshops" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-3">
                            <BookOpen class="h-5 w-5 text-gray-400" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Ver Talleres Disponibles</span>
                        </div>
                    </Link>
                    <Link href="/my-workshops" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center gap-3">
                            <Award class="h-5 w-5 text-gray-400" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Mis Talleres</span>
                        </div>
                    </Link>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
