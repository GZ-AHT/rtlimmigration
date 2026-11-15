# RTL Immigration: Immigration Management System + CMS

Laravel 13 · Vue 3 · Inertia · TypeScript · Tailwind CSS v4 · MySQL

- Requirements (source of truth): `RTL_Immigration_Complete_Admin_Dashboard_Requirements.md`
- Roadmap, decisions and open questions: `docs/ROADMAP.md`
- Admin design reference: `admin-demo.html`
- Website design reference: Figma file `RTLIMMIG`

## Requirements

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `intl`, `bcmath`, `gd`, `zip`
- MySQL 8
- Node 22 and **npm 10 or newer**: `package.json` uses npm aliases that npm 9 cannot parse

## Setup

```bash
composer install
npm install
cp .env.example .env        # then set DB_DATABASE / DB_USERNAME / DB_PASSWORD
php artisan key:generate
php artisan migrate --seed  # roles, permissions, default settings
npm run build               # or: composer run dev
```

Tests use a separate database, `rtl_immigration_testing` (see `phpunit.xml`).

### Local demo accounts

In the `local` environment, the seeder creates one user per role. The password for each is `password`.

| Role         | Email                      | Lands on     |
| ------------ | -------------------------- | ------------ |
| Super Admin  | `super-admin@example.com`  | `/admin`     |
| Lead Manager | `lead-manager@example.com` | `/admin`     |
| Counselor    | `counselor@example.com`    | `/counselor` |
| Accounts     | `accounts@example.com`     | `/accounts`  |
| Student      | `student@example.com`      | `/portal`    |

The panel component reference is at `/admin/ui-kit` (local only).

## Checks

```bash
./vendor/bin/pest           # tests
./vendor/bin/pint           # PHP formatting
./vendor/bin/phpstan analyse --memory-limit=1G
npm run check               # lint + format
npm run types:check         # vue-tsc
npm run build
```

## Where things live

| Concern                               | Location                                                                 |
| ------------------------------------- | ------------------------------------------------------------------------ |
| Roles & permission registry           | `config/rbac.php`, `database/seeders/RolesAndPermissionsSeeder.php`      |
| Sidebar menus (all four areas)        | `config/navigation.php`, `app/Support/Navigation.php`                    |
| Database-driven settings              | `app/Support/Settings/SettingsRepository.php`                            |
| Document numbers (STU-, FILE-, INV-…) | `config/numbering.php`, `app/Support/Numbering/NumberGenerator.php`      |
| Area routes                           | `routes/areas.php`                                                       |
| Panel layout & shared components      | `resources/js/layouts/PanelLayout.vue`, `resources/js/components/panel/` |
| Design tokens (panel + website)       | `resources/css/app.css`                                                  |
