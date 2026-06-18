<?php

use Harvirsidhu\FilamentTimepicker\SmartTimePicker;
use Illuminate\Support\Carbon;

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

it('defaults display to the non-padded 12-hour format', function () {
    expect(SmartTimePicker::make('start')->getDisplayFormat())->toBe('g:i A');
});

it('exposes the seconds flag', function () {
    expect(SmartTimePicker::make('start')->getSeconds())->toBeFalse()
        ->and(SmartTimePicker::make('start')->seconds()->getSeconds())->toBeTrue();
});

it('keeps native() and timezone() as no-ops for drop-in parity', function () {
    $field = SmartTimePicker::make('start')->native(false)->timezone('Asia/Kuala_Lumpur');

    expect($field)->toBeInstanceOf(SmartTimePicker::class)
        ->and($field->getName())->toBe('start');
});
