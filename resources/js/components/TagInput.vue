<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const tags = ref<string[]>([]);
const inputValue = ref('');
const inputRef = ref<HTMLInputElement | null>(null);

watch(
    () => props.modelValue,
    (val) => {
        const parsed = val
            ? val.split(/[,;]\s*/).map((s) => s.trim()).filter(Boolean)
            : [];
        if (JSON.stringify(parsed) !== JSON.stringify(tags.value)) {
            tags.value = parsed;
        }
    },
    { immediate: true },
);

watch(tags, () => {
    emit('update:modelValue', tags.value.join(', '));
}, { deep: true });

function addTag() {
    const val = inputValue.value.trim();
    if (val && !tags.value.includes(val)) {
        tags.value.push(val);
    }
    inputValue.value = '';
}

function removeTag(index: number) {
    tags.value.splice(index, 1);
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addTag();
    }
    if (e.key === 'Backspace' && !inputValue.value && tags.value.length) {
        tags.value.pop();
    }
}

function onPaste(e: ClipboardEvent) {
    e.preventDefault();
    const text = e.clipboardData?.getData('text') || '';
    const pasted = text.split(/[,;\n]\s*/).map((s) => s.trim()).filter(Boolean);
    for (const item of pasted) {
        if (!tags.value.includes(item)) {
            tags.value.push(item);
        }
    }
}
</script>

<template>
    <div
        class="flex min-h-[38px] flex-wrap items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800"
        @click="inputRef?.focus()"
    >
        <span
            v-for="(tag, idx) in tags"
            :key="idx"
            class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200"
        >
            {{ tag }}
            <button
                type="button"
                @click.stop="removeTag(idx)"
                class="inline-flex items-center text-indigo-400 hover:text-indigo-600 dark:text-indigo-300 dark:hover:text-indigo-100"
            >
                <X class="h-3 w-3" />
            </button>
        </span>
        <input
            ref="inputRef"
            v-model="inputValue"
            @keydown="onKeydown"
            @paste="onPaste"
            type="text"
            :placeholder="tags.length ? '' : placeholder || 'Escribe y presiona Enter o coma...'"
            class="min-w-[80px] flex-1 border-none bg-transparent p-0 text-sm text-gray-900 placeholder-gray-400 outline-none dark:text-gray-100 dark:placeholder-gray-500"
        />
    </div>
</template>
