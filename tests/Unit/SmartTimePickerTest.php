<?php

declare(strict_types=1);

use Harvirsidhu\FilamentTimepicker\SmartTimePicker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

it('defaults to a 15-minute interval and overrides it', function () {
    expect(SmartTimePicker::make('start')->getInterval())->toBe(15)
        ->and(SmartTimePicker::make('start')->interval(30)->getInterval())->toBe(30);
});

it('clamps a non-positive interval to at least one minute', function () {
    expect(SmartTimePicker::make('start')->interval(0)->getInterval())->toBe(1);
});

it('normalizes string and Carbon boundaries to canonical H:i', function () {
    expect(SmartTimePicker::make('start')->minTime('9am')->getMinTime())->toBe('09:00')
        ->and(SmartTimePicker::make('start')->maxTime('6:30 PM')->getMaxTime())->toBe('18:30')
        ->and(SmartTimePicker::make('start')->minTime(Carbon::createFromTime(8, 15))->getMinTime())->toBe('08:15');
});

it('treats blank boundaries as null', function () {
    expect(SmartTimePicker::make('start')->getMinTime())->toBeNull()
        ->and(SmartTimePicker::make('start')->maxTime('')->getMaxTime())->toBeNull();
});

it('defaults display to the non-padded, lowercase 12-hour format', function () {
    expect(SmartTimePicker::make('start')->getDisplayFormat())->toBe('g:i a');
});

it('exposes the default duration, clamped to at least one minute', function () {
    expect(SmartTimePicker::make('end')->getDefaultDuration())->toBeNull()
        ->and(SmartTimePicker::make('end')->defaultDuration(30)->getDefaultDuration())->toBe(30)
        ->and(SmartTimePicker::make('end')->defaultDuration(0)->getDefaultDuration())->toBe(1);
});

it('stores the durationFrom sibling path', function () {
    $durationFrom = (function () {
        return $this->durationFrom;
    })->call(SmartTimePicker::make('end')->durationFrom('start'));

    expect($durationFrom)->toBe('start');
});

it('exposes the seconds flag', function () {
    expect(SmartTimePicker::make('start')->getSeconds())->toBeFalse()
        ->and(SmartTimePicker::make('start')->seconds()->getSeconds())->toBeTrue();
});

it('exposes the strict flag', function () {
    expect(SmartTimePicker::make('start')->isStrict())->toBeFalse()
        ->and(SmartTimePicker::make('start')->strict()->isStrict())->toBeTrue()
        ->and(SmartTimePicker::make('start')->strict(false)->isStrict())->toBeFalse();
});

it('treats only interval-aligned times as on-grid', function () {
    $field = SmartTimePicker::make('start')->interval(15);
    $isOnGrid = new ReflectionMethod($field, 'isOnGrid');

    expect($isOnGrid->invoke($field, '12:00'))->toBeTrue()
        ->and($isOnGrid->invoke($field, '12:15'))->toBeTrue()
        ->and($isOnGrid->invoke($field, '12:01'))->toBeFalse()
        ->and($isOnGrid->invoke($field, '12:07'))->toBeFalse()
        ->and($isOnGrid->invoke($field, '12:00:30'))->toBeFalse();
});

it('confines the grid to min/max bounds', function () {
    $field = SmartTimePicker::make('start')->interval(30)->minTime('09:00')->maxTime('17:00');
    $isOnGrid = new ReflectionMethod($field, 'isOnGrid');

    expect($isOnGrid->invoke($field, '09:00'))->toBeTrue()
        ->and($isOnGrid->invoke($field, '09:30'))->toBeTrue()
        ->and($isOnGrid->invoke($field, '08:30'))->toBeFalse()
        ->and($isOnGrid->invoke($field, '17:30'))->toBeFalse()
        ->and($isOnGrid->invoke($field, '09:15'))->toBeFalse();
});

it('fails validation for an off-grid time only in strict mode', function () {
    $strict = SmartTimePicker::make('start')->interval(15)->strict();
    $loose = SmartTimePicker::make('start')->interval(15);

    $validate = fn (SmartTimePicker $field, string $value): bool => Validator::make(
        ['start' => $value],
        ['start' => $field->getValidationRules()],
    )->fails();

    expect($validate($strict, '12:01'))->toBeTrue()   // off the 15-min grid
        ->and($validate($strict, '12:15'))->toBeFalse() // on grid
        ->and($validate($loose, '12:01'))->toBeFalse(); // strict off → anything parseable passes
});

it('uses a translated, interval-aware message for off-grid values', function () {
    $field = SmartTimePicker::make('start')->interval(30)->strict();

    $validator = Validator::make(
        ['start' => '12:01'],
        ['start' => $field->getValidationRules()],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('start'))->toBe('Choose a time at 30-minute intervals.');
});

it('fails validation for a time outside minTime/maxTime, strict or not', function () {
    $field = SmartTimePicker::make('start')->interval(15)->minTime('09:00')->maxTime('17:00');

    $errorFor = function (string $value) use ($field): ?string {
        $validator = Validator::make(['start' => $value], ['start' => $field->getValidationRules()]);

        return $validator->fails() ? $validator->errors()->first('start') : null;
    };

    // Bounds hold in loose mode — a bound is a bound, not a suggestion.
    expect($errorFor('08:45'))->toBe('Choose a time at or after 9:00 am.')
        ->and($errorFor('17:15'))->toBe('Choose a time at or before 5:00 pm.')
        ->and($errorFor('09:00'))->toBeNull()
        ->and($errorFor('17:00'))->toBeNull()
        ->and($errorFor('12:30'))->toBeNull();
});

it('reports the bound, not the interval, for an out-of-window time in strict mode', function () {
    // "08:47" is both out of range and off-grid; the range message is the
    // useful one, so it wins.
    $field = SmartTimePicker::make('start')->interval(15)->minTime('09:00')->strict();

    $validator = Validator::make(['start' => '08:47'], ['start' => $field->getValidationRules()]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('start'))->toBe('Choose a time at or after 9:00 am.');
});

it('states bounds in the field display format', function () {
    $field = SmartTimePicker::make('start')->minTime('09:00')->displayFormat('H:i');

    $validator = Validator::make(['start' => '08:00'], ['start' => $field->getValidationRules()]);

    expect($validator->errors()->first('start'))->toBe('Choose a time at or after 09:00.');
});

it('leaves an unparseable value to the parser, not the bounds rule', function () {
    // dehydrateStateUsing nulls it; the rule must not add a confusing second
    // error on top.
    $field = SmartTimePicker::make('start')->minTime('09:00')->strict();

    $validator = Validator::make(['start' => 'nope'], ['start' => $field->getValidationRules()]);

    expect($validator->fails())->toBeFalse();
});

it('compares bounds by the second when seconds are enabled', function () {
    $field = SmartTimePicker::make('start')->maxTime('17:00')->seconds();

    $validate = fn (string $value): bool => Validator::make(
        ['start' => $value],
        ['start' => $field->getValidationRules()],
    )->fails();

    expect($validate('17:00:00'))->toBeFalse()
        ->and($validate('17:00:30'))->toBeTrue();
});

it('resolves the package translation namespace', function () {
    expect(__('harvirsidhu-filament-timepicker::time-picker.no_matching_time'))->toBe('No matching time')
        ->and(__('harvirsidhu-filament-timepicker::time-picker.duration.hours'))->toBe('hours');
});

it('keeps native() and timezone() as no-ops for drop-in parity', function () {
    $field = SmartTimePicker::make('start')->native(false)->timezone('Asia/Kuala_Lumpur');

    expect($field)->toBeInstanceOf(SmartTimePicker::class)
        ->and($field->getName())->toBe('start');
});
