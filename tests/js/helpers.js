import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

import smartTimePicker from '../../resources/js/components/smart-time-picker.js'

export const fixtures = JSON.parse(
    readFileSync(
        fileURLToPath(new URL('../Fixtures/parse-cases.json', import.meta.url)),
        'utf8',
    ),
)

/**
 * A viewport for the component to measure against. Node has no `window`, and
 * positionPanel() reads innerHeight/visualViewport from it.
 */
export function fakeWindow({ height = 800, visualViewport = null } = {}) {
    globalThis.window = { innerHeight: height, visualViewport }

    return globalThis.window
}

/**
 * Stand-ins for the two elements the component holds refs to. The panel records
 * the style properties written to it so a test can read the placement back.
 */
export function fakeRefs({
    rect = { top: 100, bottom: 140, left: 32, width: 250 },
    rows = [],
} = {}) {
    return {
        input: {
            getBoundingClientRect: () => rect,
            select() {},
        },
        panel: {
            style: {},
            scrollTop: 0,
            clientHeight: 240,
            querySelectorAll: () => rows,
        },
    }
}

/**
 * Build the Alpine component's data object outside Alpine.
 *
 * The factory returns a plain object, so these tests drive it directly. Rather
 * than stubbing out the methods that touch layout — which would leave the
 * trickiest code untested — the browser globals they need are faked above, so
 * everything here runs the real implementation.
 */
export function picker(config = {}) {
    return smartTimePicker({
        state: null,
        interval: 15,
        min: null,
        max: null,
        seconds: false,
        strict: false,
        displayFormat: 'g:i a',
        isDisabled: false,
        isInvalid: false,
        durationFromStatePath: null,
        defaultDuration: null,
        fieldId: 'test-field',
        durationLabels: {
            hour: 'hour',
            hours: 'hours',
            minute: 'min',
            minutes: 'mins',
            shortHour: 'h',
            shortMinute: 'm',
        },
        ...config,
    })
}

/**
 * A picker with its option grid built and its DOM faked, as init() would leave
 * it in a browser.
 *
 * `$nextTick` is a no-op and requestAnimationFrame never invokes its callback,
 * so the autoposition loop registers but doesn't spin — a test that wants
 * another measurement calls positionPanel() itself.
 */
export function bootedPicker(config = {}, refs = {}) {
    globalThis.requestAnimationFrame ??= () => 1
    globalThis.cancelAnimationFrame ??= () => {}
    fakeWindow()

    const component = picker(config)

    component.$refs = fakeRefs(refs)
    component.$nextTick = () => {}

    component.options = component.generateOptions()
    component.syncFromState()

    return component
}
