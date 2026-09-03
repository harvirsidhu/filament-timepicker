/**
 * Behaviour of the Alpine component that has no PHP counterpart: the option
 * grid, bounds and grid enforcement on commit, duration labels, and the
 * keyboard/highlight rules.
 */
import { strict as assert } from 'node:assert'
import { describe, it } from 'node:test'

import { bootedPicker, fakeWindow } from './helpers.js'

describe('generateOptions()', () => {
    it('walks the whole day at the interval by default', () => {
        const component = bootedPicker({ interval: 30 })

        assert.equal(component.options.length, 48)
        assert.equal(component.options[0].value, '00:00')
        assert.equal(component.options[0].label, '12:00 am')
        assert.equal(component.options.at(-1).value, '23:30')
    })

    it('confines the grid to min and max, inclusive', () => {
        const component = bootedPicker({
            interval: 60,
            min: '09:00',
            max: '17:00',
        })

        assert.deepEqual(
            component.options.map((option) => option.value),
            [
                '09:00',
                '10:00',
                '11:00',
                '12:00',
                '13:00',
                '14:00',
                '15:00',
                '16:00',
                '17:00',
            ],
        )
    })

    it('aligns the grid to min, not to midnight', () => {
        const component = bootedPicker({ interval: 30, min: '09:10' })

        assert.equal(component.options[0].value, '09:10')
        assert.equal(component.options[1].value, '09:40')
        assert.equal(component.isOnGrid('09:40'), true)
        assert.equal(component.isOnGrid('09:30'), false)
    })

    it('emits seconds in option values when configured', () => {
        const component = bootedPicker({ seconds: true, interval: 720 })

        assert.deepEqual(
            component.options.map((option) => option.value),
            ['00:00:00', '12:00:00'],
        )
    })
})

describe('isInRange()', () => {
    it('treats the bounds as inclusive', () => {
        const component = bootedPicker({ min: '09:00', max: '17:00' })

        assert.equal(component.isInRange('09:00'), true)
        assert.equal(component.isInRange('17:00'), true)
        assert.equal(component.isInRange('08:59'), false)
        assert.equal(component.isInRange('17:01'), false)
    })

    it('compares seconds, so a bare ceiling still rejects a later second', () => {
        const component = bootedPicker({ seconds: true, max: '17:00' })

        assert.equal(component.isInRange('17:00:00'), true)
        assert.equal(component.isInRange('17:00:30'), false)
    })

    it('is unbounded when no min or max is set', () => {
        const component = bootedPicker()

        assert.equal(component.isInRange('00:00'), true)
        assert.equal(component.isInRange('23:59'), true)
    })
})

describe('commit()', () => {
    it('writes the canonical value and the formatted display', () => {
        const component = bootedPicker()
        component.commit('3:30 pm')

        assert.equal(component.state, '15:30')
        assert.equal(component.display, '3:30 pm')
    })

    it('ignores an unparseable value entirely', () => {
        const component = bootedPicker({ state: '09:00' })
        component.commit('nope')

        assert.equal(component.state, '09:00')
    })

    it('rejects a time outside the bounds even in loose mode', () => {
        const component = bootedPicker({ state: '09:00', max: '17:00' })
        component.commit('18:00')

        assert.equal(component.state, '09:00')
        assert.equal(component.display, '9:00 am')
    })

    it('accepts an off-grid time in loose mode', () => {
        const component = bootedPicker({ interval: 15 })
        component.commit('9:20')

        assert.equal(component.state, '09:20')
    })

    it('rejects an off-grid time in strict mode, snapping back', () => {
        const component = bootedPicker({
            interval: 15,
            strict: true,
            state: '09:15',
        })
        component.commit('9:20')

        assert.equal(component.state, '09:15')
        assert.equal(component.display, '9:15 am')
    })
})

describe('custom options', () => {
    it('offers both readings of an ambiguous typed time, in order', () => {
        const component = bootedPicker({ interval: 60 })
        component.onInput('3:25')

        const custom = component.filtered.filter(
            (option) => option.minutes % 60 !== 0,
        )

        assert.deepEqual(
            custom.map((option) => option.value),
            ['03:25', '15:25'],
        )
        // Chronological, not pinned to the top.
        assert.ok(
            component.filtered.findIndex((o) => o.value === '03:00') <
                component.filtered.findIndex((o) => o.value === '03:25'),
        )
    })

    it('previews a half-typed minute as its tens place', () => {
        const component = bootedPicker({ interval: 60 })

        assert.deepEqual(component.customCandidates('9:2'), ['09:20', '21:20'])
    })

    it('keeps a single reading when the meridiem is explicit', () => {
        const component = bootedPicker()

        assert.deepEqual(component.customCandidates('3:25 pm'), ['15:25'])
    })

    it('never offers a custom option outside the bounds', () => {
        const component = bootedPicker({ interval: 60, max: '12:00' })
        component.onInput('3:25')

        assert.deepEqual(
            component.filtered
                .map((option) => option.value)
                .filter((value) => value.endsWith(':25')),
            ['03:25'],
        )
    })

    it('offers nothing off-grid in strict mode', () => {
        const component = bootedPicker({ interval: 60, strict: true })
        component.onInput('3:25')

        assert.deepEqual(component.filtered, [])
    })
})

describe('filtering', () => {
    it('matches the formatted label as you type', () => {
        const component = bootedPicker({ interval: 60 })
        component.onInput('9:00 a')

        assert.equal(component.filtered[0].value, '09:00')
    })

    it('surfaces the slot for shorthand that no label starts with', () => {
        const component = bootedPicker({ interval: 60 })
        component.onInput('9pm')

        assert.ok(component.filtered.some((option) => option.value === '21:00'))
    })

    it('treats a dot or h separator like a colon', () => {
        const component = bootedPicker({ interval: 60 })
        component.onInput('9.30')

        assert.ok(component.filtered.some((option) => option.value === '09:30'))
    })
})

describe('duration labels', () => {
    it('uses words up to an hour and the compact form past it', () => {
        const component = bootedPicker()

        assert.equal(component.formatDuration(1), '1 min')
        assert.equal(component.formatDuration(30), '30 mins')
        assert.equal(component.formatDuration(60), '1 hour')
        assert.equal(component.formatDuration(90), '1h 30m')
        assert.equal(component.formatDuration(120), '2h')
        assert.equal(component.formatDuration(0), '')
    })
})

describe('keyboard navigation', () => {
    const opened = () => {
        const component = bootedPicker({ interval: 60 })
        component.filtered = component.options
        component.highlight = 0
        component.isOpen = true

        return component
    }

    it('wraps with the arrow keys', () => {
        const component = opened()

        component.move(-1)
        assert.equal(component.highlight, 23)
        component.move(1)
        assert.equal(component.highlight, 0)
    })

    it('clamps, rather than wraps, with Home / End / PageUp / PageDown', () => {
        const component = opened()

        component.moveTo(999)
        assert.equal(component.highlight, 23)
        component.movePage(1)
        assert.equal(component.highlight, 23)
        component.movePage(-1)
        assert.equal(component.highlight, 13)
        component.moveTo(0)
        assert.equal(component.highlight, 0)
        component.movePage(-1)
        assert.equal(component.highlight, 0)
    })

    it('does not count merely opening the panel as an interaction', () => {
        // Guards the Tab handler: focusing an empty field highlights the slot
        // nearest to now, and tabbing straight past must not commit it.
        const component = bootedPicker({ interval: 60 })
        component.open()

        assert.equal(component.hasInteracted, false)

        component.move(1)
        assert.equal(component.hasInteracted, true)
    })

    it('counts typing as an interaction', () => {
        const component = bootedPicker({ interval: 60 })
        component.open()
        component.onInput('9')

        assert.equal(component.hasInteracted, true)
    })
})

describe('onEnter()', () => {
    const keyEvent = () => {
        const event = { prevented: false, stopped: false }
        event.preventDefault = () => (event.prevented = true)
        event.stopPropagation = () => (event.stopped = true)

        return event
    }

    it('picks the highlight and swallows the key while open', () => {
        const component = bootedPicker({ interval: 60 })
        component.open()
        component.onInput('3p')
        const event = keyEvent()

        component.onEnter(event)

        assert.equal(component.state, '15:00')
        assert.equal(component.isOpen, false)
        assert.equal(event.prevented, true)
        assert.equal(event.stopped, true)
    })

    it('still commits typed text after Escape, but lets the submit through', () => {
        // Escape closes the panel without committing; Enter must not lose what
        // was typed on its way to submitting the form.
        const component = bootedPicker({ interval: 60 })
        component.open()
        component.onInput('3p')
        component.close()
        const event = keyEvent()

        component.onEnter(event)

        assert.equal(component.state, '15:00')
        assert.equal(event.prevented, false)
        assert.equal(event.stopped, false)
    })
})

describe('readConfig()', () => {
    const bridge = (config) => ({
        getAttribute: () => JSON.stringify(config),
    })

    it('rebuilds the grid when a bound changes', () => {
        const component = bootedPicker({ interval: 60 })

        component.readConfig(
            bridge({ interval: 60, min: '09:00', max: '11:00' }),
        )

        assert.deepEqual(
            component.options.map((option) => option.value),
            ['09:00', '10:00', '11:00'],
        )
    })

    it('ignores an unchanged or malformed payload', () => {
        const component = bootedPicker({ interval: 60 })
        const before = component.options

        component.readConfig(bridge({ interval: 60 }))
        assert.equal(component.options, before)

        component.readConfig({ getAttribute: () => '{not json' })
        assert.equal(component.options, before)
    })

    it('re-renders the box when the display format changes while closed', () => {
        const component = bootedPicker({ state: '15:30' })

        component.readConfig(bridge({ displayFormat: 'H:i' }))

        assert.equal(component.display, '15:30')
    })

    it('keeps a typed filter, and does not arm Tab, when reconfigured while open', () => {
        const component = bootedPicker({ interval: 60 })
        component.open()
        component.onInput('9')
        component.hasInteracted = false // isolate: was the flag set by readConfig?

        component.readConfig(bridge({ interval: 30 }))

        assert.equal(component.hasInteracted, false)
        assert.ok(component.filtered.every((o) => o.label.startsWith('9')))
        assert.ok(component.filtered.some((o) => o.value === '09:30'))
    })

    it('shows the full new grid when reconfigured while open on a committed value', () => {
        const component = bootedPicker({ interval: 60, state: '09:00' })
        component.open()

        component.readConfig(bridge({ interval: 30, max: '10:00' }))

        assert.equal(component.hasInteracted, false)
        assert.equal(component.filtered.length, 21)
        assert.equal(component.filtered[component.highlight].value, '09:00')
    })
})

describe('positionPanel()', () => {
    const positioned = (rect, viewportHeight = 800) => {
        const component = bootedPicker({ interval: 60 }, { rect })
        fakeWindow({ height: viewportHeight })

        return { component, style: component.$refs.panel.style }
    }

    it('sits below the input when there is room, matching its width', () => {
        const { component, style } = positioned({
            top: 100,
            bottom: 140,
            left: 32,
            width: 250,
        })

        component.positionPanel()

        assert.equal(style.top, '144px')
        assert.equal(style.left, '32px')
        assert.equal(style.width, '250px')
        assert.equal(style.transform, '')
        assert.equal(style.maxHeight, '240px')
    })

    it('flips above the input when the space below is short', () => {
        // The reported bug: a field near the bottom of a long form.
        const { component, style } = positioned({
            top: 740,
            bottom: 780,
            left: 32,
            width: 250,
        })

        component.positionPanel()

        assert.equal(style.top, '736px')
        assert.equal(style.transform, 'translateY(-100%)')
    })

    it('clamps its height to the space on the chosen side', () => {
        const cramped = positioned(
            { top: 20, bottom: 60, left: 0, width: 200 },
            180,
        )

        cramped.component.positionPanel()

        // 180 - 60 - 4 = 116px below, against only 20px above: it stays below
        // and scrolls internally rather than running off-screen.
        assert.equal(cramped.style.transform, '')
        assert.equal(cramped.style.maxHeight, '116px')
    })

    it('measures the visual viewport when there is one', () => {
        const { component, style } = positioned({
            top: 300,
            bottom: 340,
            left: 0,
            width: 200,
        })
        // A soft keyboard covering the lower half: the layout viewport is
        // unchanged, so only visualViewport reveals there is no room below.
        fakeWindow({
            height: 800,
            visualViewport: { offsetTop: 0, height: 400 },
        })

        component.positionPanel()

        assert.equal(style.transform, 'translateY(-100%)')
    })

    it('skips redundant writes when nothing has moved', () => {
        const rect = { top: 100, bottom: 140, left: 32, width: 250 }
        const { component, style } = positioned(rect)

        component.positionPanel()
        style.top = 'CLOBBERED'
        component.positionPanel()

        assert.equal(style.top, 'CLOBBERED')

        rect.top = 200
        rect.bottom = 240
        component.positionPanel()

        assert.equal(style.top, '244px')
    })

    it('does nothing before the teleported panel exists', () => {
        const { component } = positioned({
            top: 100,
            bottom: 140,
            left: 0,
            width: 200,
        })
        component.$refs.panel = undefined

        assert.doesNotThrow(() => component.positionPanel())
    })
})

describe('scrollToHighlight()', () => {
    // Scrolling must happen on the panel itself. scrollIntoView walks up every
    // scrollable ancestor, so it would scroll the page too — moving the input
    // out from under the panel that was just positioned.
    const withRows = (rowHeight = 40, visible = 3) => {
        const rows = Array.from({ length: 24 }, (_, index) => ({
            offsetTop: index * rowHeight,
            offsetHeight: rowHeight,
            scrollIntoView: () => assert.fail('must not use scrollIntoView'),
        }))
        const component = bootedPicker({ interval: 60 }, { rows })
        const panel = component.$refs.panel
        panel.clientHeight = rowHeight * visible

        return { component, panel }
    }

    it('scrolls down just far enough to reveal the highlight', () => {
        const { component, panel } = withRows()
        component.highlight = 5

        component.scrollToHighlight()

        assert.equal(panel.scrollTop, 120) // six rows deep, three visible
    })

    it('scrolls up to a highlight above the viewport', () => {
        const { component, panel } = withRows()
        panel.scrollTop = 400
        component.highlight = 2

        component.scrollToHighlight()

        assert.equal(panel.scrollTop, 80)
    })

    it('leaves an already-visible highlight alone', () => {
        const { component, panel } = withRows()
        panel.scrollTop = 40
        component.highlight = 2

        component.scrollToHighlight()

        assert.equal(panel.scrollTop, 40)
    })
})
