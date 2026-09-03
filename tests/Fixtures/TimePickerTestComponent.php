<?php

declare(strict_types=1);

namespace Harvirsidhu\FilamentTimepicker\Tests\Fixtures;

use Closure;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Harvirsidhu\FilamentTimepicker\SmartTimePicker;
use Livewire\Component;

/**
 * A minimal host for the field, so the Blade view is exercised end to end
 * rather than only through its getters. Configured from static properties so a
 * test can vary the field without defining a component per case.
 */
class TimePickerTestComponent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var ?Closure(SmartTimePicker): SmartTimePicker */
    public static ?Closure $configure = null;

    /** @var array<string, mixed> */
    public static array $initialState = [];

    public function mount(): void
    {
        $this->form->fill(static::$initialState);
    }

    public function form(Schema $schema): Schema
    {
        $field = SmartTimePicker::make('start_time');

        if (static::$configure !== null) {
            $field = (static::$configure)($field);
        }

        return $schema
            ->components([$field])
            ->statePath('data');
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                {{ $this->form }}
            </div>
        BLADE;
    }
}
