<?php

declare(strict_types=1);

arch('no debugging leftovers ship')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('everything in src declares strict types')
    ->expect('Harvirsidhu\FilamentTimepicker')
    ->toUseStrictTypes();

arch('the parser stays framework-free')
    // README offers TimeParser as "a plain, dependency-free class you can reuse
    // outside the field". Keep it that way — no Laravel, no Filament.
    ->expect('Harvirsidhu\FilamentTimepicker\Support')
    ->not->toUse(['Illuminate', 'Filament', 'Livewire']);

arch('the field is the only entry point')
    ->expect('Harvirsidhu\FilamentTimepicker\SmartTimePicker')
    ->toExtend('Filament\Forms\Components\Field');
