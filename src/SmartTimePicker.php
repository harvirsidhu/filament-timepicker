<?php

namespace Harvirsidhu\FilamentTimepicker;

use Closure;
use Filament\Forms\Components\Concerns;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Contracts\HasAffixActions;
use Filament\Support\Concerns\HasExtraAlpineAttributes;
use Harvirsidhu\FilamentTimepicker\Support\TimeParser;
use Illuminate\Support\Carbon;

/**
 * A smart, type-ahead time field. Type freely ("3p", "330",
 * "3:30 PM"); a filterable, keyboard-navigable dropdown suggests times at a
 * configurable interval. Stored as a canonical wall-clock `H:i` string with no
 * timezone shift — a 3 PM slot is always "15:00".
 */
class SmartTimePicker extends Field implements HasAffixActions
{
    use Concerns\HasAffixes;
    use Concerns\HasExtraInputAttributes;
    use Concerns\HasPlaceholder;
    use HasExtraAlpineAttributes;

    protected string $view = 'harvirsidhu-filament-timepicker::time-picker';

    protected int | Closure $interval = 15;

    protected string | Carbon | Closure | null $minTime = null;

    protected string | Carbon | Closure | null $maxTime = null;

    protected string | Closure | null $relativeTo = null;

    protected string | Closure $displayFormat = 'g:i a';

    protected bool | Closure $hasSeconds = false;

    protected bool | Closure $isStrict = false;

    protected function setUp(): void
    {
        parent::setUp();

        // No prefix icon by default — consumers opt in with
        // ->prefixIcon(\Filament\Support\Icons\Heroicon::OutlinedClock).

        // Display the stored value in canonical form so reopening a record
        // never shows seconds the field is configured to hide.
        $this->formatStateUsing(fn (?string $state): ?string => TimeParser::parse($state, $this->getSeconds()));

        // The authoritative normalizer: whatever lands in state (typed,
        // pasted, JS-missed) is coerced to canonical `H:i`/`H:i:s` or null.
        $this->dehydrateStateUsing(fn (?string $state): ?string => TimeParser::parse($state, $this->getSeconds()));

        // In strict mode, surface a real validation error for a validly-parsed
        // time that isn't on the interval grid (e.g. a pasted/programmatic
        // "12:01" with a 15-min interval) instead of silently dropping it. The
        // JS already snaps typed off-grid input back, so this mainly guards
        // values that bypass the client.
        $component = $this;

        $this->rule(function () use ($component): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                if (! $component->isStrict()) {
                    return;
                }

                $parsed = TimeParser::parse(is_string($value) ? $value : null, $component->getSeconds());

                if ($parsed !== null && ! $component->isOnGrid($parsed)) {
                    $fail(__('harvirsidhu-filament-timepicker::time-picker.off_grid', ['interval' => $component->getInterval()]));
                }
            };
        });
    }

    /**
     * Minutes between suggested options in the dropdown.
     */
    public function interval(int | Closure $minutes): static
    {
        $this->interval = $minutes;

        return $this;
    }

    /**
     * Earliest selectable/suggested time (inclusive).
     */
    public function minTime(string | Carbon | Closure | null $time): static
    {
        $this->minTime = $time;

        return $this;
    }

    /**
     * Latest selectable/suggested time (inclusive).
     */
    public function maxTime(string | Carbon | Closure | null $time): static
    {
        $this->maxTime = $time;

        return $this;
    }

    /**
     * Show duration labels ("30 mins", "1 hour") relative to a sibling field,
     * and only offer times after that field's value. Pass the sibling's name
     * (e.g. 'start_time'); repeater/group nesting is resolved automatically.
     */
    public function relativeTo(string | Closure | null $statePath): static
    {
        $this->relativeTo = $statePath;

        return $this;
    }

    public function displayFormat(string | Closure $format): static
    {
        $this->displayFormat = $format;

        return $this;
    }

    public function seconds(bool | Closure $condition = true): static
    {
        $this->hasSeconds = $condition;

        return $this;
    }

    /**
     * Restrict committed values to the interval grid. When true, a free-typed
     * time that parses validly but doesn't land on a generated slot (e.g.
     * "12:01" with a 15-minute interval, or anything outside min/max) is
     * rejected rather than stored.
     */
    public function strict(bool | Closure $condition = true): static
    {
        $this->isStrict = $condition;

        return $this;
    }

    /**
     * Accepted for drop-in parity with the native TimePicker; this component is
     * always the custom combobox, so "native" has no effect.
     */
    public function native(bool | Closure $condition = true): static
    {
        return $this;
    }

    /**
     * Accepted for drop-in parity with the native TimePicker. Times are
     * wall-clock, so no timezone offset is ever applied.
     */
    public function timezone(string | Closure | null $timezone): static
    {
        return $this;
    }

    public function getInterval(): int
    {
        return max(1, (int) $this->evaluate($this->interval));
    }

    public function getMinTime(): ?string
    {
        return $this->normalizeBoundary($this->minTime);
    }

    public function getMaxTime(): ?string
    {
        return $this->normalizeBoundary($this->maxTime);
    }

    public function getDisplayFormat(): string
    {
        return $this->evaluate($this->displayFormat);
    }

    public function getSeconds(): bool
    {
        return (bool) $this->evaluate($this->hasSeconds);
    }

    public function isStrict(): bool
    {
        return (bool) $this->evaluate($this->isStrict);
    }

    /**
     * Whether a canonical `H:i`/`H:i:s` value lands on a generated dropdown
     * slot: zero seconds, within [min, max], and aligned to the interval from
     * the floor. Mirrors generateOptions() in the JS component.
     */
    protected function isOnGrid(string $canonical): bool
    {
        $parts = array_map('intval', explode(':', $canonical));
        $minutes = ($parts[0] * 60) + $parts[1];
        $second = $parts[2] ?? 0;

        if ($second !== 0) {
            return false;
        }

        $start = $this->getMinTime() !== null ? $this->toMinutes($this->getMinTime()) : 0;
        $end = $this->getMaxTime() !== null ? $this->toMinutes($this->getMaxTime()) : (24 * 60) - 1;

        if ($minutes < $start || $minutes > $end) {
            return false;
        }

        return (($minutes - $start) % $this->getInterval()) === 0;
    }

    protected function toMinutes(string $canonical): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $canonical));

        return ($hour * 60) + $minute;
    }

    /**
     * Absolute Livewire state path of the sibling field used by relativeTo,
     * or null when relativeTo isn't configured.
     */
    public function getRelativeStatePath(): ?string
    {
        $relativeTo = $this->evaluate($this->relativeTo);

        if (blank($relativeTo)) {
            return null;
        }

        return $this->resolveRelativeStatePath($relativeTo);
    }

    protected function normalizeBoundary(string | Carbon | Closure | null $value): ?string
    {
        $value = $this->evaluate($value);

        if (blank($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            $value = $value->format('H:i');
        }

        return TimeParser::parse((string) $value);
    }
}
