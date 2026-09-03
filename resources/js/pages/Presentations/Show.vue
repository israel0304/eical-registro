<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    Calendar,
    Clock,
    MapPin,
    Hash,
    Pencil,
    ChevronDown,
    ChevronUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DisciplineInput from '@/components/DisciplineInput.vue';
import TagInput from '@/components/TagInput.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    presentation: any;
    isAuthor?: boolean;
}>();

const page = usePage();
const can = (permission: string) =>
    (page.props.auth.permissions as string[] | undefined)?.includes(
        permission,
    ) ?? false;
const canPresented = computed(() => can('presentations.presented'));
const canEdit = computed(
    () =>
        can('presentations.edit') ||
        (props.isAuthor && can('presentations.my')),
);

const showModal = ref(false);
const form = useForm({
    title: '',
    abstract: '',
    discipline: '',
    keywords: '',
});

const openEditModal = () => {
    form.title = props.presentation.title || '';
    form.abstract = props.presentation.abstract || '';
    form.discipline = props.presentation.discipline || '';
    form.keywords = props.presentation.keywords || '';
    showModal.value = true;
};

const savePresentation = () => {
    form.put('/presentations/' + props.presentation.id, {
        onSuccess: () => {
            showModal.value = false;
        },
    });
};

const breadcrumbs = computed(() =>
    can('presentations.view')
        ? [
              { title: 'Ponencias', href: '/presentations' },
              {
                  title: props.presentation.title,
                  href: '/presentations/' + props.presentation.id,
              },
          ]
        : [],
);

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

const authors = ref(props.presentation.authors);
const toggling = ref<number | null>(null);

const expandedAuthors = ref<Set<number>>(new Set());
const expandedMods = ref<Set<number>>(new Set());

const toggleAuthorSemblanza = (userId: number) => {
    const expanded = expandedAuthors.value;
    if (expanded.has(userId)) {
        expanded.delete(userId);
    } else {
        expanded.add(userId);
    }
    void expanded; // mutado in-situ (mismo ref)
};

const toggleModSemblanza = (userId: number) => {
    const expanded = expandedMods.value;
    if (expanded.has(userId)) {
        expanded.delete(userId);
    } else {
        expanded.add(userId);
    }
    void expanded; // mutado in-situ (mismo ref)
};

const togglePresented = (userId: number, presented: boolean) => {
    toggling.value = userId;
    axios
        .put('/presentations/' + props.presentation.id, {
            authors_presented: [{ user_id: userId, presented }],
        })
        .then(() => {
            const author = authors.value.find((a: any) => a.id === userId);
            if (author) author.pivot = { ...(author.pivot ?? {}), presented };
        })
        .finally(() => {
            toggling.value = null;
        });
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
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="presentation.title" />

        <div class="mx-auto min-h-screen w-full max-w-4xl space-y-6 px-8 py-8">
            <button
                v-if="can('presentations.view')"
                @click="goBack"
                class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4" /> Volver a ponencias
            </button>

            <div
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ presentation.title }}
                    </h1>
                    <button
                        v-if="canEdit"
                        @click="openEditModal"
                        class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        <Pencil class="h-4 w-4" /> Editar
                    </button>
                </div>

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
                        {{
                            presentation.day ? formatDay(presentation.day) : '—'
                        }}
                    </div>
                    <div
                        class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                    >
                        <Clock class="h-4 w-4 shrink-0" />
                        {{ presentation.start_time || '—' }}
                        {{
                            presentation.start_time && presentation.end_time
                                ? '-'
                                : ''
                        }}
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
                v-if="presentation.abstract"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <span
                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                >
                    Resumen / Abstract
                </span>
                <p
                    class="mt-3 text-sm leading-relaxed whitespace-pre-line text-gray-700 dark:text-gray-300"
                >
                    {{ presentation.abstract }}
                </p>
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
                        v-for="author in authors"
                        :key="author.id"
                        class="flex flex-wrap items-start gap-2 rounded-lg border p-2.5 text-sm text-gray-700 dark:text-gray-300"
                        :class="
                            expandedAuthors.has(author.id)
                                ? 'border-indigo-300 bg-indigo-50 dark:border-indigo-700 dark:bg-indigo-950/40'
                                : 'border-transparent'
                        "
                    >
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300"
                        >
                            {{ author.first_name?.[0]
                            }}{{ author.last_name?.[0] }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="font-medium"
                                    >{{ author.first_name }}
                                    {{ author.last_name }}</span
                                >
                                <span
                                    v-if="author.affiliation"
                                    class="text-xs text-gray-400"
                                >
                                    ({{ author.affiliation }})
                                </span>
                            </div>
                            <button
                                v-if="author.semblanza"
                                type="button"
                                class="mt-1 inline-flex items-center gap-1 rounded-full border border-indigo-300 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-100 hover:text-indigo-800 dark:border-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300 dark:hover:bg-indigo-900/60 dark:hover:text-indigo-200"
                                @click="toggleAuthorSemblanza(author.id)"
                            >
                                <ChevronDown
                                    v-if="!expandedAuthors.has(author.id)"
                                    class="h-3.5 w-3.5"
                                />
                                <ChevronUp v-else class="h-3.5 w-3.5" />
                                {{ expandedAuthors.has(author.id)
                                    ? 'Ocultar semblanza'
                                    : 'Ver semblanza'
                                }}
                            </button>
                            <p
                                v-if="
                                    author.semblanza &&
                                    expandedAuthors.has(author.id)
                                "
                                class="mt-1 text-sm leading-relaxed whitespace-pre-line text-gray-600 dark:text-gray-400"
                            >
                                {{ author.semblanza }}
                            </p>
                        </div>

                        <div
                            v-if="canPresented"
                            class="flex items-center gap-2"
                        >
                            <button
                                type="button"
                                :disabled="toggling !== null"
                                @click="
                                    togglePresented(
                                        author.id,
                                        !author.pivot?.presented,
                                    )
                                "
                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                                :class="
                                    author.pivot?.presented
                                        ? 'bg-indigo-600'
                                        : 'bg-gray-300 dark:bg-zinc-600'
                                "
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="
                                        author.pivot?.presented
                                            ? 'translate-x-6'
                                            : 'translate-x-1'
                                    "
                                ></span>
                            </button>
                            <span
                                class="text-xs text-gray-600 dark:text-gray-400"
                                >Presentó</span
                            >
</div>
                        <span
                            v-if="!canPresented && author.pivot?.presented"
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
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <span
                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                >
                    Moderador(es) asignado(s)
                </span>

                <ul
                    v-if="presentation.moderators?.length"
                    class="mt-3 space-y-3"
                >
                    <li
                        v-for="mod in presentation.moderators"
                        :key="mod.id"
                        class="flex flex-wrap items-start gap-2 rounded-lg border p-2.5 text-sm text-gray-700 dark:text-gray-300"
                        :class="
                            expandedMods.has(mod.id)
                                ? 'border-indigo-300 bg-indigo-50 dark:border-indigo-700 dark:bg-indigo-950/40'
                                : 'border-transparent'
                        "
                    >
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-amber-100 text-xs font-medium text-amber-700 dark:bg-amber-900 dark:text-amber-300"
                        >
                            {{ mod.first_name?.[0] }}{{ mod.last_name?.[0] }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="font-medium"
                                    >{{ mod.first_name }}
                                    {{ mod.last_name }}</span
                                >
                                <span
                                    v-if="mod.affiliation"
                                    class="text-xs text-gray-400"
                                >
                                    ({{ mod.affiliation }})
                                </span>
                            </div>
                            <button
                                v-if="mod.semblanza"
                                type="button"
                                class="mt-1 inline-flex items-center gap-1 rounded-full border border-indigo-300 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-100 hover:text-indigo-800 dark:border-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300 dark:hover:bg-indigo-900/60 dark:hover:text-indigo-200"
                                @click="toggleModSemblanza(mod.id)"
                            >
                                <ChevronDown
                                    v-if="!expandedMods.has(mod.id)"
                                    class="h-3.5 w-3.5"
                                />
                                <ChevronUp v-else class="h-3.5 w-3.5" />
                                {{ expandedMods.has(mod.id)
                                    ? 'Ocultar semblanza'
                                    : 'Ver semblanza'
                                }}
                            </button>
                            <p
                                v-if="
                                    mod.semblanza &&
                                    expandedMods.has(mod.id)
                                "
                                class="mt-1 text-sm leading-relaxed whitespace-pre-line text-gray-600 dark:text-gray-400"
                            >
                                {{ mod.semblanza }}
                            </p>
                        </div>
                        <span v-if="mod.email" class="text-xs text-gray-400">
                            {{ mod.email }}
                        </span>
                    </li>
                </ul>

                <p v-else class="mt-3 text-sm text-gray-400 dark:text-gray-500">
                    Sin moderador asignado
                </p>
            </div>
        </div>

        <!-- Edit Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0"
            >
                <div
                    class="fixed inset-0 bg-black/50 transition-opacity"
                    @click="showModal = false"
                ></div>
                <span class="hidden sm:inline-block sm:h-screen sm:align-middle"
                    >&#8203;</span
                >
                <div
                    class="relative inline-block transform overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-8 sm:align-middle dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <h3
                        class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Editar Ponencia
                    </h3>

                    <form @submit.prevent="savePresentation">
                        <div
                            v-if="Object.keys(form.errors).length > 0"
                            class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                        >
                            <p class="font-medium">
                                Corrige los siguientes errores:
                            </p>
                            <ul class="mt-1 list-inside list-disc">
                                <li
                                    v-for="(message, key) in form.errors"
                                    :key="key"
                                >
                                    {{ message }}
                                </li>
                            </ul>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Titulo *
                                </label>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    required
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Resumen
                                </label>
                                <textarea
                                    v-model="form.abstract"
                                    rows="4"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                ></textarea>
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Disciplina
                                </label>
                                <DisciplineInput v-model="form.discipline" />
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Palabras clave
                                </label>
                                <TagInput v-model="form.keywords" />
                            </div>

                            <div
                                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                            >
                                <span
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                >
                                    Moderador(es) asignado(s)
                                </span>

                                <ul
                                    v-if="presentation.moderators?.length"
                                    class="mt-3 space-y-3"
                                >
                                    <li
                                        v-for="mod in presentation.moderators"
                                        :key="mod.id"
                                        class="flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        <span
                                            class="flex h-7 w-7 items-center justify-center rounded-full bg-amber-100 text-xs font-medium text-amber-700 dark:bg-amber-900 dark:text-amber-300"
                                        >
                                            {{ mod.first_name?.[0]
                                            }}{{ mod.last_name?.[0] }}
                                        </span>
                                        <div class="flex-1">
                                            <span class="font-medium"
                                                >{{ mod.first_name }}
                                                {{ mod.last_name }}</span
                                            >
                                            <span
                                                v-if="mod.affiliation"
                                                class="ml-1 text-xs text-gray-400"
                                            >
                                                ({{ mod.affiliation }})
                                            </span>
                                        </div>
                                        <span
                                            v-if="mod.email"
                                            class="text-xs text-gray-400"
                                        >
                                            {{ mod.email }}
                                        </span>
                                    </li>
                                </ul>

                                <p
                                    v-else
                                    class="mt-3 text-sm text-gray-400 dark:text-gray-500"
                                >
                                    Sin moderador asignado
                                </p>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                @click="showModal = false"
                                class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{
                                    form.processing
                                        ? 'Guardando...'
                                        : 'Guardar cambios'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
