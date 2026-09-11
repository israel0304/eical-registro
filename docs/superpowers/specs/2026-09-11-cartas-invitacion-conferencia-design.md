# Design Spec: Cartas de Invitación por Conferencia (Speakers)

## Overview
Add per-conference invitation letters for conference speakers. A speaker can
download one invitation letter per conference where they participate, each
bound to the conference's kind (magistral/especial/simposio/grupo_temático) so
the participation type label ("Conferencista magistral", etc.) matches the
existing ParticipationType taxonomy. Letters are pre-event, so **no
activation is required**, and **no admin bulk generation** is added (self-service
only from the Constancias page).

## Data Model & Templates

1. **ParticipationType seed**: new `conferencia_grupo_tematico`,
   label "Conferencista de grupo temático", `event_kind = 'conference'`,
   `kind = 'grupo_tematico'`, `role = 'speaker'`, active. Completes the 4 conference
   speaker kinds (magistral, especial, simposio, grupo_tematico).

2. **Certificate**: new `event_type = 'carta-conference'`, `event_id` = conference id,
   `participation_type_id` = the conference-type ParticipationType of the conference
   kind and role speaker, `role_id` = Speaker role. Same folio/`finalize` flow as
   `issueCartaForPresentation`.

3. **Template resolution** (`invitationTemplateForConference(Conference $conference)`):
   active invitation template by (`participation_type_id` of the conference kind,
   Speaker role) → (`role_id` Speaker, any) → generic (`role_id` null). Prefers
   `is_default` at each level, mirroring `invitationTemplateFor`.

4. **Metadata** (`buildCartaConferenceMetadata`): `nombre`, `nombre_completo`,
   `rol` = "Speaker", `tipo_participacion` = ParticipationType label,
   `evento`/`ponencia` = conference title, `fecha_evento` = conference day
   (Spanish date), `location` = conference location, plus `horario` = start–end
   time, `institucion`, `pais`, `fecha` = user registration date,
   `trabajos` = [title], `autores`/`speakers` = **every speaker of the conference**
   (including the downloading user) on one line, comma-separated, ordered by
   pivotal `id`/`author_order`. Renderer converts `{speakers}` and `{autores}` to
   that same string.

## Backend (Constancias)

- `ConstanciaController::downloadInvitacionConferencia(Request, Conference)`:
  - 403/error if the user is not a speaker member of the conference (membership with
    `role = 'speaker'`; `activated` not required).
  - error "La carta de invitación no está disponible." if no template.
  - `issueCartaForConference`, `downloaded_at = now()`, `respondWithHtml`.
- `myCertificates`: new prop `cartaConferences` — conferences where the user is
  speaker, ordered by `day`, each with `title`, `kind`, `tipo_participacion` label,
  `day`, `location`, `start_time`/`end_time`, `cartaFolio` (from existing
  `carta-conference` certificate), `downloaded`.
- Route: `GET constancias/invitacion/conferencia/{conference}/download`
  (`can:constancias.download`, name `constancias.invitacion.conferencia.download`),
  placed with the other constancia invitation routes in `routes/web.php`.

## Frontend (Constancias page)

New section "Cartas de Invitación por Conferencia" in `Constancias/Index.vue`,
mirroring the ponencias section but **without a kind filter** (one letter per
conference, like `cartaPresentations`). Each row shows conference info
(title, kind label, day, location, schedule) and a button
`window.open('/constancias/invitacion/conferencia/{id}/download')` when no folio
yet, or a "Descargar de nuevo" link once downloaded.

## Admin Invitation Templates

Extend the existing invitation template editor (currently role-only) so a
Speaker template can optionally bind to a `participation_type_id` (the
conference type it covers):
- `InvitationTemplateController` index passes all `ParticipationTypes` of
  `event_kind = 'conference'` and kind != null (role speaker over conference
  kinds); store/update validate `participation_type_id` (nullable, must exist,
  and when set the template role must be Speaker).
- `clearDefault` for invitation templates is scoped to
  (`kind`, `role_id`, `participation_type_id`) so defaults coexist per
  conference type.
- Admin list shows the bound type label ("Speaker - Magistral", etc.); creation
  modal defaults the role to first role and the type select is shown when the
  selected role is Speaker.
- New template variables available in the editor + preview regex + sample:
  `{speakers}` and `{tipo_participacion}`.

## Tests (Pest, `tests/Feature/CartaInvitacionTest.php` style)

- Speaker member (not activated) downloads letter → 200, `Certificate`
  `carta-conference`/conference id, `participation_type_id` = type of conference
  kind, `role_id` = Speaker, metadata `evento` = title,
  `tipo_participacion` = type label, `speakers`/`autores` contain the user and a
  co-speaker.
- Same kind picks the type-bound template over the plain Speaker template.
- Non-member cannot download → redirect/errors.
- No template → error, no certificate.
- `{speakers}` and `{tipo_participacion}` variables render in output.
- Seeder creates the 4 speaker conference types (`conferencia_magistral`,
  `conferencia_especial`, `simposiasta`, `conferencia_grupo_tematico`).
- `myCertificates` exposes `cartaConferences` with the letter entries.