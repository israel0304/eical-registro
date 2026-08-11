# Design Spec: Módulo de Programa del Evento

## Overview
Build a new admin module "Programa del Evento" (sidebar section) that shows the event schedule built from a hybrid mix of **live references** to existing activities (workshops, presentations, conferences) and **manual blocks** (registro, inauguración, receso, clausura, otro). Access is role-based via two permissions (`programa.view`, `programa.manage`) assignable from the Roles module. Includes a print/PDF view.

## Architecture & Data Model

1. **Database Table**: `program_items`
   - `id`, `created_by` (FK → users), `timestamps`
   - Manual block fields (nullable): `title` (string), `description` (text), `location` (string), `day` (date), `start_time` (time), `end_time` (time), `block_type` (string, nullable)
   - Activity reference (nullable): `activity_type` (string, morph alias `workshop|presentation|conference`), `activity_id` (unsignedBigInteger)
   - Unique index `(activity_type, activity_id)` to prevent duplicate activities; index on `day`
   - Each row is either an activity reference OR a manual block, never both.

2. **Model**: `App\Models\ProgramItem`
   - `$fillable`: title, description, location, day, start_time, end_time, block_type, activity_type, activity_id, created_by
   - `$casts`: `day` → date
   - `activity(): MorphTo` (reuses existing morph map), `creator(): BelongsTo`
   - Helpers: `isActivity()`, `isBlock()`

3. **Config**: `config/program.php` with `block_types` (`registro`, `inauguracion`, `receso`, `clausura`, `otro`), each with `label` and Tailwind `color`.

4. **Permissions** (in `config/permissions.php`, module "Programa"):
   - `programa.view` — see the program (sidebar item, read-only)
   - `programa.manage` — add/edit/delete items
   - Synced via existing `permissions:sync` command; superadmin gets both; assignable per role from the Roles module.

## Backend Controllers & Routes

All under `auth` + `verified`, prefix `admin/programa`:

| Method | URI | Permission | Action |
|---|---|---|---|
| GET | `/admin/programa` | `programa.view` | Inertia `Programa/Index` |
| POST | `/admin/programa` | `programa.manage` | Add manual block or activity |
| PUT | `/admin/programa/{item}` | `programa.manage` | Update manual block |
| DELETE | `/admin/programa/{item}` | `programa.manage` | Remove item |
| GET | `/admin/programa/imprimir` | `programa.view` | Blade print/PDF view |
| GET | `/admin/programa/actividades?search=` | `programa.view` | Activity picker search endpoint |

Only manual blocks are editable; activities are live references (no editable fields).

## Service & Data Flow

`App\Services\ProgramService`:
- `getProgram(): array` loads all `program_items` with eager-loaded `activity`, resolves each item:
  - Activity: reads `day`/`start_time`/`end_time`/title/location from the current model (normalized title per type: `Workshop.name`, `Presentation.title`, `Conference.title`); soft-deleted or missing activities are collected as **orphans**.
  - Block: uses stored fields.
- Groups by `day` ascending, sorts by `start_time` within each day.
- Returns `['days' => [...], 'orphans' => [...]]`.

Items without day/time are not placed and are reported as incomplete/orphans.

## Frontend Views

- **Sidebar**: new item "Programa del Evento" (icon `CalendarDays`), visible with `programa.view`.
- **`resources/js/pages/Programa/Index.vue`** (AppSidebarLayout, breadcrumb "Programa del Evento"):
  - Sections per day with sticky headers; each item shows time range, title, location, type badge (color from `block_type`; for activities "Taller/Ponencia/Conferencia").
  - With `programa.manage`: buttons "Agregar bloque manual", "Agregar actividad" (picker modal with search against `/admin/programa/actividades`), edit/delete per item, orphans alert with cleanup option.
  - Print button linking to `/admin/programa/imprimir`.
- **Print view**: Blade `resources/views/programa/imprimir.blade.php` with print CSS (pattern of `certificates/verificar.blade.php`); user prints/exporta a PDF desde el navegador.

## Validation & Error Handling

- Store requires exactly one of:
  - Activity reference: valid `activity_type` (`workshop|presentation|conference`) + existing `activity_id`.
  - Manual block: `title`, `day` (date), `start_time` y `end_time` (formato `H:i`) requeridos; `description`, `location` y `block_type` opcionales; `block_type` debe estar en `config/program.php`.
- Duplicate activity → clear error message (unique index + validation).
- Orphans displayed as a non-breaking warning; admin can remove them.

## Testing

`tests/Feature/ProgramaTest.php`:
- Permission gating (view vs manage; 403 without permission).
- Add manual block; add activity; duplicate prevention.
- Ordering by day/time.
- Live reference reflects activity changes.
- Soft-deleted activity → orphan handling.
- Print route renders.
