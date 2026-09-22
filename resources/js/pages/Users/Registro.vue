<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
import RegisterUserForm from '@/components/RegisterUserForm.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import { store } from '@/routes/users/registro';

withDefaults(
    defineProps<{
        closed?: boolean;
    }>(),
    {
        closed: false,
    },
);
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Registro', href: '/users/registro' }]">
        <Head title="Registro de asistentes" />

        <div class="mx-auto min-h-screen w-full max-w-xl space-y-6 px-8 py-8">
            <h1
                class="mb-8 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Registro de asistentes
            </h1>

            <div
                v-if="closed"
                class="flex flex-col items-center gap-4 rounded-lg border border-gray-200 bg-white p-8 text-center dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-muted"
                >
                    <Lock class="h-6 w-6 text-muted-foreground" />
                </div>
                <p class="text-sm leading-relaxed text-muted-foreground">
                    El registro de cuentas para este evento está cerrado.
                </p>
            </div>

            <div
                v-else
                class="rounded-lg border border-gray-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"
            >
                <RegisterUserForm :form="store.form()" submit-label="Registrar" />
            </div>
        </div>
    </AppLayout>
</template>