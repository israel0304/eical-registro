<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    X,
    Calendar,
    Clock,
    MapPin,
    Hash,
    Pencil,
} from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';
import DisciplineInput from '@/components/DisciplineInput.vue';
import TagInput from '@/components/TagInput.vue';

const props = defineProps<{
    presentationId: number;
    url: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const page = usePage();
const can = (permission: string) =>
    (page.props.auth.permissions as string[] | undefined)?.includes(
        permission,
    ) ?? false;
const loading = ref(true);
const presentation = ref<any>(null);
const isAuthor = ref(false);
const error = ref('');

const canPresented = computed(() => can('presentations.presented'));
const canEdit = computed(
    () =>
        can('presentations.edit') ||
        (isAuthor.value && can('presentations.my')),
);

const showModal = ref(false);
const form = useForm({
    title: '',
    abstract: '',
    discipline: '',
    keywords: '',
});

const openEditModal = () => {
    form.title = presentation.value.title || '';
    form.abstract = presentation.value.abstract || '';
    form.discipline = presentation.value.discipline || '';
    form.keywords = presentation.value.keywords || '';
    showModal.value = true;
};

const savePresentation = () => {
    form.put('/presentations/' + presentation.value.id, {
        onSuccess: () => {
            presentation.value.title = form.title;
            presentation.value.abstract = form.abstract;
            presentation.value.discipline = form.discipline;
            presentation.value.keywords = form.keywords;
            showModal.value = false;
        },
    });
};

const authors = ref<any[]>([]);
const toggling = ref<number | null>(null);

const togglePresented = (userId: number, presented: boolean) => {
    toggling.value = userId;
    axios
        .put('/presentations/' + presentation.value.id, {
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
    if (!presentation.value?.keywords) return [];
    return presentation.value.keywords
        .split(/[,;]\s*/)
        .map((k: string) => k.trim())
        .filter(Boolean);
});

const disciplinesList = computed(() => {
    if (!presentation.value?.discipline) return [];
    return presentation.value.discipline
        .split(/\s*\|\|\s*/)
        .map((d: string) => d.trim())
        .filter(Boolean);
});

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

const fetchData = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await fetch(
            '/mis-asignaciones/presentation/' + props.presentationId,
        );
        if (!response.ok) throw new Error('Error al cargar');
        const data = await response.json();
        presentation.value = data.presentation;
        isAuthor.value = data.isAuthor;
        authors.value = data.presentation.authors || [];
    } catch (e: any) {
        error.value = e.message || 'Error al cargar los datos';
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchData();
});
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        <div
            class="fixed inset-0 bg-black/50 transition-opacity"
            @click="emit('close')"
        ></div>

        <div
            class="relative flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-zinc-800"
            >
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Detalle de la Ponencia
                </h2>
                <button
                    @click="emit('close')"
                    class="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-zinc-800 dark:hover:text-gray-300"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6">
                <!-- Loading -->
                <div
                    v-if="loading"
                    class="flex items-center justify-center py-12"
                >
                    <div
                        class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-600 border-t-transparent"
                    ></div>
                </div>

                <!-- Error -->
                <div
                    v-else-if="error"
                    class="rounded-lg border border-red-200 bg-red-50 p-4 text-center text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                >
                    {{ error }}
                </div>

                <!-- Presentation Details -->
                <template v-else-if="presentation">
                    <!-- Title -->
                    <div class="mb-6">
                        <div class="flex items-start justify-between gap-4">
                            <h3
                                class="text-xl font-semibold text-gray-900 dark:text-white"
                            >
                                {{ presentation.title }}
                            </h3>
                            <button
                                v-if="canEdit"
                                @click="openEditModal"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                            >
                                <Pencil class="h-3.5 w-3.5" /> Editar
                            </button>
                        </div>

                        <!-- Submission ID -->
                        <div
                            v-if="presentation.submission_id"
                            class="mt-2 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                        >
                            <Hash class="h-4 w-4" />
                            ID de envio: {{ presentation.submission_id }}
                        </div>

                        <!-- Disciplines & Keywords -->
                        <div
                            v-if="
                                presentation.discipline || presentation.keywords
                            "
                            class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm"
                        >
                            <div
                                v-if="disciplinesList.length"
                                class="flex flex-wrap gap-1.5"
                            >
                                <span
                                    v-for="d in disciplinesList"
                                    :key="d"
                                    class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200"
                                >
                                    {{ d }}
                                </span>
                            </div>
                            <div
                                v-if="keywordsList.length"
                                class="flex flex-wrap gap-1.5"
                            >
                                <span
                                    v-for="kw in keywordsList"
                                    :key="kw"
                                    class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200"
                                >
                                    {{ kw }}
                                </span>
                            </div>
                        </div>

                        <!-- Date/Time/Location -->
                        <div
                            class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-lg bg-gray-50 p-4 text-sm dark:bg-zinc-800"
                        >
                            <div
                                class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300"
                            >
                                <Calendar class="h-4 w-4" />
                                {{
                                    presentation.day
                                        ? formatDay(presentation.day)
                                        : '—'
                                }}
                            </div>
                            <div
                                class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300"
                            >
                                <Clock class="h-4 w-4" />
                                {{ presentation.start_time || '—' }}
                                {{
                                    presentation.start_time &&
                                    presentation.end_time
                                        ? '-'
                                        : ''
                                }}
                                {{ presentation.end_time || '' }}
                            </div>
                            <div
                                class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300"
                            >
                                <MapPin class="h-4 w-4" />
                                {{ presentation.location || '—' }}
                            </div>
                        </div>
                    </div>

                    <!-- Abstract -->
                    <div
                        v-if="presentation.abstract"
                        class="mb-6 rounded-xl border border-gray-200 p-5 dark:border-zinc-800"
                    >
                        <span
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Resumen / Abstract
                        </span>
                        <p
                            class="mt-2 text-sm leading-relaxed whitespace-pre-line text-gray-700 dark:text-gray-300"
                        >
                            {{ presentation.abstract }}
                        </p>
                    </div>

                    <!-- Authors -->
                    <div class="mb-6">
                        <span
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Autores
                        </span>

                        <ul class="mt-3 space-y-3">
                            <li
                                v-for="author in authors"
                                :key="author.id"
                                class="flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300"
                                >
                                    {{ author.first_name?.[0]
                                    }}{{ author.last_name?.[0] }}
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
                                    v-if="
                                        !canPresented && author.pivot?.presented
                                    "
                                    class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900 dark:text-green-200"
                                >
                                    Presentada
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- Moderators -->
                    <div>
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
                </template>
            </div>

            <!-- Footer -->
            <div
                class="flex items-center justify-between border-t border-gray-200 px-6 py-4 dark:border-zinc-800"
            >
                <a
                    :href="url"
                    class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                >
                    Ver pagina completa
                </a>
                <button
                    @click="emit('close')"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                >
                    Cerrar
                </button>
            </div>
        </div>

        <!-- Edit Modal (nested) -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="fixed inset-0 bg-black/50 transition-opacity"
                @click="showModal = false"
            ></div>
            <div
                class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-zinc-800 dark:bg-zinc-900"
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
</template>
