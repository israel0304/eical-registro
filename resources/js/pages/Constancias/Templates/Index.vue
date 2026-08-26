<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Plus,
    Pencil,
    Trash2,
    CheckCircle2,
    Image,
    LayoutTemplate,
    Layers,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    templates: any[];
    participationTypes: any[];
    kind?: string;
}>();

const isBadge = computed(() => props.kind === 'badge');
const basePath = computed(() =>
    isBadge.value
        ? '/admin/gafetes/plantillas'
        : '/admin/constancias/plantillas',
);

const showModal = ref(false);
const backgroundPreview = ref<string | null>(null);

const form = useForm({
    name: '',
    description: '',
    participation_type_id: '' as number | string,
    is_default: false,
    width: isBadge.value ? 1050 : 1800,
    height: isBadge.value ? 700 : 1200,
    print_width_mm: isBadge.value ? 75 : 210,
    print_height_mm: isBadge.value ? 125 : 297,
    background: null as File | null,
});

const openCreateModal = () => {
    backgroundPreview.value = null;
    form.reset();
    form.width = isBadge.value ? 1050 : 1800;
    form.height = isBadge.value ? 700 : 1200;
    form.print_width_mm = isBadge.value ? 75 : 210;
    form.print_height_mm = isBadge.value ? 125 : 297;
    form.participation_type_id = isBadge.value
        ? ''
        : (props.participationTypes.find((t) => t.is_active)?.id ?? '');
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
    form.post(basePath.value, {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
            backgroundPreview.value = null;
        },
    });
};

const deleteTemplate = (id: number) => {
    if (confirm('¿Eliminar esta plantilla?')) {
        router.delete(basePath.value + '/' + id, {
            preserveScroll: true,
        });
    }
};

const templateEditUrl = (id: number) => basePath.value + '/' + id + '/edit';

const participationTypeLabel = (id: number | null) => {
    if (!id) return 'Sin tipo';
    return props.participationTypes.find((t) => t.id === id)?.label ?? 'Tipo';
};

const activeTypes = computed(() =>
    props.participationTypes.filter((t) => t.is_active),
);
</script>

<template>
    <AppLayout
        :breadcrumbs="
            isBadge
                ? [
                      { title: 'Gafete', href: '/gafete' },
                      { title: 'Plantilla del Gafete', href: basePath },
                  ]
                : [
                      { title: 'Constancias', href: '/constancias' },
                      { title: 'Plantillas', href: basePath },
                  ]
        "
    >
        <Head
            :title="
                isBadge ? 'Plantilla del Gafete' : 'Plantillas de Certificados'
            "
        />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-10 px-8 py-8">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                    >
                        {{
                            isBadge
                                ? 'Plantilla del Gafete'
                                : 'Plantillas de Certificados'
                        }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{
                            isBadge
                                ? 'Personaliza la credencial de acceso que los participantes descargan desde su perfil.'
                                : 'Crea y personaliza los certificados que se generan para cada tipo de participación.'
                        }}
                    </p>
                </div>
                <button
                    @click="openCreateModal"
                    class="inline-flex items-center justify-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 focus:ring-2 focus:ring-black focus:ring-offset-2"
                >
                    <Plus class="h-4 w-4" /> Nueva Plantilla
                </button>
            </div>

            <!-- Templates grid -->
            <div
                v-if="templates.length > 0"
                class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <Link :href="templateEditUrl(template.id)" class="block">
                        <div
                            class="flex aspect-[3/2] w-full items-center justify-center overflow-hidden bg-gray-100 dark:bg-zinc-800"
                        >
                            <img
                                v-if="template.background_path"
                                :src="'/storage/' + template.background_path"
                                :alt="template.name"
                                class="h-full w-full object-cover"
                            />
                            <div
                                v-else
                                class="flex flex-col items-center gap-2 text-gray-400 dark:text-gray-500"
                            >
                                <Image class="h-10 w-10" />
                                <span class="text-xs">Sin fondo</span>
                            </div>
                            <div
                                v-if="template.is_default"
                                class="absolute top-3 left-3 inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                            >
                                <CheckCircle2 class="h-3 w-3" /> Default
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-2">
                                <h3
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ template.name }}
                                </h3>
                                <span
                                    class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400"
                                >
                                    <Layers class="h-3 w-3" />
                                    {{ template.elements_count }}
                                </span>
                            </div>
                            <p
                                v-if="template.description"
                                class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ template.description }}
                            </p>
                            <div
                                v-if="!isBadge"
                                class="mt-3 inline-flex rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                            >
                                {{
                                    participationTypeLabel(
                                        template.participation_type_id,
                                    )
                                }}
                            </div>
                        </div>
                    </Link>
                    <div
                        class="absolute top-3 right-3 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        <Link
                            :href="templateEditUrl(template.id)"
                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                        >
                            <Pencil class="h-4 w-4" />
                        </Link>
                        <button
                            @click="deleteTemplate(template.id)"
                            class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-red-400"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <LayoutTemplate
                    class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600"
                />
                <p class="mt-4 text-gray-500 dark:text-gray-400">
                    Aún no hay plantillas. Crea la primera para comenzar.
                </p>
            </div>

            <!-- Participation types -->
            <div
                v-if="!isBadge"
                class="flex items-center justify-between gap-4 rounded-xl border border-dashed border-gray-200 bg-gray-50 p-6 dark:border-zinc-800 dark:bg-zinc-900/50"
            >
                <div>
                    <h2
                        class="text-xl font-normal tracking-tight text-gray-900 dark:text-white"
                    >
                        Tipos de Participación
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        La gestión de tipos se movió a su propio módulo.
                    </p>
                </div>
                <Link
                    href="/admin/constancias/tipos"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                >
                    Ir a Tipos
                </Link>
            </div>

            <p v-if="!isBadge" class="text-xs text-gray-400 dark:text-gray-600">
                La página pública de verificación se accede en
                <code class="rounded bg-gray-100 px-1 py-0.5 dark:bg-zinc-800"
                    >/constancias/verificar/{folio}</code
                >
            </p>
        </div>

        <!-- Modal -->
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
                    class="relative inline-block transform overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-xl sm:p-8 sm:align-middle dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <h3
                        class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Nueva Plantilla
                    </h3>

                    <form @submit.prevent="saveTemplate">
                        <div
                            v-if="Object.keys(form.errors).length > 0"
                            class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                        >
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
                                    >Nombre *</label
                                >
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                                <p
                                    v-if="form.errors.name"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ form.errors.name }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Descripción</label
                                >
                                <textarea
                                    v-model="form.description"
                                    rows="2"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                ></textarea>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div v-if="!isBadge">
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >Tipo de participación *</label
                                    >
                                    <select
                                        v-model="form.participation_type_id"
                                        required
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    >
                                        <option
                                            v-for="type in activeTypes"
                                            :key="type.id"
                                            :value="type.id"
                                        >
                                            {{ type.label }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.participation_type_id"
                                        class="mt-1 text-xs text-red-500"
                                    >
                                        {{ form.errors.participation_type_id }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        Tamaño del lienzo (px)
                                    </label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input
                                            v-model.number="form.width"
                                            type="number"
                                            min="200"
                                            max="5000"
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                        />
                                        <input
                                            v-model.number="form.height"
                                            type="number"
                                            min="200"
                                            max="5000"
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div v-if="isBadge">
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Tamaño de impresión (mm)
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input
                                        v-model.number="form.print_width_mm"
                                        type="number"
                                        min="10"
                                        max="500"
                                        step="0.1"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                    <input
                                        v-model.number="form.print_height_mm"
                                        type="number"
                                        min="10"
                                        max="500"
                                        step="0.1"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                </div>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Fondo (PNG)</label
                                >
                                <div
                                    class="flex items-center gap-3 rounded-md border border-dashed border-gray-300 p-3 dark:border-zinc-700"
                                >
                                    <div
                                        v-if="backgroundPreview"
                                        class="h-16 w-24 overflow-hidden rounded border border-gray-200 dark:border-zinc-700"
                                    >
                                        <img
                                            :src="backgroundPreview"
                                            class="h-full w-full object-cover"
                                        />
                                    </div>
                                    <div
                                        v-else
                                        class="flex h-16 w-24 items-center justify-center rounded border border-gray-200 bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800"
                                    >
                                        <Image
                                            class="h-6 w-6 text-gray-300 dark:text-gray-600"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <input
                                            type="file"
                                            accept="image/png"
                                            @change="onBackgroundChange"
                                            class="w-full text-sm text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-900/30 dark:file:text-indigo-300"
                                        />
                                        <p
                                            v-if="form.errors.background"
                                            class="mt-1 text-xs text-red-500"
                                        >
                                            {{ form.errors.background }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <label
                                class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    v-model="form.is_default"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                {{
                                    isBadge
                                        ? 'Usar como plantilla por defecto del gafete'
                                        : 'Usar como plantilla por defecto de este tipo'
                                }}
                            </label>
                        </div>

                        <div
                            class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4 dark:border-zinc-800"
                        >
                            <button
                                type="button"
                                @click="showModal = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Crear plantilla
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
