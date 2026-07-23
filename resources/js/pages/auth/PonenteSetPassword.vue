<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';

defineProps<{
    email: string;
    name: string;
}>();

const form = useForm({
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('ponente.set-password'));
}
</script>

<template>
    <AuthLayout
        title="Establecer Contraseña"
        description="Crea una contraseña para acceder al sistema"
    >
        <Head title="Establecer contraseña" />

        <div class="mb-4 text-center text-sm text-muted-foreground">
            Hola <strong>{{ name }}</strong> ({{ email }})
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="password">Contraseña</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autofocus
                        minlength="8"
                        placeholder="Mínimo 8 caracteres"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmar Contraseña</Label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        placeholder="Repite tu contraseña"
                    />
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Guardar Contraseña
                </Button>
            </div>
        </form>
    </AuthLayout>
</template>
