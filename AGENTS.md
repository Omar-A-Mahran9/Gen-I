# GEN-I Backend — Project Instructions

GEN-I is a technology retail and service platform. It covers:
- a product catalog and comparison,
- store reservation,
- repair booking,
- corporate RFQ and quotations,
- a governed AI assistant.

This repository is the **Laravel 13 backend**: a Metronic admin dashboard plus a REST API at `/api/v1`. The public React frontend is a separate application and is out of scope here.

## Read first
- `docs/business-decisions.md`: approved technical decisions (TD-*) and open decisions (OT-*, BD-*). Approved decisions override the technology proposals in the source PDFs.
- `docs/architecture.md`, `docs/module-map.md`, `docs/database-design.md`, `docs/api-contracts.md`, `docs/metronic-migration.md`.
- `docs/implementation-roadmap.md`: the current phase and its gates. **Do not start a phase without the owner's explicit approval.**
- `docs/business-requirements.md`: consolidated FR/NFR/business rules, with sources.

## Hard rules
- Work only inside `Gen-I/`. The sibling folder `twasol_backend_backup_20261101_025513/` is **read-only**. It may be read as a reference for Metronic UI assets, never modified. Never copy its business code, schema, routes, permissions or branding.
- Git must run inside `Gen-I/` only. Check that `git rev-parse --show-toplevel` ends in `/Gen-I` before any git command. Never touch the home-directory repo or the Twasol repo. Never force push without explicit approval.
- Branches: `main` holds stable releases, `develop` holds integration work. Commit each completed and tested phase separately.
- Do not invent business workflows, providers, credentials, policies or numeric commitments. Unapproved items stay marked *proposed* or *open*.
- Keep secrets out of Git. `.env` is never committed, and seeders read bootstrap credentials from env.
- Verify every package against PHP 8.3 / Laravel 13 before installing it.
- **Precedence:** the GEN-I rules in this section override the generic Laravel Boost guidelines and skills below wherever they conflict. Two examples:
  - The `tailwindcss-development` skill applies only to non-admin pages. Metronic admin pages are Bootstrap 5.3 with no Tailwind.
  - Updating `docs/` is part of the approved phase workflow.
- `CLAUDE.md` and `AGENTS.md` must stay identical. After editing one, copy it over the other (`boost:update` regenerates only the `<laravel-boost-guidelines>` block).

## Stack and conventions
- PHP 8.3 (`php` on PATH). Composer: use `/opt/homebrew/bin/composer` (2.11.x). The interactive shell alias `composer` points at a missing `~/composer.phar`.
- MySQL 8.4 (local Homebrew `mysql@8.4` on 127.0.0.1:3307; start it per session with `brew services run mysql@8.4`). The app uses the least-privilege `geni` user, which can access only the databases `geni` and `geni_testing`. Never use root in the app, never change the server config, and never touch other projects' databases on that server. Never print the DB password.
- Tests: `php artisan test` uses SQLite `:memory:`. `composer test:mysql` uses MySQL `geni_testing`. Never run `migrate:fresh` or other destructive commands against anything except `geni`/`geni_testing`.
- Traditional MVC:
  - Controllers are thin, split into `Admin\*` and `Api\V1\*`.
  - Validation lives in Form Requests.
  - Authorization uses Policies/Gates plus Spatie permissions (`<module>.<resource>.<action>`).
  - Business logic lives in `App\Services\<Domain>` and is shared by Admin and API controllers.
  - Use Events, Jobs and Notifications for side effects.
- One `users` table for staff and customers. Roles and memberships decide access. There is no blanket `Gate::before` bypass.
- State changes go through Services inside DB transactions, with row locks on contended resources.
- Money uses DECIMAL and string amounts in the API. Never use floats.
- Locales: Arabic `ar` is the default and RTL; English `en` is LTR. Admin supports light and dark themes.
- Admin UI: Metronic 8.2.0 static bundles live in `public/assets/admin/`. Page plugins load only on the pages that use them. Load no CDN scripts globally. **Never load Tailwind on admin pages.** Tailwind (`resources/css/app.css`) is for non-admin pages only.
- Before each phase commit, `php artisan test` and `composer test:mysql` must both pass, and Pint must be clean.

## Admin foundation conventions (as built)
- Routes:
  - Admin routes live in `routes/admin.php`, registered with the `/admin` prefix and `admin.` names.
  - API routes live in `routes/api.php`, under `/api/v1` with `api.v1.` names.
  - Protected admin routes use the `auth`, `auth.session` and `admin.access` middleware.
- Sidebar: add items to `config/admin.php` → `menu`, each with a translation key, icon, route, `active` patterns and permission. Never hard-code items in Blade.
- Permissions: add them to `config/admin.php` → `permissions` as `<group>.<action>`, then run `php artisan db:seed`. The seeder is idempotent.
- Pages extend `admin.layouts.app` and define:
  - `@section('title')`,
  - `@section('toolbar')` containing `<x-admin.toolbar>`,
  - `@section('content')`.
- Pages reuse the `<x-admin.*>` components: card, button, alert, modal, empty-state, datatable, plugins, and `form.*` (input, select, textarea, switch, checkbox, radio-group, image-upload).
- Page plugins load through `<x-admin.plugins :use="['datatables']" />`, as declared in `config/admin.plugins`.
- Forms:
  - Mark forms `data-geni-ajax-form`.
  - One-click actions use `data-geni-action` with `data-geni-method`, plus optional `data-geni-confirm`, `data-geni-table` and `data-geni-reload`.
  - Controllers return `$this->success($request, $message, $redirect)`, so the same endpoint serves AJAX and classic posts.
- Tables: list endpoints return `App\Support\DataTable::of(...)->searchable()->sortable()->filter()->map()->toResponse()` for JSON requests. Escape every cell (`e()` or Blade partials).
- Never mix inline `@php(...)` with `@php … @endphp` blocks in one Blade file; the compiler mis-pairs them.
- Every user-facing string goes in `lang/{ar,en}/admin.php`. Keep the two files key-for-key identical.
- Build assets with `npx vite build`. The repo's `.npmrc` has `ignore-scripts=true`, which blocks `npm run build` on npm 6.
- First admin: `php artisan app:create-admin`. Never seed users or credentials.

## Business modules conventions (Phase 2)
- Read `docs/business-modules.md` first. The schema follows the S2 blueprint §9; every deviation goes in `docs/database-design.md` §9.
- Organization-scoped models use `App\Models\Concerns\BelongsToOrganization`: the `forUser()` scope plus scoped route binding (out of scope → 404). Policies extend `OrganizationScopedPolicy`.
- Create/update requests extend `App\Http\Requests\OrganizationScopedRequest`. Super admins choose the organization; others are bound to theirs. Use `existsInOrganization()` for every foreign key.
- Business logic, transactions and audit live in `App\Services\Business\*`, shared by `Admin\Business\*` and `Api\V1\*` controllers. Business-rule refusals throw `AdminActionException` (422, or 409 via `::conflict()`).
- Stock changes only go through `InventoryService::adjust()`: row lock, immutable movement, idempotency key. Prices only through `PricingService`: history is never overwritten.
- New business permissions are seeded for super-admin only. Never grant them to other roles without the approved role catalogue (BD-14).

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
