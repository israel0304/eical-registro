<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, Clock, MapPin, Download } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    conference: any;
}>();

const page = usePage();
const hasRole = (name: string) =>
    page.props.auth.user?.roles?.some((r: any) => r.name === name) ?? false;
const isAdmin = computed(() => hasRole('Administrator'));

const goBack = () => {
    router.get('/conferences');
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

const kindLabel = (kind: string) =>
    ({ magistral: 'Magistral', especial: 'Especial', simposio: 'Simposio', mesa_dialogo: 'Mesa de diálogo' })[kind] ?? kind;

const roleLabel = (role: string) =>
    ({ speaker: 'Speaker', moderator: 'Moderador' })[role] ?? role;

const toggleActivation = (userId: number) => {
    router.post('/conferences/' + props.conference.id + '/members/' + userId + '/activation', {}, {
        preserveScroll: true,
    });
};

const downloadConstancia = (userId: number) => {
    window.open(
        '/admin/constancias/conferencia/' +
            props.conference.id +
            '/' +
            userId +
            '/download',
        '_blank',
    );
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Conferencias', href: '/conferences' },
            {
                title: conference.title,
                href: '/conferences/' + conference.id,
            },
        ]"
    >
        <Head :title="conference.title" />

        <div class="mx-auto min-h-screen w-full max-w-4xl space-y-6 px-8 py-8">
            <button
                @click="goBack"
                class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4" /> Volver a conferencias
            </button>

            <div
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ conference.title }}
                    </h1>
                    <span
                        class="inline-flex rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                    >
                        {{ kindLabel(conference.kind) }}
                    </span>
                </div>

                <div
                    class="mt-4 flex flex-wrap items-start gap-x-6 gap-y-2 rounded-lg bg-gray-50 p-4 text-sm dark:bg-zinc-800"
                >
                    <div
                        class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                    >
                        <Calendar class="h-4 w-4 shrink-0" />
                        {{ conference.day ? formatDay(conference.day) : '—' }}
                    </div>
                    <div
                        class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                    >
                        <Clock class="h-4 w-4 shrink-0" />
                        {{ conference.start_time || '—' }}
                        {{
                            conference.start_time && conference.end_time
                                ? '-'
                                : ''
                        }}
                        {{ conference.end_time || '' }}
                    </div>
                    <div
                        class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                    >
                        <MapPin class="h-4 w-4 shrink-0" />
                        {{ conference.location || '—' }}
                    </div>
                </div>
            </div>

            <div
                v-if="conference.description"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <span
                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                >
                    Descripción
                </span>
                <p
                    class="mt-3 text-sm leading-relaxed whitespace-pre-line text-gray-700 dark:text-gray-300"
                >
                    {{ conference.description }}
                </p>
            </div>

            <div
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <span
                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                >
                    Participantes
                </span>

                <ul class="mt-3 space-y-3">
                    <li
                        v-for="member in conference.members"
                        :key="member.id"
                        class="flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                    >
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300"
                        >
                            {{ member.first_name?.[0]
                            }}{{ member.last_name?.[0] }}
                        </span>
                        <div class="flex-1">
                            <span class="font-medium"
                                >{{ member.first_name }}
                                {{ member.last_name }}</span
                            >
                            <span
                                class="ml-1 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-zinc-800 dark:text-gray-400"
                            >
                                {{ roleLabel(member.pivot?.role) }}
                            </span>
                        </div>

                        <template v-if="isAdmin">
                            <label
                                class="flex cursor-pointer items-center gap-1 text-xs text-gray-600 dark:text-gray-400"
                            >
                                <input
                                    type="checkbox"
                                    :checked="member.pivot?.activated"
                                    @change="toggleActivation(member.id)"
                                    class="h-3.5 w-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Constancia activada
                            </label>
                            <button
                                v-if="member.pivot?.activated"
                                type="button"
                                @click="downloadConstancia(member.id)"
                                class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-600 transition-colors hover:bg-indigo-100 dark:bg-indigo-950 dark:text-indigo-300"
                            >
                                <Download class="h-3 w-3" />
                                Constancia
                            </button>
                        </template>

                        <span
                            v-else-if="member.pivot?.activated"
                            class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900 dark:text-green-200"
                        >
                            Constancia activada
                        </span>
                    </li>
                </ul>

                <div
                    v-if="isAdmin && conference.members.length"
                    class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200"
                >
                    <p class="font-medium">Activación de constancias</p>
                    <p class="mt-1 leading-relaxed">
                        Marca la casilla para activar la constancia de cada
                        participante. Solo quienes tengan la constancia activada
                        podrán descargarla desde su panel.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
