<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Image from '@tiptap/extension-image';
import LinkExt from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    ArrowLeft,
    Bold,
    Braces,
    ImageIcon,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Mail,
    Search,
    Send,
    Strikethrough,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AudienceSelector from '@/components/correos/AudienceSelector.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type {
    AudienceSegment,
    AudienceCourse,
} from '@/types/notificaciones';

const props = defineProps<{
    roles: { id: number; name: string }[];
    conferenceKinds: Record<string, string | null>;
    templates: { id: number; name: string; subject: string; body_html: string }[];
    courses: AudienceCourse[];
    canManageAll: boolean;
    workshopId?: number | null;
    workshopName?: string | null;
}>();

const isWorkshopContext = computed(() => props.workshopId != null);

const form = useForm({
    segments: [] as AudienceSegment[],
    template_id: null as number | null,
    subject: '',
    body_html: '',
});

if (isWorkshopContext.value && props.workshopId) {
    form.segments = [
        {
            type: 'workshop_enrollment',
            workshop_ids: [props.workshopId],
            all_workshops: false,
        },
    ];
}

const editor = useEditor({
    extensions: [
        StarterKit,
        LinkExt.configure({ openOnClick: false }),
        Image.configure({ allowBase64: true }),
        Placeholder.configure({ placeholder: 'Escribe el contenido del correo…' }),
    ],
    content: '',
    onUpdate: ({ editor }) => {
        form.body_html = editor.getHTML();
    },
});

const variables = computed<Record<string, string>>(() => {
    const base: Record<string, string> = {
        nombre_completo: 'Nombre completo',
        nombre: 'Nombre',
        apellidos: 'Apellidos',
        correo: 'Correo',
        dni: 'DNI / RFC',
        rol: 'Rol asignado',
        tipo_conferencia: 'Primer tipo de conferencia del grupo',
        tipos_conferencia: 'Tipos de conferencia (separados por coma)',
        nombre_taller: 'Primer taller del grupo',
        nombres_talleres: 'Talleres del grupo (separados por coma)',
        grupos: 'Grupos a los que pertenece el destinatario',
    };

    return base;
});

const insertVariable = (key: string) => {
    editor.value?.chain().focus().insertContent('{{ ' + key + ' }}').run();
};

const variableToken = (key: string) => '{{ ' + key + ' }}';

const toggleBold = () => editor.value?.chain().focus().toggleBold().run();
const toggleItalic = () => editor.value?.chain().focus().toggleItalic().run();
const toggleStrike = () => editor.value?.chain().focus().toggleStrike().run();
const toggleBullet = () => editor.value?.chain().focus().toggleBulletList().run();
const toggleOrdered = () => editor.value?.chain().focus().toggleOrderedList().run();

const setLink = () => {
    const previousUrl = editor.value?.getAttributes('link').href;
    const url = window.prompt('URL del enlace', previousUrl ?? 'https://');
    if (url === null) return;
    if (url === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};

const addImage = () => {
    const url = window.prompt('URL de la imagen');
    if (!url) return;
    editor.value?.chain().focus().setImage({ src: url }).run();
};

const getCookie = (name: string) => {
    try {
        const row = document.cookie
            .split('; ')
            .find((entry) => entry.startsWith(`${name}=`));
        return row ? decodeURIComponent(row.slice(name.length + 1)) : '';
    } catch {
        return '';
    }
};

const previewCount = ref(0);
const previewSample = ref<
    { name: string; email: string; groups: string[] }[]
>([]);
const previewLoading = ref(false);
const previewError = ref('');

let previewTimer: ReturnType<typeof setTimeout> | null = null;

const runPreview = () => {
    previewLoading.value = true;
    previewError.value = '';
    fetch('/admin/notificaciones/preview', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
        },
        body: JSON.stringify({ segments: form.segments }),
    })
        .then(async (response) => {
            if (response.status === 419) {
                previewError.value = 'Sesión expirada. Recarga la página.';
                window.setTimeout(() => window.location.reload(), 1500);
                return;
            }
            if (!response.ok) {
                previewError.value =
                    'No se pudo calcular la audiencia (código ' +
                    response.status +
                    ').';
                previewCount.value = 0;
                previewSample.value = [];
                return;
            }
            const data = await response.json();
            previewCount.value = data.count;
            previewSample.value = data.sample ?? [];
        })
        .catch(() => {
            previewError.value = 'No se pudo calcular la audiencia.';
            previewCount.value = 0;
            previewSample.value = [];
        })
        .finally(() => {
            previewLoading.value = false;
        });
};

watch(
    () => form.segments,
    () => {
        if (previewTimer) clearTimeout(previewTimer);
        previewTimer = setTimeout(runPreview, 300);
    },
    { deep: true, immediate: isWorkshopContext.value },
);

const audienceReady = computed(() => form.segments.length > 0);

const canSubmit = computed(
    () =>
        audienceReady.value &&
        previewCount.value > 0 &&
        form.subject.trim() !== '' &&
        form.body_html.trim() !== '',
);

const onTemplateChange = (event: Event) => {
    const id = (event.target as HTMLSelectElement).value;
    form.template_id = id ? Number(id) : null;
    const template = props.templates.find((t) => t.id === form.template_id);
    if (template) {
        form.subject = template.subject;
        editor.value?.commands.setContent(template.body_html);
        form.body_html = template.body_html;
    }
};

const confirmOpen = ref(false);

const submit = () => {
    confirmOpen.value = false;
    form.post('/admin/notificaciones', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Nueva notificación" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Notificaciones', href: '/admin/notificaciones' },
            { title: 'Nuevo envío', href: '/admin/notificaciones/enviar' },
        ]"
    >
        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <Link
                href="/admin/notificaciones"
                class="inline-flex items-center gap-1.5 text-sm text-gray-500 transition-colors hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4" /> Volver a Notificaciones
            </Link>

            <div>
                <h1
                    class="mb-1 flex items-center gap-2 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                >
                    <Mail class="h-7 w-7 text-gray-400" />
                    Nueva notificación por correo
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Selecciona la audiencia, redacta el mensaje y confirma el envío.
                </p>
            </div>

            <div
                v-if="Object.keys(form.errors).length > 0"
                class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
            >
                <ul class="list-inside list-disc">
                    <li v-for="(message, key) in form.errors" :key="key">
                        {{ message }}
                    </li>
                </ul>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
                <!-- Columna principal -->
                <div class="space-y-6">
                    <!-- 1. Audiencia -->
                    <section
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <h2
                            class="mb-4 flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-white"
                        >
                            <Users class="h-5 w-5 text-indigo-500" /> 1 ·
                            {{ isWorkshopContext ? 'Audiencia' : 'Destinatarios' }}
                        </h2>

                        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                            Combina los grupos que necesites en un solo envío.
                            Si una persona está en más de un grupo, recibe un
                            único correo.
                        </p>

                        <div
                            v-if="isWorkshopContext"
                            class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-950/40"
                        >
                            <p
                                class="text-sm font-semibold text-indigo-900 dark:text-indigo-200"
                            >
                                Inscritos del taller: {{ workshopName }}
                            </p>
                            <p
                                class="mt-1 text-xs text-indigo-700 dark:text-indigo-300"
                            >
                                El correo se enviará a todos los participantes
                                inscritos en este taller. Variable disponible:
                                <code class="font-semibold">{{
                                    variableToken('nombre_taller')
                                }}</code>
                            </p>
                        </div>

                        <AudienceSelector
                            v-model="form.segments"
                            :roles="roles"
                            :conference-kinds="conferenceKinds"
                            :courses="courses"
                            :can-manage-all="canManageAll"
                        />

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                @click="runPreview"
                                :disabled="!audienceReady"
                                class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                            >
                                <Search class="h-4 w-4" />
                                Calcular destinatarios
                            </button>
                            <span
                                v-if="previewLoading"
                                class="text-sm text-gray-400"
                            >
                                Calculando…
                            </span>
                            <span
                                v-else-if="previewError"
                                class="text-sm text-red-600 dark:text-red-400"
                            >
                                {{ previewError }}
                            </span>
                            <span
                                v-else-if="audienceReady"
                                class="text-sm font-medium text-gray-900 dark:text-white"
                            >
                                Se enviarán
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ previewCount }}
                                </span>
                                correo(s).
                            </span>
                        </div>

                        <div
                            v-if="previewSample.length"
                            class="mt-3 space-y-2 rounded-lg bg-gray-50 p-3 text-xs text-gray-500 dark:bg-zinc-800/60 dark:text-gray-400"
                        >
                            <p class="font-medium text-gray-600 dark:text-gray-300">
                                Muestra de destinatarios:
                            </p>
                            <div
                                v-for="(item, index) in previewSample"
                                :key="index"
                            >
                                · {{ item.name }} &lt;{{ item.email }}&gt;
                                <span
                                    v-if="item.groups?.length"
                                    class="text-gray-400"
                                >
                                    — {{ item.groups.join(' · ') }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <!-- 2. Mensaje -->
                    <section
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <h2
                            class="mb-4 flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-white"
                        >
                            <Mail class="h-5 w-5 text-indigo-500" /> 2 · Mensaje
                        </h2>

                        <div class="mb-4">
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Usar plantilla existente (opcional)
                            </label>
                            <select
                                :value="form.template_id ?? ''"
                                @change="onTemplateChange"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                            >
                                <option value="">Ninguna — redactar manualmente</option>
                                <option
                                    v-for="template in templates"
                                    :key="template.id"
                                    :value="template.id"
                                >
                                    {{ template.name }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-gray-400">
                                Al elegir una plantilla se cargan el asunto y el
                                cuerpo, listos para editar.
                            </p>
                        </div>

                        <div class="mb-4">
                            <label
                                for="notif-subject"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Asunto
                            </label>
                            <input
                                id="notif-subject"
                                v-model="form.subject"
                                type="text"
                                placeholder="Ej. Recordatorio del evento EICAL {{ año }}"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Cuerpo
                            </label>
                            <div
                                class="overflow-hidden rounded-lg border border-gray-300 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 dark:border-zinc-700"
                            >
                                <div
                                    class="flex flex-wrap items-center gap-0.5 border-b border-gray-200 bg-gray-50 px-2 py-1.5 dark:border-zinc-700 dark:bg-zinc-800"
                                >
                                    <button
                                        type="button"
                                        @click="toggleBold"
                                        title="Negritas"
                                        class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-white"
                                        :class="{ 'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white': editor?.isActive('bold') }"
                                    >
                                        <Bold class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="toggleItalic"
                                        title="Cursiva"
                                        class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-white"
                                        :class="{ 'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white': editor?.isActive('italic') }"
                                    >
                                        <Italic class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="toggleStrike"
                                        title="Tachado"
                                        class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-white"
                                        :class="{ 'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white': editor?.isActive('strike') }"
                                    >
                                        <Strikethrough class="h-4 w-4" />
                                    </button>
                                    <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-zinc-700"></span>
                                    <button
                                        type="button"
                                        @click="setLink"
                                        title="Enlace"
                                        class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-white"
                                        :class="{ 'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white': editor?.isActive('link') }"
                                    >
                                        <LinkIcon class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="addImage"
                                        title="Imagen"
                                        class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-white"
                                    >
                                        <ImageIcon class="h-4 w-4" />
                                    </button>
                                    <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-zinc-700"></span>
                                    <button
                                        type="button"
                                        @click="toggleBullet"
                                        title="Lista con viñetas"
                                        class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-white"
                                        :class="{ 'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white': editor?.isActive('bulletList') }"
                                    >
                                        <List class="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="toggleOrdered"
                                        title="Lista numerada"
                                        class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-white"
                                        :class="{ 'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white': editor?.isActive('orderedList') }"
                                    >
                                        <ListOrdered class="h-4 w-4" />
                                    </button>
                                </div>
                                <EditorContent
                                    :editor="editor"
                                    class="prose-sm max-w-none p-4 [&_.tiptap]:min-h-[240px] [&_.tiptap]:outline-none"
                                />
                            </div>
                        </div>
                    </section>

                    <!-- 3. Confirmar -->
                    <div class="flex justify-end">
                        <button
                            type="button"
                            :disabled="!canSubmit || form.processing"
                            @click="confirmOpen = true"
                            class="inline-flex items-center gap-2 rounded-md border border-transparent bg-black px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Send class="h-4 w-4" />
                            {{
                                canSubmit
                                    ? `Enviar a ${previewCount} destinatario(s)`
                                    : 'Completa la audiencia y el mensaje'
                            }}
                        </button>
                    </div>
                </div>

                <!-- Sidebar: variables -->
                <div class="space-y-5">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <h3
                            class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"
                        >
                            <Braces class="h-4 w-4 text-indigo-500" />
                            Variables
                        </h3>
                        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                            Haz clic para insertar en el cursor. Se reemplazan por
                            el dato de cada destinatario al enviar.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="(label, key) in variables"
                                :key="key"
                                type="button"
                                @click="insertVariable(key)"
                                class="group inline-flex flex-col items-start gap-0.5 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-left transition-colors hover:border-indigo-300 hover:bg-indigo-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/40"
                            >
                                <code class="text-xs font-semibold text-indigo-600 group-hover:text-indigo-700 dark:text-indigo-400">
                                    {{ variableToken(key) }}
                                </code>
                                <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                    {{ label }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:open="confirmOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar envío</DialogTitle>
                    <DialogDescription>
                        <p class="mb-2">
                            Se enviará el correo a
                            <span class="font-bold text-gray-900 dark:text-white">
                                {{ previewCount }}
                            </span>
                            destinatario(s).
                        </p>
                        <p class="text-xs text-gray-400">
                            «{{ form.subject }}»
                        </p>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <button
                        @click="confirmOpen = false"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:text-gray-300 dark:hover:bg-zinc-800"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 disabled:opacity-50"
                    >
                        <Send class="h-4 w-4" />
                        Confirmar envío
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>