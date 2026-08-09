<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Image from '@tiptap/extension-image';
import LinkExt from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    ArrowLeft,
    Bold,
    Braces,
    Eye,
    ImageIcon,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Mail,
    Pencil,
    Plus,
    RotateCcw,
    Save,
    Strikethrough,
    Trash2,
    Zap,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import TriggerFormModal from '@/components/correos/TriggerFormModal.vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

interface EmailTrigger {
    id: number;
    event_key: string;
    email_template_id: number | null;
    to: string | null;
    role_id: number | null;
    is_active: boolean;
    template?: { id: number; event_key: string; name: string } | null;
}

interface EmailTemplate {
    id: number;
    event_key: string;
    name: string;
}

interface CatalogEvent {
    event_key: string;
    label: string;
    group: string;
    to_options: Record<string, string>;
    variables: Record<string, string>;
}

interface RoleOption {
    id: number;
    name: string;
}

const props = defineProps<{
    template: {
        id: number;
        event_key: string;
        name: string;
        subject: string;
        body_html: string;
    };
    templates: EmailTemplate[];
    triggers: EmailTrigger[];
    catalog: CatalogEvent[];
    roles: RoleOption[];
    variables: Record<string, string>;
}>();

const form = useForm({
    name: props.template.name,
    subject: props.template.subject,
    body_html: props.template.body_html,
});

const editor = useEditor({
    extensions: [
        StarterKit,
        LinkExt.configure({ openOnClick: false }),
        Image.configure({ allowBase64: true }),
        Placeholder.configure({
            placeholder: 'Escribe el contenido del correo…',
        }),
    ],
    content: props.template.body_html,
    onUpdate: ({ editor }) => {
        form.body_html = editor.getHTML();
        schedulePreview();
    },
});

const previewing = ref(false);
const previewSubject = ref('');
const previewBody = ref('');
const previewError = ref('');

let previewTimer: ReturnType<typeof setTimeout> | null = null;

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

const schedulePreview = () => {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(runPreview, 500);
};

const runPreview = async () => {
    previewing.value = true;
    previewError.value = '';
    try {
        const response = await fetch('/admin/correos/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
            },
            body: JSON.stringify({
                event_key: props.template.event_key,
                subject: form.subject,
                body_html: form.body_html,
            }),
        });
        if (response.status === 419) {
            previewError.value = 'Sesión expirada. Recarga la página.';
            window.setTimeout(() => window.location.reload(), 1500);
            previewing.value = false;
            return;
        }
        if (!response.ok) {
            previewError.value =
                'No se pudo actualizar la vista previa (código ' +
                response.status +
                ').';
            previewing.value = false;
            return;
        }
        const data = await response.json();
        previewSubject.value = data.subject;
        previewBody.value = data.body_html;
        previewing.value = false;
    } catch {
        previewError.value = 'No se pudo actualizar la vista previa.';
        previewing.value = false;
    }
};

const insertVariable = (key: string) => {
    editor.value?.chain().focus().insertContent('{{ ' + key + ' }}').run();
};

const setLink = () => {
    const previousUrl = editor.value?.getAttributes('link').href;
    const url = window.prompt('URL del enlace', previousUrl ?? 'https://');
    if (url === null) return;
    if (url === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    editor.value
        ?.chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href: url })
        .run();
};

const addImage = () => {
    const url = window.prompt('URL de la imagen');
    if (!url) return;
    editor.value?.chain().focus().setImage({ src: url }).run();
};

const toggleBold = () => editor.value?.chain().focus().toggleBold().run();
const toggleItalic = () => editor.value?.chain().focus().toggleItalic().run();
const toggleStrike = () => editor.value?.chain().focus().toggleStrike().run();
const toggleBullet = () =>
    editor.value?.chain().focus().toggleBulletList().run();
const toggleOrdered = () =>
    editor.value?.chain().focus().toggleOrderedList().run();

const save = () => {
    form.put('/admin/correos/plantillas/' + props.template.id, {
        preserveScroll: true,
    });
};

const resetContent = () => {
    editor.value?.commands.setContent(props.template.body_html);
    form.body_html = props.template.body_html;
    schedulePreview();
};

onMounted(() => {
    runPreview();
});

onBeforeUnmount(() => {
    editor.value?.destroy();
});

const eventLabel = computed(() => {
    const key = props.template.event_key;
    const labels: Record<string, string> = {
        'workshop.enrollment': 'Confirmación de inscripción a taller',
        'workshop.qr_sent': 'Código QR de asistencia a instructor',
        'user.welcome': 'Bienvenida y activación de cuenta',
    };
    return labels[key] ?? key;
});

const catalogByKey = computed(
    () => new Map(props.catalog.map((event) => [event.event_key, event])),
);

const activeTrigger = computed(
    () =>
        props.triggers.find(
            (t) => t.email_template_id === props.template.id,
        ) ?? null,
);

// ── Disparadores ─────────────────────────────────────────────────────────────

const showTriggerModal = ref(false);
const editingTrigger = ref<EmailTrigger | null>(null);

const eventTriggers = computed(() =>
    props.triggers.filter(
        (t) => t.email_template_id === props.template.id,
    ),
);

const roleName = (id: number | null) =>
    props.roles.find((role) => role.id === id)?.name ?? null;

const recipientLabel = (trigger: EmailTrigger) => {
    if (trigger.role_id) {
        return `Rol: ${roleName(trigger.role_id) ?? `#${trigger.role_id}`}`;
    }

    return (
        catalogByKey.value.get(trigger.event_key)?.to_options[trigger.to ?? ''] ??
        trigger.to ??
        '—'
    );
};

const openTriggerModal = () => {
    editingTrigger.value = null;
    showTriggerModal.value = true;
};

const openEditTriggerModal = (trigger: EmailTrigger) => {
    editingTrigger.value = trigger;
    showTriggerModal.value = true;
};

const toggleTrigger = (trigger: EmailTrigger) => {
    router.put(
        '/admin/correos/disparadores/' + trigger.id,
        {
            event_key: trigger.event_key,
            email_template_id: trigger.email_template_id ?? '',
            to: trigger.to ?? '',
            role_id: trigger.role_id ?? '',
            is_active: !trigger.is_active,
        },
        { preserveScroll: true },
    );
};

const deleteTrigger = (trigger: EmailTrigger) => {
    if (confirm(`¿Eliminar el disparador «${trigger.event_key}»?`)) {
        router.delete('/admin/correos/disparadores/' + trigger.id, {
            preserveScroll: true,
        });
    }
};

const subjectPlaceholder = 'Inscripción confirmada: {{ taller }}';

const varToken = (key: string) => '{{ ' + key + ' }}';

const previewSrcDoc = computed(() => {
    const css = `
        body{margin:0;padding:0;background-color:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1a1a2e}
        .wrapper{width:100%;background-color:#f4f4f7;padding:32px 0}
        .container{max-width:600px;margin:0 auto;background-color:#fff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb}
        .header{background-color:#0f3460;padding:24px 32px}.header h1{margin:0;color:#fff;font-size:20px}
        .content{padding:32px;font-size:15px;line-height:1.6;color:#333}
        .content h1,.content h2,.content h3{color:#0f3460;margin-top:0}
        .content a{color:#e94560}
        .content table{width:100%;border-collapse:collapse;margin:16px 0}
        .content table td,.content table th{border:1px solid #e5e7eb;padding:8px 12px;text-align:left;font-size:14px}
        .content table th{background-color:#f8fafc;color:#0f3460}
        .content img{max-width:100%;height:auto}
    `;
    return `<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><style>${css}</style></head>
<body><div class="wrapper"><div class="container">
<div class="header"><h1>Registro EICAL</h1></div>
<div class="content">${previewBody.value}</div>
</div></div></body></html>`;
});
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Plantillas', href: '/admin/plantillas?tab=correos' },
            { title: 'Correos', href: '/admin/plantillas?tab=correos' },
            { title: template.name },
        ]"
    >
        <Head :title="template.name" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <Link
                href="/admin/plantillas?tab=correos"
                class="inline-flex items-center gap-1.5 text-sm text-gray-500 transition-colors hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            >
                <ArrowLeft class="h-4 w-4" /> Volver a Plantillas de Correo
            </Link>

            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <h1
                            class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                        >
                            {{ form.name }}
                        </h1>
                        <span
                            v-if="activeTrigger"
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                            :class="
                                activeTrigger.is_active
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400'
                            "
                        >
                            <Zap class="h-3 w-3" />
                            {{
                                activeTrigger.is_active
                                    ? 'Disparador activo'
                                    : 'Disparador inactivo'
                            }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ eventLabel }} ·
                        <code
                            class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-zinc-800"
                            >{{ template.event_key }}</code
                        >
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="resetContent"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        <RotateCcw class="h-4 w-4" /> Restablecer
                    </button>
                    <button
                        @click="save"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 disabled:opacity-50"
                    >
                        <Save class="h-4 w-4" /> Guardar
                    </button>
                </div>
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

            <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
                <!-- Editor -->
                <div class="space-y-5">
                    <div>
                        <label
                            for="tpl-name"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Nombre de la plantilla
                        </label>
                        <input
                            id="tpl-name"
                            v-model="form.name"
                            type="text"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                        />
                    </div>

                    <div>
                        <label
                            for="tpl-subject"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Asunto
                        </label>
                        <input
                            id="tpl-subject"
                            v-model="form.subject"
                            type="text"
                            @input="schedulePreview"
                            :placeholder="subjectPlaceholder"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                        />
                        <p class="mt-1 text-xs text-gray-400">
                            Las variables en el asunto también se reemplazan.
                        </p>
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
                                    :class="{
                                        'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white':
                                            editor?.isActive('bold'),
                                    }"
                                >
                                    <Bold class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="toggleItalic"
                                    title="Cursiva"
                                    class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-white"
                                    :class="{
                                        'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white':
                                            editor?.isActive('italic'),
                                    }"
                                >
                                    <Italic class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="toggleStrike"
                                    title="Tachado"
                                    class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-white"
                                    :class="{
                                        'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white':
                                            editor?.isActive('strike'),
                                    }"
                                >
                                    <Strikethrough class="h-4 w-4" />
                                </button>
                                <span
                                    class="mx-1 h-5 w-px bg-gray-200 dark:bg-zinc-700"
                                ></span>
                                <button
                                    type="button"
                                    @click="setLink"
                                    title="Enlace"
                                    class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-white"
                                    :class="{
                                        'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white':
                                            editor?.isActive('link'),
                                    }"
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
                                <span
                                    class="mx-1 h-5 w-px bg-gray-200 dark:bg-zinc-700"
                                ></span>
                                <button
                                    type="button"
                                    @click="toggleBullet"
                                    title="Lista con viñetas"
                                    class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-white"
                                    :class="{
                                        'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white':
                                            editor?.isActive('bulletList'),
                                    }"
                                >
                                    <List class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="toggleOrdered"
                                    title="Lista numerada"
                                    class="rounded p-1.5 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-700 dark:hover:text-white"
                                    :class="{
                                        'bg-gray-200 text-gray-900 dark:bg-zinc-700 dark:text-white':
                                            editor?.isActive('orderedList'),
                                    }"
                                >
                                    <ListOrdered class="h-4 w-4" />
                                </button>
                            </div>
                            <EditorContent
                                :editor="editor"
                                class="prose-sm max-w-none p-4 [&_.tiptap]:min-h-[280px] [&_.tiptap]:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Sidebar: variables + preview -->
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
                            Haz clic en una variable para insertarla en el
                            cursor.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="(label, key) in variables"
                                :key="key"
                                type="button"
                                @click="insertVariable(key)"
                                class="group inline-flex flex-col items-start gap-0.5 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-left transition-colors hover:border-indigo-300 hover:bg-indigo-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/40"
                            >
                                <code
                                    class="text-xs font-semibold text-indigo-600 group-hover:text-indigo-700 dark:text-indigo-400"
                                >
                                    {{ varToken(key) }}
                                </code>
                                <span
                                    class="text-[11px] text-gray-500 dark:text-gray-400"
                                >
                                    {{ label }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <h3
                            class="mb-1 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"
                        >
                            <Eye class="h-4 w-4 text-indigo-500" />
                            Vista previa en vivo
                        </h3>
                        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                            Con datos de ejemplo.
                        </p>
                        <div
                            class="mb-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800"
                        >
                            <p class="text-xs text-gray-400">Asunto</p>
                            <p
                                class="text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{ previewSubject || form.subject }}
                            </p>
                        </div>
                        <iframe
                            :srcdoc="previewSrcDoc"
                            class="h-[420px] w-full rounded-md border border-gray-200 bg-white dark:border-zinc-700"
                            title="Vista previa del correo"
                        ></iframe>
                        <p
                            v-if="previewing"
                            class="mt-2 flex items-center gap-1 text-xs text-gray-400"
                        >
                            <Mail class="h-3 w-3" /> Actualizando vista previa…
                        </p>
                        <p
                            v-else-if="previewError"
                            class="mt-2 flex items-center gap-1 text-xs text-red-500"
                        >
                            <Mail class="h-3 w-3" /> {{ previewError }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Disparadores -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div
                    class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
                >
                    <div>
                        <h3
                            class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white"
                        >
                            <Zap class="h-4 w-4 text-indigo-500" />
                            Disparadores de esta plantilla
                        </h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Controla qué evento envía esta plantilla, el
                            destinatario y si está activa.
                        </p>
                    </div>
                    <button
                        @click="openTriggerModal"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800"
                    >
                        <Plus class="h-4 w-4" /> Nuevo Disparador
                    </button>
                </div>

                <div
                    v-if="eventTriggers.length > 0"
                    class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-zinc-800"
                >
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr
                                class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-zinc-800 dark:text-gray-400"
                            >
                                <th class="px-4 py-3 font-medium">Evento</th>
                                <th class="px-4 py-3 font-medium">
                                    Destinatario
                                </th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                <th
                                    class="px-4 py-3 text-right font-medium"
                                >
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="trigger in eventTriggers"
                                :key="trigger.id"
                                class="border-b border-gray-100 last:border-0 dark:border-zinc-800"
                            >
                                <td
                                    class="px-4 py-3 font-medium text-gray-900 dark:text-white"
                                >
                                    {{ eventLabel }}
                                </td>
                                <td
                                    class="px-4 py-3 text-gray-600 dark:text-gray-400"
                                >
                                    {{ recipientLabel(trigger) }}
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        @click="toggleTrigger(trigger)"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
                                        :class="
                                            trigger.is_active
                                                ? 'bg-emerald-500'
                                                : 'bg-gray-300 dark:bg-zinc-700'
                                        "
                                    >
                                        <span
                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                            :class="
                                                trigger.is_active
                                                    ? 'translate-x-[18px]'
                                                    : 'translate-x-[3px]'
                                            "
                                        />
                                    </button>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <button
                                            @click="
                                                openEditTriggerModal(trigger)
                                            "
                                            class="rounded-md border border-gray-300 bg-white p-1.5 text-gray-500 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            @click="deleteTrigger(trigger)"
                                            class="rounded-md border border-gray-300 bg-white p-1.5 text-gray-500 shadow-sm transition-colors hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-red-400"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="mt-4 rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-zinc-700 dark:text-gray-400"
                >
                    Aún no hay disparadores para esta plantilla. Crea uno para
                    enviar este correo automáticamente cuando ocurra el evento.
                </div>
            </div>
        </div>

        <TriggerFormModal
            :show="showTriggerModal"
            :catalog="catalog"
            :templates="templates"
            :roles="roles"
            :trigger="editingTrigger"
            :locked-event-key="template.event_key"
            :default-template-id="template.id"
            @close="showTriggerModal = false"
            @saved="showTriggerModal = false"
        />
    </AppLayout>
</template>
