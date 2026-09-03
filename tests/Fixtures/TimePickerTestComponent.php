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

    /**
     * Validate through Filament's own entry point. Livewire's generic
     * validate() collects rules from getCachedSchemas(), which is only
     * populated once something in the request has touched the schema — so
     * whether it sees this field's rules depends on request ordering that
     * varies between Filament/Livewire versions. Schema::getState() calls
     * validate() on the schema itself, which is what a real save does.
     */
    public function save(): void
    {
        $this->form->getState();
    }

    /**
     * Put an error on the field's state path. Livewire only exposes methods
     * declared on the component itself to ->call(), so addError() has to be
     * reached through one of these.
     */
    public function fail(): void
    {
        $this->addError('data.start_time', 'Nope.');
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
