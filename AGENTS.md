# AGENTS.md — filament-timepicker

Briefing for any coding-agent session working in this package. Read this first.

## What this is

`harvirsidhu/filament-timepicker` — a standalone Filament v4/v5 **form field** package
providing `SmartTimePicker`, a smart, type-ahead time combobox: free-text parsing
(`3p`, `330`, `3:30 PM`), an interval dropdown, keyboard nav, and optional `relativeTo`
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
  instant UX). **If you change parsing rules, change both** and update both test suites.
- **State decoupling.** The `<input>` is bound to a local Alpine `display` string, not
  `wire:model`. The canonical value is written to the entangled `state` only on commit. This is
  deliberate — it stops Livewire echoing state back and clobbering the cursor. Don't "simplify"
  it to a direct `x-model="state"`.

## Public API — this is a stable contract

The clinic app (and any future consumer) depends on these. Keep them stable or bump
deliberately:

```php
->interval(int|Closure)                          // default 15
->minTime(string|Carbon|Closure|null)            // inclusive floor
->maxTime(string|Carbon|Closure|null)            // inclusive ceiling
->relativeTo(string|Closure|null)                // sibling field → duration labels + floor
->displayFormat(string|Closure)                  // PHP date() tokens, default 'g:i A'
->seconds(bool|Closure)                          // default false
->strict(bool|Closure)                           // default false — reject off-grid times
->native(bool|Closure)        // NO-OP — drop-in parity with Filament TimePicker
->timezone(string|Closure|null) // NO-OP — wall-clock, never shifts
```

Parse rules (PHP + JS): `3:30 PM`→`15:30`, `3p`/`3pm`→`15:00`, `9`→`09:00`, `330`→`03:30`,
`1530`→`15:30`, `0930`→`09:30`, `9.30`/`9h30`→`09:30`, `15:00` passthrough, invalid→`null`.
Separators accepted in `hh:mm`: `:` `.` `h`.

`strict(true)` confines commits to the interval grid: a validly-parsed time that isn't a
generated slot (off-interval, or outside min/max) is rejected. The JS `commit()` snaps typed
off-grid input back to the last good value; on the PHP side a **validation rule** (registered in
`setUp()`) fails off-grid values that bypass the client (paste/programmatic/import) with a
message, rather than silently nulling them. Grid math lives in `SmartTimePicker::isOnGrid()`
mirrored by `isOnGrid()` in the JS component. `dehydrateStateUsing` stays a pure parse normalizer.

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
composer test    # Pest + orchestra/testbench
```

- `tests/Unit/TimeParserTest.php` — table-driven parser rules. The canary for the wall-clock
  contract. Add a row here whenever you touch parsing.
- `tests/Unit/SmartTimePickerTest.php` — fluent API + getters (interval/min/max/seconds/strict/no-ops).

`php`/`composer` run via Herd: `php ~/.config/herd/bin/composer.phar …`.

## How the clinic app consumes it (don't be surprised)

- Wired via a **symlinked Composer path repository** (`../filament-timepicker`) — PHP changes
  reflect live, but **JS/CSS changes need a rebuild + `filament:assets` + theme rebuild** in the
  app. The app also adds an `@source` for `resources/views/**` so Tailwind compiles the dropdown.
- A **breaking API change here silently breaks the app** until its tests re-run. Keep the public
  API stable, or coordinate the change on both sides.

## Lifecycle TODO (package-folder work)

- README has full usage docs; add screenshots/GIFs.
- Tag `v0.1.0`, publish to Packagist / push the GitHub repo, then consumers can drop the path
  repo for a version constraint.
- Optional: CI (pint/rector/pest matrix across Filament 4 & 5), a Pest browser smoke test for
  keyboard nav / teleport.
