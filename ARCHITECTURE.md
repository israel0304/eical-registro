# Arquitectura del Sistema: Registro EICAL 2026

## 1. Resumen del Proyecto

Plataforma de gestion integral para el evento **Registro EICAL 2026**. Administra registro de participantes, talleres, ponencias, asistencia y constancias. Sustituye flujos de trabajo manuales con una arquitectura relacional que garantiza integridad de datos y trazabilidad.

---

## 2. Stack Tecnologico

- **Framework:** Laravel 12 (PHP 8.2+)
- **Base de Datos:** MySQL (`eical_registro`)
- **Autenticacion:** Laravel Fortify (2FA, verificacion de email)
- **Frontend:** Vue 3 + Inertia.js + TypeScript
- **UI:** Tailwind CSS 4 + Reka UI + Lucide Icons
- **QR:** qrcode (frontend) + bacon/bacon-qr-code (server-side PNG)
- **Mail:** `MAIL_MAILER=log` (emails en `storage/logs/laravel.log`)

---

## 3. Modelo de Datos

### 3.1 Identidad y Acceso (IAM)

- **`roles`**: 3 roles con IDs fijos: Administrator (1), Ponente (2), Asistente (3)
- **`users`**: `first_name`, `last_name`, `dni` (unique, auto-generado `CNV-` + 7 chars), `email` (unique), `password`, `affiliation`, `country`, `state`, `role_id` (FK), `is_active`, `activation_token`, `password_set_at`. Soporta 2FA.

### 3.2 Talleres (Workshops)

- **`workshops`**: `title`, `description`, `capacity`, `location` (texto libre), `starts_at`, `ends_at`, `event_day`, `qr_time_restricted` (bool), `created_by` (FK a users). Sin campos de instructor (ver abajo).
- **`instructors`**: `workshop_id` (FK cascade delete), `name`, `institution`, `email` (requerido). Max 5 por taller.
- **`workshop_enrollments`**: `user_id` + `workshop_id` (unique), `status` enum (`enrolled`/`cancelled`), `enrolled_at`.

### 3.3 Ponencias (Presentations)

- **`presentations`**: `submission_id` (unique), `title`, `abstract`, `type`, `status`, `event_day`, `starts_at`, `ends_at`, `ponente_user_id` (FK).
- **`presentation_authors`**: `presentation_id` (FK cascade), `name`, `institution`, `email`, `order` (para autores ordenados).

### 3.4 Asistencia (Attendances)

- **`attendances`**: `user_id` (FK cascade), `presentation_id` (FK nullOnDelete), `workshop_id` (FK nullOnDelete), `event_day` (string, formato YYYY-MM-DD), `registered_by` (FK a users), `certificate_generated`, `certificate_generated_at`.

### 3.5 Infraestructura

- **`qr_codes`**: Tabla polimorfica con identificadores UUID.
- **`templates`** y **`template_fields`**: Sistema de coordenadas para constancias.
- **`settings`**: Configuracion dinamica llave/valor.

---

## 4. Roles y Permisos

| Rol | Permisos |
|---|---|
| **Administrator** (id=1) | CRUD completo: usuarios, talleres, ponencias, asistencia, constancias, reportes. Puede cancelar cualquier inscripcion. |
| **Ponente** (id=2) | Editar propias ponencias, perfil propio, inscribirse a talleres, ver constancias. |
| **Asistente** (id=3) | Auto-registro o creado por admin. Inscribirse a talleres libremente, ver constancias. |

---

## 5. Funcionalidades Principales

### 5.1 Registro y Activacion de Ponentes

- Ponentes importados via CSV desde sistema academico OJS (tab-delimited).
- Solo se importan submissions con status "Aceptada".
- Cuenta creada inactiva (sin password). Activacion via `submission_id` + email.
- Seleccion de password en segundo paso.

### 5.2 Talleres

- CRUD restringido a administrador.
- Instructores en tabla separada (max 5, email requerido).
- Capacidad limitada, inscripcion/desuscripcion con reglas:
  - No cancelar dentro de 10 minutos de inicio.
  - No cancelar si asistencia ya confirmada.
  - Admin puede cancelar cualquier inscripcion.

### 5.3 Sistema QR y Asistencia

- **QR por taller** (no por usuario). Un solo QR se proyecta/imprime en el salón.
- **Restriccion de horario**: Admin puede activar/desactivar que solo se acepte QR dentro del rango del taller.
- **Auto-checkin**: Escaneo de QR registra asistencia automaticamente.
- **Manual**: Admin puede toggle asistencia individual por usuario.
- **Email**: QR se envia por email a los instructores del taller.

### 5.4 Constancias

- Generadas como HTML por cada taller completado.
- Descargables por el usuario.

### 5.5 Reportes (Solo Admin)

- Asistencia por taller (exportable CSV)
- Asistencia general
- Asistencia a ponencias
- Resumen general
- Ocupacion de talleres
- Estadisticas

---

## 6. Flujo de Datos Clave

### Inscripcion a Taller

1. Usuario ve talleres disponibles en `/workshops`.
2. Hace click en "Inscribirse" → `POST /workshops/{id}/enroll`.
3. Backend valida capacidad, crea `workshop_enrollment` con status `enrolled`.
4. Se envia email de confirmacion al usuario.
5. QR del taller se envia por email a los instructores.

### Asistencia via QR

1. QR del taller se muestra en pantalla/imprese.
2. Usuario escanea → `/scan` → redirige a confirmacion.
3. Si `qr_time_restricted` esta activo, valida hora actual contra `starts_at`/`ends_at`.
4. `POST /workshops/{workshop}/scan` registra attendance.
5. Toggle: `POST/DELETE /admin/workshops/{workshop}/attendance/{userId}`.

### Cancelacion de Inscripcion

1. Usuario hace click en "Cancelar" → `DELETE /workshops/{id}/enroll`.
2. Backend valida: no tiene asistencia confirmada, no esta dentro de 10 min de inicio.
3. Admin no tiene restricciones.
4. Enrollment status cambia a `cancelled`.

---

## 7. Configuracion

- **DB:** MySQL `eical_registro` / `eical_registro` / `4xTqhK576$F2`
- **Mail:** `MAIL_MAILER=log` (emails en `storage/logs/laravel.log`)
- **Roles IDs:** Administrator=1, Ponente=2, Asistente=3 (fijos en seeder)
- **DNI format:** `CNV-` + 7 caracteres aleatorios (auto-generado)

---

## 8. Notas de Implementacion

- **Role ID casting:** `role_id` casteado como `integer` en User model para evitar issues con comparaciones estrictas en JavaScript.
- **Imagick:** Usar `flattenImages()` en vez de `compositeImages()` para compatibilidad con PHP 8.4.
- **Instructor email:** Validacion `email` (sin `rfc,dns`) para evitar fallos por verificacion DNS.
- **Event day:** Campo string (no integer) para flexibilidad de formato.
- **Wayfinder:** Las rutas generan objetos `{ url, method }` compatibles con Inertia `Link`.

---

_Documentacion actualizada - Julio 2026._
