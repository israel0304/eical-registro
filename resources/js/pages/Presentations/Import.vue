<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ArrowLeft, UploadCloud } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);

const form = useForm({
    csv_file: null as File | null,
});

const triggerFileUpload = () => {
    fileInput.value?.click();
};

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        selectedFile.value = file;
        form.csv_file = file;
    }
};

const submitImport = () => {
    if (!form.csv_file) {
        alert('Selecciona un archivo CSV primero.');
        return;
    }

    form.post('/admin/presentations/import', {
        onSuccess: () => {
            selectedFile.value = null;
            if (fileInput.value) {
                fileInput.value.value = '';
            }
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
            { title: 'Importar CSV', href: '/admin/presentations/import' },
        ]"
    >
        <Head title="Importar Ponencias" />

        <div class="mx-auto min-h-screen w-full max-w-4xl space-y-6 px-8 py-8">
            <button
                @click="goBack"
                class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4" /> Volver a ponencias
            </button>

            <h1
                class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
            >
                Importar Ponencias desde CSV
            </h1>

            <div
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="space-y-6">
                    <div>
                        <h3
                            class="text-sm font-semibold text-gray-900 dark:text-white"
                        >
                            Formato esperado
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                        >
                            El archivo CSV debe contener las columnas del
                            sistema académico (OJS). Se importarán
                            automáticamente:
                        </p>
                        <ul
                            class="mt-2 list-inside list-disc text-sm text-gray-600 dark:text-gray-400"
                        >
                            <li>Ponencias con estado "Aceptada"</li>
                            <li>Hasta 10 autores por ponencia</li>
                            <li>
                                Cada autor se crea como usuario con rol
                                "Ponente"
                            </li>
                        </ul>
                    </div>

                    <div
                        class="border-t border-gray-100 pt-4 dark:border-zinc-800"
                    >
                        <label
                            class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Seleccionar archivo CSV
                        </label>
                        <input
                            type="file"
                            ref="fileInput"
                            class="hidden"
                            accept=".csv,.txt"
                            @change="handleFileUpload"
                        />
                        <div
                            @click="triggerFileUpload"
                            class="flex cursor-pointer items-center justify-center gap-3 rounded-lg border-2 border-dashed border-gray-300 p-8 transition-colors hover:border-indigo-400 hover:bg-indigo-50/50 dark:border-zinc-700 dark:hover:border-indigo-500"
                        >
                            <UploadCloud class="h-8 w-8 text-gray-400" />
                            <div class="text-center">
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        selectedFile
                                            ? selectedFile.name
                                            : 'Haz clic para seleccionar archivo'
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Solo archivos .csv o .txt
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex justify-end border-t border-gray-100 pt-4 dark:border-zinc-800"
                    >
                        <button
                            @click="submitImport"
                            :disabled="!selectedFile || form.processing"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                        >
                            <UploadCloud class="h-4 w-4" />
                            {{ form.processing ? 'Importando...' : 'Importar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
