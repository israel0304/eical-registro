# Design Spec: Módulo de Notificaciones por Correo

## Overview

Nuevo módulo admin para enviar correos masivos a audiencias del sistema, con
composición directa o reutilizando `EmailTemplate` existentes del módulo de
Correos. Soporta cuatro audiencias: todos los usuarios activos, un rol
(incluidos roles nuevos creados dinámicamente), speakers por tipo de
conferencia (magistral/especial/simposio/grupo_temático — **siempre todos los
asignados, sin discriminar `activated`**), y una lista de usuarios individuales.
No guarda mensajes como plantillas (opción A); el historial se registra por
campaña y por destinatario, con reintento de fallidos.

Reutiliza la infraestructura existente (`emails.layout`, `VariableResolver`,
`{{ var }}`, cola DB) sin modificar `EmailTemplate` ni `EventTrigger`.

## Data Model

### `notification_sends` (campaña)

| Campo            | Tipo              | Descripción                                    |
|------------------|-------------------|------------------------------------------------|
| `id`             | big PK            |                                                |
| `subject`        | string(191)       | Asunto snapshot                                |
| `body_html`      | text              | Cuerpo HTML snapshot                           |
| `audience_type`  | string            | `all_users` / `role` / `speakers_by_kind` / `individual` |
| `audience_value` | nullable json     | `role` → role_id; `speakers_by_kind` → kind; `individual` → array user_ids; otros null |
| `template_id`    | nullable FK → `email_templates` | plantilla usada como base (opcional) |
| `sent_by`        | FK → `users`      | usuario que envió                              |
| `recipient_count`| int               | total calculado antes de envío                 |
| `sent_count`     | int default 0     | envíos exitosos                                |
| `failed_count`   | int default 0     | envíos fallidos                                |
| `status`         | enum string       | `pending` / `processing` / `sent` / `partial` / `failed` |
| `error`          | nullable text     | error general (fallo completo)                 |
| `timestamps`     |                   |                                                |

### `notification_recipients` (por destinatario)

| Campo                   | Tipo          | Descripción                                |
|-------------------------|---------------|--------------------------------------------|
| `id`                    | big PK        |                                            |
| `notification_send_id`  | FK cascade    | campaña padre                              |
| `email`                 | string        |                                            |
| `name`                  | string        | nombre completo snapshot                   |
| `payload`               | nullable json | variables resueltas (nombre, rol, tipo_conferencia, …) |
| `status`                | enum string   | `pending` / `sent` / `failed`              |
| `error`                 | nullable string | mensaje del mailer                       |
| `sent_at`               | nullable timestamp |                                      |
| `timestamps`            |               |                                            |

Indexes: `notification_recipients(notification_send_id)`,
`notification_sends(status)`.

## Audience Resolution (`app/Services/NotificationAudienceService.php`)

Todos los métodos retornan `Illuminate\Support\Collection<User>` de usuarios
**activos** (`is_active = true`), deduplicados por email.

- `allUsers()` → `User::where('is_active', true)->get()`
- `byRole(int $roleId)` → `User::whereHas('roles', fn ($q) => $q->whereKey($roleId))`
- `speakersByKind(string $kind)` →
  ```php
  User::where('is_active', true)
      ->whereHas('conferences', fn ($q) =>
          $q->where('kind', $kind)->wherePivot('role', 'speaker'))
  ```
  Requiere **nueva relación** `User::conferences()`:
  ```php
  return $this->belongsToMany(Conference::class, 'conference_members')
      ->withPivot('role', 'activated', 'activated_at')
      ->withTimestamps();
  ```
  **No se filtra por `activated`**: siempre todos los asignados.
- `individual(array $userIds)` → `User::whereIn('id', $userIds)->where('is_active', true)`

`resolve(int|null $roleId, string|null $kind, array $userIds, string $type)` —
método central que despacha por `audience_type` (usado por job y por preview).

## Mailable (`app/Mail/NotificationMailable.php`)

Mismo comportamiento que `EmailTemplateMailable` pero sin requerir
`EmailTemplate`:

- Constructor `(string $subject, string $bodyHtml, array $payload = [])`.
- Pre-resuelve `{{ var }}` con `VariableResolver::resolve`.
- `envelope()` → subject resuelto.
- `build()` → `view('emails.layout', ['subject' => $this->renderedSubject, 'bodyHtml' => $this->renderedBody])`.
- Soporta imágenes inline base64 (replicar `EmailTemplateMailable::inlineImages()`):
  data URIs en el payload se embeben como `cid:` y se reemplazan en el body.

## Variables de personalización

| Variable | Fuente |
|---|---|
| `{{ nombre_completo }}` | `$user->name` |
| `{{ nombre }}` | `$user->first_name` |
| `{{ apellidos }}` | `trim($user->last_name)` |
| `{{ correo }}` | `$user->email` |
| `{{ dni }}` | `$user->dni` |
| `{{ rol }}` | primer rol del usuario (o vacío) |
| `{{ tipo_conferencia }}` | label `ParticipationType` (event_kind `conference`, role `speaker`, kind de la audiencia); vacío si la audiencia no es `speakers_by_kind` |

`buildPayload(User $user, ?string $tipoConferenciaLabel)` en el service, reutilizado
por job y preview.

## Job (`app/Jobs/SendNotificationCampaign.php`)

`ShouldQueue`, `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels`.
Cola `default` (DB, `queue:listen` ya operando), `tries = 3`, `timeout = 3600`.

1. Marca campaña `processing`.
2. Resuelve audiencia (service).
3. Crea filas `notification_recipients` (`pending`) con email, name, payload.
4. Por cada destinatario: `Mail::bcc($email)->send(new NotificationMailable(...))`
   → marca `sent`/`failed` con `error` y `sent_at`.
5. Acumula `sent_count`/`failed_count`; cierra `sent` (0 fallidos),
   `partial` (fallidos > 0), o `failed` (todo fallido).
6. En excepción general → `status = failed`, `error`.

Error parcial no revierte: si un single sender lanza excepción se captura
(`try/catch` per recipient) y se continúa.

## Rutas y controlador (`app/Http/Controllers/NotificationController.php`)

Rutas dentro del grupo `Route::middleware(['auth','verified'])` con subgrupo
`Route::middleware('can:correos.notifications.manage')->prefix('admin/notificaciones')->name('correos.notificaciones.')`:

| Método | URI | Nombre | Descripción |
|---|---|---|---|
| GET | `/` | `index` | Historial paginado (con search de asunto/status) |
| GET | `/enviar` | `create` | Formulario nuevo envío; pasa `roles`, `conferenceKinds` (key → label ParticipationType), `templates` (EmailTemplate todas) |
| POST | `/preview` | `preview` | Body: `audience_type`, `role_id`, `kind`, `user_ids`; retorna `{ count, sample: [{email, name, payload}] }` (muestra de hasta 5) |
| POST | `/` | `store` | Valida y crea campaña + dispatch job |
| POST | `/{notificationSend}/reenviar-fallidos` | `retry-failed` | Re-despacha solo recipients `failed` |

`store` valida: `subject` required max 191, `body_html` required, `audience_type`
in enum, `audience_value` según tipo, `template_id` nullable exists. Recalcula la
audiencia para `recipient_count`; si es 0, redirige con error (no crear campaña).

## UI (Vue/Inertia)

- **`resources/js/pages/Notificaciones/Index.vue`**: tabla historial (fecha,
  audiencia legible, subject truncado, status badge, sent/failed counts, enviado
  por); botón "Reenviar fallidos" cuando `failed_count > 0` y `failed`/`partial`;
  estado "procesando" para `processing`/`pending`.
- **`resources/js/pages/Notificaciones/Create.vue`**: form por pasos en una página:
  1. **Audiencia**: radio buttons (Todos los usuarios / Por rol dropdown /
     Speakers por tipo dropdown / Individual multi-selección con buscador
     debounced estilo Users). Al seleccionar, POST a `/preview` → muestra
     "Se enviarán X correos" + muestra de hasta 5 destinatarios.
  2. **Mensaje**: select "Plantilla existente" (opcional; al elegirla carga
     asunto/cuerpo en el editor, editable) + input asunto + editor Tiptap con
     toolbar de variables `{{ var }}`.
  3. **Confirmar**: botón "Enviar" abre modal de confirmación
     ("¿Enviar a X destinatarios?") y hace POST store con `preserveScroll`.
- Componente de editor rico: reutilizar patrón de `Correos/Edit.vue`
  (Tiptap + inserción de variables).

## Permiso y navegación

- `config/permissions.php` → módulo `Correos`:
  `'correos.notifications.manage' => 'Gestionar notificaciones por correo'`.
  El admin recibe todas las keys vía el bypass de `User::permissionKeys()`;
  `PermissionSync`/`DatabaseSeeder` crea automáticamente la fila de permiso desde
  config y asigna las keys.
- `resources/js/composables/useModuleNav.ts` → `mainModules`:
  `{ title: 'Notificaciones', href: '/admin/notificaciones', icon: Bell, permissions: ['correos.notifications.manage'] }`.

## Tests

**Unit:**
- `NotificationAudienceServiceTest`: cada tipo de audiencia, dedup, solo activos,
  `speakersByKind` no filtra por `activated`, `individual` respeta array.
- `NotificationMailableTest`: variables resueltas, imagen inline, subject.

**Feature:**
- `NotificacionesTest` (Mail::fake + cola sync):
  - admin accede al form (permisos).
  - preview retorna count/sample por cada audiencia.
  - store crea campaña + recipients y despacha job.
  - job envía a cada destinatario (`Mail::assertSent(NotificationMailable)`) y
    actualiza status.
  - envío a rol speaker magistral incluye todos los asignados (activados y no).
  - rol nuevo (creado dinámicamente) es atacable por `byRole`.
  - `retry-failed` reintenta solo `failed`.
  - audiencia vacía → error, no crea campaña.
  - sin permiso → 403.

## Notas de alineación

- Vistas: `emails/layout.blade.php` (pasar `subject` para el `<title>`).
- `VariableResolver` soporta `{{ key }}` — sin cambios.
- Sin cambios a `email_templates`, `email_triggers`, `event_logs`.
- Envío individual por destinatario con `Mail::bcc()` (privacidad).
- Se añade `User::conferences()` (relación nueva, solo eso en `User`).