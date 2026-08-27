# Changelog

All notable changes to `myclub/common-lib` are documented in this file.

The library is released as git tags on `main`; there is no version string in
the source. Each heading below links a release to the changes it contains.

## 1.0.6 - 2026-08-27

### Changed

- `BaseActivityService::createOrUpdateActivity()` now decodes HTML entities in
  the plain text activity fields (`title`, `location`, `calendar_name`, `type`,
  `base_type` and `meet_up_place`) before they are stored. The MyClub API
  delivers these fields HTML escaped, which meant the entities ended up in the
  database and had to be worked around when the values were rendered. The
  database now holds the actual text, and escaping is left to the output code.
- The activity description is sanitized onto the activity object instead of
  only in the array that is written to the database.

### Fixed

- Activities were reported as changed on every synchronization, which cleared
  the page caches unnecessarily. The change detection compared the raw values
  from the API against the stored row, so descriptions rewritten by
  `wp_kses_post()` and integer values such as `meet_up_time` never matched the
  strings returned by the database. The values that are about to be written are
  now compared against the stored row, with both sides cast to string.
- A change to `show_on_club_calendar` was never detected. The flag is passed in
  `$calendar_array` and not on the activity object, so the previous hardcoded
  key list could not see it.
- `BaseUtils::prepareActivitiesJson()` no longer runs `addslashes()` on the
  activity description. It compensated for the `wp_unslash()` that
  `update_post_meta()` applied back when the activities were stored in post
  meta. The value now goes to the REST API, so the slashes were no longer
  removed and showed up in the block editor.

## 1.0.5 - 2026-08-06

### Fixed

- Guard against a missing activity row when an activity is removed from a post.
  `removeActivityFromPost()` dereferenced the result of `getActivity()` without
  checking it for `null`.

## 1.0.4 - 2026-07-24

### Changed

- Moved the booking calls to the external `bookings/` API namespace.
  `loadBookables()`, `loadBookableSlots()`, `loadBookableSlot()`, `bookSlot()`
  and `bookSlotsBulk()` now request `bookings/bookables/…` instead of
  `bookables/…`.

## 1.0.3 - 2026-04-21

### Changed

- The section calendar request now sends `version=2`, so that section
  activities are returned in the same format as the team activities.
- Reformatted `BaseRestApi` to follow the coding style used in the rest of the
  library.

## 1.0.2 - 2026-04-15

### Changed

- Activity descriptions are sanitized with `wp_kses_post()` instead of
  `htmlspecialchars()`. The API delivers the description as HTML, so escaping
  it wholesale meant the markup was displayed as text.

## 1.0.1 - 2026-04-02

### Added

- `bookSlotsBulk()` for booking several sessions in one API call.

### Fixed

- Newlines in activity descriptions were replaced with `<br` instead of `<br>`.

## 1.0 - 2026-01-07

First released version. Shared logic extracted from the MyClub WordPress
plugins:

- `BaseRestApi` - MyClub API client with the groups, teams, sections,
  calendar, menu, news and booking endpoints.
- `BaseActivityService` - activity and activity/post link tables, and the
  create, update, delete and listing methods for them.
- `BaseImageService` - image handling shared between the plugins.
- `BaseUtils` - shared helpers, including plugin version checks and the
  activity JSON used by the blocks.
- `BackgroundProcessing` - async request and background process base classes.
