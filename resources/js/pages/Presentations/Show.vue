<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    presentation: any;
    auth: any;
}>();

const isAdmin = props.auth.user.role_id === 1;

const form = useForm({
    title: props.presentation.title || '',
    abstract: props.presentation.abstract || '',
    discipline: props.presentation.discipline || '',
    keywords: props.presentation.keywords || '',
    location: props.presentation.location || '',
    day: props.presentation.day || '',
    start_time: props.presentation.start_time || '',
    end_time: props.presentation.end_time || '',
});

const savePresentation = () => {
    form.put('/presentations/' + props.presentation.id, {
        onSuccess: () => {
            // Saved
        },
    });
};

const goBack = () => {
    router.get('/presentations');
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Ponencias', href: '/presentations' },
            {
                title: presentation.title,
                href: '/presentations/' + presentation.id,
            },
        ]"
    >
        <Head :title="presentation.title" />

        <div class="mx-auto min-h-screen w-full max-w-4xl space-y-6 px-8 py-8">
            <button
                @click="goBack"
                class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4" /> Volver a ponencias
            </button>

            <div
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    {{ presentation.title }}
                </h1>

                <div class="mt-4">
                    <span
                        class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400"
                        >Autores</span
                    >
                    <div class="mt-1 space-y-1">
                        <div
                            v-for="author in presentation.authors"
                            :key="author.id"
                            class="text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{ author.first_name }} {{ author.last_name }}
                            <span class="text-xs text-gray-400"
                                >({{
                                    author.affiliation || author.email
                                }})</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <form
                @submit.prevent="savePresentation"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <h2
                    class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        isAdmin ? 'Editar Ponencia' : 'Editar Datos Académicos'
                    }}
                </h2>

                <div class="space-y-4">
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >Título *</label
                        >
                        <input
                            v-model="form.title"
                            type="text"
                            required
                            :disabled="!isAdmin"
                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >Resumen / Abstract</label
                        >
                        <textarea
                            v-model="form.abstract"
                            rows="4"
                            :disabled="!isAdmin"
                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                        ></textarea>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >Disciplina</label
                            >
                            <input
                                v-model="form.discipline"
                                type="text"
                                :disabled="!isAdmin"
                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >Palabras clave</label
                            >
                            <input
                                v-model="form.keywords"
                                type="text"
                                :disabled="!isAdmin"
                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                            />
                        </div>
                    </div>

                    <template v-if="isAdmin">
                        <div
                            class="border-t border-gray-100 pt-4 dark:border-zinc-800"
                        >
                            <h3
                                class="mb-3 text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                Asignación (Solo Admin)
                            </h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Lugar</label
                                    >
                                    <input
                                        v-model="form.location"
                                        type="text"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Día</label
                                    >
                                    <input
                                        v-model="form.day"
                                        type="text"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Hora Inicio</label
                                    >
                                    <input
                                        v-model="form.start_time"
                                        type="time"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Hora Fin</label
                                    >
                                    <input
                                        v-model="form.end_time"
                                        type="time"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div
                    class="mt-6 flex justify-end border-t border-gray-100 pt-4 dark:border-zinc-800"
                >
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <Save class="h-4 w-4" /> Guardar
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
