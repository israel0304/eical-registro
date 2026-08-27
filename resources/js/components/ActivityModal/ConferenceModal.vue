<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { X, Calendar, Clock, MapPin } from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';

const props = defineProps<{
    conferenceId: number;
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
const currentUserId = computed(() => page.props.auth.user?.id);

const loading = ref(true);
const conference = ref<any>(null);
const error = ref('');

const isAssignedModerator = computed(() => {
    return conference.value?.members?.some(
        (m: any) =>
            m.id === currentUserId.value && m.pivot?.role === 'moderator',
    );
});
const canManage = computed(
    () => can('conferences.edit') || isAssignedModerator.value,
);

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
    ({
        magistral: 'Magistral',
        especial: 'Especial',
        simposio: 'Simposio',
        mesa_dialogo: 'Mesa de dialogo',
    })[kind] ?? kind;

const roleLabel = (role: string) =>
    ({ speaker: 'Speaker', moderator: 'Moderador' })[role] ?? role;

const toggleActivation = (userId: number) => {
    router.post(
        '/conferences/' +
            conference.value.id +
            '/members/' +
            userId +
            '/activation',
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                const member = conference.value.members.find(
                    (m: any) => m.id === userId,
                );
                if (member?.pivot) {
                    member.pivot.activated = !member.pivot.activated;
                }
            },
        },
    );
};

const fetchData = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await fetch(
            '/mis-asignaciones/conference/' + props.conferenceId,
        );
        if (!response.ok) throw new Error('Error al cargar');
        conference.value = await response.json();
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
            class="relative flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-900"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-zinc-800"
            >
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Detalle de la Conferencia
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

                <!-- Conference Details -->
                <template v-else-if="conference">
                    <!-- Title & Kind -->
                    <div class="mb-6">
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <h3
                                class="text-xl font-semibold text-gray-900 dark:text-white"
                            >
                                {{ conference.title }}
                            </h3>
                            <span
                                class="inline-flex rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                            >
                                {{ kindLabel(conference.kind) }}
                            </span>
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
                                    conference.day
                                        ? formatDay(conference.day)
                                        : '—'
                                }}
                            </div>
                            <div
                                class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300"
                            >
                                <Clock class="h-4 w-4" />
                                {{ conference.start_time || '—' }}
                                {{
                                    conference.start_time && conference.end_time
                                        ? '-'
                                        : ''
                                }}
                                {{ conference.end_time || '' }}
                            </div>
                            <div
                                class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300"
                            >
                                <MapPin class="h-4 w-4" />
                                {{ conference.location || '—' }}
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div
                        v-if="conference.description"
                        class="mb-6 rounded-xl border border-gray-200 p-5 dark:border-zinc-800"
                    >
                        <span
                            class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Descripcion
                        </span>
                        <p
                            class="mt-2 text-sm leading-relaxed whitespace-pre-line text-gray-700 dark:text-gray-300"
                        >
                            {{ conference.description }}
                        </p>
                    </div>

                    <!-- Members -->
                    <div>
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

                                <template
                                    v-if="
                                        can('conferences.activate') &&
                                        (can('conferences.view') ||
                                            isAssignedModerator)
                                    "
                                >
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="
                                                toggleActivation(member.id)
                                            "
                                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
                                            :class="
                                                member.pivot?.activated
                                                    ? 'bg-indigo-600'
                                                    : 'bg-gray-300 dark:bg-zinc-600'
                                            "
                                        >
                                            <span
                                                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                                :class="
                                                    member.pivot?.activated
                                                        ? 'translate-x-6'
                                                        : 'translate-x-1'
                                                "
                                            ></span>
                                        </button>
                                        <span
                                            class="text-xs text-gray-600 dark:text-gray-400"
                                            >Constancia activada</span
                                        >
                                    </div>
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
                            v-if="canManage && conference.members?.length"
                            class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200"
                        >
                            <p class="font-medium">Activacion de constancias</p>
                            <p class="mt-1 leading-relaxed">
Usa el interruptor para activar la constancia de
                                cada participante. Solo quienes tengan la
                                constancia activada podrán descargarla desde su
                                panel.
                            </p>
                        </div>
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
    </div>
</template>
