<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const flashMessage = ref<string | null>(null);
const flashType = ref<'success' | 'error'>('success');

function checkFlash() {
    const flash = (page.props as any).flash as Record<string, string> | undefined;
    if (flash?.success) {
        flashMessage.value = flash.success;
        flashType.value = 'success';
    } else if (flash?.error) {
        flashMessage.value = flash.error;
        flashType.value = 'error';
    }
    if (flashMessage.value) {
        setTimeout(() => (flashMessage.value = null), 5000);
    }
}

watch(() => page.props, checkFlash, { immediate: true });

const dismissFlash = () => {
    flashMessage.value = null;
};
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <div
                v-if="flashMessage"
                :class="[
                    'mx-4 mt-4 flex items-center justify-between rounded-lg px-4 py-3 text-sm font-medium',
                    flashType === 'success'
                        ? 'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200'
                        : 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200',
                ]"
            >
                <span>{{ flashMessage }}</span>
                <button @click="dismissFlash" class="shrink-0 ml-3">
                    <X class="h-4 w-4" />
                </button>
            </div>
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
</template>
