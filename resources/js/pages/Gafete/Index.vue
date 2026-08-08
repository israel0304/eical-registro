<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    QrCode,
    Printer,
    Download,
    Camera,
    CheckCircle2,
    BadgeCheck,
    Building2,
    UserRound,
    Trash2,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

const props = defineProps<{
    user: {
        id: number;
        name: string;
        first_name: string;
        last_name: string;
        dni: string;
        affiliation: string | null;
        role: string;
        profile_photo_path: string | null;
        checkin_token: string;
        has_photo: boolean;
    };
    eventoNombre: string;
    checkinEnabled: boolean;
    checkedIn: boolean;
    printUrl: string;
}>();

const qrCanvas = ref<HTMLCanvasElement | null>(null);

const photoForm = useForm<{ photo: File | null }>({
    photo: null,
});

const deletePhotoForm = useForm({});

const removePhoto = () => {
    if (!window.confirm('¿Eliminar tu foto de perfil?')) return;
    deletePhotoForm.delete('/gafete/foto', {
        preserveScroll: true,
    });
};

const photoUrl = computed(() =>
    props.user.profile_photo_path
        ? '/storage/' + props.user.profile_photo_path
        : null,
);

const onPhotoChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    photoForm.photo = file;
    photoForm.post('/gafete/foto', {
        preserveScroll: true,
        onSuccess: () => {
            photoForm.reset();
        },
    });
};

const generateQR = async () => {
    await nextTick();
    if (!qrCanvas.value) return;

    const QRCode = await import('qrcode');
    const url =
        window.location.origin +
        '/gafete/escaneo?token=' +
        props.user.checkin_token;
    QRCode.toCanvas(qrCanvas.value, url, {
        width: 220,
        margin: 2,
        color: { dark: '#000000', light: '#ffffff' },
    });
};

const openPrint = () => {
    window.open(props.printUrl, '_blank');
};

const downloadPdf = () => {
    window.open('/gafete/imprimir/pdf', '_blank');
};

const initials = (() => {
    const first = (props.user.first_name || '').trim().charAt(0);
    const last = (props.user.last_name || '').trim().charAt(0);
    return (first + last).toUpperCase();
})();

onMounted(generateQR);
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Mi Gafete', href: '/gafete' }]">
        <Head title="Mi Gafete" />

        <div class="mx-auto min-h-screen w-full max-w-5xl space-y-8 px-8 py-8">
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div>
                    <h1
                        class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                    >
                        Mi Gafete
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ eventoNombre }}
                    </p>
                </div>
                <div
                    v-if="checkedIn"
                    class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1.5 text-sm font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                >
                    <CheckCircle2 class="h-4 w-4" />
                    Asistencia registrada hoy
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-2">
                <!-- Badge preview -->
                <div>
                    <div
                        class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div
                            class="flex items-center justify-between bg-gray-900 px-6 py-4"
                        >
                            <span
                                class="text-lg font-bold tracking-wide text-white"
                            >
                                {{ eventoNombre }}
                            </span>
                            <span class="text-sm text-slate-400"> Acceso </span>
                        </div>
                        <div class="p-6">
                            <div class="flex items-start gap-5">
                                <div
                                    class="relative h-28 w-28 flex-shrink-0 overflow-hidden rounded-xl border-2 border-gray-200 bg-gray-100 dark:border-zinc-700 dark:bg-zinc-800"
                                >
                                    <img
                                        v-if="photoUrl"
                                        :src="photoUrl"
                                        :alt="user.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-3xl font-bold text-slate-400"
                                    >
                                        {{ initials }}
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <h2
                                        class="text-xl font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ user.name }}
                                    </h2>
                                    <p
                                        class="mt-0.5 font-mono text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ user.dni }}
                                    </p>
                                    <span
                                        class="mt-2 inline-block rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                                    >
                                        {{ user.role }}
                                    </span>
                                    <p
                                        v-if="user.affiliation"
                                        class="mt-2 flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        <Building2 class="h-4 w-4" />
                                        {{ user.affiliation }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="mt-6 flex items-center justify-center border-t border-gray-100 pt-6 dark:border-zinc-800"
                            >
                                <canvas
                                    ref="qrCanvas"
                                    class="rounded-lg"
                                ></canvas>
                            </div>
                            <p
                                class="mt-3 text-center text-xs text-gray-400 dark:text-gray-500"
                            >
                                Muestra este código en la mesa de registro.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <button
                            @click="openPrint"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-black px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-gray-800 dark:bg-white dark:text-black dark:hover:bg-gray-200"
                        >
                            <Printer class="h-4 w-4" /> Imprimir gafete
                        </button>
                        <button
                            @click="downloadPdf"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            <Download class="h-4 w-4" /> Descargar PDF
                        </button>
                    </div>
                </div>

                <!-- Info / photo -->
                <div class="space-y-6">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-gray-100 pb-4 dark:border-zinc-800"
                        >
                            <BadgeCheck
                                class="h-6 w-6 text-indigo-600 dark:text-indigo-400"
                            />
                            <h2
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                Foto de perfil
                            </h2>
                        </div>
                        <p
                            class="mt-4 text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{
                                user.has_photo
                                    ? 'Tu foto de perfil aparecerá en el gafete impreso.'
                                    : 'Aún no tienes foto. Agrega una para que aparezca en tu gafete (se mostrarán tus iniciales mientras tanto).'
                            }}
                        </p>
                        <label
                            class="mt-4 flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm font-medium text-gray-600 transition-colors hover:border-indigo-400 hover:text-indigo-600 dark:border-zinc-700 dark:text-gray-400 dark:hover:text-indigo-400"
                        >
                            <Camera class="h-4 w-4" />
                            {{ user.has_photo ? 'Cambiar foto' : 'Subir foto' }}
                            <input
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="onPhotoChange"
                            />
                        </label>
                        <p
                            v-if="photoForm.processing"
                            class="mt-2 text-xs text-indigo-500"
                        >
                            Subiendo...
                        </p>
                        <p
                            v-if="deletePhotoForm.processing"
                            class="mt-2 text-xs text-indigo-500"
                        >
                            Eliminando...
                        </p>
                        <button
                            v-if="user.has_photo && !photoForm.processing"
                            type="button"
                            @click="removePhoto"
                            class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50 dark:border-red-900/50 dark:text-red-400 dark:hover:bg-red-950"
                            :disabled="deletePhotoForm.processing"
                        >
                            <Trash2 class="h-4 w-4" /> Eliminar foto
                        </button>
                    </div>

                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-gray-100 pb-4 dark:border-zinc-800"
                        >
                            <QrCode
                                class="h-6 w-6 text-indigo-600 dark:text-indigo-400"
                            />
                            <h2
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                Check-in del evento
                            </h2>
                        </div>
                        <ul
                            class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-400"
                        >
                            <li class="flex items-start gap-2">
                                <UserRound
                                    class="mt-0.5 h-4 w-4 shrink-0 text-gray-400"
                                />
                                <span>
                                    Tu asistencia al evento se registra
                                    escaneando el código QR de este gafete en la
                                    mesa de registro.
                                </span>
                            </li>
                            <li class="flex items-start gap-2">
                                <CheckCircle2
                                    class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500"
                                />
                                <span>
                                    <template v-if="checkedIn">
                                        Ya tienes tu asistencia registrada hoy.
                                        Recuerda registrarte cada día del
                                        evento. Podrás descargar tu constancia
                                        de asistencia al evento en
                                        <a
                                            href="/constancias"
                                            class="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                                            >Mis Constancias</a
                                        >.
                                    </template>
                                    <template v-else>
                                        <template v-if="checkinEnabled">
                                            El registro está habilitado.
                                        </template>
                                        <template v-else>
                                            El registro está deshabilitado por
                                            el momento.
                                        </template>
                                    </template>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
