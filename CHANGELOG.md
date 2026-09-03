# Changelog

All notable changes to `filament-timepicker` will be documented in this file.

## 1.1.0 - 2026-09-04

**Upgrading:** run `php artisan filament:assets` after updating — the dropdown's
stylesheet is now a published asset. If your theme has an `@source` line for this
package's views, it's now a no-op and can be removed.

### Fixed

- **`->live()` and `afterStateUpdated()` now fire when a time is committed.** The
  view bound state with a bare `$wire.$entangle(...)`, which defers; it now goes
  through Filament's `applyStateBindingModifiers()` like every first-party field,
  so `$entangle(path, true)` is emitted for a `->live()` field. Reactive siblings
  that depend on a `SmartTimePicker` previously did not update until some other
  interaction triggered a request.
- **Tabbing past an untouched field no longer fills it in.** Focusing the field
  opens the panel and highlights the slot nearest to the current time; Tab then
  committed that highlight, so keyboard-tabbing through a form silently wrote a
  value into empty, optional time fields. Tab now commits only after the user has
  typed or arrowed.
- **Reactive `minTime()` / `maxTime()` / `interval()` closures reach the browser.**
  The component's root carries `wire:ignore` (required — see AGENTS.md), which
  also froze its `x-data` config at first render, so a bound depending on another
  field never updated the dropdown. Config now arrives through a sibling
  `data-config` element that Livewire does morph.
- **Typing into a closed panel no longer discards the filter.** `onInput()` opened
  the panel *after* filtering, and opening rebuilds the list from scratch —
  reachable by pressing Escape and then typing.
- **`Enter` in a closed picker submits the form again** instead of being swallowed
  (it still commits any text typed before the panel was closed).
- **The dropdown no longer opens in the wrong place on the first click.** The
  panel was positioned once on open, and two things then moved it: the highlight
  was revealed with `scrollIntoView`, which walks up *every* scrollable ancestor
  and so scrolled the page when the panel was clipped at the bottom of the
  viewport; and the position was applied through a `:style` string binding, which
  Alpine writes with `setAttribute("style", …)` — replacing the whole attribute,
  including the `display`/`opacity` that `x-show` and `x-transition` put on the
  same element. Scrolling the page happened to fire a reposition, which is why
  it looked right afterwards. The panel is now scrolled directly instead of via
  `scrollIntoView`, its coordinates are written as individual style properties,
  and it re-measures every frame while open (the way Floating UI's `autoUpdate`
  does) so a field that moves — focus scrolling it into view, a modal still
  animating, a late-loading font — can't strand it.
- **`TimeParser::format()` no longer touches the app timezone.** It built a
  timestamp with `mktime()`/`date()`, which put the app's zone in the path of a
  value that is definitionally wall-clock. It now formats in UTC on a fixed date.

### Added

- **`minTime()` / `maxTime()` are enforced.** Previously only `strict()` bounded
  anything, so a typed, pasted or imported time outside the window was stored
  as-is. Both sides now reject it: the browser snaps back, and a validation rule
  with its own message (`min_time`, `max_time`, stated in the field's display
  format) catches whatever bypassed the client. `strict()` keeps its former job of
  additionally confining values to the interval grid.
- **The dropdown ships its own stylesheet**, registered as a Filament CSS asset.
  Consumers on a custom theme no longer need an `@source` line for this package's
  views — the panel was rendered unstyled without one. It reads Filament's runtime
  `--gray-*` variables, so it still follows the app's theme.
- **The committed value is rendered into the input server-side,** so an edit form
  no longer flashes empty time boxes while the lazily loaded component arrives.
- **`Home`, `End`, `PageUp` and `PageDown`** move through the suggestions, and the
  input gets `aria-invalid` when the field has an error (kept current across
  roundtrips, as is `disabled`, despite the `wire:ignore` root).
- **A JavaScript test suite** (`npm test`, via `node --test`) covering the Alpine
  component. It and the PHP suite both run `tests/Fixtures/parse-cases.json`, so
  the two mirrored parsers can no longer drift silently. Also added architecture
  tests and feature tests that render the field through a real Livewire component.
- `displayFormat` now honours `date()`-style backslash escapes on the client, as
  it already did on the server.

### Changed

- **Requires `filament/forms` instead of `filament/filament`.** The field only ever
  used Forms, Schemas and Support; depending on the full panel package meant a
  plain Livewire app couldn't install it. No change for panel users.
- `defaultDuration()` auto-fill is clamped into `[minTime, maxTime]`, not just
  capped at the ceiling.
- Removed `resources/css/index.css`, which was unreferenced and pointed at a path
  that only resolved inside this repository.

## 1.0.6 - 2026-07-21

No runtime changes — the field behaves identically to 1.0.5. This release covers
project infrastructure only.

- CI: test matrix across PHP 8.2–8.4 and Laravel 12/13, plus a `prefer-lowest`
  run. Laravel 11 is not tested: the entire 11.x line is blocked by Composer's
  security advisory policy and can no longer be installed.
- CI: pint, phpstan and rector run on every push and pull request.
- CI: the committed `resources/js/dist/` build is now verified against its source,
  so it can't silently drift for consumers.
- Security: added `SECURITY.md` with a private disclosure process, and enabled
  Dependabot for composer, npm and GitHub Actions. All Actions are pinned to
  commit SHAs.
- Internal: applied pending Rector fixes (`declare(strict_types=1)`, first-class
  callables, a `preg_replace` string cast) — all behaviour-preserving.
- `minimum-stability` is now `stable` rather than `dev`. This has no effect on
  consumers: Composer only honours that key from the root package.

## Unreleased

- Initial development: `SmartTimePicker` form field with free-text parsing,
  interval-based suggestion dropdown, keyboard navigation, and `durationFrom`
  duration labels.
- Fix: live filtering now normalizes `.`/`h` separators, so partial dotted/French
  input (`9.`, `9.3`, `9h3`) narrows the dropdown the same way `9:3` does.
- Accessibility: implement the ARIA combobox/listbox pattern (`role`,
  `aria-expanded`, `aria-controls`, `aria-activedescendant`, `aria-selected`).
- i18n: move all user-facing strings (dropdown empty state, strict-mode validation
  message, `durationFrom` duration words) into publishable translations under the
  `harvirsidhu-filament-timepicker` namespace.
- UX: the suggestion panel now flips above the input when there isn't room below.
- Mobile: the panel is measured against the visual viewport, so it stays in view
  when the on-screen keyboard opens, and caps its height to the visible space
  (scrolling internally) instead of clipping off-screen; it re-positions on
  `visualViewport` resize/scroll (keyboard show/hide, pinch-zoom).
- Mobile: dropdown options use a touch-friendly row height (≈44px), compact on
  larger pointers (`sm:` breakpoint).
- Mobile: a drag that starts on an option now scrolls the list instead of
  selecting — options commit on `pointerup` only when the pointer barely moved
  (tap vs. scroll), and `touch-pan-y` guarantees vertical scrolling.
- Fix: re-position the panel after the DOM settles (nextTick + a frame), not only
  on the focus tick — inside a Filament modal the input's measured position could
  be stale at open time, so the panel opened downward (and clipped) until a manual
  scroll corrected it.
- UI: match Filament's own Select dropdown — the panel is pinned to the input
  width, options use the neutral `bg-gray-50`/`dark:bg-white/5` highlight (not a
  primary tint) with rounded rows and `p-1` list padding.
- UX: focusing or clicking the field now selects the whole value, so the next
  keystroke replaces it (matches Google Calendar's time field).
- Feature: `durationFrom()` (floors the dropdown after a sibling time and labels
  each option with the gap) plus `defaultDuration()` — auto-fills this field with
  the sibling's time + N minutes whenever the sibling changes, still overridable.
  Keeps the gap: a start change shifts the end to preserve the current gap, read
  live from the end value (so a duration set elsewhere — e.g. a type-driven `$set`
  via `afterStateUpdated` — is respected), all client-side with no server
  roundtrip; seeded from an existing pair on edit forms.
- UX: `durationFrom` duration labels now use a hybrid format shown in brackets —
  friendly words up to an hour (`(30 mins)`, `(1 hour)`), compact past an hour
  (`(1h 30m)`, `(2h)`). Adds `duration.short_hour` / `short_minute` translations.
- Change: the default display format is now `g:i a` (lowercase, e.g. `3:30 pm`)
  instead of `g:i A` (`3:30 PM`). Set `->displayFormat('g:i A')` to keep uppercase.
- UX: an empty field opens with the slot nearest the current time highlighted
  (instead of always the first option); a committed value still highlights itself.
- Fix: scroll the highlighted option into view on open (the previous index counted
  the `<template>`/empty-state nodes, so it never scrolled to the right row).
- UX: in loose (non-strict) mode a validly-typed off-grid time (e.g. `9:20 AM`, or
  the partial `9:20 A`) now appears as a selectable "Custom" suggestion in the
  dropdown instead of "No matching time".
- Fix: add `wire:ignore` to the Alpine root so a Livewire DOM morph no longer
  re-inits the teleported dropdown outside its scope ("… is not defined" errors).
- Docs: document Filament's `->default()` (static and closure) for pre-filling.
- Tooling: add `phpstan.neon.dist` so `composer analyse` works; `export-ignore`
  `package-lock.json`.
