<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useElementSize } from '@vueuse/core';
import {
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    Copy,
    Image,
    QrCode,
    Save,
    Trash2,
    Type,
} from 'lucide-vue-next';
import QRCode from 'qrcode';
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

interface ElementModel {
    _uid: string;
    id: number | null;
    type: 'text' | 'qr';
    content: string | null;
    variable: string | null;
    x: number;
    y: number;
    width: number | null;
    height: number | null;
    font_size: number | null;
    font_weight: string | null;
    font_family: string | null;
    color: string | null;
    text_align: string;
    z_index: number;
}

const props = defineProps<{
    template: {
        id: number;
        name: string;
        description: string | null;
        participation_type_id: number | null;
        is_default: boolean;
        background_path: string | null;
        width: number;
        height: number;
        elements: any[];
    };
    variables: { key: string; label: string }[];
    participationTypes?: {
        id: number;
        key: string;
        label: string;
        event_kind: string;
        role: string;
    }[];
}>();

const form = useForm({
    name: props.template.name,
    description: props.template.description ?? '',
    participation_type_id: props.template.participation_type_id ?? '',
    is_default: props.template.is_default,
    width: props.template.width,
    height: props.template.height,
    background: null as File | null,
    elements: [] as Record<string, unknown>[],
});

const designWidth = computed(() => form.width || 1800);
const designHeight = computed(() => form.height || 1200);

let uidCounter = 0;
const nextUid = () => `el_${Date.now()}_${uidCounter++}`;

const elements = ref<ElementModel[]>(
    (props.template.elements ?? []).map((el) => ({
        _uid: nextUid(),
        id: el.id ?? null,
        type: el.type ?? 'text',
        content: el.content ?? null,
        variable: el.variable ?? null,
        x: Number(el.x ?? 0),
        y: Number(el.y ?? 0),
        width: el.width ? Number(el.width) : null,
        height: el.height ? Number(el.height) : null,
        font_size: el.font_size ? Number(el.font_size) : null,
        font_weight: el.font_weight ?? null,
        font_family: el.font_family ?? null,
        color: el.color ?? null,
        text_align: el.text_align ?? 'center',
        z_index: Number(el.z_index ?? 0),
    })),
);

const ordered = computed(() =>
    [...elements.value].sort((a, b) => a.z_index - b.z_index),
);

const selectedUid = ref<string | null>(null);
const selected = computed(
    () =>
        elements.value.find((el) => el._uid === selectedUid.value) ?? null,
);

// Canvas scaling
const wrapperRef = ref<HTMLElement | null>(null);
const { width: wrapperWidth } = useElementSize(wrapperRef);
const scale = computed(() => {
    if (!wrapperWidth.value || !designWidth.value) return 1;
    return Math.min(wrapperWidth.value / designWidth.value, 1);
});
const wrapperHeight = computed(() => designHeight.value * scale.value);

const backgroundUrl = computed(() =>
    props.template.background_path
        ? '/storage/' + props.template.background_path
        : null,
);

// Sample data for live preview
const SAMPLE: Record<string, string> = {
    '{nombre}': 'María Fernanda López',
    '{tipo_participacion}': 'Asistente a taller',
    '{evento}': 'Taller de Fotografía Científica',
    '{fecha_evento}': '12 de agosto de 2026',
    '{folio}': 'EICAL-2026-0001',
};

const previewText = (content: string | null) =>
    (content ?? '').replace(
        /\{nombre\}|\{tipo_participacion\}|\{evento\}|\{fecha_evento\}|\{folio\}/g,
        (m) => SAMPLE[m] ?? m,
    );

// QR previews (client-side, for live editing)
const qrPreviews = reactive<Record<string, string>>({});
const qrFor = (el: ElementModel) => {
    if (!qrPreviews[el._uid]) {
        QRCode.toDataURL('https://eical.cinvestav.mx/constancias/verificar/EICAL-2026-0001', {
            width: 200,
            margin: 1,
        }).then((url) => {
            qrPreviews[el._uid] = url;
        });
    }
    return qrPreviews[el._uid];
};

const addElement = (type: 'text' | 'qr') => {
    const maxZ = elements.value.reduce(
        (max, el) => Math.max(max, el.z_index),
        0,
    );
    const element: ElementModel = {
        _uid: nextUid(),
        id: null,
        type,
        content: type === 'text' ? '{nombre}' : null,
        variable: null,
        x: Math.round(designWidth.value / 2 - 200),
        y: Math.round(designHeight.value / 2 - 30),
        width: 400,
        height: 60,
        font_size: type === 'text' ? 42 : null,
        font_weight: type === 'text' ? 'bold' : null,
        font_family: type === 'text' ? 'Georgia, serif' : null,
        color: type === 'text' ? '#000000' : null,
        text_align: 'center',
        z_index: maxZ + 1,
    };
    elements.value.push(element);
    selectedUid.value = element._uid;
    if (type === 'qr') {
        qrFor(element);
    }
};

const removeElement = (uid: string) => {
    elements.value = elements.value.filter((el) => el._uid !== uid);
    if (selectedUid.value === uid) selectedUid.value = null;
};

const moveZ = (uid: string, dir: -1 | 1) => {
    const sorted = [...elements.value].sort((a, b) => a.z_index - b.z_index);
    const index = sorted.findIndex((el) => el._uid === uid);
    const target = index + dir;
    if (target < 0 || target >= sorted.length) return;
    const a = sorted[index];
    const b = sorted[target];
    const tmp = a.z_index;
    a.z_index = b.z_index;
    b.z_index = tmp;
};

const duplicateElement = (uid: string) => {
    const source = elements.value.find((el) => el._uid === uid);
    if (!source) return;
    const copy: ElementModel = {
        ...source,
        _uid: nextUid(),
        id: null,
        x: source.x + 20,
        y: source.y + 20,
        z_index: source.z_index + 1,
    };
    elements.value.push(copy);
    selectedUid.value = copy._uid;
    if (copy.type === 'qr') qrFor(copy);
};

// Drag & drop
let dragState: {
    uid: string;
    startX: number;
    startY: number;
    origX: number;
    origY: number;
} | null = null;

const onElementPointerDown = (e: PointerEvent, el: ElementModel) => {
    selectedUid.value = el._uid;
    e.stopPropagation();
    dragState = {
        uid: el._uid,
        startX: e.clientX,
        startY: e.clientY,
        origX: el.x,
        origY: el.y,
    };
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', onPointerUp);
};

const onPointerMove = (e: PointerEvent) => {
    if (!dragState) return;
    const el = elements.value.find((x) => x._uid === dragState!.uid);
    if (!el) return;
    el.x = Math.round(
        dragState.origX + (e.clientX - dragState.startX) / scale.value,
    );
    el.y = Math.round(
        dragState.origY + (e.clientY - dragState.startY) / scale.value,
    );
};

const onPointerUp = () => {
    dragState = null;
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);
};

const onCanvasPointerDown = () => {
    selectedUid.value = null;
};

// Keyboard delete
const onKeyDown = (e: KeyboardEvent) => {
    const tag = (e.target as HTMLElement)?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
    if ((e.key === 'Delete' || e.key === 'Backspace') && selected.value) {
        e.preventDefault();
        removeElement(selected.value._uid);
    }
};

// Insert variable into selected text element
const insertVariable = (key: string) => {
    if (!selected.value || selected.value.type !== 'text') return;
    selected.value.content = (selected.value.content ?? '') + key;
};

// Save
const save = () => {
    const serverElements = elements.value.map((el, i) => ({
        type: el.type,
        content: el.content,
        variable: el.variable,
        x: el.x,
        y: el.y,
        width: el.width,
        height: el.height,
        font_size: el.font_size,
        font_weight: el.font_weight,
        font_family: el.font_family,
        color: el.color,
        text_align: el.text_align,
        z_index: el.z_index ?? i,
    }));

    form.elements = serverElements;
    form.put('/admin/constancias/plantillas/' + props.template.id, {
        preserveScroll: true,
    });
};

const onBackgroundChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    form.background = file;
};

const elementStyle = (el: ElementModel) => {
    const style: Record<string, string> = {
        left: el.x + 'px',
        top: el.y + 'px',
        zIndex: String(el.z_index),
    };
    if (el.width) style.width = el.width + 'px';
    if (el.height) style.height = el.height + 'px';
    if (el.type === 'text') {
        if (el.font_size) style.fontSize = el.font_size + 'px';
        if (el.font_weight) style.fontWeight = el.font_weight;
        if (el.font_family) style.fontFamily = el.font_family;
        if (el.color) style.color = el.color;
        style.textAlign = el.text_align;
    }
    return style;
};

const FONT_FAMILIES = [
    'Georgia, serif',
    'Arial, sans-serif',
    "'Times New Roman', serif",
    'Courier New, monospace',
    'Verdana, sans-serif',
    'Impact, sans-serif',
];
const FONT_WEIGHTS = ['normal', 'bold', 'italic', '600', '700', '800'];

onMounted(() => {
    window.addEventListener('keydown', onKeyDown);
    nextTick(() => {
        elements.value.forEach((el) => {
            if (el.type === 'qr') qrFor(el);
        });
    });
});
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Plantillas', href: '/admin/constancias/plantillas' },
            { title: form.name || 'Editor', href: '#' },
        ]"
    >
        <Head :title="'Editor: ' + form.name" />

        <div class="flex h-full min-h-screen flex-col">
            <!-- Top bar -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-3 dark:border-zinc-800"
            >
                <div class="flex items-center gap-3">
                    <Link
                        href="/admin/constancias/plantillas"
                        class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </Link>
                    <div>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full rounded-md border border-transparent bg-transparent px-2 py-1 text-lg font-semibold text-gray-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:text-white"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <label
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-600 shadow-sm transition-colors hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-indigo-400"
                        title="Cambiar fondo (PNG)"
                    >
                        <Image class="h-4 w-4" />
                        {{
                            form.background
                                ? 'Fondo nuevo seleccionado'
                                : 'Fondo'
                        }}
                        <input
                            type="file"
                            accept="image/png"
                            class="hidden"
                            @change="onBackgroundChange"
                        />
                    </label>
                    <span
                        class="rounded-md bg-gray-100 px-3 py-1.5 text-xs text-gray-600 dark:bg-zinc-800 dark:text-gray-400"
                    >
                        {{ designWidth }} × {{ designHeight }} px
                    </span>
                    <button
                        @click="save"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <Save class="h-4 w-4" /> Guardar
                    </button>
                </div>
            </div>

            <div
                v-if="Object.keys(form.errors).length > 0"
                class="border-b border-red-200 bg-red-50 px-6 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
            >
                <ul class="list-inside list-disc">
                    <li v-for="(message, key) in form.errors" :key="key">
                        {{ message }}
                    </li>
                </ul>
            </div>

            <div class="flex flex-1 flex-col lg:flex-row">
                <!-- Left: palette + layers -->
                <aside
                    class="w-full border-b border-gray-200 p-4 lg:w-64 lg:border-r lg:border-b-0 dark:border-zinc-800"
                >
                    <div class="grid grid-cols-2 gap-2 lg:grid-cols-1">
                        <button
                            @click="addElement('text')"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:border-indigo-400 hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:text-indigo-400"
                        >
                            <Type class="h-4 w-4" /> Agregar texto
                        </button>
                        <button
                            @click="addElement('qr')"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:border-indigo-400 hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:text-indigo-400"
                        >
                            <QrCode class="h-4 w-4" /> Agregar QR
                        </button>
                    </div>

                    <div class="mt-4">
                        <div
                            class="mb-2 text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Capas
                        </div>
                        <ul class="space-y-1">
                            <li
                                v-for="(el, i) in ordered"
                                :key="el._uid"
                                :class="[
                                    'group flex cursor-pointer items-center gap-2 rounded-md border px-2 py-1.5 text-sm transition-colors',
                                    selectedUid === el._uid
                                        ? 'border-indigo-400 bg-indigo-50 text-indigo-700 dark:border-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                        : 'border-transparent text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-zinc-800',
                                ]"
                                @click="selectedUid = el._uid"
                            >
                                <component
                                    :is="el.type === 'qr' ? QrCode : Type"
                                    class="h-4 w-4 shrink-0"
                                />
                                <span class="flex-1 truncate">
                                    {{
                                        el.type === 'qr'
                                            ? 'QR'
                                            : previewText(el.content) ||
                                              '(vacío)'
                                    }}
                                </span>
                                <span
                                    class="hidden shrink-0 items-center gap-0.5 group-hover:flex"
                                >
                                    <button
                                        @click.stop="moveZ(el._uid, -1)"
                                        :disabled="i === 0"
                                        class="rounded p-0.5 hover:text-black disabled:opacity-30 dark:hover:text-white"
                                    >
                                        <ArrowUp class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        @click.stop="moveZ(el._uid, 1)"
                                        :disabled="
                                            i === ordered.length - 1
                                        "
                                        class="rounded p-0.5 hover:text-black disabled:opacity-30 dark:hover:text-white"
                                    >
                                        <ArrowDown class="h-3.5 w-3.5" />
                                    </button>
                                </span>
                            </li>
                        </ul>
                        <p
                            v-if="elements.length === 0"
                            class="mt-2 text-xs text-gray-400"
                        >
                            Agrega textos o un QR para comenzar.
                        </p>
                    </div>

                    <div class="mt-5 border-t border-gray-200 pt-4 dark:border-zinc-800">
                        <div
                            class="mb-2 text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"
                        >
                            Configuración
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Descripción
                                </label>
                                <textarea
                                    v-model="form.description"
                                    rows="2"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                ></textarea>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Tipo de participación
                                </label>
                                <select
                                    v-model="form.participation_type_id"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                >
                                    <option
                                        v-for="type in participationTypes ?? []"
                                        :key="type.id"
                                        :value="type.id"
                                    >
                                        {{ type.label }}
                                    </option>
                                </select>
                            </div>
                            <label
                                class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    v-model="form.is_default"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Plantilla por defecto
                            </label>
                        </div>
                    </div>
                </aside>

                <!-- Canvas -->
                <main class="flex-1 overflow-auto bg-gray-100 p-6 dark:bg-zinc-950">
                    <div
                        ref="wrapperRef"
                        :style="{ height: wrapperHeight + 'px' }"
                        class="relative mx-auto"
                        @pointerdown="onCanvasPointerDown"
                    >
                        <div
                            class="absolute top-0 left-0 overflow-hidden bg-white shadow-2xl ring-1 ring-black/5"
                            :style="{
                                width: designWidth + 'px',
                                height: designHeight + 'px',
                                transform: 'scale(' + scale + ')',
                                transformOrigin: 'top left',
                            }"
                        >
                            <img
                                v-if="backgroundUrl"
                                :src="backgroundUrl"
                                class="pointer-events-none absolute inset-0 h-full w-full select-none"
                                draggable="false"
                            />
                            <div
                                v-for="el in ordered"
                                :key="el._uid"
                                class="absolute cursor-move select-none"
                                :style="elementStyle(el)"
                                @pointerdown="onElementPointerDown($event, el)"
                            >
                                <img
                                    v-if="el.type === 'qr'"
                                    :src="qrFor(el)"
                                    class="pointer-events-none h-full w-full"
                                    draggable="false"
                                />
                                <div
                                    v-else
                                    class="pointer-events-none min-h-full whitespace-pre-line"
                                >
                                    {{ previewText(el.content) }}
                                </div>
                                <div
                                    v-if="selectedUid === el._uid"
                                    class="pointer-events-none absolute -inset-1 rounded border-2 border-indigo-500"
                                ></div>
                            </div>
                        </div>
                    </div>
                </main>

                <!-- Right: properties -->
                <aside
                    class="w-full border-t border-gray-200 p-4 lg:w-72 lg:border-l lg:border-t-0 dark:border-zinc-800"
                >
                    <template v-if="selected">
                        <div
                            class="mb-3 flex items-center justify-between"
                        >
                            <h3
                                class="text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    selected.type === 'qr'
                                        ? 'Código QR'
                                        : 'Texto'
                                }}
                            </h3>
                            <div class="flex items-center gap-1">
                                <button
                                    @click="duplicateElement(selected._uid)"
                                    class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-black dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-white"
                                    title="Duplicar"
                                >
                                    <Copy class="h-4 w-4" />
                                </button>
                                <button
                                    @click="removeElement(selected._uid)"
                                    class="rounded border border-gray-300 bg-white p-1.5 text-gray-600 shadow-sm transition-colors hover:text-red-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-400 dark:hover:text-red-400"
                                    title="Eliminar"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <template v-if="selected.type === 'text'">
                            <label
                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                            >
                                Contenido
                            </label>
                            <textarea
                                v-model="selected.content"
                                rows="4"
                                class="mb-2 w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                            ></textarea>

                            <label
                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                            >
                                Insertar variable
                            </label>
                            <div class="mb-3 flex flex-wrap gap-1">
                                <button
                                    v-for="variable in variables"
                                    :key="variable.key"
                                    @click="insertVariable(variable.key)"
                                    class="rounded bg-indigo-50 px-2 py-1 font-mono text-[11px] text-indigo-700 transition-colors hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300"
                                    :title="variable.label"
                                >
                                    {{ variable.key }}
                                </button>
                            </div>
                        </template>

                        <div
                            class="grid grid-cols-2 gap-2"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    X (px)
                                </label>
                                <input
                                    v-model.number="selected.x"
                                    type="number"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Y (px)
                                </label>
                                <input
                                    v-model.number="selected.y"
                                    type="number"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Ancho
                                </label>
                                <input
                                    v-model.number="selected.width"
                                    type="number"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Alto
                                </label>
                                <input
                                    v-model.number="selected.height"
                                    type="number"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>
                        </div>

                        <template v-if="selected.type === 'text'">
                            <div
                                class="mt-3 grid grid-cols-2 gap-2"
                            >
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        Tamaño
                                    </label>
                                    <input
                                        v-model.number="selected.font_size"
                                        type="number"
                                        min="4"
                                        max="400"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        Peso
                                    </label>
                                    <select
                                        v-model="selected.font_weight"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    >
                                        <option
                                            v-for="weight in FONT_WEIGHTS"
                                            :key="weight"
                                            :value="weight"
                                        >
                                            {{ weight }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Fuente
                                </label>
                                <select
                                    v-model="selected.font_family"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                >
                                    <option
                                        v-for="family in FONT_FAMILIES"
                                        :key="family"
                                        :value="family"
                                    >
                                        {{ family }}
                                    </option>
                                </select>
                            </div>
                            <div
                                class="mt-3 grid grid-cols-2 gap-2"
                            >
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        Color
                                    </label>
                                    <input
                                        v-model="selected.color"
                                        type="color"
                                        class="h-9 w-full cursor-pointer rounded-md border border-gray-200 bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        Alineación
                                    </label>
                                    <select
                                        v-model="selected.text_align"
                                        class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                    >
                                        <option value="left">Izquierda</option>
                                        <option value="center">Centro</option>
                                        <option value="right">Derecha</option>
                                    </select>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div class="mt-3">
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Tamaño del QR
                                </label>
                                <input
                                    v-model.number="selected.width"
                                    type="number"
                                    min="40"
                                    max="800"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>
                        </template>
                    </template>

                    <div v-else class="pt-6 text-center">
                        <QrCode
                            class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600"
                        />
                        <p
                            class="mt-2 text-sm text-gray-400 dark:text-gray-500"
                        >
                            Selecciona un elemento del lienzo o agrégale uno
                            nuevo para editar sus propiedades.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
