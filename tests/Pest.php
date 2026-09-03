<?php

declare(strict_types=1);

use Harvirsidhu\FilamentTimepicker\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * The parsing contract, shared verbatim with the JavaScript mirror's test suite
 * (tests/js/parser.test.js). Both read this file, so a rule changed on one side
 * and not the other fails CI rather than drifting silently.
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function timeParserFixtures(): array
{
    static $fixtures;

    return $fixtures ??= json_decode(
        (string) file_get_contents(__DIR__ . '/Fixtures/parse-cases.json'),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );
}

dataset('parse cases', function () {
    foreach (timeParserFixtures()['parse'] as $case) {
        yield $case['label'] => [$case['input'], $case['expected']];
    }
});

dataset('parse cases with seconds', function () {
    foreach (timeParserFixtures()['parseWithSeconds'] as $case) {
        yield $case['label'] => [$case['input'], $case['expected']];
    }
});

dataset('format cases', function () {
    foreach (timeParserFixtures()['format'] as $case) {
        yield $case['label'] => [$case['input'], $case['format'], $case['expected']];
    }
});
