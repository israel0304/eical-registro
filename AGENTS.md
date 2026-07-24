# AGENTS.md

## Stack

- **Backend:** Laravel 12 (PHP 8.2+)
- **Frontend:** Vue 3 + Inertia.js + TypeScript + Tailwind CSS 4
- **UI:** Reka UI + Lucide Icons
- **Auth:** Laravel Fortify (2FA)
- **QR:** qrcode (frontend) + bacon/bacon-qr-code (server-side PNG)

## Commands

```bash
# Frontend
npm run dev        # Start dev server
npm run build      # Build assets
npm run lint       # ESLint fix
npm run format    # Prettier write

# Backend
composer dev      # Run all (server + queue + logs + vite)
composer test    # Pint lint + PHPUnit
composer lint    # Pint only
php artisan tinker
```

## Testing

- **PHP:** PHPUnit (run with `composer test`)
- **JS/Vue:** ESLint + Prettier

## Key Directories

- `app/Http/Controllers` - Controllers
- `app/Models` - Eloquent models
- `app/Mail` - Mailable classes
- `resources/js/pages/` - Vue Inertia pages
- `resources/js/components/` - Vue components
- `routes/web.php` - Web routes
- `database/migrations/` - Migrations
- `database/seeders/` - Seeders

## Architecture Notes

- SPA via Inertia.js - no API routes needed for frontend
- 3 roles: Administrator (id=1), Ponente (id=2), Asistente (id=3)
- Ponentes imported via CSV from OJS academic system
- Workshop instructors in separate `instructors` table (max 5, email required)
- Attendance tracked per workshop/presentation via `attendances` table
- QR code per workshop for auto-checkin; time restriction toggleable by admin
- Certificates generated as HTML per completed workshop
- Mail configured as `MAIL_MAILER=log` (emails in `storage/logs/laravel.log`)

## Database

- **DB name:** `eical_registro` (MySQL)
- **Credentials:** `eical_registro` / `4xTqhK576$F2`

## Recent Updates

- **User Model:** `first_name` + `last_name` (no single `name` field), `role_id` cast as integer
- **Registration:** Auto-generates DNI (`CNV-` + 7 random chars), requires affiliation/country/state
- **Roles:** Administrator, Ponente, Asistente (IDs 1, 2, 3)
- **Project name:** "Registro EICAL" (was "Cinvesniñ@s 2026")
- **GitHub Actions:** PHP 8.2, Node.js 22 - workflows in `.github/workflows/`
- **Seed roles:** Use `Role::updateOrCreate` with fixed IDs to avoid auto-increment conflicts
