<?php

declare(strict_types=1);

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

    protected string | Closure | null $durationFrom = null;

    protected int | Closure | null $defaultDuration = null;

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

        // Surface a real validation error for a parseable time the field
        // shouldn't accept — outside minTime()/maxTime(), or (in strict mode)
        // off the interval grid — instead of silently storing or dropping it.
        // The JS snaps typed values back before they get this far, so this
        // mainly guards what bypasses the client: pastes, imports, $set calls.
        $component = $this;

        $this->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            $parsed = TimeParser::parse(is_string($value) ? $value : null, $component->getSeconds());

            if ($parsed === null) {
                return;
            }

            $message = $component->getOutOfBoundsMessage($parsed);

            if ($message !== null) {
                $fail($message);
            }
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
     * Measure this field against a sibling time field: only offer times after
     * it, and label each option with the gap ("(30 mins)", "(1h 30m)"). Pass the
     * sibling's name (e.g. 'start_time'); repeater/group nesting is resolved
     * automatically. Pair with defaultDuration() to auto-fill this field.
     */
    public function durationFrom(string | Closure | null $statePath): static
    {
        $this->durationFrom = $statePath;

        return $this;
    }

    /**
     * When the durationFrom() field is set or changed, auto-fill this field with
     * (that time + $minutes). The user can still override it afterwards. No-op
     * unless durationFrom() is also configured.
     */
    public function defaultDuration(int | Closure | null $minutes): static
    {
        $this->defaultDuration = $minutes;

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
     * "12:01" with a 15-minute interval) is rejected rather than stored.
     * minTime()/maxTime() are enforced either way.
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
        return (string) $this->evaluate($this->displayFormat);
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
     * The validation message for a canonical value this field shouldn't accept,
     * or null when it's fine. Bounds are checked before the grid so an
     * out-of-window time reports that, rather than a confusing "wrong interval".
     */
    protected function getOutOfBoundsMessage(string $canonical): ?string
    {
        $seconds = TimeParser::toSeconds($canonical);
        $min = $this->getMinTime();
        $max = $this->getMaxTime();

        if ($min !== null && $seconds < TimeParser::toSeconds($min)) {
            return __('harvirsidhu-filament-timepicker::time-picker.min_time', [
                'time' => TimeParser::format($min, $this->getDisplayFormat()),
            ]);
        }

        if ($max !== null && $seconds > TimeParser::toSeconds($max)) {
            return __('harvirsidhu-filament-timepicker::time-picker.max_time', [
                'time' => TimeParser::format($max, $this->getDisplayFormat()),
            ]);
        }

        if ($this->isStrict() && ! $this->isOnGrid($canonical)) {
            return __('harvirsidhu-filament-timepicker::time-picker.off_grid', [
                'interval' => $this->getInterval(),
            ]);
        }

        return null;
    }

    /**
     * Whether a canonical `H:i`/`H:i:s` value lands on a generated dropdown
     * slot: zero seconds, within [min, max], and aligned to the interval from
     * the floor. Mirrors generateOptions() in the JS component.
     */
    protected function isOnGrid(string $canonical): bool
    {
        $parts = array_map(intval(...), explode(':', $canonical));
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
        [$hour, $minute] = array_map(intval(...), explode(':', $canonical));

        return ($hour * 60) + $minute;
    }

    /**
     * Absolute Livewire state path of the sibling field used by durationFrom(),
     * or null when it isn't configured.
     */
    public function getDurationFromStatePath(): ?string
    {
        $durationFrom = $this->evaluate($this->durationFrom);

        if (blank($durationFrom)) {
            return null;
        }

        return $this->resolveRelativeStatePath($durationFrom);
    }

    /**
     * Default duration in minutes for defaultDuration() auto-fill, or null when
     * not set. Clamped to at least 1.
     */
    public function getDefaultDuration(): ?int
    {
        $minutes = $this->evaluate($this->defaultDuration);

        return $minutes === null ? null : max(1, (int) $minutes);
    }

    /**
     * The JSON-serialisable half of the Alpine component's config — everything
     * bar the entangled state. Rendered twice by the view: inline in `x-data`
     * for the initial boot, and into the sibling `data-config` bridge that
     * carries later changes past `wire:ignore` (see the view for why).
     *
     * @return array<string, mixed>
     */
    public function getAlpineConfig(): array
    {
        return [
            'interval' => $this->getInterval(),
            'min' => $this->getMinTime(),
            'max' => $this->getMaxTime(),
            'seconds' => $this->getSeconds(),
            'strict' => $this->isStrict(),
            'displayFormat' => $this->getDisplayFormat(),
            'isDisabled' => $this->isDisabled(),
            'durationFromStatePath' => $this->getDurationFromStatePath(),
            'defaultDuration' => $this->getDefaultDuration(),
            'fieldId' => $this->getId(),
            'durationLabels' => [
                'hour' => __('harvirsidhu-filament-timepicker::time-picker.duration.hour'),
                'hours' => __('harvirsidhu-filament-timepicker::time-picker.duration.hours'),
                'minute' => __('harvirsidhu-filament-timepicker::time-picker.duration.minute'),
                'minutes' => __('harvirsidhu-filament-timepicker::time-picker.duration.minutes'),
                'shortHour' => __('harvirsidhu-filament-timepicker::time-picker.duration.short_hour'),
                'shortMinute' => __('harvirsidhu-filament-timepicker::time-picker.duration.short_minute'),
            ],
        ];
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
