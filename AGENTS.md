# AGENTS

Repo root (for agents): `myclub-php-lib/` (this directory).

## Project overview

**Shared PHP library** (`myclub/common-lib`) consumed by all MyClub WordPress plugins (`myclub-booking`, `myclub-groups`). Provides the API client, background-processing framework, and database/media services so each plugin does not duplicate that logic.

- **Package name:** `myclub/common-lib`
- **Root namespace:** `MyClub\Common\`
- **Autoload:** PSR-4 from `src/`
- **PHP requirement:** ≥ 7.4
- **External dependencies:** none (only `ext-json`)
- **Source repository:** fetched via VCS from GitHub; consumed plugins install it to their `lib/` directory

## Key paths

| Path | Purpose |
|---|---|
| `src/Api/BaseRestApi.php` | HTTP client for MyClub backend API (`api/v3/external/`) |
| `src/BackgroundProcessing/Async_Request.php` | Abstract base for single async AJAX requests |
| `src/BackgroundProcessing/Background_Process.php` | Abstract base for persistent queue batch processors |
| `src/Services/BaseActivityService.php` | Database layer for activity (event) records |
| `src/Services/BaseImageService.php` | WordPress media-library integration for remote images |
| `src/BaseUtils.php` | Static helpers — cache clearing, URL rewriting, date formatting |

## Class reference

### `BaseRestApi` (concrete)

Handles all HTTP communication with `https://member.myclub.se/api/v3/external/`. Plugins subclass it and set `$apiKeyOptionName` to point at their own WordPress option.

**Outbound headers:** `Authorization: Api-Key {key}`, `X-MyClub-RestApi`, `X-MyClub-MultiSite`, `X-MyClub-Site`, `X-MyClub-Version`.

**Key methods:**

| Method | Description |
|---|---|
| `loadClubCalendar()` | Fetch all club events |
| `loadGroup($groupId)` | Load group with members and activities |
| `loadNews($groupId, $sectionId)` | Fetch news items |
| `loadMenuItems()` | Team navigation menu |
| `loadSections()` / `loadSection($id)` | List or get section |
| `loadSectionCalendar($id)` | Section-specific calendar |
| `loadBookables()` | Fetch bookable resources |
| `loadBookableSlots($bookableId, $start, $end)` | Available time slots |
| `loadBookableSlot($bookableId, $slotId)` | Single slot detail |
| `bookSlot(...)` | POST a booking |

### `Async_Request` (abstract)

One-off async task dispatched to WordPress `admin-ajax.php`. Subclasses implement `handle()`.

### `Background_Process` (abstract, extends `Async_Request`)

Persistent queue processor backed by `wp_options`. Handles memory/time limits, locking, pause/resume/cancel, and a WP-Cron healthcheck. Subclasses implement `task($item)`.

Key control methods: `push_to_queue()`, `save()`, `dispatch()`, `cancel()`, `pause()`, `resume()`, `is_processing()`, `is_queued()`.

### `BaseActivityService` (static methods)

Manages two database tables (names configurable via suffix):
- **Activities table** — id, uid, section_id, title, day, start/end times, location, description, calendar_name, type, base_type, meet-up fields, `show_on_club_calendar`
- **Activities link table** — junction between activities and WordPress posts

Key methods: `createOrUpdateActivity()`, `deleteActivity()`, `getActivity()`, `listClubActivities()`, `listSectionActivities()`, `listPostActivities()`, `addActivityToPost()`, `removeActivityFromPost()`, `createActivityTables()`, `deleteActivityTables()`.

### `BaseImageService` (static methods)

Downloads remote images into the WordPress media library, deduplicates via postmeta lookup, and optionally sets featured images. Uses taxonomy `myclub-images` for categorisation.

Key methods: `addImage($url, $prefix, $caption, $type)`, `addFeaturedImage($post_id, $image, $prefix, $caption, $type)`.

### `BaseUtils` (static methods)

| Method | Description |
|---|---|
| `changeHostName($url)` | Replace hostname with current site URL |
| `clearCacheForPage($post_id)` | Purge 15+ cache-plugin integrations |
| `detectCachePlugin()` | Identify active cache solution |
| `formatDateTime($utc)` | Convert UTC → local timezone |
| `prepareActivitiesJson($activities)` | Format activities for JSON output |
| `sanitizeArray($array)` | Recursively sanitise array values |
| `updateOrCreateOption($name, $value)` | Create or update a WordPress option |

## Architecture patterns

- All classes are **static utilities or concrete/abstract service classes** — no DI container.
- Plugins **extend** `BaseRestApi` and set `$apiKeyOptionName` to their own option key.
- Background tasks **extend** `Background_Process` and implement `task($item)`; they are instantiated at plugin bootstrap so WordPress registers the AJAX and cron hooks.
- Library is installed to the plugin's `lib/` directory (not `vendor/`) via `"config": {"vendor-dir": "lib"}` in the consumer's `composer.json`.

## Common commands

```bash
# No build step — pure PHP library.
# Consumers install it via Composer:
composer install   # run from the consuming plugin (myclub-booking or myclub-groups)
```
