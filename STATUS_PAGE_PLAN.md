# Status Page (status.example.com) — Build Plan

## Context

We want a public status page like `https://status.claude.com/` — showing overall
system health, per-component status, active/past incidents with a timeline, scheduled
maintenance, and subscriber notifications.

The `mohab/laravel-blueprint` package is our standard application foundation, and a
status page is almost entirely CRUD over a few entities plus a cached public read view
and notifications. That maps directly onto what the blueprint already provides
(repository/service scaffolding, `HasStatus`, `HasSlug`, `HasUuid`, the settings module,
`CacheHelper`, `MailHelper`/`FcmHelper`, `Responser`, `PaginationCollection`,
`SetLocale` + translatable). So we build the status page as a **new Laravel app that
consumes the blueprint**, staying consistent with our other projects.

The "subdomain" itself is infrastructure (DNS + web-server vhost + TLS), not something a
Composer package does — but nothing in the package prevents it; the app is served on the
subdomain the normal Laravel way.

**Outcome:** an independently deployable status app at `status.example.com`, built on our
standard architecture, that stays online even when the monitored system is down.

---

## Topology decision

**Recommended: standalone app** deployed at `status.example.com` (its own Laravel
install, DB, queue worker, scheduler, and deploy pipeline).

Rationale: a status page must stay up when the thing it monitors is down. Sharing a
process/DB with the main app defeats its purpose. The alternative — a
`Route::domain('status.example.com')->group(...)` inside an existing app — is quicker but
dies with the main app, so it is only acceptable for a low-stakes internal page.

---

## Domain model

| Entity | Purpose | Key fields / notes |
|--------|---------|--------------------|
| `ComponentGroup` | Grouping of components (e.g. "API", "Website") | `name` (translatable), `sort_order` |
| `Component` | A monitored service | `status` enum, `component_group_id`, `slug`, `uuid`, `sort_order` |
| `Incident` | An outage/degradation event | `status` enum, `impact` enum, `slug`, `uuid`, `title`/`body` (translatable) |
| `IncidentUpdate` | Timeline posts on an incident | `incident_id`, `status`, `body`, timestamps |
| `IncidentComponent` | Pivot: which components an incident affects | `incident_id`, `component_id`, affected status |
| `MaintenanceWindow` | Scheduled maintenance | `scheduled_start`/`scheduled_end`, `published_at` |
| `Subscriber` | Notification recipients | `email`/`phone`/`webhook_url`, `verified_at`, double opt-in token |
| `UptimeCheck` / `Metric` (optional) | Automated probing + uptime % graphs | `component_id`, `checked_at`, `is_up`, `response_ms` |

**Status enums** (PHP enums using the package `Enumable` trait):
- Component status: `operational`, `degraded_performance`, `partial_outage`, `major_outage`, `under_maintenance`
- Incident status: `investigating`, `identified`, `monitoring`, `resolved`
- Incident impact: `none`, `minor`, `major`, `critical`

---

## How the package is used

- **Scaffolding** — `php artisan module:add-repository <Name> --crud --model` generates
  Model/Repository/Interface/Service/Controller per entity (module-based). For a plain-app
  layout, extend the base classes directly:
  - Models extend `Mohab\LaravelBlueprint\Models\BaseModel`.
  - Repositories extend `Mohab\LaravelBlueprint\Repository\Eloquent\Repository` and
    implement an interface extending `RepositoryInterface`.
- **Migrations** — use the blueprint macros: `slug()`, `uuid()`, `status($default)`,
  `ordering()`, `publishedAt()`, `audit()`, `meta()`, `userId()`.
- **`BaseModel`** — soft deletes, actor tracking (`created_by`), human-readable dates,
  translatable fallback for multilingual content.
  - Note the documented `HumanDates` gotcha: `created_at`/`updated_at` render as localized
    strings on `BaseModel` children; for the public API/JSON timeline use
    `getRawOriginal()` or dedicated ISO fields where machine-readable timestamps matter.
- **`HasStatus`** + `Enumable` enums — component/incident state transitions and scopes
  (`scopeWithStatus`, `activate`/`deactivate`, `setStatus`).
- **`HasSlug` + `HasUuid`** — pretty, stable public URLs (`/incidents/{slug}`) without
  leaking DB ids.
- **Settings module + `getSettings()`** — site name, logo, timezone, support URL, theme;
  editable without deploys and already cached (lazy, crash-safe before migration).
- **`CacheHelper`** — cache the assembled public status payload (read-heavy, write-rare);
  bust the cache on any incident/component/maintenance write.
- **`MailHelper` (email) + `FcmHelper` (push)** — subscriber notifications on incident
  create/update. `FcmHelper` needs the optional `kreait/firebase-php` package.
- **`Responser` + `PaginationCollection`** — consistent JSON API for a SPA/mobile/embed.
- **`SetLocale` + translatable** — `en`/`ar` status pages.

---

## Feature scope (phased)

### Phase 0 — Project + infra skeleton
1. New Laravel app; add the blueprint via the `SETUP.md` VCS-repo + `COMPOSER_AUTH` flow;
   `composer require mohab/laravel-blueprint`.
2. `php artisan migrate` (creates `seeders` + `settings` tables).
3. Register the `set.locale` middleware alias (per README).

### Phase 1 — Data + admin (internal)
4. Migrations for the entities above using blueprint macros.
5. Status/impact PHP enums with `Enumable`.
6. Scaffold Repository/Service/Interface/Controller per entity; wire auth-protected admin
   routes and forms (create/update incidents, post updates, toggle component status).
7. Seed initial component groups/components via `make:seeder` + `generate:seed`.

### Phase 2 — Public status page
8. Public controller assembling the payload: overall status banner (worst component
   status), component tree, active incidents, recent history (last N days), upcoming
   maintenance.
9. Cache the payload with `CacheHelper`; invalidate on any relevant write (model observers).
10. Render via Blade (SSR, best for a status page's SEO/uptime simplicity) — optionally
    expose a JSON API (`/api/v1/status`) for embeds/mobile.

### Phase 3 — Notifications
11. `Subscriber` CRUD with double opt-in (email confirmation token).
12. On incident create/update → queued job → fan-out via `MailHelper` / `FcmHelper` /
    webhooks. Respect per-subscriber channel + component scoping.

### Phase 4 — Uptime automation (optional)
13. Scheduled job (`php artisan schedule:run`) probing each component's health URL.
14. Threshold logic to auto-open/auto-resolve incidents; store `UptimeCheck` rows.
15. Compute and display uptime % (e.g. 90-day) per component.

---

## Subdomain + infrastructure (standalone app)

1. **DNS** — add `status` record: `A`/`AAAA` to the server IP, or `CNAME` to the LB/host.
2. **Web server vhost** (nginx/Apache) with `server_name status.example.com` and
   `root .../status-app/public`.
3. **TLS** — issue a cert for `status.example.com` (Let's Encrypt or your CA).
4. **App config** — `APP_URL=https://status.example.com`; its own `.env`, DB, Redis,
   queue worker, and scheduler.
5. **Deploy** — a pipeline independent from the main app (containerized via the repo's
   `docker-development` setup if desired, reusing the `COMPOSER_AUTH` token flow).

---

## Alternatives considered

- **Cachet** (open-source Laravel status page) does most of this out of the box and is the
  faster path if "get a page live" is the only goal. We build on the blueprint instead to
  match our standard architecture, share conventions/traits with our other apps, and own
  the data model — which is the point of standardizing on this package.

---

## Verification

- **Boot safety**: on a fresh DB, `php artisan migrate` succeeds (the blueprint no longer
  queries `settings` at boot; `getSettings()` is lazy/crash-safe).
- **Admin flow**: create a component → set status to `major_outage` → public page banner
  and component reflect it; open an incident, post updates, resolve it → timeline updates.
- **Caching**: public payload served from cache; a component/incident write busts it
  (assert cache key cleared / value refreshed).
- **Notifications**: create a verified subscriber → open an incident → assert queued
  notification dispatched (`Mail::fake()` / `Queue::fake()` in tests; `MailHelper::guard()`
  short-circuits when SMTP is unreachable).
- **Subdomain**: `https://status.example.com` resolves, serves over TLS, and renders the
  public page independently of the main app.
- **i18n**: switching locale (`set.locale`) renders `en`/`ar` content.
