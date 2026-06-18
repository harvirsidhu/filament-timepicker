<?php

namespace Harvirsidhu\FilamentTimepicker;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentTimepickerServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-timepicker';

    public static string $viewNamespace = 'harvirsidhu-filament-timepicker';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasViews(static::$viewNamespace)
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        // Ship the smart-time-picker Alpine component as an async, lazily
        // loaded asset. The field's Blade view pulls it in with
        // `x-load` + FilamentAsset::getAlpineComponentSrc(), so it only
        // downloads when a SmartTimePicker is actually rendered. Registering
        // here (not in a panel provider) means every panel — tenant AND
        // admin — gets the component automatically.
        FilamentAsset::register([
            AlpineComponent::make(
                'smart-time-picker',
                __DIR__ . '/../resources/js/dist/components/smart-time-picker.js',
            ),
        ], package: 'harvirsidhu/filament-timepicker');
    }
}
