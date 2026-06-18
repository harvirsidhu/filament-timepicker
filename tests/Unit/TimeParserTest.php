<?php

use Harvirsidhu\FilamentTimepicker\Support\TimeParser;

it('parses every supported input shape to canonical H:i', function (?string $input, ?string $expected) {
    expect(TimeParser::parse($input))->toBe($expected);
})->with([
    // 12-hour with meridiem
    '3:30 PM' => ['3:30 PM', '15:30'],
    '3:30 pm' => ['3:30 pm', '15:30'],
    '3:30 p.m.' => ['3:30 p.m.', '15:30'],
    '11:45 am' => ['11:45 am', '11:45'],
    '12:00 pm (noon)' => ['12:00 pm', '12:00'],
    '12:00 am (midnight)' => ['12:00 am', '00:00'],

    // hour + meridiem, no minutes
    '3p' => ['3p', '15:00'],
    '3pm' => ['3pm', '15:00'],
    '3 PM' => ['3 PM', '15:00'],
    '9a' => ['9a', '09:00'],

    // bare hour
    '9' => ['9', '09:00'],
    '0' => ['0', '00:00'],
    '12' => ['12', '12:00'],

    // bare digits
    '330' => ['330', '03:30'],
    '1530' => ['1530', '15:30'],
    '0930' => ['0930', '09:30'],

    // alternative separators: dot (UK/MY), "h" (French)
    '9.30' => ['9.30', '09:30'],
    '9.30 pm' => ['9.30 pm', '21:30'],
    '15.45' => ['15.45', '15:45'],
    '9h30' => ['9h30', '09:30'],
    '9H30 (uppercase)' => ['9H30', '09:30'],
    '12.00 am' => ['12.00 am', '00:00'],
    'dot, single minute digit is invalid' => ['9.5', null],

    // already canonical / 24h
    '15:00' => ['15:00', '15:00'],
    '09:05' => ['09:05', '09:05'],
    '23:59' => ['23:59', '23:59'],

    // whitespace tolerance
    '  3:30 pm  ' => ['  3:30 pm  ', '15:30'],

    // invalid
    'empty' => ['', null],
    'null' => [null, null],
    'garbage' => ['nope', null],
    'out of range hour' => ['25:00', null],
    'out of range minute' => ['12:60', null],
    'bad 4-digit minute' => ['1260', null],
]);

it('keeps an unambiguous 24-hour hour even with a stray meridiem', function () {
    expect(TimeParser::parse('15:00 pm'))->toBe('15:00');
});

it('emits seconds when requested', function () {
    expect(TimeParser::parse('3:30:45 PM', seconds: true))->toBe('15:30:45')
        ->and(TimeParser::parse('330', seconds: true))->toBe('03:30:00');
});

it('formats canonical values for display', function (?string $input, ?string $expected) {
    expect(TimeParser::format($input))->toBe($expected);
})->with([
    '15:30' => ['15:30', '3:30 PM'],
    '09:00' => ['09:00', '9:00 AM'],
    '00:00' => ['00:00', '12:00 AM'],
    '12:00' => ['12:00', '12:00 PM'],
    'blank' => ['', null],
    'null' => [null, null],
]);
