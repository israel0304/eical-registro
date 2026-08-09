<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface CatalogEvent {
    event_key: string;
    label: string;
    group: string;
    to_options: Record<string, string>;
    variables: Record<string, string>;
}

interface EmailTemplate {
    id: number;
    event_key: string;
    name: string;
}

interface RoleOption {
    id: number;
    name: string;
}

interface EmailTrigger {
    id: number;
    event_key: string;
    email_template_id: number | null;
    to: string | null;
    role_id: number | null;
    is_active: boolean;
}

const props = defineProps<{
    show: boolean;
    catalog: CatalogEvent[];
    templates: EmailTemplate[];
    roles: RoleOption[];
    trigger: EmailTrigger | null;
    lockedEventKey?: string | null;
    defaultTemplateId?: number | null;
}>();

const emit = defineEmits<{
    close: [];
    saved: [];
}>();

const catalogByKey = computed(
    () => new Map(props.catalog.map((event) => [event.event_key, event])),
);

const editingId = ref<number | null>(null);
const recipientMode = ref<'payload' | 'role'>('payload');

const triggerForm = useForm({
    event_key: '',
    email_template_id: '' as number | string,
    to: '',
    role_id: '' as number | string,
    is_active: true,
});

const isEditing = computed(() => editingId.value !== null);

const selectedEvent = computed(() =>
    catalogByKey.value.get(triggerForm.event_key),
);

const selectedEventToOptions = computed(() =>
    Object.entries(selectedEvent.value?.to_options ?? {}),
);

const canUsePayloadRecipient = computed(
    () => selectedEventToOptions.value.length > 0,
);

const matchingTemplates = computed(() =>
    props.templates.filter((t) => t.event_key === triggerForm.event_key),
);

const eventOptions = computed(() =>
    props.lockedEventKey
        ? props.catalog.filter((e) => e.event_key === props.lockedEventKey)
        : props.catalog,
);

watch(
    () => props.show,
    (open) => {
        if (!open) return;

        triggerForm.clearErrors();
        triggerForm.reset();

        if (props.trigger) {
            editingId.value = props.trigger.id;
            triggerForm.event_key = props.trigger.event_key;
            triggerForm.email_template_id = props.trigger.email_template_id ?? '';
            triggerForm.to = props.trigger.to ?? '';
            triggerForm.role_id = props.trigger.role_id ?? '';
            triggerForm.is_active = props.trigger.is_active;
            recipientMode.value = props.trigger.role_id ? 'role' : 'payload';
        } else {
            editingId.value = null;
            triggerForm.event_key = props.lockedEventKey || props.catalog[0]?.event_key || '';
            triggerForm.email_template_id = props.defaultTemplateId ?? '';
            triggerForm.to = '';
            triggerForm.role_id = '';
            triggerForm.is_active = true;
            recipientMode.value =
                selectedEventToOptions.value.length > 0 ? 'payload' : 'role';
        }
    },
);

const onEventChange = () => {
    if (isEditing.value) return;

    triggerForm.to = '';
    triggerForm.role_id = '';
    triggerForm.email_template_id = props.lockedEventKey
        ? props.defaultTemplateId ?? ''
        : '';
    recipientMode.value =
        selectedEventToOptions.value.length > 0 ? 'payload' : 'role';
};

const save = () => {
    const options = {
        onSuccess: () => emit('saved'),
    };

    if (editingId.value) {
        triggerForm.put(
            '/admin/correos/disparadores/' + editingId.value,
            options,
        );
    } else {
        triggerForm.post('/admin/correos/disparadores', options);
    }
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
    >
        <div
            class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0"
        >
            <div
                class="fixed inset-0 bg-black/50 transition-opacity"
                @click="emit('close')"
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
                    {{ isEditing ? 'Editar Disparador' : 'Nuevo Disparador' }}
                </h3>

                <form @submit.prevent="save">
                    <div
                        v-if="Object.keys(triggerForm.errors).length > 0"
                        class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                    >
                        <ul class="mt-1 list-inside list-disc">
                            <li
                                v-for="(message, key) in triggerForm.errors"
                                :key="key"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label
                                for="trg-event"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Evento
                            </label>
                            <select
                                id="trg-event"
                                v-model="triggerForm.event_key"
                                required
                                :disabled="isEditing || !!lockedEventKey"
                                @change="onEventChange"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:bg-gray-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:disabled:bg-zinc-800"
                            >
                                <option value="" disabled>
                                    Selecciona un evento
                                </option>
                                <option
                                    v-for="event in eventOptions"
                                    :key="event.event_key"
                                    :value="event.event_key"
                                >
                                    {{ event.group }} — {{ event.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                for="trg-template"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Plantilla
                            </label>
                            <select
                                id="trg-template"
                                v-model="triggerForm.email_template_id"
                                required
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                            >
                                <option value="" disabled>
                                    Selecciona una plantilla
                                </option>
                                <option
                                    v-for="template in matchingTemplates.length > 0
                                        ? matchingTemplates
                                        : templates"
                                    :key="template.id"
                                    :value="template.id"
                                >
                                    {{ template.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Destinatario
                            </label>
                            <div
                                v-if="canUsePayloadRecipient"
                                class="mb-2 flex flex-wrap gap-2"
                            >
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
                                    :class="
                                        recipientMode === 'payload'
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                            : 'border-gray-300 text-gray-600 dark:border-zinc-700 dark:text-gray-400'
                                    "
                                >
                                    <input
                                        v-model="recipientMode"
                                        type="radio"
                                        value="payload"
                                        class="h-3.5 w-3.5 accent-indigo-600"
                                    />
                                    Participante del evento
                                </label>
                                <label
                                    v-if="roles.length > 0"
                                    class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
                                    :class="
                                        recipientMode === 'role'
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                            : 'border-gray-300 text-gray-600 dark:border-zinc-700 dark:text-gray-400'
                                    "
                                >
                                    <input
                                        v-model="recipientMode"
                                        type="radio"
                                        value="role"
                                        class="h-3.5 w-3.5 accent-indigo-600"
                                    />
                                    Rol
                                </label>
                            </div>

                            <select
                                v-if="recipientMode === 'payload'"
                                id="trg-to"
                                v-model="triggerForm.to"
                                required
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                            >
                                <option value="" disabled>
                                    Selecciona una opción
                                </option>
                                <option
                                    v-for="[key, label] in selectedEventToOptions"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>

                            <select
                                v-else-if="recipientMode === 'role'"
                                id="trg-role"
                                v-model="triggerForm.role_id"
                                required
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                            >
                                <option value="" disabled>
                                    Selecciona un rol
                                </option>
                                <option
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="role.id"
                                >
                                    {{ role.name }}
                                </option>
                            </select>

                            <div
                                v-else
                                class="rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400"
                            >
                                Selecciona un evento para configurar el
                                destinatario.
                            </div>
                        </div>

                        <label
                            class="flex cursor-pointer items-center gap-3"
                        >
                            <input
                                v-model="triggerForm.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span
                                class="text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Disparador activo
                            </span>
                        </label>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="emit('close')"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="triggerForm.processing"
                            class="inline-flex items-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800 disabled:opacity-50"
                        >
                            {{
                                isEditing
                                    ? 'Guardar Cambios'
                                    : 'Crear Disparador'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
