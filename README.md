# Filament Smart Time Picker

A **type-ahead time field for [Filament](https://filamentphp.com).** Your users just type —
`3p`, `330`, `3:30 PM`, `1530` — and a filterable, keyboard-navigable dropdown suggests times
at whatever interval you pick. It looks and behaves like a native Filament field, but it's far
more forgiving for the people filling in your forms all day.

```php
use Harvirsidhu\FilamentTimepicker\SmartTimePicker;

SmartTimePicker::make('start_time')
    ->interval(15);
```

> **Requirements:** PHP 8.2+ · Filament v4 or v5

---

## Contents

- [What you get](#what-you-get)
- [Quick start](#quick-start) — install and use it in 3 steps
- [How input parsing works](#how-input-parsing-works)
- [Recipes](#recipes) — the common things you'll want to do
- [API reference](#api-reference)
- [Migrating from Filament's `TimePicker`](#migrating-from-filaments-timepicker)
- [Keyboard shortcuts](#keyboard-shortcuts)
- [Troubleshooting](#troubleshooting)
- [Translations](#translations)
- [How it works under the hood](#how-it-works-under-the-hood)
- [Contributing & development](#contributing--development)

---

## What you get

- ⌨️ **Forgiving free-text input** — `3p`, `9`, `330`, `1530`, `9.30`, `9h30` all parse to a
  clean 24-hour value.
- 📋 **A suggestion dropdown** at any `interval` you choose, filtered live as the user types.
- 🧭 **Full keyboard control** — arrow keys to move, Enter/Tab to pick, Esc to close.
- 🕒 **Min / max bounds** — only offer times inside a window (e.g. opening hours).
- ⏱️ **Relative duration hints** — show `30 mins`, `1 hour 15 mins` next to each option, ideal
  for an "end time" that depends on a "start time".
- 🔒 **Optional strict mode** — confine values to the grid and reject anything off it.
- 🌍 **Wall-clock safe** — a 3 PM slot is always stored as `15:00`. No surprise timezone shifts.
- 🎨 **Native Filament look** — same input chrome, light/dark aware, screen-reader friendly.
- 🪶 **Zero cost when unused** — the JavaScript is lazy-loaded only on pages that have the field.

---

## Quick start

**1. Install the package**

```bash
composer require harvirsidhu/filament-timepicker
```

**2. Publish the assets** (copies the lazy-loaded JavaScript into `/public`)

```bash
php artisan filament:assets
```

**3. Use it in a form**

```php
use Harvirsidhu\FilamentTimepicker\SmartTimePicker;

SmartTimePicker::make('start_time')
    ->label('Start time')
    ->interval(15)      // a suggestion every 15 minutes (this is the default)
    ->required();
```

That's it. The field stores a plain `H:i` string like `"15:30"`, so it drops straight into a
`time` column with no casting or accessors.

> **Using a [custom Filament theme](https://filamentphp.com/docs/5.x/themes)?** One extra step:
> add the package's views to your theme so Tailwind compiles the dropdown styles. In your theme's
> `resources/css/filament/<panel>/theme.css`:
>
> ```css
> @source '../../../../vendor/harvirsidhu/filament-timepicker/resources/views/**/*.blade.php';
> ```
>
> Then rebuild: `npm run build`. (Skip this if you use Filament's default styling.)

---

## How input parsing works

The whole point of this field is that people can type a time however they think of it, and it
still ends up stored cleanly. Here's the mapping:

| The user types  | Stored as | Displayed as |
|-----------------|-----------|--------------|
| `9`             | `09:00`   | 9:00 AM      |
| `3p`            | `15:00`   | 3:00 PM      |
| `330`           | `03:30`   | 3:30 AM      |
| `1530`          | `15:30`   | 3:30 PM      |
| `3:30 PM`       | `15:30`   | 3:30 PM      |
| `9.30` / `9h30` | `09:30`   | 9:30 AM      |
| `nonsense`      | *rejected* | —           |

**Separators are flexible.** In the `hh:mm` part you can use a colon, a dot, or an `h` — so
`9:30`, `9.30` (common in the UK and Malaysia), and `9h30` (French) all parse to the same value.

**Bad input never reaches your database.** Parsing happens twice: instantly in JavaScript for a
snappy feel, and authoritatively in PHP on the server. The server always has the final say, so a
saved value is either a clean `H:i` (or `H:i:s`) string, or `null`.

---

## Recipes

### Set a default value

Use Filament's standard `->default()`. It accepts the same forgiving formats and normalizes them
for you:

```php
SmartTimePicker::make('start_time')
    ->default('9am');   // stored as 09:00, shown as "9:00 AM"
```

`->default('15:30')`, `->default('330')`, and `->default('3:30 PM')` all work. For a dynamic
default, pass a closure:

```php
SmartTimePicker::make('start_time')
    ->default(fn () => now()->format('H:i'));   // the current time
```

> Filament only applies `default()` when **creating** a record, not when editing one.

### Restrict to a window (e.g. opening hours)

```php
SmartTimePicker::make('start_time')
    ->minTime('09:00')
    ->maxTime('18:00')
    ->interval(30);
```

`minTime` / `maxTime` accept a string, a `Carbon` instance, or a closure — so a bound can depend
on another field:

```php
SmartTimePicker::make('start_time')
    ->minTime(fn (Get $get) => $get('opens_at'));
```

### Start & end times, with live duration labels

Point an "end time" at its "start time" with `relativeTo()`. The dropdown then only offers times
*after* the start, and labels each option with how long the gap would be:

```php
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

SmartTimePicker::make('start_time')
    ->live(),   // make it ->live() so end_time updates as it changes

SmartTimePicker::make('end_time')
    ->relativeTo('start_time'),   // options read "30 mins", "1 hour", "1 hour 15 mins" …
```

Pass the sibling field's **name** (`'start_time'`), not its full path — `relativeTo()` resolves
it for you, even inside repeaters and nested groups.

### Show seconds

```php
SmartTimePicker::make('alarm_at')
    ->seconds()      // stores and displays "H:i:s"
    ->interval(1);
```

### Change how the time is displayed

The visible text uses [PHP `date()` tokens](https://www.php.net/manual/en/datetime.format.php).
The default is `g:i A` (e.g. *3:30 PM*):

```php
SmartTimePicker::make('start_time')->displayFormat('h:i A');  // 03:30 PM
SmartTimePicker::make('start_time')->displayFormat('H:i');    // 15:30 (24-hour)
```

This only changes what's shown — the stored value is always canonical `H:i`/`H:i:s`.

### Lock input to the grid (strict mode)

By default the field is forgiving: a user can type any valid time (e.g. `12:01`) even if it isn't
one of the suggested slots. Call `strict()` to confine values to the interval grid:

```php
SmartTimePicker::make('start_time')
    ->interval(15)
    ->strict();   // only :00, :15, :30, :45 (within min/max) are accepted
```

With strict on, typing an off-grid time snaps the box back to the last valid value as you go, and
any off-grid value that bypasses the browser (a paste, a programmatic default, a CSV import) fails
**server-side validation** with a clear message rather than being silently dropped.

---

## API reference

| Method | Default | Description |
|--------|---------|-------------|
| `interval(int\|Closure)` | `15` | Minutes between dropdown suggestions. |
| `minTime(string\|Carbon\|Closure\|null)` | `null` | Earliest selectable time (inclusive). |
| `maxTime(string\|Carbon\|Closure\|null)` | `null` | Latest selectable time (inclusive). |
| `relativeTo(string\|Closure\|null)` | `null` | Sibling field name; adds duration labels and a floor. |
| `displayFormat(string\|Closure)` | `'g:i A'` | How the value is shown, in PHP `date()` tokens. |
| `seconds(bool\|Closure)` | `false` | Store/display seconds (`H:i:s`). |
| `strict(bool\|Closure)` | `false` | Confine values to the grid; off-grid times fail validation. |
| `native(bool\|Closure)` | — | **No-op.** Accepted for drop-in parity with Filament's `TimePicker`. |
| `timezone(string\|Closure\|null)` | — | **No-op.** Times are wall-clock and never shift. |

All the usual Filament `Field` methods (`->label()`, `->required()`, `->disabled()`,
`->prefixIcon()`, `->placeholder()`, `->live()`, …) work as you'd expect.

### Parsing time strings anywhere

The parser is a plain, dependency-free class you can reuse outside the field — in validation,
imports, API endpoints, anywhere:

```php
use Harvirsidhu\FilamentTimepicker\Support\TimeParser;

TimeParser::parse('3:30 pm');   // "15:30"
TimeParser::parse('930');       // "09:30"
TimeParser::parse('9.30');      // "09:30"  (dot and "h" separators too)
TimeParser::parse('nope');      // null
TimeParser::format('15:30');    // "3:30 PM"
```

---

## Migrating from Filament's `TimePicker`

It's a drop-in replacement. Swap the import and the class name — your existing call chain keeps
working, because `native()` and `timezone()` are accepted as harmless no-ops:

```diff
- use Filament\Forms\Components\TimePicker;
+ use Harvirsidhu\FilamentTimepicker\SmartTimePicker;

- TimePicker::make('start_time')
+ SmartTimePicker::make('start_time')
      ->seconds(false)
      ->native(false)        // ignored, but harmless
      ->required();
```

---

## Keyboard shortcuts

The field follows the ARIA combobox/listbox pattern, so it's fully operable from the keyboard and
announced by screen readers.

| Key            | Action                                   |
|----------------|------------------------------------------|
| Type           | Filter the suggestions live              |
| ↑ / ↓          | Move through the suggestions             |
| Enter          | Select the highlighted suggestion        |
| Tab            | Select the highlighted suggestion & move on |
| Esc            | Close the dropdown                        |

---

## Troubleshooting

**The dropdown shows up unstyled (no colours, wrong layout).**
You're using a custom Filament theme and haven't told Tailwind about the package's views. Add the
`@source` line from [Quick start](#quick-start), then rebuild your theme with `npm run build`.

**Nothing happens when I focus the field / the JS doesn't load.**
Run `php artisan filament:assets` and reload. This publishes (and re-publishes) the lazy-loaded
component into `/public`. Re-run it any time the package updates.

**A typed time gets blanked out on save.**
The value didn't parse. Check the [parsing table](#how-input-parsing-works) — anything
unrecognised normalizes to `null`. If you're in `strict()` mode, an off-grid time is rejected by
design (with a validation message when it bypasses the browser).

**`relativeTo()` labels aren't updating.**
Make the source field `->live()` so its value propagates as it changes.

---

## Translations

Every user-facing string — the "no matching time" hint, the strict-mode validation message, and
the `relativeTo` duration words ("hour", "mins") — lives under the
`harvirsidhu-filament-timepicker` namespace. Publish them to translate or override:

```bash
php artisan vendor:publish --tag=filament-timepicker-translations
```

Then edit `lang/vendor/filament-timepicker/<locale>/time-picker.php`.

---

## How it works under the hood

Curious or extending it? The design choices that matter:

- **The text box is driven by a local Alpine string, never bound directly to Livewire.** The
  canonical `H:i` value is written to the entangled state only when you *commit* (pick an option,
  press Enter, or blur with valid text). Typing never round-trips half-parsed text through the
  server, so the cursor never jumps.
- **Parsing lives in two mirrored places** — instantly in JavaScript for feedback, and
  authoritatively in PHP (`TimeParser`) on dehydration. The server's result is final: clean
  `H:i`/`H:i:s` or `null`.
- **The Alpine component ships as a Filament asset and is lazy-loaded with `x-load`,** so it costs
  nothing on pages that don't use the field.
- **It implements the ARIA combobox/listbox pattern** (`role`, `aria-expanded`,
  `aria-activedescendant`, `aria-selected`), so keyboard navigation is announced to screen readers.

---

## Contributing & development

```bash
composer install
npm install

npm run dev      # watch + rebuild the Alpine component
npm run build    # production build into resources/js/dist
composer test    # Pest test suite
```

The compiled `resources/js/dist/` file **is committed** — consumers don't build it. After
changing the JS, rebuild, then in any consuming app re-run `php artisan filament:assets` to
re-publish.

If you change a parsing rule, change it in **both** `src/Support/TimeParser.php` (PHP,
authoritative) and `resources/js/components/smart-time-picker.js` (JS), and update both test
suites — they're meant to stay in lockstep.

---

## Credits

- [harvirsidhu](https://github.com/harvirsidhu)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
