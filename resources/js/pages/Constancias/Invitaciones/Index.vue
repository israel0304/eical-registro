<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Plus,
    Pencil,
    Trash2,
    CheckCircle2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    templates: any[];
    participationTypes: any[];
}>();

const showModal = ref(false);
const backgroundPreview = ref<string | null>(null);

const form = useForm({
    name: '',
    description: '',
    participation_type_id: '' as number | string,
    is_default: false,
    width: 816,
    height: 1056,
    background: null as File | null,
});

const basePath = '/admin/constancias/invitaciones/plantillas';

const openCreateModal = () => {
    backgroundPreview.value = null;
    form.reset();
    form.width = 816;
    form.height = 1056;
    form.participation_type_id = props.participationTypes.find((t) => t.is_active)?.id ?? '';
    showModal.value = true;
};

const onBackgroundChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    form.background = file;
    backgroundPreview.value = URL.createObjectURL(file);
};

const saveTemplate = () => {
    form.post(basePath, {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
            backgroundPreview.value = null;
        },
    });
};

const deleteTemplate = (id: number) => {
    if (confirm('¿Eliminar esta plantilla de carta?')) {
        router.delete(basePath + '/' + id, {
            preserveScroll: true,
        });
    }
};

const templateEditUrl = (id: number) => basePath + '/' + id + '/edit';

const participationTypeLabel = (id: number | null) => {
    if (!id) return 'Sin tipo';
    return (
        props.participationTypes.find((t) => t.id === id)?.label ?? 'Tipo'
    );
};

const activeTypes = computed(() =>
    props.participationTypes.filter((t) => t.is_active),
);
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Constancias', href: '/constancias' },
            { title: 'Cartas de Invitación', href: basePath },
        ]"
    >
        <Head title="Plantillas de Cartas de Invitación" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-10 px-8 py-8">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white">
                        Plantillas de Cartas de Invitación
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Plantillas para cartas de invitación (tamaño carta 816×1056).
                        Editor visual drag-and-drop con variables dinámicas.
                    </p>
                </div>
                <button
                    @click="openCreateModal"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                >
                    <Plus class="h-4 w-4" />
                    Nueva carta
                </button>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
                        <thead class="bg-gray-50 dark:bg-zinc-800/50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300">
                                    Nombre
                                </th>
                                <th class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300">
                                    Tipo participación
                                </th>
                                <th class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300">
                                    Dimensiones
                                </th>
                                <th class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300">
                                    Elementos
                                </th>
                                <th class="px-5 py-3 text-left text-xs font-bold tracking-wider text-gray-600 uppercase dark:text-gray-300">
                                    Defecto
                                </th>
                                <th class="relative px-5 py-3">
                                    <span class="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr v-for="template in templates" :key="template.id">
                                <td class="px-5 py-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ template.name }}
                                    </div>
                                    <div v-if="template.description" class="text-xs text-gray-400">
                                        {{ template.description }}
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">
                                    {{ participationTypeLabel(template.participation_type_id) }}
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">
                                    {{ template.width }} × {{ template.height }}
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-400">
                                    {{ template.elements_count }}
                                </td>
                                <td class="px-5 py-3">
                                    <span
                                        v-if="template.is_default"
                                        class="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:border-green-800 dark:bg-green-900/30 dark:text-green-300"
                                    >
                                        <CheckCircle2 class="mr-1 h-3 w-3" />
                                        Sí
                                    </span>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <Link
                                        :href="templateEditUrl(template.id)"
                                        class="mr-1 rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-indigo-400"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <button
                                        @click="deleteTemplate(template.id)"
                                        class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-red-400"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="templates.length === 0">
                                <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No hay plantillas de carta. Crea la primera con el botón "Nueva carta".
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="showModal"
                class="fixed inset-0 z-[60] overflow-y-auto"
                aria-labelledby="modal-title"
                role="dialog"
                aria-modal="true"
            >
                <div class="flex min-h-screen items-center justify-center px-4 text-center sm:p-0">
                    <div
                        class="fixed inset-0 bg-black/50 transition-opacity"
                        @click="showModal = false"
                    ></div>

                    <div class="relative z-10 inline-block w-full max-w-3xl overflow-hidden rounded-3xl border bg-white p-6 text-left shadow-2xl transition-all dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between mb-6">
                            <h2 id="modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                                Nueva plantilla de carta
                            </h2>
                            <button
                                @click="showModal = false"
                                type="button"
                                class="text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-white"
                            >
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form @submit.prevent="saveTemplate" class="space-y-4">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Nombre *
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    placeholder="ej. Carta Invitación EICAL"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Descripción
                                </label>
                                <textarea
                                    v-model="form.description"
                                    rows="2"
                                    placeholder="Descripción opcional"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                                <p v-if="form.errors.description" class="mt-1 text-xs text-red-500">
                                    {{ form.errors.description }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Tipo de participación *
                                </label>
                                <select
                                    v-model="form.participation_type_id"
                                    required
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                >
                                    <option value="" disabled>Selecciona un tipo</option>
                                    <option
                                        v-for="type in activeTypes"
                                        :key="type.id"
                                        :value="type.id"
                                    >
                                        {{ type.label }} ({{ type.key }})
                                    </option>
                                </select>
                                <p v-if="form.errors.participation_type_id" class="mt-1 text-xs text-red-500">
                                    {{ form.errors.participation_type_id }}
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                        Ancho (px) *
                                    </label>
                                    <input
                                        v-model.number="form.width"
                                        type="number"
                                        required
                                        min="200"
                                        max="5000"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                        Alto (px) *
                                    </label>
                                    <input
                                        v-model.number="form.height"
                                        type="number"
                                        required
                                        min="200"
                                        max="5000"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    Imagen de fondo (PNG)
                                </label>
                                <input
                                    type="file"
                                    @change="onBackgroundChange"
                                    accept="image/png"
                                    class="w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900/30 dark:file:text-indigo-400"
                                />
                                <p v-if="form.errors.background" class="mt-1 text-xs text-red-500">
                                    {{ form.errors.background }}
                                </p>
                                <div v-if="backgroundPreview" class="mt-2">
                                    <img :src="backgroundPreview" alt="Vista previa" class="max-w-xs rounded border border-gray-200 dark:border-zinc-700" />
                                </div>
                            </div>

                            <label class="flex cursor-pointer items-center gap-2">
                                <input
                                    type="checkbox"
                                    v-model="form.is_default"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                    Plantilla por defecto para este tipo
                                </span>
                            </label>

                            <div class="mt-6 flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    @click="showModal = false"
                                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    <Plus v-if="!form.processing" class="h-4 w-4" />
                                    <svg v-else class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    {{ form.processing ? 'Guardando...' : 'Crear plantilla' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>