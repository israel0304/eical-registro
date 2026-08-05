<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ScanLine,
    QrCode,
    CheckCircle2,
    XCircle,
    UserRound,
    CameraOff,
    Search,
} from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineProps<{
    attendances: any[];
    checkinEnabled: boolean;
    dayLabel: string;
    requiredDays: number;
}>();

type ScanResult = {
    success: boolean;
    message: string;
    already?: boolean;
    certificate_issued?: boolean;
    day_label?: string;
    days_attended?: number;
    required_days?: number;
    qualifies?: boolean;
    user?: {
        id: number;
        name: string;
        dni: string;
        affiliation: string | null;
        photo: string | null;
        checked_in: boolean;
        days_attended?: number;
    } | null;
};

const scannerActive = ref(false);
const scanning = ref(false);
const scannerError = ref<string | null>(null);
const result = ref<ScanResult | null>(null);
const manualToken = ref('');
const processing = ref(false);
const readerEl = ref<HTMLDivElement | null>(null);

let html5Qr: any = null;
let lastToken = '';
let lastScannedAt = 0;

const extractToken = (text: string) => {
    const match = text.match(/[?&]token=([^&\s]+)/);
    return match ? decodeURIComponent(match[1]) : text.trim();
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

const register = async (token: string) => {
    if (!token || processing.value) return;
    processing.value = true;
    result.value = null;

    try {
        const response = await fetch('/checkin/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
            },
            body: JSON.stringify({ token }),
        });

        if (response.status === 419) {
            result.value = {
                success: false,
                message: 'Sesión expirada. Recarga la página.',
            };
            window.setTimeout(() => window.location.reload(), 1500);
            return;
        }

        const data = await response.json().catch(() => null);
        result.value =
            data ?? {
                success: false,
                message: 'Respuesta inesperada del servidor.',
            };

        if (data?.success) {
            searchResults.value = [];
            searchOpen.value = false;
            router.reload({ only: ['attendances'] });
        }
    } catch {
        result.value = {
            success: false,
            message: 'Error de conexión. Intenta de nuevo.',
        };
    } finally {
        processing.value = false;
    }
};

const stopScanner = async () => {
    scannerActive.value = false;
    if (html5Qr) {
        try {
            await html5Qr.stop();
        } catch {
            // ignore
        }
        try {
            html5Qr.clear();
        } catch {
            // ignore
        }
        html5Qr = null;
    }
};

const startScanner = async () => {
    scannerError.value = null;
    result.value = null;
    scannerActive.value = true;

    await nextTick();
    if (!readerEl.value) return;

    const { Html5Qrcode } = await import('html5-qrcode');
    html5Qr = new Html5Qrcode('qr-reader', { verbose: false });

    const onSuccess = (decodedText: string) => {
        const token = extractToken(decodedText);
        if (!token) return;

        const now = Date.now();
        if (token === lastToken && now - lastScannedAt < 3000) return;
        lastToken = token;
        lastScannedAt = now;

        stopScanner();
        scanning.value = false;
        register(token);
    };

    const onError = (err: any) => {
        const message = String(err?.message ?? err ?? '');
        if (!message.includes('NotFound')) {
            scannerError.value = message;
        }
    };

    try {
        let cameraId: string | null = null;
        try {
            const cameras = await Html5Qrcode.getCameras();
            const preferred =
                cameras.find((camera: any) => /back|environment|rear/i.test(camera.label ?? '')) ??
                cameras[0];
            cameraId = preferred?.id ?? null;
        } catch {
            // Sin acceso a la lista de cámaras; usamos facingMode.
        }

        await html5Qr.start(
            cameraId
                ? { deviceId: { exact: cameraId } }
                : { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 260, height: 260 } },
            onSuccess,
            onError,
        );
    } catch {
        scannerActive.value = false;
        scannerError.value =
            'No se pudo acceder a la cámara. Verifica que el navegador tenga permiso y que el sitio use HTTPS o localhost.';
    }
};

const submitManual = () => {
    if (searchResults.value.length === 1) {
        register(searchResults.value[0].checkin_token);
        return;
    }
    register(manualToken.value.trim());
};

const searchResults = ref<any[]>([]);
const searching = ref(false);
const searchOpen = ref(false);
let searchTimer: number | undefined;

const runLookup = async (search: string) => {
    if (!search.trim() || search.trim().length < 2) {
        searchResults.value = [];
        searchOpen.value = false;
        return;
    }
    searching.value = true;
    try {
        const response = await fetch(
            '/checkin/lookup?search=' + encodeURIComponent(search.trim()),
            { headers: { Accept: 'application/json' } },
        );
        if (!response.ok) {
            searchResults.value = [];
            return;
        }
        searchResults.value = await response.json();
        searchOpen.value = true;
    } catch {
        searchResults.value = [];
    } finally {
        searching.value = false;
    }
};

const onSearchInput = () => {
    if (searchTimer) window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => runLookup(manualToken.value), 350);
};

const selectUser = (user: any) => {
    searchOpen.value = false;
    manualToken.value = user.name;
    register(user.checkin_token);
};

const clearResult = () => {
    result.value = null;
};

onMounted(async () => {
    await startScanner();
});

onBeforeUnmount(() => {
    stopScanner();
    if (searchTimer) window.clearTimeout(searchTimer);
});
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Check-in', href: '/checkin' }]">
        <Head title="Check-in de Gafetes" />

        <div class="mx-auto min-h-screen w-full max-w-7xl space-y-8 px-8 py-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-3xl font-normal tracking-tight text-gray-900 dark:text-white"
                    >
                        Check-in de Gafetes
                    </h1>
                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Escanea el QR del gafete para registrar la asistencia
                        al evento.
                    </p>
                    <p
                        class="mt-1 text-sm font-medium text-indigo-600 dark:text-indigo-400"
                    >
                        {{ dayLabel }}
                    </p>
                </div>
                <span
                    v-if="!checkinEnabled"
                    class="inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1.5 text-sm font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                >
                    <CameraOff class="h-4 w-4" />
                    Check-in deshabilitado
                </span>
            </div>

            <div class="grid gap-8 lg:grid-cols-2">
                <!-- Scanner -->
                <div class="space-y-4">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div
                            class="mb-4 flex items-center justify-between"
                        >
                            <h2
                                class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                <ScanLine
                                    class="h-5 w-5 text-indigo-600 dark:text-indigo-400"
                                />
                                Escáner
                            </h2>
                            <button
                                v-if="scannerActive"
                                @click="stopScanner"
                                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300"
                            >
                                Detener
                            </button>
                            <button
                                v-else
                                @click="startScanner"
                                class="rounded-md bg-black px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-gray-800 dark:bg-white dark:text-black"
                            >
                                Iniciar cámara
                            </button>
                        </div>

                        <div
                            id="qr-reader"
                            ref="readerEl"
                            class="overflow-hidden rounded-lg bg-gray-950"
                            :class="scannerActive ? '' : 'hidden'"
                        ></div>

                        <div
                            v-if="!scannerActive && !scanning"
                            class="flex h-48 items-center justify-center rounded-lg border border-dashed border-gray-300 dark:border-zinc-700"
                        >
                            <div class="text-center">
                                <QrCode
                                    class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600"
                                />
                                <p class="mt-2 text-sm text-gray-400">
                                    {{
                                        scannerError ??
                                        'Cámara apagada. Inicia para escanear.'
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Manual fallback -->
                        <div
                            class="relative mt-4 border-t border-gray-100 pt-4 dark:border-zinc-800"
                        >
                            <label
                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                            >
                                Registro manual (busca por nombre, DNI o código)
                            </label>
                            <div
                                class="flex gap-2"
                            >
                                <input
                                    v-model="manualToken"
                                    type="text"
                                    placeholder="Ej. García, CNV-1234... o GFT-XXXX"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    @input="onSearchInput"
                                    @keyup.enter="submitManual"
                                    @blur="searchOpen = false"
                                />
                                <button
                                    @click="submitManual"
                                    :disabled="processing || searching"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    <Search class="h-4 w-4" /> Buscar
                                </button>
                            </div>

                            <!-- Search results -->
                            <div
                                v-if="searchOpen && searchResults.length"
                                class="absolute left-0 right-0 top-full z-20 mt-1 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <div class="max-h-64 overflow-y-auto">
                                    <button
                                        v-for="user in searchResults"
                                        :key="user.id"
                                        type="button"
                                        :disabled="user.checked_in"
                                        @mousedown.prevent="!user.checked_in && selectUser(user)"
                                        class="flex w-full items-center gap-3 border-b border-gray-100 px-3 py-2.5 text-left transition-colors last:border-b-0 dark:border-zinc-800"
                                        :class="
                                            user.checked_in
                                                ? 'cursor-not-allowed opacity-60'
                                                : 'hover:bg-gray-50 dark:hover:bg-zinc-800'
                                        "
                                    >
                                        <div
                                            class="h-9 w-9 shrink-0 overflow-hidden rounded-full bg-gray-200 dark:bg-zinc-700"
                                        >
                                            <img
                                                v-if="user.photo"
                                                :src="user.photo"
                                                class="h-full w-full object-cover"
                                            />
                                            <UserRound
                                                v-else
                                                class="h-full w-full p-1.5 text-gray-400"
                                            />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ user.name }}
                                            </p>
                                            <p
                                                class="truncate text-xs text-gray-500 dark:text-gray-400"
                                            >
                                                {{ user.dni }}
                                                <span
                                                    v-if="user.affiliation"
                                                    class="text-gray-400 dark:text-gray-500"
                                                >
                                                    · {{ user.affiliation }}
                                                </span>
                                            </p>
                                        </div>
                                        <span
                                            v-if="user.checked_in"
                                            class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300"
                                        >
                                            Ya registrado hoy
                                        </span>
                                        <span
                                            v-else
                                            class="shrink-0 rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300"
                                        >
                                            Registrar
                                        </span>
                                    </button>
                                </div>
                            </div>
                            <div
                                v-else-if="searching"
                                class="absolute left-0 right-0 top-full z-20 mt-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-500 shadow-lg dark:border-zinc-700 dark:bg-zinc-900 dark:text-gray-400"
                            >
                                Buscando...
                            </div>
                        </div>
                    </div>

                    <!-- Result -->
                    <div
                        v-if="result"
                        :class="[
                            'rounded-xl border p-6 shadow-sm',
                            result.success
                                ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950'
                                : 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950',
                        ]"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <CheckCircle2
                                    v-if="result.success"
                                    class="mt-1 h-10 w-10 shrink-0 text-emerald-600 dark:text-emerald-400"
                                />
                                <XCircle
                                    v-else
                                    class="mt-1 h-10 w-10 shrink-0 text-red-600 dark:text-red-400"
                                />
                                <div>
                                    <h3
                                        class="font-semibold text-gray-900 dark:text-white"
                                    >
                                        {{ result.message }}
                                    </h3>
                                    <div
                                        v-if="result.user"
                                        class="mt-3 flex items-center gap-4"
                                    >
                                        <div
                                            class="h-14 w-14 overflow-hidden rounded-full bg-gray-200 dark:bg-zinc-700"
                                        >
                                            <img
                                                v-if="result.user.photo"
                                                :src="result.user.photo"
                                                class="h-full w-full object-cover"
                                            />
                                            <UserRound
                                                v-else
                                                class="h-full w-full p-2 text-gray-400"
                                            />
                                        </div>
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-gray-900 dark:text-white"
                                            >
                                                {{ result.user.name }}
                                            </p>
                                            <p
                                                class="font-mono text-xs text-gray-500 dark:text-gray-400"
                                            >
                                                {{ result.user.dni }}
                                            </p>
                                            <div
                                                v-if="
                                                    result.day_label ||
                                                    result.days_attended !==
                                                        undefined
                                                "
                                                class="mt-2 space-y-1"
                                            >
                                                <p
                                                    v-if="result.day_label"
                                                    class="text-xs font-medium text-gray-600 dark:text-gray-300"
                                                >
                                                    {{ result.day_label }}
                                                </p>
                                                <p
                                                    class="text-xs text-gray-500 dark:text-gray-400"
                                                >
                                                    Asistencia:
                                                    {{
                                                        result.days_attended
                                                    }}
                                                    /
                                                    {{
                                                        result.required_days
                                                    }}
                                                    días requeridos
                                                </p>
                                                <p
                                                    v-if="
                                                        result.certificate_issued
                                                    "
                                                    class="text-xs text-emerald-700 dark:text-emerald-300"
                                                >
                                                    Constancia de evento
                                                    disponible.
                                                </p>
                                                <p
                                                    v-else-if="result.success"
                                                    class="text-xs text-gray-500 dark:text-gray-400"
                                                >
                                                    Faltan
                                                    {{
                                                        (result.required_days ??
                                                            0) -
                                                        (result.days_attended ??
                                                            0)
                                                    }}
                                                    día(s) para la constancia.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button
                                @click="clearResult"
                                class="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Today's list -->
                <div
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div
                        class="mb-4 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-zinc-800"
                    >
                        <h2
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Asistencias registradas
                        </h2>
                        <span
                            class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                        >
                            {{ attendances.length }}
                        </span>
                    </div>

                    <ul
                        v-if="attendances.length > 0"
                        class="max-h-[560px] space-y-2 overflow-y-auto pr-1"
                    >
                        <li
                            v-for="attendance in attendances"
                            :key="attendance.id"
                            class="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-2 dark:border-zinc-800"
                        >
                            <div
                                class="h-9 w-9 flex-shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-zinc-800"
                            >
                                <img
                                    v-if="attendance.user?.profile_photo_path"
                                    :src="
                                        '/storage/' +
                                        attendance.user.profile_photo_path
                                    "
                                    class="h-full w-full object-cover"
                                />
                                <UserRound
                                    v-else
                                    class="h-full w-full p-1.5 text-gray-400"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{ attendance.user?.first_name }}
                                    {{ attendance.user?.last_name }}
                                </p>
                                <p
                                    class="truncate font-mono text-xs text-gray-400"
                                >
                                    {{ attendance.user?.dni }}
                                </p>
                            </div>
                            <span
                                :class="[
                                    'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium',
                                    attendance.certificate_issued
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                        : 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
                                ]"
                            >
                                {{
                                    attendance.certificate_issued
                                        ? 'Constancia'
                                        : 'Sin constancia'
                                }}
                            </span>
                        </li>
                    </ul>

                    <div v-else class="py-10 text-center">
                        <QrCode
                            class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600"
                        />
                        <p
                            class="mt-2 text-sm text-gray-400 dark:text-gray-500"
                        >
                            Aún no hay asistencias registradas.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
