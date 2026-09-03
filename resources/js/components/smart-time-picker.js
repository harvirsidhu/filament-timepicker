/**
 * Smart time picker — a type-ahead time combobox.
 *
 * The text input is driven by a LOCAL `display` string, never bound directly
 * to Livewire. The canonical wall-clock value ("H:i" / "H:i:s") is written to
 * the entangled `state` only on commit (pick / Enter / valid blur), so typing
 * never round-trips half-parsed text through the server.
 *
 * The parsing + display rules mirror src/Support/TimeParser.php — keep the two
 * in lockstep. `tests/Fixtures/parse-cases.json` is the shared table both test
 * suites run, so a rule changed here and not there (or vice versa) fails CI.
 */

// Trailing meridiem: "p", "pm", "p.m.", optional leading space. No `g` flag, so
// the shared instance is safe to reuse across match / replace / test.
const MERIDIEM = /\s*([ap])\.?m?\.?$/
const MINUTES_IN_DAY = 24 * 60
const pad2 = (n) => String(n).padStart(2, '0')

// Config keys the `data-config` bridge may change after first render (a
// reactive minTime()/maxTime()/interval()/disabled() closure, or the field
// gaining/losing a validation error). Everything else — fieldId,
// durationFromStatePath — is structural and fixed for the field's lifetime.
const RECONFIGURABLE = [
    'interval',
    'min',
    'max',
    'seconds',
    'strict',
    'displayFormat',
    'isDisabled',
    'isInvalid',
    'defaultDuration',
]

export default function smartTimePicker(config) {
    return {
        state: config.state, // entangled canonical "H:i" / "H:i:s" (or null)
        interval: config.interval || 15,
        min: config.min || null, // "HH:MM" or null
        max: config.max || null,
        seconds: config.seconds || false,
        strict: config.strict || false,
        displayFormat: config.displayFormat || 'g:i a',
        isDisabled: config.isDisabled || false,
        isInvalid: config.isInvalid || false,
        durationFromStatePath: config.durationFromStatePath || null,
        defaultDuration: config.defaultDuration || null,
        fieldId: config.fieldId || null,
        durationLabels: config.durationLabels || {
            hour: 'hour',
            hours: 'hours',
            minute: 'min',
            minutes: 'mins',
            shortHour: 'h',
            shortMinute: 'm',
        },

        display: '',
        isOpen: false,
        options: [],
        filtered: [],
        highlight: 0,
        positionFrame: null,
        lastPlacement: null,
        configObserver: null,
        lastConfig: null,
        pointerOrigin: null,
        // Whether the user has typed or arrowed since the panel opened. Tab
        // commits the highlight only when this is true — see the note on
        // hasInteracted in the view.
        hasInteracted: false,
        lastReference: null, // previous durationFrom value (minutes), for gap math

        init() {
            this.options = this.generateOptions()
            this.syncFromState()
            this.watchConfig()

            // Reflect programmatic state changes (e.g. a server-computed
            // end_time) back into the visible box when the panel is closed.
            this.$watch('state', () => {
                if (!this.isOpen) {
                    this.syncFromState()
                }
            })

            // Auto-fill from the durationFrom() field: when it is set or changed,
            // shift this field to keep the current gap (read live, so a duration
            // set elsewhere is respected), seeding with defaultDuration while this
            // field is empty. Seed lastReference from any existing pair so an edit
            // form keeps its saved gap. All client-side — no server roundtrip.
            if (this.durationFromStatePath && this.defaultDuration) {
                this.lastReference = this.referenceMinutes()
                this.$wire.$watch(this.durationFromStatePath, () =>
                    this.applyDurationFrom(),
                )
            }

            // Nothing here watches the viewport: startAutoPosition() re-measures
            // every frame the panel is open, which covers the mobile soft
            // keyboard and pinch-zoom (neither fires a window resize) along with
            // everything else that can move the input.
        },

        destroy() {
            this.configObserver?.disconnect()
            this.stopAutoPosition()
        },

        // ---- reactive config bridge ----
        // This component's root carries `wire:ignore`, so Livewire never morphs
        // its x-data — a reactive minTime()/maxTime()/interval() closure would
        // stay pinned to its first-render value. The view renders the same
        // config onto a sibling <div data-config> that Livewire DOES morph;
        // watching that attribute is how a new bound reaches the option grid.
        watchConfig() {
            const el = this.fieldId
                ? document.getElementById(`${this.fieldId}-config`)
                : null

            if (!el || typeof MutationObserver === 'undefined') {
                return
            }

            // Seed from the current attribute so the first mutation is a real
            // change rather than a redundant rebuild.
            this.lastConfig = el.getAttribute('data-config')

            this.configObserver = new MutationObserver(() =>
                this.readConfig(el),
            )
            this.configObserver.observe(el, {
                attributes: true,
                attributeFilter: ['data-config'],
            })
        },

        readConfig(el) {
            const raw = el.getAttribute('data-config')

            if (raw === null || raw === this.lastConfig) {
                return
            }

            this.lastConfig = raw

            let next

            try {
                next = JSON.parse(raw)
            } catch (error) {
                return
            }

            let changed = false

            for (const key of RECONFIGURABLE) {
                if (!(key in next)) {
                    continue
                }

                const value = next[key] ?? null

                if (value !== (this[key] ?? null)) {
                    this[key] = value
                    changed = true
                }
            }

            if (!changed) {
                return
            }

            this.options = this.generateOptions()

            if (!this.isOpen) {
                // displayFormat may have changed too — re-render the box.
                this.syncFromState()

                return
            }

            // Rebuild the open list against the new grid so an option the new
            // bounds just excluded can't stay highlighted. Mid-typing, re-run
            // the typed filter; otherwise rebuild the full list as open() does
            // (filtering by the committed value would collapse it to one row).
            // Neither counts as the user interacting — a config change must
            // not arm the Tab commit on its own.
            const hasInteracted = this.hasInteracted
            const committed = this.parse(this.state)
            const isTyping =
                this.display !==
                (committed === null ? '' : this.toDisplay(committed))

            if (isTyping) {
                this.onInput(this.display)
            } else {
                this.open()
            }

            this.hasInteracted = hasInteracted
        },

        // Enter picks the highlight while the panel is open, and swallows the
        // event so the form doesn't submit. On a closed panel (after Escape) it
        // still commits whatever was typed — otherwise the text would be lost
        // when the submit goes through — but lets the submit proceed.
        onEnter(event) {
            const wasOpen = this.isOpen

            this.selectHighlighted()

            if (wasOpen) {
                event.preventDefault()
                event.stopPropagation()
            }
        },

        // ---- parsing (mirror of PHP TimeParser::parse) ----
        parse(value) {
            if (value === null || value === undefined) {
                return null
            }

            let s = String(value).trim().toLowerCase()

            if (s === '') {
                return null
            }

            let meridiem = null
            const meridiemMatch = s.match(MERIDIEM)

            if (meridiemMatch) {
                meridiem = meridiemMatch[1]
                s = s.replace(MERIDIEM, '').trim()
            }

            let hour = null
            let minute = 0
            let second = 0
            let m

            // colon, dot, or "h" separator (UK/MY "9.30", French "9h30")
            if ((m = s.match(/^(\d{1,2})[:.h](\d{2})(?:[:.h](\d{2}))?$/))) {
                hour = parseInt(m[1], 10)
                minute = parseInt(m[2], 10)
                second = m[3] ? parseInt(m[3], 10) : 0
            } else if (/^\d+$/.test(s)) {
                if (s.length <= 2) {
                    hour = parseInt(s, 10)
                } else if (s.length === 3) {
                    hour = parseInt(s.slice(0, 1), 10)
                    minute = parseInt(s.slice(1), 10)
                } else if (s.length === 4) {
                    hour = parseInt(s.slice(0, 2), 10)
                    minute = parseInt(s.slice(2), 10)
                } else {
                    return null
                }
            } else {
                return null
            }

            if (meridiem !== null && hour >= 1 && hour <= 12) {
                if (meridiem === 'p' && hour < 12) {
                    hour += 12
                } else if (meridiem === 'a' && hour === 12) {
                    hour = 0
                }
            }

            if (
                hour < 0 ||
                hour > 23 ||
                minute < 0 ||
                minute > 59 ||
                second < 0 ||
                second > 59
            ) {
                return null
            }

            return this.seconds
                ? `${pad2(hour)}:${pad2(minute)}:${pad2(second)}`
                : `${pad2(hour)}:${pad2(minute)}`
        },

        // ---- display formatting (PHP date() token subset) ----
        toDisplay(canonical) {
            const parts = canonical.split(':')
            const hour = parseInt(parts[0], 10)
            const minute = parseInt(parts[1], 10)
            const second = parseInt(parts[2] || '0', 10)
            const hour12 = hour % 12 || 12

            const tokens = {
                g: String(hour12),
                G: String(hour),
                h: pad2(hour12),
                H: pad2(hour),
                i: pad2(minute),
                s: pad2(second),
                A: hour < 12 ? 'AM' : 'PM',
                a: hour < 12 ? 'am' : 'pm',
            }

            let out = ''
            let escaped = false

            for (const ch of this.displayFormat) {
                // A backslash escapes the next character, exactly as PHP date()
                // does — "\a\t g:i" renders "at 3:30", not "ampm 3:30".
                if (escaped) {
                    out += ch
                    escaped = false
                } else if (ch === '\\') {
                    escaped = true
                } else {
                    out += ch in tokens ? tokens[ch] : ch
                }
            }

            return out
        },

        minutesOf(canonical) {
            const parts = canonical.split(':')

            return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10)
        },

        // Seconds since midnight. Range checks use this rather than minutes so a
        // bare "17:00" ceiling still rejects "17:00:30" when seconds are on.
        // Mirrors TimeParser::toSeconds() on the PHP side.
        secondsOf(canonical) {
            const parts = canonical.split(':')

            return (
                parseInt(parts[0], 10) * 3600 +
                parseInt(parts[1], 10) * 60 +
                parseInt(parts[2] || '0', 10)
            )
        },

        // Whether a canonical value sits inside minTime()/maxTime(). Unlike the
        // grid check this applies in loose mode too: a bound is a bound.
        // Mirrors the range half of SmartTimePicker::getOutOfBoundsMessage().
        isInRange(canonical) {
            const seconds = this.secondsOf(canonical)

            if (this.min !== null && seconds < this.secondsOf(this.min)) {
                return false
            }

            return !(this.max !== null && seconds > this.secondsOf(this.max))
        },

        fromMinutes(total) {
            const value = `${pad2(Math.floor(total / 60))}:${pad2(total % 60)}`

            return this.seconds ? `${value}:00` : value
        },

        // Hybrid wording: up to an hour, friendly words ("30 mins", "1 hour");
        // past an hour, the compact form ("1h 30m", "2h") so long gaps stay
        // short. The brackets are added in the view, not here.
        formatDuration(mins) {
            if (mins <= 0) {
                return ''
            }

            const labels = this.durationLabels

            if (mins < 60) {
                return `${mins} ${mins === 1 ? labels.minute : labels.minutes}`
            }

            if (mins === 60) {
                return `1 ${labels.hour}`
            }

            const hours = Math.floor(mins / 60)
            const minutes = mins % 60
            const hourPart = `${hours}${labels.shortHour}`

            return minutes
                ? `${hourPart} ${minutes}${labels.shortMinute}`
                : hourPart
        },

        generateOptions() {
            const step = this.interval
            const start = this.min ? this.minutesOf(this.min) : 0
            const end = this.max ? this.minutesOf(this.max) : MINUTES_IN_DAY - 1
            const options = []

            for (let m = start; m <= end && m < MINUTES_IN_DAY; m += step) {
                const value = this.fromMinutes(m)
                options.push({
                    value,
                    label: this.toDisplay(value),
                    minutes: m,
                })
            }

            return options
        },

        referenceMinutes() {
            if (!this.durationFromStatePath) {
                return null
            }

            const parsed = this.parse(
                this.$wire.get(this.durationFromStatePath),
            )

            return parsed === null ? null : this.minutesOf(parsed)
        },

        // When the durationFrom() field changes, shift this field to keep the
        // current gap: read the live end (so a value set elsewhere — e.g. a
        // type-driven $set — is respected) and move it by however far the source
        // moved. Seed with defaultDuration while this field is empty. Capped at
        // max (or end of day). No-op when the source is empty/invalid.
        applyDurationFrom() {
            const reference = this.referenceMinutes()

            if (reference === null) {
                this.lastReference = null

                return
            }

            const end = this.parse(this.state)
            const gap =
                end !== null && this.lastReference !== null
                    ? this.minutesOf(end) - this.lastReference
                    : null

            const duration =
                gap !== null && gap > 0 ? gap : this.defaultDuration
            const floor = this.min ? this.minutesOf(this.min) : 0
            const cap = this.max ? this.minutesOf(this.max) : MINUTES_IN_DAY - 1

            // Clamped into [min, max] — the auto-fill writes state directly, so
            // it has to respect the same bounds commit() enforces.
            this.state = this.fromMinutes(
                Math.min(Math.max(reference + duration, floor), cap),
            )
            this.syncFromState()
            this.lastReference = reference
        },

        visibleOptions() {
            const reference = this.referenceMinutes()

            if (reference === null) {
                return this.options
            }

            return this.options
                .filter((option) => option.minutes > reference)
                .map((option) => ({
                    ...option,
                    duration: this.formatDuration(option.minutes - reference),
                }))
        },

        syncFromState() {
            const parsed = this.parse(this.state)
            this.display = parsed === null ? '' : this.toDisplay(parsed)
        },

        open() {
            if (this.isDisabled) {
                return
            }

            const current = this.parse(this.state)
            this.filtered = this.withCustomOptions(
                this.visibleOptions(),
                current === null ? [] : [current],
            )
            this.highlight = this.initialHighlightIndex()
            this.hasInteracted = false
            this.isOpen = true

            // Position now, then again after paint. Inside a Filament modal the
            // layout can still be settling (open transition, scroll-into-view)
            // when open() fires, so the first measurement can be stale and flip
            // the wrong way; re-measuring locks it to the final spot.
            // Place it before the first paint, then let the loop keep it
            // pinned while the page finishes settling.
            this.positionPanel()
            this.startAutoPosition()
            this.$nextTick(() => this.scrollToHighlight())
        },

        // Which option to highlight when the panel opens with no typed filter:
        // the committed value if there is one, otherwise the slot nearest the
        // current wall-clock time (so an empty field opens somewhere useful
        // rather than at 12:00 AM). Highlight only — nothing commits until pick.
        initialHighlightIndex() {
            if (!this.filtered.length) {
                return 0
            }

            const current = this.parse(this.state)
            const selected = this.filtered.findIndex(
                (option) => option.value === current,
            )

            if (selected !== -1) {
                return selected
            }

            const now = new Date()
            const nowMinutes = now.getHours() * 60 + now.getMinutes()

            let nearest = 0
            let smallestDelta = Infinity

            this.filtered.forEach((option, index) => {
                const delta = Math.abs(option.minutes - nowMinutes)

                if (delta < smallestDelta) {
                    smallestDelta = delta
                    nearest = index
                }
            })

            return nearest
        },

        close() {
            this.isOpen = false
            this.stopAutoPosition()
        },

        // Highlight the whole value on focus/click so the next keystroke replaces
        // it — people retype a time wholesale rather than editing one character
        // (matches Google Calendar's time field). Bound to both focus and click:
        // a mouse focus would otherwise have its selection cleared by the trailing
        // mouseup, and click fires after that, so the selection sticks.
        selectAll() {
            this.$refs.input.select()
        },

        onInput(value) {
            // Open BEFORE filtering, never after: open() rebuilds `filtered`
            // from the full option list, so opening last would throw the typed
            // filter away. Reachable by pressing Escape (which closes the panel
            // but keeps focus) and then typing.
            if (!this.isOpen) {
                this.open()
            }

            this.display = value
            this.hasInteracted = true

            // Normalize the typed separator (".", "h") to ":" before matching, so
            // partial dotted/French input ("9.", "9.3", "9h3") filters live the
            // same way "9:3" does — option labels/values are always colon-formed.
            const needle = value
                .trim()
                .toLowerCase()
                .replace(/\s/g, '')
                .replace(/[.h]/g, ':')
            const base = this.visibleOptions()
            // Run the text through the parser too, so shorthand like "9pm" or
            // "330" (which never prefix-matches a formatted label) still surfaces
            // its corresponding slot.
            const parsed = this.parse(value)

            const matches =
                needle === ''
                    ? base
                    : base.filter(
                          (option) =>
                              option.label
                                  .toLowerCase()
                                  .replace(/\s/g, '')
                                  .startsWith(needle) ||
                              option.value.startsWith(needle) ||
                              (parsed !== null && option.value === parsed),
                      )

            this.filtered = this.withCustomOptions(
                matches,
                this.customCandidates(value),
            )
            this.highlight = 0
        },

        onBlur() {
            const parsed = this.parse(this.display)

            if (parsed === null) {
                this.syncFromState()
            } else {
                this.commit(parsed)
            }

            this.close()
        },

        move(direction) {
            if (!this.isOpen) {
                this.open()

                return
            }

            const count = this.filtered.length

            if (!count) {
                return
            }

            this.hasInteracted = true
            this.highlight = (this.highlight + direction + count) % count
            this.scrollToHighlight()
        },

        // Jump straight to an index (Home / End), clamped to the list.
        moveTo(index) {
            const count = this.filtered.length

            if (!count) {
                return
            }

            this.hasInteracted = true
            this.highlight = Math.max(0, Math.min(count - 1, index))
            this.scrollToHighlight()
        },

        // PageUp / PageDown step by a screenful. Ten rows is close enough to the
        // panel's visible height at every row size, and — unlike move() — this
        // clamps at the ends instead of wrapping, which is what the ARIA
        // combobox pattern expects.
        movePage(direction) {
            this.moveTo(this.highlight + direction * 10)
        },

        selectHighlighted() {
            if (this.isOpen && this.filtered[this.highlight]) {
                this.commit(this.filtered[this.highlight].value)
            } else {
                const parsed = this.parse(this.display)

                if (parsed !== null) {
                    this.commit(parsed)
                }
            }

            this.close()
        },

        select(option) {
            this.commit(option.value)
            this.close()
        },

        // Touch/mouse selection that tells a tap apart from a scroll-drag.
        // pointerdown records the origin and keeps focus on the input (so the
        // panel doesn't blur-close and the mobile keyboard stays up); the option
        // commits on pointerup only if the pointer barely moved. A drag to scroll
        // the list — which on a phone almost always starts on a row — therefore
        // scrolls instead of selecting.
        onOptionPointerDown(event) {
            // Keep focus on the input. This does NOT block native scrolling:
            // per the Pointer Events spec, scroll is governed by touch-action,
            // not by preventDefault on pointerdown.
            event.preventDefault()
            this.pointerOrigin = { x: event.clientX, y: event.clientY }
        },

        onOptionPointerUp(event, option) {
            const origin = this.pointerOrigin
            this.pointerOrigin = null

            if (origin === null) {
                return
            }

            const moved =
                Math.abs(event.clientX - origin.x) +
                Math.abs(event.clientY - origin.y)

            // A tap/click barely moves; beyond the threshold it was a scroll.
            if (moved <= 10) {
                this.select(option)
            }
        },

        // ---- ARIA wiring (combobox/listbox pattern) ----
        listboxId() {
            return this.fieldId ? `${this.fieldId}-listbox` : null
        },

        optionId(index) {
            return this.fieldId ? `${this.fieldId}-option-${index}` : null
        },

        // The id of the currently highlighted option, announced to screen readers
        // via aria-activedescendant; null when closed or empty.
        activeDescendantId() {
            return this.isOpen && this.filtered.length
                ? this.optionId(this.highlight)
                : null
        },

        // Whether a canonical value lands on a generated slot. Mirrors
        // SmartTimePicker::isOnGrid() on the PHP side.
        isOnGrid(canonical) {
            return this.options.some((option) => option.value === canonical)
        },

        // In loose (non-strict) mode, surface validly-typed times that aren't on
        // the interval grid as selectable rows, so "9:20 AM" (or the partial
        // "9:20 A") can be picked even though it isn't a generated slot. Strict
        // mode deliberately omits them — off-grid times aren't allowed.
        withCustomOptions(list, candidates) {
            if (this.strict || !candidates.length) {
                return list
            }

            const extra = candidates
                .filter(
                    (value) =>
                        this.isInRange(value) &&
                        !list.some((option) => option.value === value),
                )
                .map((value) => this.customOption(value))

            if (!extra.length) {
                return list
            }

            // Insert custom rows in chronological position (not pinned on top) so
            // a reopened off-grid value sits among the grid times — e.g. 3:25 PM
            // lands between 3:15 PM and 3:30 PM, where open() then highlights and
            // scrolls to it.
            return [...list, ...extra].sort((a, b) => a.minutes - b.minutes)
        },

        // Canonical value(s) the typed text could mean. A 12-hour time without a
        // meridiem is ambiguous, so "3:25" yields both 03:25 and 15:25 ("3:25 AM"
        // and "3:25 PM"); anything unambiguous (meridiem given, 24-hour hour,
        // midnight) yields a single value.
        customCandidates(value) {
            // Mid-typing a single minute digit ("9:2") doesn't parse yet; treat
            // the digit as the tens place and preview the filled minute
            // ("9:2" → 9:20) so a suggestion shows as you type the minute.
            const parsed =
                this.parse(value) ?? this.parse(this.fillPartial(value))

            if (parsed === null) {
                return []
            }

            const candidates = [parsed]

            if (!this.hasMeridiem(value)) {
                const alternate = this.toggleMeridiem(parsed)

                if (alternate !== null && alternate !== parsed) {
                    candidates.push(alternate)
                }
            }

            return candidates
        },

        // Complete "hour + separator + single minute digit" by padding the minute
        // to two digits (the typed digit is the tens place): "9:2" → "9:20",
        // "9.2 p" → "9:20 p". Returns null when the text isn't that shape.
        fillPartial(value) {
            let s = String(value).trim().toLowerCase()
            let meridiem = ''

            const found = s.match(MERIDIEM)

            if (found) {
                meridiem = ` ${found[1]}`
                s = s.replace(MERIDIEM, '').trim()
            }

            const partial = s.match(/^(\d{1,2})[:.h](\d)$/)

            return partial ? `${partial[1]}:${partial[2]}0${meridiem}` : null
        },

        hasMeridiem(value) {
            return MERIDIEM.test(String(value).trim().toLowerCase())
        },

        // The other 12-hour reading of a canonical value: 1–11 ⇄ 13–23, 12 ⇄ 00.
        // Returns null for hours that have no ambiguous twin (00, 13–23).
        toggleMeridiem(canonical) {
            const parts = canonical.split(':').map((n) => parseInt(n, 10))
            let hour = parts[0]

            if (hour >= 1 && hour <= 11) {
                hour += 12
            } else if (hour === 12) {
                hour = 0
            } else {
                return null
            }

            return [hour, ...parts.slice(1)].map(pad2).join(':')
        },

        customOption(value) {
            const minutes = this.minutesOf(value)
            const option = {
                value,
                label: this.toDisplay(value),
                minutes,
            }

            const reference = this.referenceMinutes()

            if (reference !== null && minutes > reference) {
                option.duration = this.formatDuration(minutes - reference)
            }

            return option
        },

        commit(value) {
            const normalized = this.parse(value)

            if (normalized === null) {
                return
            }

            // Reject anything outside minTime()/maxTime(), and — in strict mode
            // — anything off the interval grid, snapping the box back to the
            // last good state instead of committing it. The server repeats both
            // checks for values that never went through here.
            if (
                !this.isInRange(normalized) ||
                (this.strict && !this.isOnGrid(normalized))
            ) {
                this.syncFromState()

                return
            }

            this.state = normalized
            this.display = this.toDisplay(normalized)
        },

        // Measure the input and place the panel. Styles are written as
        // individual properties on the element rather than through a `:style`
        // string binding: Alpine sets a string binding with
        // setAttribute("style", ...), which replaces the WHOLE attribute and so
        // fights the display/opacity that x-show and x-transition write to the
        // same element.
        positionPanel() {
            const panel = this.$refs.panel

            // The teleported panel may not exist yet on the very first open;
            // the autoposition loop retries next frame.
            if (!panel) {
                return
            }

            const rect = this.$refs.input.getBoundingClientRect()
            const margin = 4
            const maxPanelHeight = 240

            // Measure against the *visual* viewport, not window.innerHeight, so
            // the on-screen keyboard on mobile is accounted for -- the layout
            // viewport doesn't shrink when the keyboard opens, but the visual
            // one does, and an unflipped panel would render behind it.
            const vv = window.visualViewport
            const viewTop = vv ? vv.offsetTop : 0
            const viewBottom = vv
                ? vv.offsetTop + vv.height
                : window.innerHeight
            const spaceBelow = viewBottom - rect.bottom
            const spaceAbove = rect.top - viewTop

            // Flip the panel above the input when there isn't room below and
            // there's more room above -- keeps the dropdown on screen in a tall
            // form's last row, or when the keyboard covers the lower half.
            const openUp =
                spaceBelow < maxPanelHeight && spaceAbove > spaceBelow

            // Place by `top` for both directions (fixed-position `top` is stable
            // across browsers); when opening up, translate the panel fully above
            // the input so its own height never needs measuring.
            const top = openUp ? rect.top - margin : rect.bottom + margin

            // Never exceed the visible space on the chosen side, so a cramped
            // screen scrolls the list internally instead of clipping off-screen.
            const available = (openUp ? spaceAbove : spaceBelow) - margin
            const maxHeight = Math.max(96, Math.min(maxPanelHeight, available))

            // Skip the write when nothing moved. The autoposition loop runs every
            // frame the panel is open, and a no-op style write would still
            // invalidate layout.
            const placement = [
                rect.left,
                top,
                rect.width,
                maxHeight,
                openUp,
            ].join()

            if (placement === this.lastPlacement) {
                return
            }

            this.lastPlacement = placement

            // Width is pinned to the input, exactly like Filament's own Select
            // dropdown (dropdown.style.width = button width).
            panel.style.left = `${rect.left}px`
            panel.style.top = `${top}px`
            panel.style.width = `${rect.width}px`
            panel.style.maxHeight = `${maxHeight}px`
            panel.style.transform = openUp ? 'translateY(-100%)' : ''
        },

        // Re-measure every frame while the panel is open.
        //
        // A single measurement on open is not enough: the input can move right
        // after it -- the browser scrolling a focused field into view, a modal
        // still running its open transition, a late-loading font, a validation
        // message appearing above. Any of those left the panel stranded at its
        // original coordinates until something happened to fire a scroll or
        // resize event. Polling one getBoundingClientRect per frame (and writing
        // only when it actually changed) is what Floating UI's autoUpdate does,
        // and it costs nothing while the panel is closed.
        startAutoPosition() {
            if (this.positionFrame !== null) {
                return
            }

            const tick = () => {
                if (!this.isOpen) {
                    this.positionFrame = null

                    return
                }

                this.positionPanel()
                this.positionFrame = requestAnimationFrame(tick)
            }

            this.positionFrame = requestAnimationFrame(tick)
        },

        stopAutoPosition() {
            if (this.positionFrame !== null) {
                cancelAnimationFrame(this.positionFrame)
                this.positionFrame = null
            }
        },

        scrollToHighlight() {
            const panel = this.$refs.panel

            if (!panel) {
                return
            }

            // Index the rendered option rows, not panel.children -- the latter
            // also counts the x-if/x-for <template> markers and the empty-state
            // <li>, which would offset the target.
            const active =
                panel.querySelectorAll('[role="option"]')[this.highlight]

            if (!active) {
                return
            }

            // Scroll the panel by hand rather than with scrollIntoView. That
            // walks up EVERY scrollable ancestor, so when the panel is clipped
            // at the bottom of the viewport it also scrolls the page -- moving
            // the input out from under the panel we just positioned.
            const top = active.offsetTop
            const bottom = top + active.offsetHeight

            if (top < panel.scrollTop) {
                panel.scrollTop = top
            } else if (bottom > panel.scrollTop + panel.clientHeight) {
                panel.scrollTop = bottom - panel.clientHeight
            }
        },
    }
}
