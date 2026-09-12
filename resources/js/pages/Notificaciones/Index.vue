<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    Eye,
    MailPlus,
    RefreshCcw,
    Search,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    notifications: {
        data: any[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: any;
}>();

const formFilters = useForm({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
});

let searchTimeout: ReturnType<typeof setTimeout>;
watch(
    () => [formFilters.search, formFilters.status],
    () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            formFilters.get('/admin/notificaciones', {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);
    },
    { deep: true },
);

const statusMeta: Record<string, { label: string; cls: string }> = {
    pending: { label: 'Pendiente', cls: 'bg-gray-100 text-gray-700 dark:bg-zinc-800 dark:text-gray-300' },
    processing: { label: 'Enviando…', cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' },
    sent: { label: 'Enviado', cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' },
    partial: { label: 'Parcial', cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
    failed: { label: 'Fallido', cls: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' },
};

const statusOf = (s: string) => statusMeta[s] ?? { label: s, cls: '' };

const formatDate = (value: string | null) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('es-MX', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const selectedSend = ref<any>(null);
const detailOpen = ref(false);
const detailRecipients = ref<any[]>([]);
const detailLoading = ref(false);

const openDetail = async (send: any) => {
    selectedSend.value = send;
    detailOpen.value = true;
    detailLoading.value = true;
    detailRecipients.value = [];
    try {
        const response = await fetch(`/admin/notificaciones/${send.id}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        detailRecipients.value = data.data ?? [];
    } finally {
        detailLoading.value = false;
    }
};

const retryFailed = (send: any) => {
    if (confirm(`¿Reintentar el envío a ${send.failed_count} destinatario(s) fallido(s)?`)) {
        router.post(
            `/admin/notificaciones/${send.id}/reenviar-fallidos`,
            {},
            { preserveScroll: true },
        );
    }
};

const canRetry = (send: any) =>
    send.failed_count > 0 &&
    (send.status === 'partial' || send.status === 'failed' || send.status === 'sent');
</script>

<template>
    <Head title="Notificaciones" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Notificaciones', href: '/admin/notificaciones' },
        ]"
    >
        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-6 px-8 py-8">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="mb-1 text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                    >
                        Notificaciones por correo
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Historial de envíos realizados a las distintas audiencias.
                    </p>
                </div>
                <Link
                    href="/admin/notificaciones/enviar"
                    class="inline-flex items-center gap-2 rounded-md border border-transparent bg-black px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-gray-800"
                >
                    <MailPlus class="h-4 w-4" /> Nuevo envío
                </Link>
            </div>

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center"
            >
                <div class="relative sm:w-72">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                    />
                    <input
                        v-model="formFilters.search"
                        type="text"
                        placeholder="Buscar por asunto…"
                        class="w-full rounded-md border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                    />
                </div>
                <select
                    v-model="formFilters.status"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                >
                    <option value="">Todos los estados</option>
                    <option value="pending">Pendiente</option>
                    <option value="processing">Enviando…</option>
                    <option value="sent">Enviado</option>
                    <option value="partial">Parcial</option>
                    <option value="failed">Fallido</option>
                </select>
            </div>

            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
                        <thead class="bg-gray-50 dark:bg-zinc-800/60">
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Audiencia</th>
                                <th class="px-4 py-3">Asunto</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Enviados / Fallidos</th>
                                <th class="px-4 py-3">Enviado por</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr
                                v-for="send in notifications.data"
                                :key="send.id"
                                class="text-sm text-gray-900 dark:text-white"
                            >
                                <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                                    {{ formatDate(send.created_at) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    {{ send.audience_label }}
                                </td>
                                <td class="max-w-[240px] truncate px-4 py-3">
                                    {{ send.subject }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="statusOf(send.status).cls"
                                    >
                                        {{ statusOf(send.status).label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="text-emerald-600 dark:text-emerald-400">
                                        {{ send.sent_count }}
                                    </span>
                                    /
                                    <span class="text-red-600 dark:text-red-400">
                                        {{ send.failed_count }}
                                    </span>
                                    <span class="text-gray-400"> ({{ send.recipient_count }})</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                                    {{ send.sender?.name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <button
                                            @click="openDetail(send)"
                                            title="Ver destinatarios"
                                            class="rounded-md border border-gray-200 p-2 text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-zinc-700 dark:text-gray-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="canRetry(send)"
                                            @click="retryFailed(send)"
                                            title="Reenviar fallidos"
                                            class="rounded-md border border-gray-200 p-2 text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-900 dark:border-zinc-700 dark:text-gray-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                                        >
                                            <RefreshCcw class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="notifications.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-sm text-gray-400"
                                >
                                    No hay envíos registrados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="notifications.total > 0"
                    class="flex justify-end gap-4 border-t border-gray-100 px-6 py-4 text-sm font-medium dark:border-zinc-800"
                >
                    <Link
                        v-if="notifications.prev_page_url"
                        :href="notifications.prev_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                        >Anterior</Link
                    >
                    <span v-else class="text-gray-400 dark:text-zinc-600"
                        >Anterior</span
                    >
                    <Link
                        v-if="notifications.next_page_url"
                        :href="notifications.next_page_url"
                        class="text-gray-900 hover:underline dark:text-white"
                        >Siguiente</Link
                    >
                    <span v-else class="text-gray-400 dark:text-zinc-600"
                        >Siguiente</span
                    >
                </div>
            </div>
        </div>

        <Dialog v-model:open="detailOpen">
            <DialogContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        Destinatarios — {{ selectedSend?.subject ?? '' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ selectedSend?.recipient_count }} destinatario(s) en
                        esta campaña.
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-[50vh] overflow-auto rounded-lg border border-gray-200 dark:border-zinc-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
                        <thead class="bg-gray-50 dark:bg-zinc-800/60">
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2">Nombre</th>
                                <th class="px-3 py-2">Correo</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2">Error</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                            <tr
                                v-for="recipient in detailRecipients"
                                :key="recipient.id"
                                class="text-sm text-gray-900 dark:text-white"
                            >
                                <td class="px-3 py-2">{{ recipient.name }}</td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                    {{ recipient.email }}
                                </td>
                                <td class="px-3 py-2">
                                    <span
                                        v-if="recipient.status === 'sent'"
                                        class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                                    >
                                        Enviado
                                    </span>
                                    <span
                                        v-else-if="recipient.status === 'failed'"
                                        class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                    >
                                        Fallido
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-zinc-800 dark:text-gray-300"
                                    >
                                        Pendiente
                                    </span>
                                </td>
                                <td class="max-w-[220px] truncate px-3 py-2 text-xs text-red-600 dark:text-red-400">
                                    {{ recipient.error ?? '—' }}
                                </td>
                            </tr>
                            <tr v-if="detailLoading">
                                <td
                                    colspan="4"
                                    class="px-3 py-6 text-center text-sm text-gray-400"
                                >
                                    Cargando…
                                </td>
                            </tr>
                            <tr v-if="!detailLoading && detailRecipients.length === 0">
                                <td
                                    colspan="4"
                                    class="px-3 py-6 text-center text-sm text-gray-400"
                                >
                                    Sin destinatarios.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DialogFooter>
                    <button
                        @click="detailOpen = false"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:text-gray-300 dark:hover:bg-zinc-800"
                    >
                        Cerrar
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>