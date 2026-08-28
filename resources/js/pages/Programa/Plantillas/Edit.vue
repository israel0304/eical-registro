<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useElementSize } from '@vueuse/core';
import axios from 'axios';
import {
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    Copy,
    GripVertical,
    Image,
    ListTodo,
    QrCode,
    Save,
    Trash2,
    Type,
    Upload,
} from 'lucide-vue-next';
import QRCode from 'qrcode';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
} from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

type ElementType = 'text' | 'qr' | 'image' | 'program';

interface ElementModel {
    _uid: string;
    id: number | null;
    type: ElementType;
    content: string | null;
    variable: string | null;
    x: number;
    y: number;
    width: number | null;
    height: number | null;
    font_size: number | null;
    auto_fit: boolean;
    word_wrap: boolean;
    font_weight: string | null;
    font_family: string | null;
    color: string | null;
    text_align: string;
    z_index: number;
}

const LIST_DEFAULTS: Record<string, any> = {
    show_day: true,
    show_time: true,
    show_location: true,
    show_persons: true,
    accent_color: '#9d174d',
    text_color: '#111827',
    badge_text_color: '#ffffff',
    row_font_size: 13,
    day_font_size: 14,
    time_column: 96,
    bottom_padding: 32,
    row_padding_y: 8,
    type_colors: {
        workshop: '#b45309',
        presentation: '#0369a1',
        conference: '#9d174d',
        block: '#475569',
    },
};

const props = defineProps<{
    template: {
        id: number;
        name: string;
        description: string | null;
        is_default: boolean;
        is_active: boolean;
        background_path: string | null;
        width: number;
        height: number;
        elements: any[];
    };
    variables: { key: string; label: string }[];
    groups: {
        label: string;
        items: Record<string, any>[];
    }[];
    meta: {
        eventName: string;
        fechas: string;
        lugar: string;
    };
}>();

const basePath = '/programa/plantillas';

const form = useForm({
    name: props.template.name,
    description: props.template.description ?? '',
    is_default: props.template.is_default,
    is_active: props.template.is_active ?? true,
    width: props.template.width,
    height: props.template.height,
    background: null as File | null,
    elements: [] as Record<string, unknown>[],
});

const designWidth = computed(() => form.width || 816);
const designHeight = computed(() => form.height || 1056);

let uidCounter = 0;
const nextUid = () => `el_${Date.now()}_${uidCounter++}`;

const elements = ref<ElementModel[]>(
    (props.template.elements ?? []).map((el) => ({
        _uid: nextUid(),
        id: el.id ?? null,
        type: (el.type ?? 'text') as ElementType,
        content: el.content ?? null,
        variable: el.variable ?? null,
        x: Number(el.x ?? 0),
        y: Number(el.y ?? 0),
        width: el.width ? Number(el.width) : null,
        height: el.height ? Number(el.height) : null,
        font_size: el.font_size ? Number(el.font_size) : null,
        auto_fit: Boolean(el.auto_fit ?? false),
        word_wrap: el.word_wrap !== false,
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
    () => elements.value.find((el) => el._uid === selectedUid.value) ?? null,
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

const backgroundPreview = ref<string | null>(null);
const canvasBackground = computed(
    () => backgroundPreview.value ?? backgroundUrl.value,
);

onBeforeUnmount(() => {
    if (backgroundPreview.value) URL.revokeObjectURL(backgroundPreview.value);
});

// Sample data for live preview
const SAMPLE: Record<string, string> = {
    '{evento}': props.meta?.eventName || 'EICAL 2026',
    '{nombre_evento}': props.meta?.eventName || 'EICAL 2026',
    '{fecha_evento}': props.meta?.fechas || '',
    '{lugar_evento}': props.meta?.lugar || '',
    '{pagina}': '1',
    '{total_paginas}': '1',
};

const previewText = (content: string | null) =>
    (content ?? '').replace(
        /\{evento\}|\{nombre_evento\}|\{fecha_evento\}|\{lugar_evento\}|\{pagina\}|\{total_paginas\}/g,
        (m) => SAMPLE[m] ?? m,
    );

const measureCtx = (() => {
    if (typeof document === 'undefined') return null;
    return document.createElement('canvas').getContext('2d');
})();

const fittedFontSize = (el: ElementModel): number => {
    const base = el.font_size || 0;
    if (!base || !el.width) return base;
    const text = previewText(el.content);
    if (!text || !measureCtx) return base;
    measureCtx.font = `${el.font_weight || 'normal'} ${base}px ${el.font_family || 'sans-serif'}`;
    const measured = measureCtx.measureText(text).width;
    if (!measured) return base;
    return Math.min(base, (base * el.width * 0.96) / measured);
};

// QR previews (client-side, for live editing)
const qrPreviews = reactive<Record<string, string>>({});
const qrFor = (el: ElementModel) => {
    if (!qrPreviews[el._uid]) {
        QRCode.toDataURL('/programa/publico', {
            width: 200,
            margin: 1,
        }).then((url) => {
            qrPreviews[el._uid] = url;
        });
    }
    return qrPreviews[el._uid];
};

const addElement = (type: ElementType) => {
    const maxZ = elements.value.reduce(
        (max, el) => Math.max(max, el.z_index),
        0,
    );
    const isProgram = type === 'program';
    const element: ElementModel = {
        _uid: nextUid(),
        id: null,
        type,
        content: isProgram
            ? JSON.stringify(LIST_DEFAULTS)
            : type === 'text'
              ? '{evento}'
              : null,
        variable: null,
        x: isProgram ? 48 : Math.round(designWidth.value / 2 - 200),
        y: isProgram
            ? Math.round(designHeight.value * 0.18)
            : Math.round(designHeight.value / 2 - 30),
        width: isProgram ? designWidth.value - 96 : type === 'text' ? 400 : 200,
        height: isProgram ? null : type === 'text' ? 60 : 200,
        font_size: type === 'text' ? 42 : null,
        auto_fit: false,
        word_wrap: true,
        font_weight: type === 'text' ? 'bold' : null,
        font_family: type === 'text' ? 'Georgia, serif' : null,
        color: type === 'text' ? '#000000' : null,
        text_align: isProgram ? 'left' : 'center',
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

// Image upload
const uploadingImage = ref(false);
const uploadImage = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    uploadingImage.value = true;
    try {
        const formData = new FormData();
        formData.append('file', file);
        const res = await axios.post(basePath + '/upload-image', formData);
        if (selected.value) {
            selected.value.content = res.data.url;
        }
    } catch {
        // ignore
    } finally {
        uploadingImage.value = false;
        input.value = '';
    }
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
        auto_fit: el.auto_fit,
        word_wrap: el.word_wrap,
        font_weight: el.font_weight,
        font_family: el.font_family,
        color: el.color,
        text_align: el.text_align,
        z_index: el.z_index ?? i,
    }));

    form.elements = serverElements;
    form.put(basePath + '/' + props.template.id, {
        preserveScroll: true,
    });
};

const onBackgroundChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    form.background = file;
    if (backgroundPreview.value) URL.revokeObjectURL(backgroundPreview.value);
    backgroundPreview.value = URL.createObjectURL(file);
};

// List (program element) configuration
const listConfig = (el: ElementModel): Record<string, any> => {
    let parsed: Record<string, any> = {};
    if (el.content) {
        try {
            parsed = JSON.parse(el.content) ?? {};
        } catch {
            parsed = {};
        }
    }
    return { ...LIST_DEFAULTS, ...parsed };
};

const saveListConfig = (el: ElementModel, patch: Record<string, any>) => {
    el.content = JSON.stringify({ ...listConfig(el), ...patch });
};

const saveTypeColor = (el: ElementModel, key: string, value: string) => {
    const c = listConfig(el);

    saveListConfig(el, {
        type_colors: { ...(c.type_colors ?? {}), [key]: value },
    });
};

// ---------- Listado real del programa (espejo del renderer PHP) ----------
interface ListRow {
    type: 'day' | 'item';
    label?: string;
    item?: Record<string, any>;
}

const escapeHtml = (value: string) =>
    String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

const buildListRows = (groups: typeof props.groups): ListRow[] => {
    const rows: ListRow[] = [];
    for (const group of groups ?? []) {
        rows.push({ type: 'day', label: group?.label ?? '' });
        for (const item of group?.items ?? []) {
            rows.push({ type: 'item', item });
        }
    }
    return rows;
};

const listBodyWidth = (config: Record<string, any>, listW: number) => {
    const time =
        config.show_time !== false ? Number(config.time_column ?? 96) : 0;

    return Math.max(120, listW - time - 16);
};

const listWrapLines = (
    text: string,
    width: number,
    fontSize: number,
    bold = false,
) => {
    text = String(text).trim();
    if (text === '') return 0;
    const avgChar = fontSize * (bold ? 0.58 : 0.51);
    const charsPerLine = Math.max(1, Math.floor(width / Math.max(1, avgChar)));

    return Math.max(1, Math.ceil(text.length / charsPerLine));
};

const listDayHeight = (config: Record<string, any>) =>
    Math.round(Number(config.day_font_size ?? 14) * 1.4 + 10);

const listItemHeight = (
    item: Record<string, any>,
    config: Record<string, any>,
    listW: number,
) => {
    const colW = listBodyWidth(config, listW);
    const font = Number(config.row_font_size ?? 13);

    let lines = 1;
    lines += listWrapLines(item?.title ?? '', colW, font, true);

    if (config.show_location !== false && item?.location) {
        lines += listWrapLines(item.location, colW, font - 1, false);
    }

    if (config.show_persons !== false) {
        for (const group of item?.people ?? []) {
            const names = (group?.names ?? []).join(', ');
            if (names === '') continue;
            lines += listWrapLines(
                `${group?.label ?? ''}: ${names}`,
                colW,
                font - 2,
                false,
            );
        }
    }

    const pad = Number(config.row_padding_y ?? 8);
    const height = pad * 2 + lines * Math.round(font * 1.3) + 1;

    return Math.max(40, height);
};

const listPaginate = (
    rows: ListRow[],
    config: Record<string, any>,
    budget: number,
    listW: number,
) => {
    const pages: ListRow[][] = [];
    let current: ListRow[] = [];
    let used = 0;

    for (const row of rows) {
        const h =
            row.type === 'day'
                ? listDayHeight(config)
                : listItemHeight(row.item ?? {}, config, listW);

        if (current.length > 0 && used + h > budget) {
            pages.push(current);
            current = [];
            used = 0;
        }

        current.push(row);
        used += h;
    }

    if (current.length > 0) pages.push(current);

    return pages;
};

const badgeKindFor = (item: Record<string, any>) =>
    ['workshop', 'presentation', 'conference'].includes(item?.activity_type)
        ? item.activity_type
        : 'block';

const listRenderList = (
    rows: ListRow[],
    config: Record<string, any>,
    el: ElementModel,
) => {
    const scope = `pl-${el._uid}`;
    const showTime = config.show_time !== false;
    const showLocation = config.show_location !== false;
    const showPersons = config.show_persons !== false;
    const accent = config.accent_color;
    const text = config.text_color;
    const badgeText = config.badge_text_color;
    const font = Number(config.row_font_size ?? 13);
    const dayFont = Number(config.day_font_size ?? 14);
    const timeCol = Number(config.time_column ?? 96);
    const rowPad = Number(config.row_padding_y ?? 8);
    const metaFont = font - 1;
    const peopleFont = font - 2;
    const colors = {
        workshop: config.type_colors?.workshop ?? '#b45309',
        presentation: config.type_colors?.presentation ?? '#0369a1',
        conference: config.type_colors?.conference ?? '#9d174d',
        block: config.type_colors?.block ?? '#475569',
    };

    const css = [
        `.${scope} .pd{font-size:${dayFont}px;font-weight:800;color:${accent};text-transform:uppercase;letter-spacing:.03em;border-bottom:2px solid ${accent};padding-bottom:4px;margin-bottom:2px;}`,
        `.${scope} .pi{display:flex;gap:16px;padding:${rowPad}px 0;border-bottom:1px solid #e5e7eb;}`,
        `.${scope} .pi-time{width:${timeCol}px;font-size:${font}px;font-weight:700;color:${accent};line-height:1.3;flex-shrink:0;}`,
        `.${scope} .pi-body{flex:1;min-width:0;}`,
        `.${scope} .pi-badge{display:inline-block;font-size:9px;text-transform:uppercase;letter-spacing:.04em;color:${badgeText};border-radius:999px;padding:2px 8px;margin-bottom:3px;}`,
        `.${scope} .pi-badge-workshop{background:${colors.workshop};}`,
        `.${scope} .pi-badge-presentation{background:${colors.presentation};}`,
        `.${scope} .pi-badge-conference{background:${colors.conference};}`,
        `.${scope} .pi-badge-block{background:${colors.block};}`,
        `.${scope} .pi-title{font-size:${font}px;font-weight:700;color:${text};line-height:1.3;}`,
        `.${scope} .pi-meta{font-size:${metaFont}px;color:#6b7280;line-height:1.3;margin-top:1px;}`,
        `.${scope} .pi-people{font-size:${peopleFont}px;color:#374151;line-height:1.3;}`,
    ].join('\n');

    let html = `<style>${css}</style><div class="${scope}">`;

    for (const row of rows) {
        if (row.type === 'day') {
            html += `<div class="pd">${escapeHtml(row.label ?? '')}</div>`;

            continue;
        }

        const item = row.item ?? {};
        const badge =
            item.kind === 'activity'
                ? (item.activity_label ?? 'Actividad')
                : (item.block_label ?? 'Actividad');
        const badgeKind = badgeKindFor(item);

        html += `<div class="pi">`;

        if (showTime) {
            html += `<div class="pi-time">${escapeHtml(
                item.time_label ?? '—',
            )}</div>`;
        }

        html += `<div class="pi-body">`;
        html += `<span class="pi-badge pi-badge-${badgeKind}">${escapeHtml(
            badge,
        )}</span>`;
        html += `<div class="pi-title">${escapeHtml(
            String(item.title ?? ''),
        )}</div>`;

        if (showLocation && item?.location) {
            html += `<div class="pi-meta">${escapeHtml(
                String(item.location),
            )}</div>`;
        }

        if (showPersons) {
            for (const group of item?.people ?? []) {
                const names = (group?.names ?? []).join(', ');
                if (names === '') continue;
                html += `<div class="pi-people"><b>${escapeHtml(
                    String(group?.label ?? ''),
                )}:</b> ${escapeHtml(names)}</div>`;
            }
        }

        html += `</div>`;
        html += `</div>`;
    }

    html += `</div>`;

    return html;
};

const listBudget = (el: ElementModel) => {
    const c = listConfig(el);

    return Math.max(
        120,
        designHeight.value - el.y - Number(c.bottom_padding ?? 32),
    );
};

const listFoldY = (el: ElementModel) => el.y + listBudget(el);

const listRenderInfo = (el: ElementModel) => {
    const rows = buildListRows(props.groups ?? []);
    const config = listConfig(el);
    const listW = el.width ?? Math.max(200, designWidth.value - 96);
    const pages = listPaginate(rows, config, listBudget(el), listW);

    if (rows.length === 0) {
        return {
            html: `<div style="border:1px dashed #d1d5db;padding:10px 12px;font-size:12px;color:#9ca3af;">Sin actividades todavía: agrega bloques o enlaza talleres, ponencias y conferencias en la pestaña Programa.</div>`,
            totalPages: 1,
        };
    }

    return {
        html: listRenderList(pages[0] ?? [], config, el),
        totalPages: Math.max(1, pages.length),
    };
};

const renderRealList = (el: ElementModel) => {
    try {
        return listRenderInfo(el).html;
    } catch {
        return '<div style="border:1px dashed #ef4444;padding:10px 12px;font-size:12px;color:#ef4444;">No se pudo generar la vista previa de la lista.</div>';
    }
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
        if (el.font_size)
            style.fontSize =
                (el.auto_fit ? fittedFontSize(el) : el.font_size) + 'px';
        if (el.font_weight) style.fontWeight = el.font_weight;
        if (el.font_family) style.fontFamily = el.font_family;
        if (el.color) style.color = el.color;
        style.textAlign = el.text_align;
        if (!el.word_wrap) {
            style.whiteSpace = 'nowrap';
            style.overflow = 'hidden';
        }
    }
    if (el.type === 'image') {
        style.overflow = 'hidden';
    }
    if (el.type === 'program') {
        style.overflow = 'hidden';
        style.height = listBudget(el) + 'px';
    }
    return style;
};

// Drag de la línea de corte inferior (ajusta bottom_padding)
let foldDrag: { startY: number; origBudget: number } | null = null;

const onFoldPointerDown = (e: PointerEvent) => {
    if (!selected.value || selected.value.type !== 'program') return;
    e.stopPropagation();
    foldDrag = {
        startY: e.clientY,
        origBudget: listBudget(selected.value),
    };
    window.addEventListener('pointermove', onFoldPointerMove);
    window.addEventListener('pointerup', onFoldPointerUp);
};

const onFoldPointerMove = (e: PointerEvent) => {
    if (!foldDrag || !selected.value || selected.value.type !== 'program') {
        return;
    }
    const delta = (e.clientY - foldDrag.startY) / scale.value;
    const minBudget = 120;
    const maxBudget = designHeight.value - selected.value.y;
    const budget = Math.min(
        maxBudget,
        Math.max(minBudget, Math.round(foldDrag.origBudget + delta)),
    );
    saveListConfig(selected.value, {
        bottom_padding: Math.round(
            designHeight.value - selected.value.y - budget,
        ),
    });
};

const onFoldPointerUp = () => {
    foldDrag = null;
    window.removeEventListener('pointermove', onFoldPointerMove);
    window.removeEventListener('pointerup', onFoldPointerUp);
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
            { title: 'Programa', href: '/programa' },
            { title: 'Plantillas del programa', href: basePath },
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
                        :href="basePath"
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
                    <div class="flex items-center gap-2">
                        <label
                            class="text-xs font-medium text-gray-600 dark:text-gray-400"
                        >
                            Ancho
                        </label>
                        <input
                            v-model.number="form.width"
                            type="number"
                            min="200"
                            max="5000"
                            class="w-24 rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                        />
                        <label
                            class="text-xs font-medium text-gray-600 dark:text-gray-400"
                        >
                            Alto
                        </label>
                        <input
                            v-model.number="form.height"
                            type="number"
                            min="200"
                            max="5000"
                            class="w-24 rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                        />
                        <span class="text-xs text-gray-400">px</span>
                    </div>
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
                            @click="addElement('program')"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-2.5 text-sm font-medium text-indigo-700 shadow-sm transition-colors hover:border-indigo-500 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300"
                            title="La lista del programa: flota y ocupa varias páginas"
                        >
                            <ListTodo class="h-4 w-4" />
                            Lista del programa
                        </button>
                        <button
                            @click="addElement('qr')"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:border-indigo-400 hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:text-indigo-400"
                        >
                            <QrCode class="h-4 w-4" /> Agregar QR
                        </button>
                        <button
                            @click="addElement('image')"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:border-indigo-400 hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-300 dark:hover:text-indigo-400"
                        >
                            <Image class="h-4 w-4" /> Agregar imagen
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
                                    :is="
                                        el.type === 'qr'
                                            ? QrCode
                                            : el.type === 'image'
                                              ? Image
                                              : el.type === 'program'
                                                ? ListTodo
                                                : Type
                                    "
                                    class="h-4 w-4 shrink-0"
                                />
                                <span class="flex-1 truncate">
                                    {{
                                        el.type === 'qr'
                                            ? 'QR'
                                            : el.type === 'image'
                                              ? 'Imagen'
                                              : el.type === 'program'
                                                ? 'Lista del programa'
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
                                        :disabled="i === ordered.length - 1"
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
                            Agrega una "Lista del programa" y textos para
                            comenzar.
                        </p>
                    </div>

                    <div
                        class="mt-5 border-t border-gray-200 pt-4 dark:border-zinc-800"
                    >
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
                            <label
                                class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Plantilla activa (se usa en Imprimir/PDF)
                            </label>
                            <p class="text-xs leading-relaxed text-gray-400">
                                La lista se muestra en{" "}
                                <code class="font-mono">{programa}</code>? No:
                                el bloque "Lista del programa" marca dónde
                                empieza el listado. Los elementos que están
                                encima se repiten en cada página; el listado
                                fluye hacia abajo y pagina solo.
                            </p>
                        </div>
                    </div>
                </aside>

                <!-- Canvas -->
                <main
                    class="flex-1 overflow-auto bg-gray-100 p-6 dark:bg-zinc-950"
                >
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
                                v-if="canvasBackground"
                                :src="canvasBackground"
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
                                <img
                                    v-else-if="el.type === 'image'"
                                    :src="el.content || ''"
                                    class="pointer-events-none h-full w-full object-contain"
                                    draggable="false"
                                />
                                <div
                                    v-else-if="el.type === 'program'"
                                    class="pointer-events-none max-h-full overflow-hidden"
                                    v-html="renderRealList(el)"
                                ></div>
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
                            <div
                                v-if="selected?.type === 'program'"
                                class="absolute inset-x-0 z-[60] h-4 cursor-ns-resize"
                                :style="{
                                    top: listFoldY(selected) - 8 + 'px',
                                }"
                                @pointerdown="onFoldPointerDown"
                            >
                                <div
                                    class="pointer-events-none absolute inset-x-0 top-2 border-t-2 border-dashed border-red-400"
                                ></div>
                                <div
                                    class="pointer-events-none absolute top-1/2 left-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center gap-1 rounded-full border border-red-300 bg-white px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap text-red-600 shadow-sm select-none dark:border-red-900 dark:bg-zinc-800"
                                    title="Arrastra para subir o bajar el punto donde corta la lista"
                                >
                                    <GripVertical class="h-3 w-3" />
                                    Corte inferior
                                </div>
                            </div>
                            <div
                                v-if="
                                    selected?.type === 'program' &&
                                    listRenderInfo(selected).totalPages > 1
                                "
                                class="pointer-events-none absolute inset-x-0 z-[61] flex justify-center"
                                :style="{
                                    top: listFoldY(selected) + 10 + 'px',
                                }"
                            >
                                <div
                                    class="rounded-full bg-red-600 px-3 py-0.5 text-center text-[10px] font-semibold text-white shadow-sm"
                                >
                                    La lista continúa en la siguiente página
                                    repitiendo este encabezado y fondo · Página
                                    1 de
                                    {{ listRenderInfo(selected).totalPages }}
                                </div>
                            </div>
                        </div>
                    </div>
                </main>

                <!-- Right: properties -->
                <aside
                    class="w-full border-t border-gray-200 p-4 lg:w-72 lg:border-t-0 lg:border-l dark:border-zinc-800"
                >
                    <template v-if="selected">
                        <div class="mb-3 flex items-center justify-between">
                            <h3
                                class="text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    selected.type === 'qr'
                                        ? 'Código QR'
                                        : selected.type === 'image'
                                          ? 'Imagen'
                                          : selected.type === 'program'
                                            ? 'Lista del programa'
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

                        <div class="grid grid-cols-2 gap-2">
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
                                    :disabled="selected.type === 'program'"
                                    class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                />
                            </div>
                        </div>

                        <template v-if="selected.type === 'text'">
                            <div class="mt-3 grid grid-cols-2 gap-2">
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
                            <label
                                class="mt-3 flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    v-model="selected.auto_fit"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Ajustar automáticamente
                            </label>
                            <label
                                class="mt-1 flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    v-model="selected.word_wrap"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Permitir salto de línea
                            </label>
                            <div class="mt-3 grid grid-cols-2 gap-2">
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
                                        <option value="justify">
                                            Justificado
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </template>

                        <template v-else-if="selected.type === 'image'">
                            <div class="mt-3">
                                <label
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                >
                                    Imagen
                                </label>
                                <label
                                    class="mt-1 flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-500 transition-colors hover:border-indigo-400 hover:text-indigo-600 dark:border-zinc-600 dark:bg-zinc-800 dark:text-gray-400 dark:hover:border-indigo-500"
                                >
                                    <Upload class="h-5 w-5" />
                                    <span v-if="uploadingImage"
                                        >Subiendo...</span
                                    >
                                    <span v-else>
                                        {{
                                            selected.content
                                                ? 'Cambiar imagen'
                                                : 'Seleccionar imagen'
                                        }}
                                    </span>
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                        class="hidden"
                                        @change="uploadImage"
                                    />
                                </label>
                                <img
                                    v-if="selected.content"
                                    :src="selected.content"
                                    class="mt-2 max-h-32 w-full rounded-md border border-gray-200 object-contain dark:border-zinc-700"
                                />
                            </div>
                        </template>
                        <template v-else-if="selected.type === 'qr'">
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
                                <p class="mt-1 text-xs text-gray-400">
                                    Apunta a la versión pública del programa.
                                </p>
                            </div>
                        </template>

                        <template v-else-if="selected.type === 'program'">
                            <div class="space-y-3">
                                <p
                                    class="text-xs leading-relaxed text-gray-400"
                                >
                                    El listado empieza aquí y fluye hacia abajo.
                                    Todo lo que está encima de este bloque se
                                    repite en cada página.
                                </p>

                                <div class="grid grid-cols-2 gap-x-2 gap-y-3">
                                    <label
                                        class="flex items-center gap-2 text-xs font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                listConfig(selected).show_day
                                            "
                                            @change="
                                                saveListConfig(selected, {
                                                    show_day: (
                                                        $event.target as HTMLInputElement
                                                    ).checked,
                                                })
                                            "
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        Encabezados de día
                                    </label>
                                    <label
                                        class="flex items-center gap-2 text-xs font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                listConfig(selected).show_time
                                            "
                                            @change="
                                                saveListConfig(selected, {
                                                    show_time: (
                                                        $event.target as HTMLInputElement
                                                    ).checked,
                                                })
                                            "
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        Columna hora
                                    </label>
                                    <label
                                        class="flex items-center gap-2 text-xs font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                listConfig(selected)
                                                    .show_location
                                            "
                                            @change="
                                                saveListConfig(selected, {
                                                    show_location: (
                                                        $event.target as HTMLInputElement
                                                    ).checked,
                                                })
                                            "
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        Ubicación
                                    </label>
                                    <label
                                        class="flex items-center gap-2 text-xs font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                listConfig(selected)
                                                    .show_persons
                                            "
                                            @change="
                                                saveListConfig(selected, {
                                                    show_persons: (
                                                        $event.target as HTMLInputElement
                                                    ).checked,
                                                })
                                            "
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        Personas
                                    </label>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                        >
                                            Texto de fila
                                        </label>
                                        <input
                                            :value="
                                                listConfig(selected)
                                                    .row_font_size
                                            "
                                            type="number"
                                            min="8"
                                            max="30"
                                            @change="
                                                saveListConfig(selected, {
                                                    row_font_size: (
                                                        $event.target as HTMLInputElement
                                                    ).valueAsNumber,
                                                })
                                            "
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                        >
                                            Texto de día
                                        </label>
                                        <input
                                            :value="
                                                listConfig(selected)
                                                    .day_font_size
                                            "
                                            type="number"
                                            min="8"
                                            max="36"
                                            @change="
                                                saveListConfig(selected, {
                                                    day_font_size: (
                                                        $event.target as HTMLInputElement
                                                    ).valueAsNumber,
                                                })
                                            "
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                        >
                                            Ancho hora (px)
                                        </label>
                                        <input
                                            :value="
                                                listConfig(selected).time_column
                                            "
                                            type="number"
                                            min="40"
                                            max="300"
                                            @change="
                                                saveListConfig(selected, {
                                                    time_column: (
                                                        $event.target as HTMLInputElement
                                                    ).valueAsNumber,
                                                })
                                            "
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                        >
                                            Padding fila (px)
                                        </label>
                                        <input
                                            :value="
                                                listConfig(selected)
                                                    .row_padding_y
                                            "
                                            type="number"
                                            min="0"
                                            max="40"
                                            @change="
                                                saveListConfig(selected, {
                                                    row_padding_y: (
                                                        $event.target as HTMLInputElement
                                                    ).valueAsNumber,
                                                })
                                            "
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                        >
                                            Corte inferior (px)
                                        </label>
                                        <input
                                            :value="
                                                listConfig(selected)
                                                    .bottom_padding
                                            "
                                            type="number"
                                            min="0"
                                            max="400"
                                            @change="
                                                saveListConfig(selected, {
                                                    bottom_padding: (
                                                        $event.target as HTMLInputElement
                                                    ).valueAsNumber,
                                                })
                                            "
                                            class="w-full rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-gray-100"
                                        />
                                        <p
                                            class="mt-1 text-[10px] leading-snug text-gray-400"
                                        >
                                            Distancia mínima desde el borde
                                            inferior de la página: corta antes
                                            si la lista no cabe. También puedes
                                            arrastrar la línea roja punteada.
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        Color de acento
                                    </label>
                                    <input
                                        :value="
                                            listConfig(selected).accent_color
                                        "
                                        type="color"
                                        @change="
                                            saveListConfig(selected, {
                                                accent_color: (
                                                    $event.target as HTMLInputElement
                                                ).value,
                                            })
                                        "
                                        class="h-9 w-full cursor-pointer rounded-md border border-gray-200 bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        Color de los títulos
                                    </label>
                                    <input
                                        :value="listConfig(selected).text_color"
                                        type="color"
                                        @change="
                                            saveListConfig(selected, {
                                                text_color: (
                                                    $event.target as HTMLInputElement
                                                ).value,
                                            })
                                        "
                                        class="h-9 w-full cursor-pointer rounded-md border border-gray-200 bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800"
                                    />
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                    >
                                        Color del texto de la etiqueta
                                    </label>
                                    <input
                                        :value="
                                            listConfig(selected)
                                                .badge_text_color
                                        "
                                        type="color"
                                        @change="
                                            saveListConfig(selected, {
                                                badge_text_color: (
                                                    $event.target as HTMLInputElement
                                                ).value,
                                            })
                                        "
                                        class="h-9 w-full cursor-pointer rounded-md border border-gray-200 bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800"
                                    />
                                </div>

                                <div
                                    class="border-t border-gray-200 pt-3 dark:border-zinc-800"
                                >
                                    <div
                                        class="mb-2 text-[11px] font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    >
                                        Color del badge por tipo
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div
                                            v-for="type in [
                                                ['workshop', 'Taller'],
                                                ['presentation', 'Ponencia'],
                                                ['conference', 'Conferencia'],
                                                ['block', 'Bloques'],
                                            ]"
                                            :key="type[0]"
                                        >
                                            <label
                                                class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400"
                                            >
                                                {{ type[1] }}
                                            </label>
                                            <input
                                                :value="
                                                    listConfig(selected)
                                                        .type_colors?.[
                                                        type[0]
                                                    ] ??
                                                    LIST_DEFAULTS.type_colors[
                                                        type[0]
                                                    ]
                                                "
                                                type="color"
                                                @change="
                                                    saveTypeColor(
                                                        selected,
                                                        type[0],
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    )
                                                "
                                                class="h-9 w-full cursor-pointer rounded-md border border-gray-200 bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>

                    <div v-else class="pt-6 text-center">
                        <ListTodo
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
