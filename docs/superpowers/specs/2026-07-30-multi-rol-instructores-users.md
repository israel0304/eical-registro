# Multi-rol e Instructores como Usuarios del Sistema

## Resumen

Migrar de un único rol por usuario (`users.role_id`) a roles múltiples
(tabla pivote `role_user`). Los instructores de taller pasan de la tabla
`instructors` a ser usuarios del sistema con rol Instructor, vinculados a
talleres mediante `workshop_instructor_user`.

## Motivación

- Un usuario puede ser ponente e instructor a la vez
- El módulo de constancias se unifica: siempre es `User` → descarga
- Preparar el terreno para el panel central de constancias
- Permitir agregar nuevos roles sin migraciones futuras

## Modelo de datos

### Nuevas tablas

**`role_user`**
| Columna | Tipo |
|---------|------|
| user_id | FK → users |
| role_id | FK → roles |
| PK | (user_id, role_id) |

**`workshop_instructor_user`**
| Columna | Tipo |
|---------|------|
| workshop_id | FK → workshops |
| user_id | FK → users |
| institution | string, nullable |
| PK | (workshop_id, user_id) |

### Tablas eliminadas

- `users.role_id` (columna)
- `instructors` (tabla completa)

### Nuevos campos

- `presentations.type`: string, default `'ponencia'`
  - Valores: `ponencia`, `conferencia_magistral`, `conferencia_especial`

### Roles

| ID | Name |
|----|------|
| 1 | Administrator |
| 2 | Ponente |
| 3 | Asistente |
| 4 | Instructor |

## Migración de datos

### Usuarios existentes
Cada usuario existente migra su `role_id` a un registro en `role_user`.

### Instructores existentes
Por cada registro en `instructors`:
1. Buscar User por `email`
2. Si no existe: crear User (`first_name`, `last_name` extraídos de `name`,
   `password` aleatorio, `dni` generado, rol Instructor)
3. Crear registro en `role_user` con rol Instructor (si no existe ya)
4. Crear registro en `workshop_instructor_user`

### Seeders
- `Role::updateOrCreate` para los 4 roles con IDs fijos
- Usuario admin por defecto con rol Administrator
- Instructores existentes se migran vía migración, no seeder

## Backend

### User model
- Eliminar `role_id` de `$fillable` y `$casts`
- Agregar `roles(): BelongsToMany`
- Agregar `hasRole(string $name): bool`
- `isAdmin()`, `isPonente()`, `isAsistente()`: delegar a `hasRole()`
- Agregar `isInstructor(): bool`

### Role model
- Sin cambios mayores (ya tiene `users()` hasMany → cambiar a BelongsToMany)

### Workshop model
- `instructors()`: HasMany → BelongsToMany(User) con `workshop_instructor_user`
- Agregar `instructiong_users()` alias con la tabla pivote

### HandleInertiaRequests
- Compartir `auth.user.roles` en lugar de `auth.user.role_id`

### UserController
- Filtro por rol: `role_id` en query → `role` por nombre
- Crear/editar usuario: select único → array de role_ids

### PresentationImportController
- `role_id => 2` → `roles()->sync([2])`

### CreateNewUser (Fortify)
- `role_id => Asistente` → `roles()->sync([3])`

### WorkshopController
- Instructores se asignan como Users por email
- Buscar/crear User, asignar rol Instructor, sync a taller

### API ponentes (routes/web.php)
- `role_id => 2` → `roles()->sync([2])`

## Frontend

### Tipo `User`
- `role_id: number` → `roles: Array<{id: number, name: string}>`

### AppSidebar.vue
- `role_id === X` → `hasRole('Name')`
- Acumulativo: un usuario con múltiples roles ve ítems de todos sus roles

### Dashboard.vue
- `user.role_id === 1` → `hasRole('Administrator')`
- `user.role_id === 2` → `hasRole('Ponente')`

### Constancias/Index.vue
- `user.role_id === 1 || user.role_id === 2` → `hasRole('Administrator') || hasRole('Ponente')`

### Presentaciones (Index.vue, Show.vue)
- `isAdmin = user.role_id === 1` → `isAdmin = hasRole('Administrator')`

### Workshops (Index.vue, Show.vue)
- `isAdmin = user.role_id === 1` → `isAdmin = hasRole('Administrator')`

### Users/Index.vue
- Select `role_id` → multi-select de roles (checkboxes)
- Filtro búsqueda por rol: cambia a selector múltiple o texto

## Flujo de instructores (crear/editar taller)

Admin ingresa email + nombre del instructor:
1. Backend busca User por email
2. Si existe: asigna rol Instructor via `role_user` (sin quitar roles existentes)
3. Si no existe: crea User con datos mínimos + rol Instructor + password aleatorio + envía notificación bienvenida
4. Inserta en `workshop_instructor_user` con el workshop_id

## Constancias (próximo paso)

Una vez implementado multi-rol:
- Panel admin en `/admin/constancias` con tabs: Talleres | Ponencias | Conferencias
- Instructores ven su constancia en "Mis Constancias"
- Cada constancia se descarga por User (sin distinción de tabla)

## Lo que no cambia

- `presentation_authors` (ya es tabla pivote)
- `attendances` (ya referencias a User)
- Certificados HTML (mismo formato)
- Layouts (AppSidebarLayout, AppLayout)
- Autenticación y verificación de correo
