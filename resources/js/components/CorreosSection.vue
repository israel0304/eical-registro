<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    CheckCircle2,
    Clock,
    Mail,
    Pencil,
    Plus,
    RotateCcw,
    Send,
    Trash2,
    XCircle,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import TriggerFormModal from '@/components/correos/TriggerFormModal.vue';

interface EmailTemplate {
    id: number;
    event_key: string;
    name: string;
    subject: string;
    triggers: EmailTrigger[];
}

interface EmailTrigger {
    id: number;
    event_key: string;
    email_template_id: number | null;
    to: string | null;
    role_id: number | null;
    is_active: boolean;
    template?: { id: number; event_key: string; name: string } | null;
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

interface EventLogEntry {
    id: number;
    event_key: string;
    event_label: string;
    status: string;
    message: string | null;
    subject_type: string | null;
    subject_id: number | null;
    actor_name: string | null;
    payload: Record<string, unknown> | null;
    created_at: string | null;
}

const props = defineProps<{
    templates: EmailTemplate[];
    triggers: EmailTrigger[];
    catalog: CatalogEvent[];
    logs: EventLogEntry[];
    roles: RoleOption[];
    canManage: boolean;
}>();

type Section = 'plantillas' | 'disparadores' | 'auditoria';

const section = ref<Section>('plantillas');

const catalogByKey = computed(
    () => new Map(props.catalog.map((event) => [event.event_key, event])),
);

const eventLabel = (key: string) => catalogByKey.value.get(key)?.label ?? key;

const templateFor = (key: string) =>
    props.templates.find((t) => t.event_key === key);

// ── Plantillas ────────────────────────────────────────────────────────────────

const showTemplateModal = ref(false);

const templateForm = useForm({
    event_key: '',
    name: '',
    subject: '',
    body_html: '',
});

const availableTemplateEvents = computed(() =>
    props.catalog.filter((event) => !templateFor(event.event_key)),
);

const openTemplateModal = () => {
    templateForm.reset();
    showTemplateModal.value = true;
};

const onTemplateEventChange = () => {
    const event = catalogByKey.value.get(templateForm.event_key);
    if (event) {
        templateForm.name = event.label;
    }
};

const createTemplate = () => {
    templateForm.post('/admin/correos/plantillas', {
        onSuccess: () => {
            showTemplateModal.value = false;
            templateForm.reset();
        },
    });
};

const deleteTemplate = (template: EmailTemplate) => {
    if (confirm(`¿Eliminar la plantilla «${template.name}»?`)) {
        router.delete('/admin/correos/plantillas/' + template.id, {
            preserveScroll: true,
        });
    }
};

// ── Disparadores ─────────────────────────────────────────────────────────────

const showTriggerModal = ref(false);
const editingTrigger = ref<EmailTrigger | null>(null);

const roleName = (id: number | null) =>
    props.roles.find((role) => role.id === id)?.name ?? null;

const recipientLabel = (trigger: EmailTrigger) => {
    if (trigger.role_id) {
        return `Rol: ${roleName(trigger.role_id) ?? `#${trigger.role_id}`}`;
    }

    return (
        catalogByKey.value.get(trigger.event_key)?.to_options[
            trigger.to ?? ''
        ] ??
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
    if (
        confirm(`¿Eliminar el disparador «${eventLabel(trigger.event_key)}»?`)
    ) {
        router.delete('/admin/correos/disparadores/' + trigger.id, {
            preserveScroll: true,
        });
    }
};

// ── Auditoría ────────────────────────────────────────────────────────────────

const statusBadge = (status: string) => {
    switch (status) {
        case 'sent':
            return {
                classes:
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                icon: CheckCircle2,
            };
        case 'failed':
            return {
                classes:
                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                icon: XCircle,
            };
        case 'queued':
            return {
                classes:
                    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                icon: Clock,
            };
        default:
            return {
                classes:
                    'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400',
                icon: Clock,
            };
    }
};

const statusLabel = (status: string) => {
    switch (status) {
        case 'sent':
            return 'Enviado';
        case 'failed':
            return 'Falló';
        case 'queued':
            return 'En cola';
        default:
            return 'Registrado';
    }
};

const resendingId = ref<number | null>(null);

const resendLog = async (log: EventLogEntry) => {
    resendingId.value = log.id;
    try {
        const res = await fetch(
            `/admin/correos/event-logs/${log.id}/resend`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((c) => c.startsWith('XSRF-TOKEN='))
                            ?.split('=')[1] ?? '',
                    ),
                },
            },
        );
        const data = await res.json();
        if (data.success) {
            log.status = 'queued';
        } else {
            alert(data.message ?? 'No se pudo reenviar.');
        }
    } catch {
        alert('Error de red al reenviar.');
    } finally {
        resendingId.value = null;
    }
};

const subjectPlaceholder = 'Inscripción confirmada: {{ taller }}';

const varToken = (key: string) => '{{ ' + key + ' }}';
</script>

<template>
    <div class="space-y-6">
        <!-- Sub-tabs -->
        <div
            class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-zinc-800"
        >
            <button
                v-for="item in [
                    { key: 'plantillas', label: 'Plantillas', icon: Mail },
                    { key: 'disparadores', label: 'Disparadores', icon: Zap },
                    { key: 'auditoria', label: 'Auditoría', icon: Activity },
                ]"
                :key="item.key"
                type="button"
                @click="section = item.key"
                class="-mb-px inline-flex items-center gap-2 rounded-t-lg border-b-2 px-4 py-2.5 text-sm font-medium transition-colors"
                :class="
                    section === item.key
                        ? 'border-indigo-600 text-indigo-700 dark:border-indigo-400 dark:text-indigo-300'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-zinc-700 dark:hover:text-gray-300'
                "
            >
                <component :is="item.icon" class="h-4 w-4" />
                {{ item.label }}
            </button>
        </div>

        <!-- ══ PLANTILLAS ══ -->
        <section v-if="section === 'plantillas'" class="space-y-6">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                    >
                        Plantillas de Correo
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Crea y personaliza el asunto y cuerpo HTML de cada
                        correo. Las variables se reemplazan con datos reales al
                        enviarse.
                    </p>
                </div>
                <button
                    v-if="canManage"
                    @click="openTemplateModal"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 focus:ring-2 focus:ring-black focus:ring-offset-2"
                >
                    <Plus class="h-4 w-4" /> Nueva Plantilla
                </button>
            </div>

            <div
                v-if="templates.length > 0"
                class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="group relative flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div
                            class="inline-flex rounded-md bg-indigo-50 px-2 py-1 text-[11px] font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                        >
                            {{ eventLabel(template.event_key) }}
                        </div>
                        <span
                            v-if="template.triggers.length > 0"
                            class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-semibold"
                            :class="
                                template.triggers[0].is_active
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                    : 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400'
                            "
                        >
                            <Send class="h-3 w-3" />
                            {{
                                template.triggers[0].is_active
                                    ? 'Activo'
                                    : 'Inactivo'
                            }}
                        </span>
                    </div>
                    <h3
                        class="mt-3 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{ template.name }}
                    </h3>
                    <p
                        class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Asunto: {{ template.subject }}
                    </p>
                    <div v-if="canManage" class="mt-4 flex items-center gap-2">
                        <Link
                            :href="
                                '/admin/correos/plantillas/' +
                                template.id +
                                '/edit'
                            "
                            class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:text-white"
                        >
                            <Pencil class="h-3.5 w-3.5" /> Editar
                        </Link>
                        <button
                            @click="deleteTemplate(template)"
                            class="ml-auto rounded-md border border-gray-300 bg-white p-1.5 text-gray-500 shadow-sm transition-colors hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-red-400"
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
                <Mail
                    class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600"
                />
                <p class="mt-4 text-gray-500 dark:text-gray-400">
                    Aún no hay plantillas de correo.
                </p>
                <p
                    v-if="canManage"
                    class="mt-1 text-sm text-gray-400 dark:text-gray-500"
                >
                    Crea la primera para comenzar.
                </p>
            </div>
        </section>

        <!-- ══ DISPARADORES ══ -->
        <section v-else-if="section === 'disparadores'" class="space-y-6">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                    >
                        Disparadores
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Controla qué evento envía qué plantilla y si está
                        activo. Sin disparador, el evento solo se registra en la
                        auditoría.
                    </p>
                </div>
                <button
                    v-if="canManage"
                    @click="openTriggerModal"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 focus:ring-2 focus:ring-black focus:ring-offset-2"
                >
                    <Plus class="h-4 w-4" /> Nuevo Disparador
                </button>
            </div>

            <div
                v-if="triggers.length > 0"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-gray-200 text-xs tracking-wide text-gray-500 uppercase dark:border-zinc-800 dark:text-gray-400"
                        >
                            <th class="px-5 py-3 font-medium">Evento</th>
                            <th class="px-5 py-3 font-medium">Plantilla</th>
                            <th class="px-5 py-3 font-medium">Destinatario</th>
                            <th class="px-5 py-3 font-medium">Estado</th>
                            <th
                                v-if="canManage"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="trigger in triggers"
                            :key="trigger.id"
                            class="border-b border-gray-100 last:border-0 dark:border-zinc-800"
                        >
                            <td
                                class="px-5 py-3 font-medium text-gray-900 dark:text-white"
                            >
                                {{ eventLabel(trigger.event_key) }}
                            </td>
                            <td
                                class="px-5 py-3 text-gray-600 dark:text-gray-400"
                            >
                                {{ trigger.template?.name ?? 'Sin plantilla' }}
                            </td>
                            <td
                                class="px-5 py-3 text-gray-600 dark:text-gray-400"
                            >
                                {{ recipientLabel(trigger) }}
                            </td>
                            <td class="px-5 py-3">
                                <button
                                    v-if="canManage"
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
                                <span
                                    v-else
                                    class="text-xs"
                                    :class="
                                        trigger.is_active
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-gray-400'
                                    "
                                >
                                    {{
                                        trigger.is_active
                                            ? 'Activo'
                                            : 'Inactivo'
                                    }}
                                </span>
                            </td>
                            <td v-if="canManage" class="px-5 py-3">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <button
                                        @click="openEditTriggerModal(trigger)"
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
                class="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <Zap
                    class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600"
                />
                <p class="mt-4 text-gray-500 dark:text-gray-400">
                    No hay disparadores configurados.
                </p>
                <p
                    v-if="canManage"
                    class="mt-1 text-sm text-gray-400 dark:text-gray-500"
                >
                    Los eventos se registran en la auditoría aunque no tengan
                    disparador.
                </p>
            </div>
        </section>

        <!-- ══ AUDITORÍA ══ -->
        <section v-else class="space-y-6">
            <div>
                <h1
                    class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                >
                    Auditoría de Eventos
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Registro de los eventos emitidos por el sistema y el estado
                    del correo asociado.
                </p>
            </div>

            <div
                v-if="logs.length > 0"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-gray-200 text-xs tracking-wide text-gray-500 uppercase dark:border-zinc-800 dark:text-gray-400"
                        >
                            <th class="px-5 py-3 font-medium">Evento</th>
                            <th class="px-5 py-3 font-medium">Estado</th>
                            <th class="px-5 py-3 font-medium">Sujeto</th>
                            <th class="px-5 py-3 font-medium">Actor</th>
                            <th class="px-5 py-3 font-medium">Fecha</th>
                            <th
                                v-if="canManage"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="log in logs"
                            :key="log.id"
                            class="border-b border-gray-100 last:border-0 dark:border-zinc-800"
                        >
                            <td class="px-5 py-3">
                                <p
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{ log.event_label }}
                                </p>
                                <p
                                    class="text-xs text-gray-400 dark:text-gray-500"
                                >
                                    {{ log.event_key }}
                                </p>
                            </td>
                            <td class="px-5 py-3">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-semibold"
                                    :class="statusBadge(log.status).classes"
                                >
                                    <component
                                        :is="statusBadge(log.status).icon"
                                        class="h-3 w-3"
                                    />
                                    {{ statusLabel(log.status) }}
                                </span>
                                <p
                                    v-if="log.message"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ log.message }}
                                </p>
                            </td>
                            <td
                                class="px-5 py-3 text-gray-600 dark:text-gray-400"
                            >
                                {{ log.subject_type }}
                                <span
                                    v-if="log.subject_id"
                                    class="text-gray-400"
                                >
                                    #{{ log.subject_id }}
                                </span>
                            </td>
                            <td
                                class="px-5 py-3 text-gray-600 dark:text-gray-400"
                            >
                                {{ log.actor_name ?? '—' }}
                            </td>
                            <td
                                class="px-5 py-3 text-gray-500 dark:text-gray-400"
                            >
                                {{ log.created_at }}
                            </td>
                            <td
                                v-if="canManage"
                                class="px-5 py-3 text-right"
                            >
                                <button
                                    v-if="
                                        log.status !== 'sent' &&
                                        log.status !== 'queued'
                                    "
                                    :disabled="resendingId === log.id"
                                    @click="resendLog(log)"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                                >
                                    <RotateCcw
                                        class="h-3.5 w-3.5"
                                        :class="
                                            resendingId === log.id
                                                ? 'animate-spin'
                                                : ''
                                        "
                                    />
                                    Reenviar
                                </button>
                                <span
                                    v-else-if="log.status === 'queued'"
                                    class="text-xs text-gray-400"
                                >
                                    En cola…
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <Activity
                    class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600"
                />
                <p class="mt-4 text-gray-500 dark:text-gray-400">
                    Aún no hay eventos registrados.
                </p>
            </div>
        </section>

        <!-- ── Modal: Nueva Plantilla ── -->
        <div
            v-if="showTemplateModal"
            class="fixed inset-0 z-50 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0"
            >
                <div
                    class="fixed inset-0 bg-black/50 transition-opacity"
                    @click="showTemplateModal = false"
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
                        Nueva Plantilla de Correo
                    </h3>

                    <form @submit.prevent="createTemplate">
                        <div
                            v-if="Object.keys(templateForm.errors).length > 0"
                            class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                        >
                            <ul class="mt-1 list-inside list-disc">
                                <li
                                    v-for="(
                                        message, key
                                    ) in templateForm.errors"
                                    :key="key"
                                >
                                    {{ message }}
                                </li>
                            </ul>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label
                                    for="tpl-event"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Evento
                                </label>
                                <select
                                    id="tpl-event"
                                    v-model="templateForm.event_key"
                                    required
                                    @change="onTemplateEventChange"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                                >
                                    <option value="" disabled>
                                        Selecciona un evento
                                    </option>
                                    <option
                                        v-for="event in availableTemplateEvents"
                                        :key="event.event_key"
                                        :value="event.event_key"
                                    >
                                        {{ event.group }} — {{ event.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label
                                    for="tpl-name"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Nombre
                                </label>
                                <input
                                    id="tpl-name"
                                    v-model="templateForm.name"
                                    type="text"
                                    required
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
                                    v-model="templateForm.subject"
                                    type="text"
                                    required
                                    :placeholder="subjectPlaceholder"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                                />
                                <p class="mt-1 text-xs text-gray-400">
                                    Puedes usar variables como
                                    <code
                                        class="rounded bg-gray-100 px-1 py-0.5 dark:bg-zinc-800"
                                        >{{ varToken('taller') }}</code
                                    >
                                </p>
                            </div>

                            <div>
                                <label
                                    for="tpl-body"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    Cuerpo (HTML)
                                </label>
                                <textarea
                                    id="tpl-body"
                                    v-model="templateForm.body_html"
                                    required
                                    rows="6"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-xs text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                                />
                                <p class="mt-1 text-xs text-gray-400">
                                    Tras crearla, podrás editarla con el editor
                                    visual y vista previa en vivo.
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-3">
                            <button
                                type="button"
                                @click="showTemplateModal = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="templateForm.processing"
                                class="inline-flex items-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 disabled:opacity-50"
                            >
                                Crear Plantilla
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ── Modal: Nuevo/Editar Disparador ── -->
        <TriggerFormModal
            :show="showTriggerModal"
            :catalog="catalog"
            :templates="templates"
            :roles="roles"
            :trigger="editingTrigger"
            @close="showTriggerModal = false"
            @saved="showTriggerModal = false"
        />
    </div>
</template>
