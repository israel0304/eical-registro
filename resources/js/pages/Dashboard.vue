<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Users,
    UserRound,
    BookOpen,
    Mic,
    Presentation,
    ClipboardCheck,
    ClipboardList,
    Award,
} from 'lucide-vue-next';
import { availableModules } from '@/composables/useModuleNav';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

defineProps<{
    stats: any;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const page = usePage();

const user = page.props.auth.user;

const superAdminRole =
    (page.props.auth as any)?.superAdminRole ?? 'Administrator';

const hasRole = (name: string) =>
    user?.roles?.some((r: any) => r.name === name) ?? false;

const can = (permission: string) =>
    (page.props.auth.permissions as string[] | undefined)?.includes(
        permission,
    ) ?? false;

const permissions =
    (page.props.auth.permissions as string[] | undefined) ?? [];

const roleModules = availableModules(permissions).filter(
    (module) => module.href !== '/dashboard',
);

const adminModules = [
    { title: 'Usuarios', href: '/users', icon: Users },
    { title: 'Asistentes', href: '/users?role=Asistente', icon: UserRound },
    { title: 'Ponentes', href: '/users?role=Ponente', icon: Mic },
    { title: 'Talleres', href: '/workshops', icon: BookOpen },
    { title: 'Ponencias', href: '/presentations', icon: Presentation },
    { title: 'Inscripciones', href: '/workshops', icon: ClipboardList },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <h1
                class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Bienvenido, {{ user.name }}
            </h1>

            <!-- Admin Dashboard -->
            <template v-if="hasRole(superAdminRole)">
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30"
                            >
                                <Users
                                    class="h-6 w-6 text-blue-600 dark:text-blue-400"
                                />
                            </div>
                            <div>
                                <div
                                    class="text-2xl font-bold text-gray-900 dark:text-white"
                                >
                                    {{ stats.total_users }}
                                </div>
                                <div
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Total Usuarios
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30"
                            >
                                <Users
                                    class="h-6 w-6 text-green-600 dark:text-green-400"
                                />
                            </div>
                            <div>
                                <div
                                    class="text-2xl font-bold text-gray-900 dark:text-white"
                                >
                                    {{ stats.asistentes }}
                                </div>
                                <div
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Asistentes
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30"
                            >
                                <Mic
                                    class="h-6 w-6 text-purple-600 dark:text-purple-400"
                                />
                            </div>
                            <div>
                                <div
                                    class="text-2xl font-bold text-gray-900 dark:text-white"
                                >
                                    {{ stats.ponentes }}
                                </div>
                                <div
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Ponentes
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30"
                            >
                                <BookOpen
                                    class="h-6 w-6 text-amber-600 dark:text-amber-400"
                                />
                            </div>
                            <div>
                                <div
                                    class="text-2xl font-bold text-gray-900 dark:text-white"
                                >
                                    {{ stats.talleres }}
                                </div>
                                <div
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Talleres
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30"
                            >
                                <ClipboardCheck
                                    class="h-6 w-6 text-indigo-600 dark:text-indigo-400"
                                />
                            </div>
                            <div>
                                <div
                                    class="text-2xl font-bold text-gray-900 dark:text-white"
                                >
                                    {{ stats.ponencias }}
                                </div>
                                <div
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Ponencias
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-lg bg-rose-100 dark:bg-rose-900/30"
                            >
                                <Award
                                    class="h-6 w-6 text-rose-600 dark:text-rose-400"
                                />
                            </div>
                            <div>
                                <div
                                    class="text-2xl font-bold text-gray-900 dark:text-white"
                                >
                                    {{ stats.inscripciones }}
                                </div>
                                <div
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Inscripciones
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="module in adminModules"
                        :key="module.title"
                        :href="module.href"
                        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-center gap-3">
                            <component
                                :is="module.icon"
                                class="h-5 w-5 text-gray-400"
                            />
                            <span
                                class="text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{ module.title }}
                            </span>
                        </div>
                    </Link>
                </div>
            </template>

            <!-- Ponente Dashboard -->
            <template v-if="can('presentations.my')">
                <div class="grid grid-cols-2 gap-4">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div
                            class="text-2xl font-bold text-gray-900 dark:text-white"
                        >
                            {{ stats.mis_ponencias }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Mis Ponencias
                        </div>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div
                            class="text-2xl font-bold text-gray-900 dark:text-white"
                        >
                            {{ stats.talleres_inscritos }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Talleres Inscritos
                        </div>
                    </div>
                </div>

                <div
                    v-if="roleModules.length"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <Link
                        v-for="module in roleModules"
                        :key="module.title"
                        :href="module.href"
                        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-center gap-3">
                            <component
                                :is="module.icon"
                                class="h-5 w-5 text-gray-400"
                            />
                            <span
                                class="text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{ module.title }}
                            </span>
                        </div>
                    </Link>
                </div>
            </template>

            <!-- Asistente Dashboard -->
            <template v-if="can('workshops.my') && !can('presentations.my')">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div
                        class="text-2xl font-bold text-gray-900 dark:text-white"
                    >
                        {{ stats.talleres_inscritos }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Talleres Inscritos
                    </div>
                </div>

                <div
                    v-if="roleModules.length"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <Link
                        v-for="module in roleModules"
                        :key="module.title"
                        :href="module.href"
                        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-center gap-3">
                            <component
                                :is="module.icon"
                                class="h-5 w-5 text-gray-400"
                            />
                            <span
                                class="text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{ module.title }}
                            </span>
                        </div>
                    </Link>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
