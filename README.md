# Filament Smart Time Picker

A **smart, type-ahead time field for [Filament](https://filamentphp.com)**. Your users
just type — `3p`, `330`, `3:30 PM`, `1530` — and a filterable, keyboard-navigable dropdown
suggests times at whatever interval you choose. It looks and feels exactly like a native
Filament field, but it's far more forgiving for non-technical staff.

```php
use Harvirsidhu\FilamentTimepicker\SmartTimePicker;

SmartTimePicker::make('start_time')
    ->label('Start time')
    ->interval(15);
```

---

## Why this exists

The native `TimePicker` is either a clumsy browser control or a spinner you have to nudge
digit by digit. People who book appointments all day want to type `9` and move on. This field
gives them that, while still storing a clean, canonical value.

| You type | It stores | It shows |
|----------|-----------|----------|
| `9`      | `09:00`   | 9:00 AM  |
| `3p`     | `15:00`   | 3:00 PM  |
| `330`    | `03:30`   | 3:30 AM  |
| `1530`   | `15:30`   | 3:30 PM  |
| `3:30 PM`| `15:30`   | 3:30 PM  |
| `9.30` / `9h30` | `09:30` | 9:30 AM |

The `hh:mm` part accepts a colon, a dot, or an `h` as the separator — so `9:30`, `9.30`
(UK/Malaysia) and `9h30` (French) all work. Anything unparseable is rejected on the server, so
bad data never reaches your database.

---

## Features

- ⌨️ **Forgiving free-text input** — meridiems (`3p`, `3 pm`), bare hours (`9`), compact
  digits (`330`, `1530`), and `:`/`.`/`h` separators (`9.30`, `9h30`) all parse to a canonical
  24-hour value.
- 📋 **Suggestion dropdown** at a configurable `interval`, filtered as you type.
- 🔒 **Optional `strict` mode** — confine values to the interval grid, with a validation error
  for anything off it.
- 🧭 **Full keyboard control** — ↑/↓ to move, Enter/Tab to pick, Esc to close.
- ⏱️ **Relative duration hints** — show `30 mins`, `1 hour 15 mins` next to each option,
  perfect for an "end time" that depends on a "start time".
- 🕒 **Min / max bounds** — only offer times inside a window (e.g. opening hours).
- 🌍 **Wall-clock safe** — a 3 PM slot is always `15:00`; no surprise timezone shifts.
- 🎨 **Native Filament look** — built on the same input chrome, light/dark aware.
- 🪶 **Lazy-loaded JS** — the Alpine component only downloads when a field is on the page.

---

## Installation

```bash
composer require harvirsidhu/filament-timepicker
```

Publish the component's assets (this copies the lazy-loaded JS into `/public`):

```bash
php artisan filament:assets
```

If you use a [custom Filament theme](https://filamentphp.com/docs/5.x/themes), register the
package's views so Tailwind compiles the dropdown styles. Add this to your theme's
`resources/css/filament/<panel>/theme.css`:

```css
@source '../../../../vendor/harvirsidhu/filament-timepicker/resources/views/**/*.blade.php';
```

Then rebuild your theme: `npm run build`.

> **Requirements:** PHP 8.2+, Filament v4 or v5.

---

## Usage

### The basics

```php
use Harvirsidhu\FilamentTimepicker\SmartTimePicker;

SmartTimePicker::make('start_time')
    ->label('Start time')
    ->interval(15)              // dropdown every 15 minutes (default)
    ->required();
```

The value is stored as a `H:i` string (e.g. `"15:30"`) — drop it straight into a `time`
column.

### Restrict to a window (e.g. opening hours)

```php
SmartTimePicker::make('start_time')
    ->minTime('09:00')
    ->maxTime('18:00')
    ->interval(30);
```

`minTime` / `maxTime` accept a string, a `Carbon` instance, or a closure:

```php
SmartTimePicker::make('start_time')
    ->minTime(fn (Get $get) => $get('opens_at'));
```

### Start & end times, with live duration labels

Point an "end time" at its "start time" with `relativeTo()`. The dropdown then only offers
times *after* the start and labels each one with how long the appointment would run:

```php
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

SmartTimePicker::make('start_time')
    ->live()
    ->afterStateUpdated(function (Get $get, Set $set) {
        // optionally precompute an end time, etc.
    }),

SmartTimePicker::make('end_time')
    ->relativeTo('start_time'),   // shows "30 mins", "1 hour", "1 hour 15 mins" …
```

`relativeTo()` resolves the sibling field automatically, even inside repeaters and nested
groups — pass the field's name, not its full path.

### Seconds

```php
SmartTimePicker::make('alarm_at')
    ->seconds()                 // stores/displays "H:i:s"
    ->interval(1);
```

### Display format

The displayed text uses [PHP `date()` tokens](https://www.php.net/manual/en/datetime.format.php).
The default is `g:i A` (non-padded, e.g. *3:30 PM*):

```php
SmartTimePicker::make('start_time')->displayFormat('h:i A');  // 03:30 PM
SmartTimePicker::make('start_time')->displayFormat('H:i');    // 15:30 (24-hour)
```

### Strict mode

By default the field is forgiving: a user can type any valid time (e.g. `12:01`) even if it
isn't one of the suggested slots. Call `strict()` to confine values to the interval grid:

```php
SmartTimePicker::make('start_time')
    ->interval(15)
    ->strict();   // only :00, :15, :30, :45 (within min/max) are accepted
```

With `strict` on, typing an off-grid time snaps the box back to the last valid value as you go,
and any off-grid value that reaches the server (a pasted entry, a programmatic default, an
import) fails validation with a clear message rather than being silently dropped. Leave it off
(the default) to keep the loose, type-anything behaviour.

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
| `strict(bool\|Closure)` | `false` | Confine values to the interval grid; off-grid times fail validation. |

It also accepts `native()` and `timezone()` as **no-ops**, so it's a drop-in replacement for
Filament's `TimePicker` — existing call chains keep working.

### Drop-in migration

```diff
- use Filament\Forms\Components\TimePicker;
+ use Harvirsidhu\FilamentTimepicker\SmartTimePicker;

- TimePicker::make('start_time')
+ SmartTimePicker::make('start_time')
      ->seconds(false)
      ->native(false)        // ignored, but harmless
      ->required();
```

### Parsing outside the field

The parser is a plain, dependency-free class you can reuse anywhere (validation, imports, APIs):

```php
use Harvirsidhu\FilamentTimepicker\Support\TimeParser;

TimeParser::parse('3:30 pm');   // "15:30"
TimeParser::parse('930');       // "09:30"
TimeParser::parse('9.30');      // "09:30"  (dot / "h" separators too)
TimeParser::parse('nope');      // null
TimeParser::format('15:30');    // "3:30 PM"
```

---

## How it works (for the curious)

- The visible text box is driven by a **local Alpine string**, never bound directly to
  Livewire. The canonical `H:i` value is written to the entangled state only when you
  *commit* (pick an option, press Enter, or blur with valid text). Typing never round-trips
  half-parsed text through the server, so your cursor never jumps.
- Parsing happens in **two places that mirror each other**: instantly in JS for feedback, and
  authoritatively in PHP (`TimeParser`) on dehydration. The server always has the final say,
  so a value is either clean `H:i`/`H:i:s` or `null`.
- The Alpine component is shipped as a Filament asset and **lazy-loaded with `x-load`**, so it
  costs nothing on pages that don't use it.

---

## Development

```bash
composer install
npm install

npm run dev      # watch + rebuild the Alpine component
npm run build    # production build into resources/js/dist
composer test    # Pest test suite
```

After changing the JS, consuming apps must re-run `php artisan filament:assets`.

---

## Credits

- [harvirsidhu](https://github.com/harvirsidhu)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
