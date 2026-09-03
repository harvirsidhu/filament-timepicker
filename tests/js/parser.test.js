/**
 * Parity suite: the JavaScript mirror of Support\TimeParser, run against the
 * exact table tests/Unit/TimeParserTest.php uses. If a parsing rule changes on
 * one side only, one of these two suites goes red.
 */
import { strict as assert } from 'node:assert'
import { describe, it } from 'node:test'

import { fixtures, picker } from './helpers.js'

describe('parse()', () => {
    for (const { label, input, expected } of fixtures.parse) {
        it(`${label}: ${JSON.stringify(input)} -> ${JSON.stringify(expected)}`, () => {
            assert.equal(picker().parse(input), expected)
        })
    }

    it('rejects undefined the same way it rejects null', () => {
        assert.equal(picker().parse(undefined), null)
    })
})

describe('parse() with seconds', () => {
    for (const { label, input, expected } of fixtures.parseWithSeconds) {
        it(`${label}: ${JSON.stringify(input)} -> ${JSON.stringify(expected)}`, () => {
            assert.equal(picker({ seconds: true }).parse(input), expected)
        })
    }
})

describe('toDisplay()', () => {
    for (const { label, input, format, expected } of fixtures.format) {
        // toDisplay() takes an already-canonical value; the null/blank rows in
        // the shared table only exercise the PHP entry point's guard clause.
        if (expected === null) {
            continue
        }

        it(`${label}: ${JSON.stringify(input)} as "${format}" -> "${expected}"`, () => {
            const component = picker({
                displayFormat: format,
                seconds: input.split(':').length > 2,
            })

            assert.equal(component.toDisplay(component.parse(input)), expected)
        })
    }
})
