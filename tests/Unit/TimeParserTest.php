<?php

declare(strict_types=1);

use Harvirsidhu\FilamentTimepicker\Support\TimeParser;

it('parses every supported input shape to canonical H:i', function (?string $input, ?string $expected) {
    expect(TimeParser::parse($input))->toBe($expected);
})->with('parse cases');

it('parses to canonical H:i:s when seconds are requested', function (?string $input, ?string $expected) {
    expect(TimeParser::parse($input, seconds: true))->toBe($expected);
})->with('parse cases with seconds');

it('formats canonical values for display', function (?string $input, string $format, ?string $expected) {
    expect(TimeParser::format($input, $format))->toBe($expected);
})->with('format cases');

it('formats identically whatever the app timezone is', function (string $timezone) {
    $original = date_default_timezone_get();
    date_default_timezone_set($timezone);

    try {
        expect(TimeParser::format('00:30'))->toBe('12:30 am')
            ->and(TimeParser::format('15:30'))->toBe('3:30 pm');
    } finally {
        date_default_timezone_set($original);
    }
})->with([
    'UTC',
    'Asia/Kuala_Lumpur',
    'America/Santiago',   // southern-hemisphere DST
    'Pacific/Chatham',    // 45-minute offset
    'Australia/Lord_Howe', // 30-minute DST shift
]);

it('measures seconds since midnight', function () {
    expect(TimeParser::toSeconds('00:00'))->toBe(0)
        ->and(TimeParser::toSeconds('09:30'))->toBe(34200)
        ->and(TimeParser::toSeconds('17:00:30'))->toBe(61230)
        ->and(TimeParser::toSeconds('23:59:59'))->toBe(86399);
});
