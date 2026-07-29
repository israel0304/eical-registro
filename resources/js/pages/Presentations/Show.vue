<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft, Calendar, Clock, MapPin, Hash, Download } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    presentation: any;
}>();

const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.role_id === 1);

const goBack = () => {
    router.get('/presentations');
};

const formatDay = (day: string) => {
    if (!day) return '';
    const date = new Date(day + 'T00:00:00');
    return date.toLocaleDateString('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const togglePresented = (userId: number, presented: boolean) => {
    axios.put('/presentations/' + props.presentation.id, {
        authors_presented: [{ user_id: userId, presented }],
    });
};

const downloadConstancia = (authorId: number) => {
    window.open(
        '/admin/constancias/ponencia/' + props.presentation.id + '/' + authorId + '/download',
        '_blank',
    );
};

const keywordsList = computed(() => {
    if (!props.presentation.keywords) return [];
    return props.presentation.keywords
        .split(/[,;]\s*/)
        .map((k: string) => k.trim())
        .filter(Boolean);
});

const disciplinesList = computed(() => {
    if (!props.presentation.discipline) return [];
    return props.presentation.discipline
        .split(/\s*\|\|\s*/)
        .map((d: string) => d.trim())
        .filter(Boolean);
});
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

                <div
                    v-if="presentation.submission_id"
                    class="mt-3 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    <Hash class="h-4 w-4" />
                    ID de envío: {{ presentation.submission_id }}
                </div>

                <div
                    v-if="presentation.discipline || presentation.keywords"
                    class="mt-4 flex flex-wrap items-start gap-x-6 gap-y-2 text-sm"
                >
                    <div
                        v-if="disciplinesList.length"
                        class="flex flex-wrap items-center gap-x-6 gap-y-2"
                    >
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span
                                v-for="d in disciplinesList"
                                :key="d"
                                class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200"
                            >
                                {{ d }}
                            </span>
                        </div>
                    </div>
                    <div
                        v-if="keywordsList.length"
                        class="flex flex-wrap items-center gap-x-6 gap-y-2"
                    >
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span
                                v-for="kw in keywordsList"
                                :key="kw"
                                class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200"
                            >
                                {{ kw }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-4 flex flex-wrap items-start gap-x-6 gap-y-2 rounded-lg bg-gray-50 p-4 text-sm dark:bg-zinc-800"
                >
                    <div
                        class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                    >
                        <Calendar class="h-4 w-4 shrink-0" />
                        {{ presentation.day ? formatDay(presentation.day) : '—' }}
                    </div>
                    <div
                        class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                    >
                        <Clock class="h-4 w-4 shrink-0" />
                        {{ presentation.start_time || '—' }}
                        {{ presentation.start_time && presentation.end_time ? '-' : '' }}
                        {{ presentation.end_time || '' }}
                    </div>
                    <div
                        class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                    >
                        <MapPin class="h-4 w-4 shrink-0" />
                        {{ presentation.location || '—' }}
                    </div>
                </div>
            </div>

            <div
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <span
                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                >
                    Autores
                </span>

                <ul class="mt-3 space-y-3">
                    <li
                        v-for="author in presentation.authors"
                        :key="author.id"
                        class="flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                    >
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300"
                        >
                            {{ author.first_name?.[0] }}{{ author.last_name?.[0] }}
                        </span>
                        <div class="flex-1">
                            <span class="font-medium"
                                >{{ author.first_name }}
                                {{ author.last_name }}</span
                            >
                            <span
                                v-if="author.affiliation"
                                class="ml-1 text-xs text-gray-400"
                            >
                                ({{ author.affiliation }})
                            </span>
                        </div>

                        <template v-if="isAdmin">
                            <label
                                class="flex cursor-pointer items-center gap-1 text-xs text-gray-600 dark:text-gray-400"
                            >
                                <input
                                    type="checkbox"
                                    :checked="author.pivot?.presented"
                                    @change="
                                        togglePresented(
                                            author.id,
                                            ($event.target as HTMLInputElement).checked,
                                        )
                                    "
                                    class="h-3.5 w-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Presentó
                            </label>
                            <button
                                v-if="author.pivot?.presented"
                                type="button"
                                @click="downloadConstancia(author.id)"
                                class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-600 transition-colors hover:bg-indigo-100 dark:bg-indigo-950 dark:text-indigo-300"
                            >
                                <Download class="h-3 w-3" />
                                Constancia
                            </button>
                        </template>

                        <span
                            v-else-if="author.pivot?.presented"
                            class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900 dark:text-green-200"
                        >
                            Presentada
                        </span>
                    </li>
                </ul>

                <div
                    class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200"
                >
                    <p class="font-medium">Información para autores</p>
                    <p class="mt-1 leading-relaxed">
                        Si algún autor requiere corregir sus datos personales
                        (nombre, correo, institución), puede hacerlo desde su
                        perfil. Para solicitar el alta o baja de un autor en
                        esta ponencia, es necesario contactar al administrador
                        escribiendo a
                        <a
                            href="mailto:soporte.encuentro.eical@gmail.com"
                            class="font-medium underline underline-offset-2 hover:text-blue-600"
                        >
                            soporte.encuentro.eical@gmail.com
                        </a>
                    </p>
                </div>
            </div>

            <div
                v-if="presentation.abstract"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <span
                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                >
                    Resumen / Abstract
                </span>
                <p
                    class="mt-3 whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300"
                >
                    {{ presentation.abstract }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>
