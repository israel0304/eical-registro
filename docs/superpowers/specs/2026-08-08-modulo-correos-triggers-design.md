# Design Spec: Módulo de Correos — Plantillas, Disparadores y Auditoría de Eventos

Fecha: 2026-08-08

## Overview

Reemplazar los correos hardcodeados (Mailables/Notification) por un sistema genérico administrable desde la UI:

- **Plantillas de correo** editables (asunto + cuerpo HTML) con variables dinámicas y preview en vivo.
- **Disparadores (triggers)** que controlan *qué evento envía qué plantilla* y si está activo — configurables sin código.
- **Auditoría de eventos** (`event_log`) que registra todo lo que ocurre, con emisión automática para módulos nuevos vía un trait.

Objetivo central: para los eventos que la app ya maneja, activar/desactivar correos, cambiar plantillas o crear triggers nuevos es pura configuración del admin. Solo un *evento completamente nuevo* requiere una llamada `EventAudit::emit()` en código.

## Architecture & Data Model

### Migraciones nuevas

**`email_templates`**
| columna | tipo | notas |
|---|---|---|
| `id` | bigint pk | |
| `key` | string unique | slug, ej. `enrollment-confirmation` |
| `name` | string | ej. "Inscripción confirmada" |
| `description` | text nullable | |
| `subject` | string | admite `{variables}` |
| `body_html` | longtext | cuerpo HTML, admite `{variables}` |
| `variables` | json | `[{name, label, sample}]` (marcadores usados + valor de ejemplo para preview) |
| timestamps | | |

**`email_triggers`**
| columna | tipo | notas |
|---|---|---|
| `id` | bigint pk | |
| `event_key` | string unique | clave del catálogo de eventos |
| `name` | string | |
| `description` | text nullable | |
| `is_active` | bool default true | **controla si se envía** |
| `template_id` | fk nullable → email_templates, onDelete set null | **qué plantilla se envía** |
| timestamps | | |

Relación `EmailTrigger::template()` → `EmailTemplate`.

**`event_log`** (auditoría)
| columna | tipo | notas |
|---|---|---|
| `id` | bigint pk | |
| `event_key` | string | |
| `payload` | json | datos del evento (claves = variables) |
| `recipient_email` | string nullable | destinatario si aplica |
| `occurred_at` | timestamp | |
| timestamps | | |

Índices: `event_key`, `occurred_at`.

### Catálogo de eventos (`config/events.php`)

Cada entrada: `key`, `name`, `description`, `variables` `[{name, label, sample}]`. Los eventos explícitos:

| key | variables |
|---|---|
| `workshop.enrollment` | `userName`, `userEmail`, `workshopName`, `day`, `startTime`, `endTime`, `location` |
| `workshop.unenrollment` | `userName`, `userEmail`, `workshopName` |
| `workshop.qr_sent` | `instructorName`, `instructorEmail`, `workshopName`, `day`, `startTime`, `endTime`, `location`, `scanUrl`, `qrImage` (raw) |
| `user.welcome` | `userName`, `userEmail`, `activationUrl` |
| `user.registered` | `userName`, `userEmail` |
| `attendance.recorded` | `userName`, `userEmail`, `workshopName`, `day` |
| `certificate.issued` | `userName`, `userEmail`, `constanciaType` |
| `ponente.activated` | `userName`, `userEmail`, `activationUrl` |

Los eventos de ciclo de vida (`{modelo}.created`, `.deleted` y `.updated` opt-in) exponen como variables las columnas del payload emitido.

## Services

### `App\Services\EventAudit`

```
emit(string $eventKey, array $payload, ?string $recipientEmail = null): EventLog
```
- Valida `eventKey` contra `config('events')` (si no está, registra con `Log::warning` y no falla).
- Escribe la fila en `event_log`.
- Despacha el job `SendTriggeredEmails` (encolado, conexión por defecto) con el `event_log.id`.
- Devuelve la fila.

### `App\Jobs\SendTriggeredEmails`

- Busca `EmailTrigger::where('event_key', $log->event_key)->with('template')->first()`.
- Si no existe trigger, `!is_active` o `!template` → **no envía** y `Log::warning` (nunca rompe la operación).
- Renderiza y envía vía `EmailDispatcher`.

### `App\Services\EmailDispatcher`

```
render(EmailTemplate $template, array $data): array  // ['subject', 'html']
send(EmailTrigger $trigger, array $data, string $to): void
```
- Reemplaza tokens `{nombre}` en `subject` y `body_html`.
- Escapado: valores por defecto con `htmlspecialchars`; clave raw permitida: `qrImage` (inserta el `<img src="data:image/png;base64,...">`).
- Envía con `EmailTemplateMailable` (layout `emails.layout`).

### `App\Mail\EmailTemplateMailable`

Mailable genérico: `subject` del trigger renderizado, `content(html: 'emails.layout', with: ['subject', 'bodyHtml', 'appName'])`. El layout es un shell HTML responsivo (cabecera con nombre del evento vía `Setting::evento_nombre`, cuerpo `{!! $bodyHtml !!}`, pie).

### Trait `App\Models\Concerns\EmiteEventos`

- `bootEmiteEventos()` registra listeners `created` y `deleted` (y `updated` **solo si** el modelo define `protected array $auditUpdatedAttributes = [...]` — opt-in para evitar ruido).
- Clave de evento: `Str::slug(class_basename($model))` → `{modelo}.created|updated|deleted`.
- Payload: `$model->toArray()` (solo las columnas relevantes si se define `$auditPayload`).
- Recipient: `$model->email ?? null` (si no hay, solo auditoría).
- Se aplica a: `User`, `Workshop`, `Presentation`, `Conference`, `WorkshopEnrollment`, `Attendance`, `Certificate`.
- Nota: los eventos de ciclo de vida quedan auditable y triggerables; los 3 correos actuales se conectan con eventos explícitos (abajo), no de ciclo de vida.

## Migración de los correos actuales

| Punto actual | Reemplazo |
|---|---|
| `WorkshopEnrollmentController` (2 llamadas `Mail::...send(WorkshopEnrollmentConfirmation)`) | `EventAudit::emit('workshop.enrollment', [...], $user->email)` |
| `AttendanceController::sendQRToInstructor` / `sendQRToAll` | `EventAudit::emit('workshop.qr_sent', [...], $instructor->email)` |
| `PonenteActivationController` → `BienvenidaNuevoUsuario` | `EventAudit::emit('user.welcome', [...], $user->email)` |

Se eliminan `app/Mail/WorkshopEnrollmentConfirmation.php`, `app/Mail/WorkshopQRForInstructor.php`, `app/Notifications/BienvenidaNuevoUsuario.php` y las vistas markdown `resources/views/emails/*.md` (sustituidas por `emails.layout`). No hay fallback en código: la semilla garantiza los 3 triggers+plantillas; si faltara, no envía y loguea.

## Seeder

`EmailTemplateSeeder` + `EmailTriggerSeeder` (invocados desde `DatabaseSeeder`), ambos `updateOrCreate` por `key`/`event_key`:

- 3 plantillas: `enrollment-confirmation`, `qr-for-instructor`, `welcome` (con `variables` y `body_html` equivalentes al contenido actual).
- 3 triggers: `workshop.enrollment`→enrollment-confirmation, `workshop.qr_sent`→qr-for-instructor, `user.welcome`→welcome. `is_active = true`.

## Permisos y rutas

- `config/permissions.php` → módulo `Correos`: `correos.templates.manage` ("Gestionar plantillas y disparadores de correos"). El `super_admin` lo obtiene automáticamente vía `PermissionSync` (pluck). No hace falta tocar la matriz de roles.
- Gate del sidebar "Plantillas" y de `CertificateTemplateController::plantillas()`: añadir `|| can('correos.templates.manage')`.
- Rutas (grupo `can:correos.templates.manage`, admin):
  - `POST admin/correos/templates` → store | `GET admin/correos/templates/{template}/edit` | `PUT admin/correos/templates/{template}` | `DELETE admin/correos/templates/{template}`
  - `POST admin/correos/templates/preview` → devuelve HTML renderizado con valores de ejemplo
  - `POST admin/correos/triggers` | `PUT admin/correos/triggers/{trigger}` | `DELETE admin/correos/triggers/{trigger}`
- El listado unificado (`plantillas.index`) pasa además `emailTemplates`, `emailTriggers`, `eventCatalog` y el flag `correos` en `permissions`.

## Frontend

### Tab "Correos" en `Plantillas/Index.vue`

4º tab (después de Cartas de Invitación), con 3 sub-secciones:

1. **Plantillas**: grid de tarjetas (nombre + asunto + nº de variables). "Nueva plantilla" (modal: nombre + key) que redirige a `Correos/Edit.vue`. Editar/eliminar en hover. No se puede eliminar una plantilla usada por un trigger (respuesta de error).
2. **Disparadores**: tabla con evento (catálogo), nombre, descripción, plantilla asignada (select de `emailTemplates`), toggle activo/inactivo, crear/editar/eliminar. Al crear: se elige `event_key` del catálogo.
3. **Auditoría**: lista reciente de `event_log` (evento, destinatario, fecha) con filtro por `event_key`.

### Editor `Correos/Edit.vue` (nuevo)

- Campos: `key` (solo lectura al editar), `name`, `description`, `subject` (input, con botones de insertar variable).
- **Cuerpo WYSIWYG** con TipTap: `@tiptap/vue-3`, `@tiptap/starter-kit`, `@tiptap/extension-link` (única dependencia nueva). Toolbar: negrita, cursiva, subrayado, H2/H3, listas, enlace/desenlace, undo/redo. Guarda HTML.
- **Panel de variables**: lista de `{name}` con label y sample, botón "insertar" que coloca `{name}` en subject/cuerpo.
- **Preview en vivo**: debounce 400 ms → `POST correos.templates.preview` con `{subject, body_html, variables}` → renderiza asunto y HTML del layout con los valores `sample` → iframe `srcdoc` y línea de asunto. Además, listado de variables disponibles del evento si se edita desde un trigger.

## Seguridad, caché y colas

- El `body_html` es contenido de confianza (solo admins); el layout lo inserta con `{!! !!}`. El editor solo genera HTML simple.
- Validación: `key` regex `^[a-z0-9._\-]+$` y único; `event_key` ∈ `array_keys(config('events'))`; `variables` `[{name,label,sample}]` con `name` regex `^[a-zA-Z][a-zA-Z0-9_]*$`.
- Caché: `Cache::remember` de triggers/plantillas por `key`; `Cache::forget` en `saved`/`deleted` de ambos modelos.
- Envío encolado (default queue). En tests/CI la conexión es `sync`.

## Testing

- `Feature/EmailTemplateTest`: CRUD + validaciones + permisos (403 sin permiso); preview devuelve HTML renderizado.
- `Feature/EmailTriggerTest`: `emit()` crea fila en `event_log`; trigger activo envía `EmailTemplateMailable` al destinatario (Mail::fake); inactivo no envía; sin plantilla no envía y loguea; render reemplaza variables y escapa (excepto `qrImage`).
- `Feature/EventAuditTest`: trait emite `created/deleted`; `updated` solo con `$auditUpdatedAttributes`; evento desconocido no rompe.
- Actualizar tests existentes que aseveran los correos (ahora `EmailTemplateMailable`).

## Out of scope (YAGNI)

- Envío manual a grupos/usuarios (se deja para un futuro módulo).
- Condiciones/audiencia por trigger (solo evento → plantilla).
- Interfaz de auditoría paginada completa (solo lista reciente filtrable).
- Generador `make:module` (el trait + catálogo ya cubren la automatización).
