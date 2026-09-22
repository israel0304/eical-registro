<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

withDefaults(
    defineProps<{
        form: any;
        submitLabel?: string;
    }>(),
    {
        submitLabel: 'Crear cuenta',
    },
);
</script>

<template>
    <Form
        v-bind="form"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="register_first_name">Nombre(s)</Label>
                <Input
                    id="register_first_name"
                    type="text"
                    required
                    autofocus
                    autocomplete="given-name"
                    name="first_name"
                    placeholder="Tus nombres"
                />
                <InputError :message="errors.first_name" />
            </div>

            <div class="grid gap-2">
                <Label for="register_last_name">Apellido(s)</Label>
                <Input
                    id="register_last_name"
                    type="text"
                    autocomplete="family-name"
                    name="last_name"
                    placeholder="Tus apellidos"
                />
                <InputError :message="errors.last_name" />
            </div>

            <div class="grid gap-2">
                <Label for="register_affiliation">Institución / Afiliación</Label>
                <Input
                    id="register_affiliation"
                    type="text"
                    required
                    name="affiliation"
                    placeholder="Universidad, institución o empresa"
                />
                <InputError :message="errors.affiliation" />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="grid gap-2">
                    <Label for="register_country">País</Label>
                    <Input
                        id="register_country"
                        type="text"
                        required
                        name="country"
                        placeholder="México"
                    />
                    <InputError :message="errors.country" />
                </div>
                <div class="grid gap-2">
                    <Label for="register_state">Estado</Label>
                    <Input
                        id="register_state"
                        type="text"
                        required
                        name="state"
                        placeholder="Jalisco"
                    />
                    <InputError :message="errors.state" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="register_email">Correo electrónico</Label>
                <Input
                    id="register_email"
                    type="email"
                    required
                    autocomplete="email"
                    name="email"
                    placeholder="correo@ejemplo.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="register_password">Contraseña</Label>
                <Input
                    id="register_password"
                    type="password"
                    required
                    autocomplete="new-password"
                    name="password"
                    placeholder="Mínimo 8 caracteres"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="register_password_confirmation"
                    >Confirmar contraseña</Label
                >
                <Input
                    id="register_password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Repite tu contraseña"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ submitLabel }}
            </Button>
        </div>
    </Form>
</template>