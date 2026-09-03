# AGENTS.md — filament-timepicker

Briefing for any coding-agent session working in this package. Read this first.

## What this is

`harvirsidhu/filament-timepicker` — a standalone Filament v4/v5 **form field** package
providing `SmartTimePicker`, a smart, type-ahead time combobox: free-text parsing
(`3p`, `330`, `3:30 PM`), an interval dropdown, keyboard nav, and optional `durationFrom`
duration labels. It is **domain-agnostic** — it knows nothing about clinics, appointments, or
availability. Bounds and relative behaviour are supplied by the consuming form.

It was extracted from the `cliniclah-app` clinic project, which is its first consumer.

## Architecture (and the rules that must not break)

- **Standalone Plugin, no `Plugin` object.** Per Filament's docs, a custom field is a
  "Standalone Plugin": all config lives in `FilamentTimepickerServiceProvider` (Spatie
  `PackageServiceProvider`). Do **not** add a `Filament\Contracts\Plugin` class — that's only
  for panel plugins.
- **`SmartTimePicker extends Field`** (`src/SmartTimePicker.php`) with a custom Blade view
  (`resources/views/time-picker.blade.php`). It reuses Filament's affix concerns so consumers
  can opt into input chrome — e.g. `->prefixIcon(Heroicon::OutlinedClock)`. No prefix is set by
  default.
- **Wall-clock `H:i` storage contract — NEVER introduce a timezone shift.** A 3 PM slot must
  store as `15:00`. `Support\TimeParser` is the single source of truth and is the authoritative
  normalizer in `dehydrateStateUsing`. The clinic app has a regression test
  (`AppointmentTest` → "stores appointment slot times as wall-clock") that guards this from the
  consumer side.
- **Two parsers that mirror each other.** `src/Support/TimeParser.php` (PHP, authoritative) and
  the `parse()`/`toDisplay()` methods in `resources/js/components/smart-time-picker.js` (JS, for
  instant UX). **If you change parsing rules, change both**, and add a row to
  `tests/Fixtures/parse-cases.json` — the shared table both suites iterate, so drift fails CI.
- **State decoupling.** The `<input>` is bound to a local Alpine `display` string, not
  `wire:model`. The canonical value is written to the entangled `state` only on commit. This is
  deliberate — it stops Livewire echoing state back and clobbering the cursor. Don't "simplify"
  it to a direct `x-model="state"`.
- **`wire:ignore` on the `x-data` root is load-bearing — don't remove it.** The suggestion
  panel uses `<template x-teleport="body">` to escape overflow clipping. When Livewire morphs
  the DOM after a roundtrip it re-processes that template and re-inits the teleported `<ul>`
  *outside* the component's Alpine scope, so every binding on it (`panelStyle`, `isOpen`,
  `filtered`, `listboxId()`, …) throws "… is not defined". `wire:ignore` tells Livewire to skip
  the Alpine-managed subtree; state still syncs via `$entangle` + the `init()` `$watch`, exactly
  like Filament's own Alpine inputs (e.g. `Select`).
- **Config reaches the browser through the `data-config` bridge, not `x-data`.** The flip side of
  `wire:ignore` is that Livewire can never morph the `x-data` attribute, so a reactive
  `minTime()`/`maxTime()`/`interval()` closure would stay pinned to its first-render value. The
  view therefore renders `SmartTimePicker::getAlpineConfig()` **twice**: inline in `x-data` for
  the initial boot, and onto a sibling `<div id="{id}-config" data-config="…" hidden>` that sits
  *outside* the ignored subtree. `watchConfig()`/`readConfig()` in the JS observe that attribute
  and rebuild the option grid. Add a new reactive option to `RECONFIGURABLE` in the JS as well as
  to `getAlpineConfig()`.
- **State binds through `$applyStateBindingModifiers`, never a bare `$entangle`.** A bare
  `$entangle(path)` is deferred, which silently breaks `->live()` and `afterStateUpdated()`. The
  helper emits `$entangle(path, true)` for a live field, matching every first-party Filament
  input. `tests/Feature/RendersFieldTest.php` asserts both forms.
- **Bounds and grid are separate contracts.** `minTime()`/`maxTime()` are *enforced* whether or
  not `strict()` is on — client-side in `commit()`/`isInRange()`, server-side in
  `getOutOfBoundsMessage()`. `strict()` adds only the interval-grid check on top. Bounds are
  reported before the grid so an out-of-window time gets the useful message.
- **The panel is positioned imperatively, never through `:style`.**
  `positionPanel()` writes `left`/`top`/`width`/`max-height`/`transform` straight
  onto `$refs.panel`. Do not move this to a `:style` binding: Alpine applies a
  *string* style binding with `setAttribute("style", …)`, which replaces the
  entire attribute and wipes the `display`/`opacity` that `x-show` and
  `x-transition` write to that same element. `startAutoPosition()` re-measures
  every frame while the panel is open (writing only on change), which is what
  keeps it pinned when the input moves for reasons no event reports; it replaces
  the old scroll/resize/visualViewport listeners. `scrollToHighlight()` sets
  `panel.scrollTop` by hand rather than calling `scrollIntoView`, which would
  also scroll the page when the panel is clipped at the viewport edge.
- **The panel's styling is plain CSS shipped as a Filament asset**
  (`resources/dist/filament-timepicker.css`, registered in the service provider). Do **not** put
  Tailwind utility classes back on the panel markup: the whole point is that consumers on a custom
  theme need no `@source` line. Use the `fi-ti-*` class names and Filament's runtime `--gray-*`
  variables so the panel follows the app's theme.
- **One translation namespace: `harvirsidhu-filament-timepicker::`.** All user-facing strings
  live in `resources/lang/<locale>/time-picker.php`. Spatie's `hasTranslations()` would register
  them under the package shortName (`filament-timepicker`), so the service provider *also*
  `loadTranslationsFrom(...)` under the view namespace — use that one prefix everywhere (PHP
  `__()`, the Blade view, and JS strings passed in via the `durationLabels` config). The JS holds
  no hardcoded English; duration words come from the lang file through the Blade `x-data`.

The package requires **`filament/forms`**, not `filament/filament` — it only uses Forms, Schemas
and Support, and depending on the panel package would block plain-Livewire consumers.
`filament/filament` stays in `require-dev` because the test harness registers its providers.

## Public API — this is a stable contract

The clinic app (and any future consumer) depends on these. Keep them stable or bump
deliberately:

```php
->interval(int|Closure)                          // default 15
->minTime(string|Carbon|Closure|null)            // inclusive floor, enforced
->maxTime(string|Carbon|Closure|null)            // inclusive ceiling, enforced
->durationFrom(string|Closure|null)              // sibling field → duration labels + floor
->defaultDuration(int|Closure|null)              // auto-fill (durationFrom value + minutes) on change
->displayFormat(string|Closure)                  // PHP date() tokens, default 'g:i a'
->seconds(bool|Closure)                          // default false
->strict(bool|Closure)                           // default false — also reject off-grid times
->native(bool|Closure)        // NO-OP — drop-in parity with Filament TimePicker
->timezone(string|Closure|null) // NO-OP — wall-clock, never shifts
```

Parse rules (PHP + JS): `3:30 PM`→`15:30`, `3p`/`3pm`→`15:00`, `9`→`09:00`, `330`→`03:30`,
`1530`→`15:30`, `0930`→`09:30`, `9.30`/`9h30`→`09:30`, `15:00` passthrough, invalid→`null`.
Separators accepted in `hh:mm`: `:` `.` `h`.

A single **validation rule** (registered in `setUp()`, message chosen by
`getOutOfBoundsMessage()`) rejects anything that bypasses the client — paste, `$set`, import:
first the `minTime`/`maxTime` window, then, only under `strict(true)`, the interval grid. The JS
mirrors both in `commit()` (`isInRange()` + `isOnGrid()`), snapping the box back to the last good
value. Grid math lives in `SmartTimePicker::isOnGrid()`, mirrored by `isOnGrid()` in the JS;
range math in `TimeParser::toSeconds()`, mirrored by `secondsOf()`/`isInRange()`.
`dehydrateStateUsing` stays a pure parse normalizer.

## Asset build loop (important)

The Alpine component is JS that must be compiled, then published by consumers:

```bash
npm run dev      # watch-rebuild during development
npm run build    # one-shot: resources/js/components/smart-time-picker.js
                 #        -> resources/js/dist/components/smart-time-picker.js  (committed)
```

The compiled `resources/js/dist/` file **is committed** (consumers don't build it). After any JS
change: rebuild, then in any consuming app run `php artisan filament:assets` to re-publish, then
reload. `node` lives under Herd's nvm (`~/.config/herd/bin/nvm/<version>/`); it's not on the
default PATH.

## Tests

```bash
composer test    # Pest + orchestra/testbench (PHP)
npm test         # node --test (the Alpine component) — no test framework dependency
```

- `tests/Fixtures/parse-cases.json` — **the parsing contract**, iterated by both suites. Add a row
  here whenever you touch parsing; that is what keeps the PHP and JS parsers honest.
- `tests/Unit/TimeParserTest.php` — the PHP half of that table, plus timezone-independence of
  `format()`. The canary for the wall-clock contract.
- `tests/Unit/SmartTimePickerTest.php` — fluent API, getters, and the bounds/grid validation rule.
- `tests/Feature/RendersFieldTest.php` — renders the field through a real Livewire component
  (`tests/Fixtures/TimePickerTestComponent.php`): the server-rendered `value`, the `$entangle`
  live/deferred forms, the `data-config` bridge, `aria-invalid`.
- `tests/js/` — the Alpine component: option grid, bounds, commit rules, custom options,
  filtering, keyboard moves, panel placement and highlight scrolling. `bootedPicker()` in
  `helpers.js` fakes the browser globals (`window`, `requestAnimationFrame`, `$refs`) rather than
  stubbing methods, so even the layout code runs for real.
- `tests/ArchTest.php` — no debug helpers, `strict_types` everywhere in `src`, and `Support` stays
  framework-free (README advertises `TimeParser` as reusable outside Filament).

`php`/`composer` run via Herd: `php ~/.config/herd/bin/composer.phar …`.

## How the clinic app consumes it (don't be surprised)

- Installed as a **tagged GitHub release** (`"harvirsidhu/filament-timepicker": "^1.0"`, no
  `repositories` block). To test uncommitted local changes, temporarily add a path repository
  (`{"type": "path", "url": "../filament-timepicker", "options": {"symlink": true}}`) and set the
  constraint to `"dev-main as 1.1.0"`, then `composer update harvirsidhu/filament-timepicker`.
  JS/CSS changes need a rebuild here + `php artisan filament:assets` in the app. The app's
  `@source` line for `resources/views/**` is now redundant (the panel ships its own CSS) and can
  be dropped.
- A **breaking API change here silently breaks the app** until its tests re-run. Keep the public
  API stable, or coordinate the change on both sides.

## Lifecycle TODO (package-folder work)

- README has full usage docs; add screenshots/GIFs.
- Optional: a Pest browser (Playwright) smoke test for keyboard nav / teleport / the panel flip —
  the one layer `tests/js/` deliberately stubs out.
