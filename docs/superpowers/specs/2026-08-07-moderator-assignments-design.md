# Design Spec: Módulo de Asignación de Moderadores y "Mis Asignaciones"

## Overview
Implement a comprehensive moderator assignment system across Workshops (Talleres), Presentations (Ponencias), and Conferences (Conferencias), featuring a dedicated dashboard view ("Mis Asignaciones") for moderators and consistent pill-based user search/creation modals in the admin panel.

## Architecture & Data Model

1. **Database Tables & Relations**:
   - **Talleres**: New pivot table `workshop_moderator_user` (`workshop_id`, `user_id`). Relation `moderators()` in `Workshop` model.
   - **Ponencias**: New pivot table `presentation_moderators` (`presentation_id`, `user_id`). Relation `moderators()` in `Presentation` model.
   - **Conferencias**: Existing table `conference_members` with `role = 'moderator'`. Relation `moderators()` in `Conference` model.
   - **System Role**: Role `"Moderator"` (ID `6`) with permissions for dashboard, attendance management, and viewing assigned activities.

2. **Backend Controllers & Routes**:
   - `ModeratorAssignmentController` or methods in respective controllers for listing moderator assignments (`/mis-asignaciones`).
   - API endpoints for searching/creating users with moderator profile (`api/moderadores` or reusing `api/usuarios`).
   - Authorization gates/middleware ensuring moderators can view assigned activity `Show` pages and perform activity-specific actions (manage attendance, activate constancias) without edit/delete access.

3. **Frontend Views**:
   - **Admin Edit Modals**: Add moderator multi-select with pill UI (searching users, creating new ones on-the-fly) in Workshop, Presentation, and Conference edit forms.
   - **"Mis Asignaciones" (`/mis-asignaciones`)**: Vue page displaying a chronological, date/time-sorted list of assigned activities across all three modules, with activity details and an "eye" (view) button linking to each activity's `Show.vue`.
   - **Activity Show Pages (`Show.vue`)**: Hide edit/delete buttons for moderators while enabling attendance/constancia actions.
