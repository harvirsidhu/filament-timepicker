<?php

declare(strict_types=1);

namespace Harvirsidhu\FilamentTimepicker;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
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
        // Spatie's hasTranslations() registers the lang files under the package
        // shortName ("filament-timepicker"). Register them under the view
        // namespace too so consumers reference a single, consistent prefix
        // ("harvirsidhu-filament-timepicker::") for both views and strings.
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', static::$viewNamespace);

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

            // The suggestion panel's styling, as plain CSS. Registering it here
            // is what frees consumers from adding an `@source` line for this
            // package's views to their Tailwind theme — without it, an app on a
            // custom theme rendered the dropdown unstyled. It reads Filament's
            // runtime --gray-* variables, so it still follows the app's theme.
            Css::make(
                'filament-timepicker',
                __DIR__ . '/../resources/dist/filament-timepicker.css',
            ),
        ], package: 'harvirsidhu/filament-timepicker');
    }
}
