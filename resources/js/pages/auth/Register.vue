<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
</script>

<template>
    <AuthBase
        title="Crear cuenta de Asistente"
        description="Regístrate para participar en el evento"
    >
        <Head title="Registro" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="first_name">Nombre(s)</Label>
                    <Input
                        id="first_name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="given-name"
                        name="first_name"
                        placeholder="Tus nombres"
                    />
                    <InputError :message="errors.first_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="last_name">Apellido(s)</Label>
                    <Input
                        id="last_name"
                        type="text"
                        :tabindex="2"
                        autocomplete="family-name"
                        name="last_name"
                        placeholder="Tus apellidos"
                    />
                    <InputError :message="errors.last_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="affiliation">Institución / Afiliación</Label>
                    <Input
                        id="affiliation"
                        type="text"
                        required
                        :tabindex="3"
                        name="affiliation"
                        placeholder="Universidad, institución o empresa"
                    />
                    <InputError :message="errors.affiliation" />
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-2">
                        <Label for="country">País</Label>
                        <Input
                            id="country"
                            type="text"
                            required
                            :tabindex="4"
                            name="country"
                            placeholder="México"
                        />
                        <InputError :message="errors.country" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="state">Estado</Label>
                        <Input
                            id="state"
                            type="text"
                            required
                            :tabindex="5"
                            name="state"
                            placeholder="Jalisco"
                        />
                        <InputError :message="errors.state" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="email">Correo electrónico</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="6"
                        autocomplete="email"
                        name="email"
                        placeholder="correo@ejemplo.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Contraseña</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="7"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Mínimo 8 caracteres"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmar contraseña</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="8"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Repite tu contraseña"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="9"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Crear cuenta
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                ¿Ya tienes cuenta?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="6"
                >Iniciar sesión</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
