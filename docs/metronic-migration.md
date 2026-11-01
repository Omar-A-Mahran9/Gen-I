# GEN-I — Metronic Migration Plan

| | |
|---|---|
| Source | `../twasol_backend_backup_20261101_025513/public/assets/dashboard/` — **read-only**, never modified (TD-05) |
| Version | Metronic **8.2.0** (Bootstrap **5.3.0**, KeenIcons). Identified from the `Product Version: 8.2.0` header in the Twasol views and the Bootstrap banner in `plugins.bundle.js` |
| Approach | Precompiled static bundles served from `public/assets/admin/` (no SASS build) |
| Target size | ≈ 20 MB (vs 99 MB in Twasol). Only listed files are copied |

## 1. Target structure

```
public/assets/admin/
  VERSION                              source, version, copy date, file manifest + sha256
  css/
    style.bundle.css                   (1.5 MB)
    style.bundle.rtl.css               (1.4 MB)
  js/
    scripts.bundle.js                  (236 KB) KTMenu, KTDrawer, KTThemeMode, KTAppLayoutBuilder …
  plugins/
    global/
      plugins.bundle.css
      plugins.bundle.rtl.css
      plugins.bundle.js                jQuery, Popper/Bootstrap, Select2, SweetAlert2, toastr, flatpickr,
                                       FormValidation, Inputmask, ApexCharts, Dropzone, Tempus Dominus,
                                       maxlength, clipboard, smooth-scroll (all bundled in 8.2.0)
      fonts/keenicons/                 (4.1 MB) duotone / outline / solid
      fonts/bootstrap-icons/           (284 KB)
      fonts/@fortawesome/              (940 KB)  ─┐ referenced by plugins.bundle.css; keep so the CSS
      fonts/line-awesome/              (3.1 MB)  ─┘ has no broken @font-face URLs
    custom/
      datatables/                      datatables.bundle.{js,css,rtl.css}   (page-level)
      formrepeater/                    formrepeater.bundle.js                (page-level)
      <editor>/                        TinyMCE **or** CKEditor — one only, chosen when a module needs rich text
      fslightbox/                      only if an approved page needs a media lightbox
  i18n/datatables/{ar,en}.json         from js/datatable-translations/
  media/
    avatars/blank.png
    flags/saudi-arabia.svg, flags/united-states.svg   (language switcher; replace with an Egypt flag if BD-04 confirms the EGP/Egypt market)
    auth/404*.png, auth/500*.png       (light + dark error illustrations)
    auth/<one background pair>         (login page background, light + dark; chosen at implementation)
    illustrations/<empty-state set>    (≤ 3 files for "no data" states)
  sounds/                              NOT copied unless an approved notification UX requires audio
  brand/                               GEN-I logos/favicon — placeholders until BD-12 assets are supplied
```

`VERSION` example:
```
Metronic 8.2.0 (Bootstrap 5.3.0) — static precompiled bundles
Source: twasol_backend_backup_20261101_025513/public/assets/dashboard (read-only)
Copied: <date>
Files: <path>  <sha256>  …
```

## 2. Explicitly NOT migrated

| Twasol path | Reason |
|---|---|
| `js/custom/**` (130 Metronic demo-app scripts) | Demo pages (ecommerce, projects, chat …) |
| `js/widgets.bundle.js` (588 KB) | Demo dashboard widgets. Add back only if an approved dashboard widget needs it |
| `js/datatables/*` (35 module files), `js/forms/*`, `js/quotations/*`, `map_create.js` | Twasol business modules |
| `js/favicon-badge.js` | Tied to Twasol notifications |
| `css/quotations.css`, `css/datatables*.bundle.css` (older duplicate) | Business / duplicate of `plugins/custom/datatables` |
| `Categories/` (gold/silver/diamond SVGs) | Twasol business icons |
| `media/stock` (25 MB), `media/products`, `media/books`, `media/demo`, `media/email`, most `media/svg`, `media/illustrations`, `media/avatars/300-*` | Demo content |
| `plugins/custom/{fullcalendar,vis-timeline,jkanban,jstree,leaflet,prismjs,typedjs,draggable,cropper,cookiealert,flotcharts}` | Not required by any approved GEN-I page yet. Add per page when justified |
| amCharts CDN scripts (11, loaded globally in Twasol) | Global CDN loading is forbidden (TD-06/UI rules) |
| Google Fonts `Cairo` link | Font choice is part of BD-12. Until then use Metronic's default stack, or self-host the font if approved |
| `public/placeholder_images/*` (Al-raqi / Platin logos, backgrounds) | **Other clients' branding** |
| `resources/views/**` | Not copied. Used as a *reference* for rebuilding (see §4) |

## 3. Reusable JavaScript — rewritten, not copied verbatim

Twasol's shared scripts mix generic helpers with business code. Each is re-authored into `resources/js/admin/` (Vite entry `admin`) with Twasol references removed:

| Twasol source | GEN-I target | Keep | Remove |
|---|---|---|---|
| `shared/js/global.js` (61 lines) | `resources/js/admin/validation.js` | display/remove Laravel 422 errors, form-repeater field mapping, scroll to first error | — |
| `dashboard/js/global/scripts.js` (527 lines) | `resources/js/admin/ui.js`, `ajax-form.js` | block/unblock UI, SweetAlert wrappers (confirm, delete, success, error), `ajaxSubmission`/`submitForm`, toast, tooltip re-init, password toggle | `getStatusObject`/`ordersStatuses`, `changeStatusAlert` wording, `/storage/Images` paths, sounds |
| `dashboard/js/global/datatable-config.js` (327 lines) | `resources/js/admin/datatable.js` | server-side DataTable init, search/filter binding, bulk-select, delete handling, locale JSON loading | hard-coded `#kt_datatable_orders`/`_transactions` selectors |
| `dashboard/js/global/crud-operations.js` (24 lines) | merged into `ajax-form.js` | generic modal create/edit | — |
| `dashboard/js/global/translations.js` (219 lines) | `lang/{ar,en}.json` exposed via a small `window.GENI.i18n` bootstrap | generic UI strings | offers/orders/customers strings |
| `dashboard/js/components/select2-ajax.js`, `dropzone.js`, `form_repeater.js`, `wizard.js` | `resources/js/admin/components/*` | generic behaviour | Twasol endpoints |
| `dashboard/css/custom.css` (56 lines) | `resources/css/admin.css` | RTL fixes for Select2 clear button, DataTables alignment, password-field invalid icon | `.pac-container` (Google Places) unless maps are approved |

## 4. Blade rebuild (Laravel 13 conventions)

Twasol files are a **visual reference only**. Components are new.

| GEN-I component / view | Reference | Notes |
|---|---|---|
| `components/admin/layouts/app.blade.php` (`<x-admin.layouts.app>`) | `dashboard/partials/master.blade.php` | `lang`/`dir` from locale, `data-bs-theme` bootstrap script, `app-default` body attributes (fixed header, hoverable fixed sidebar, toolbar), slots: `title`, `breadcrumbs`, `toolbarActions`, default |
| `admin/partials/head.blade.php` | `partials/head.blade.php` | Real GEN-I meta only (no Metronic marketing meta). LTR/RTL bundle switch. `@stack('vendor-styles')` |
| `admin/partials/header.blade.php` | `partials/header.blade.php` (805 lines) | Logo, mobile sidebar toggle, language switcher, theme-mode menu, user menu. Notifications dropdown added only when the notifications module exists |
| `admin/partials/sidebar.blade.php` + `<x-admin.sidebar-item>` | `partials/aside.blade.php`, `components/dashboard/aside-item` | Rendered from `config/admin-menu.php` through `App\Support\AdminMenu`, filtered by `@can` permission. Active-state by route name pattern |
| `<x-admin.toolbar>` / `<x-admin.breadcrumbs>` | toolbar block in `master` | Title + breadcrumb array + action slot |
| `admin/partials/footer.blade.php` | `partials/footer.blade.php` | GEN-I copyright from settings |
| `admin/partials/scripts.blade.php` | `partials/foot.blade.php` | Core bundles + Vite admin entry + `@stack('vendor-scripts')` + `@stack('scripts')`. **No CDN** |
| `components/admin/layouts/auth.blade.php` + `admin/auth/login.blade.php` | `auth/admin_login.blade.php` | Metronic sign-in layout, GEN-I branding, locale switch, throttled |
| `<x-admin.card>`, `<x-admin.modal>`, `<x-admin.datatable>`, `<x-admin.form.input/select/textarea/switch/radio-group>`, `<x-admin.image-upload>`, `<x-admin.empty-state>`, `<x-admin.alert>` | `components/dashboard/{radio-btn,upload-image-inp}` + Metronic markup | Labelled, accessible error markup (NFR-01) |
| `<x-admin.plugins :use="['datatables']">` | — | Pushes the listed page plugins (CSS + RTL CSS + JS) once per page |
| Error pages `errors/{403,404,419,500,503}` for admin | Metronic auth illustrations | Light/dark images |

**Menu config shape (`config/admin-menu.php`):**
```php
return [
    ['key' => 'dashboard', 'label' => 'admin.menu.dashboard', 'route' => 'admin.dashboard',
     'icon' => 'ki-outline ki-element-11', 'permission' => null],
    ['key' => 'access', 'label' => 'admin.menu.access', 'icon' => 'ki-outline ki-shield-tick',
     'children' => [
        ['key' => 'users', 'label' => 'admin.menu.users', 'route' => 'admin.users.index', 'permission' => 'identity.users.view'],
        ['key' => 'roles', 'label' => 'admin.menu.roles', 'route' => 'admin.roles.index', 'permission' => 'identity.roles.view'],
     ]],
    // module entries are added by each module phase
];
```

## 5. Tailwind isolation (TD-08)

- The admin layout uses `@vite(['resources/css/admin.css', 'resources/js/admin/app.js'])` only.
- `resources/css/admin.css` must **not** `@import "tailwindcss"`.
- `resources/css/app.css` (Tailwind) is used only by non-admin pages (e.g. `welcome.blade.php`).
- `vite.config.js` gains the two admin inputs. The Tailwind plugin stays and processes only files that import it.
- **Verification:** the built admin CSS contains no Tailwind preflight (`*, ::before, ::after { … border: 0 solid }`), checked in Phase 2.

## 6. Branding (BD-12)

- Until GEN-I assets are supplied: neutral text logo "GEN-I" (SVG generated from text), a neutral favicon, and Metronic's default primary colour.
- When the palette is supplied: override Bootstrap 5.3 / Metronic CSS variables in `admin.css` (`--bs-primary`, `--bs-primary-active`, `--bs-primary-light`, `--bs-primary-inverse`, `--bs-app-sidebar-*`) for both `[data-bs-theme=light]` and `[data-bs-theme=dark]`.
- Font: Metronic default (Inter) until BD-12. The Arabic font (e.g. Cairo/IBM Plex Sans Arabic) must be confirmed, and self-hosted if chosen.

## 7. Migration steps (executed in Phase 2 after approval)

1. Generate the copy manifest (this document §1) as a script in the scratchpad. Copy with `cp` from the read-only source. Never move, never `rsync --delete`.
2. Write `VERSION` with sha256 per file. Confirm the Twasol tree is unchanged (`git -C <twasol> status --porcelain` is empty before and after).
3. Grep the copied tree for `raqi`, `platin`, `twasol`, `tawasol`, `keenthemes.com/metronic` meta → expect zero matches outside vendor licence headers.
4. Build the Blade components and admin Vite entry. Render the login + empty dashboard in `ar` (RTL) and `en` (LTR), light and dark.
5. Confirm there are no 404s for fonts or assets in the browser network log, and no console errors.
6. Commit as one phase commit on `develop`.

## 8. Licensing note

The owner directed that licence confirmation is not blocking (TD-05). The ThemeForest licence terms for using Metronic in GEN-I remain the owner's responsibility. This is recorded here for traceability only.

## 9. As built (2026-11-01)

- Copied with a scripted allow-list (61 files, 21 MB): core CSS (LTR + RTL), `scripts.bundle.js`, global plugins (CSS LTR/RTL + JS) and all four icon-font folders, `plugins/custom/datatables`, media (`auth` 404/500 light+dark, `bg4` pair, `avatars/blank.png`, `svg/blank-image*`, one empty-state illustration pair), and IBM Plex Sans Arabic (OFL) from the Twasol backup's font folder. The Twasol tree was verified unchanged (`git status` clean) before and after.
- Not copied: editors (TinyMCE/CKEditor), form repeater, fslightbox, flags (the language switcher uses text badges, avoiding a market assumption), Twasol's DataTables translation JSON (not valid JSON — it contained `__()` calls); DataTables strings come from `lang/*/admin.php`.
- This Metronic build is a light, white-sidebar demo variant (no `dark-sidebar` layout CSS); the GEN-I layout follows the structural classes that exist in this bundle.
- Metronic hard-codes `html, body { font-family: Inter… }`; the Arabic font is therefore applied with an `html[lang=ar], html[lang=ar] body` rule plus the Bootstrap font variables.
- Brand placeholders live in `public/assets/admin/brand/` (text wordmark + "G" icon, explicitly not official assets) and are replaced from **Settings → Branding** (stored on the `public` disk under `branding/`; PNG/JPG/WebP logos, PNG/ICO favicon; SVG rejected).
- Verified in headless Chrome (desktop, laptop, tablet, mobile; light/dark; ar/en): no horizontal overflow, mobile drawer works, no asset 404s, KeenIcons load, and the admin CSS contains no Tailwind rules.
