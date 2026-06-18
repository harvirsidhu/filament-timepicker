/**
 * Smart time picker — a type-ahead time combobox.
 *
 * The text input is driven by a LOCAL `display` string, never bound directly
 * to Livewire. The canonical wall-clock value ("H:i" / "H:i:s") is written to
 * the entangled `state` only on commit (pick / Enter / valid blur), so typing
 * never round-trips half-parsed text through the server.
 *
 * The parsing + display rules mirror src/Support/TimeParser.php — keep the two
 * in lockstep.
 */
export default function smartTimePicker(config) {
    return {
        state: config.state, // entangled canonical "H:i" / "H:i:s" (or null)
        interval: config.interval || 15,
        min: config.min || null, // "HH:MM" or null
        max: config.max || null,
        seconds: config.seconds || false,
        strict: config.strict || false,
        displayFormat: config.displayFormat || 'g:i A',
        isDisabled: config.isDisabled || false,
        relativeStatePath: config.relativeStatePath || null,

        display: '',
        isOpen: false,
        options: [],
        filtered: [],
        highlight: 0,
        panelStyle: '',

        init() {
            this.options = this.generateOptions()
            this.syncFromState()

            // Reflect programmatic state changes (e.g. a server-computed
            // end_time) back into the visible box when the panel is closed.
            this.$watch('state', () => {
                if (!this.isOpen) {
                    this.syncFromState()
                }
            })
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
            const meridiemMatch = s.match(/\s*([ap])\.?m?\.?$/)

            if (meridiemMatch) {
                meridiem = meridiemMatch[1]
                s = s.replace(/\s*([ap])\.?m?\.?$/, '').trim()
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

            if (hour < 0 || hour > 23 || minute < 0 || minute > 59 || second < 0 || second > 59) {
                return null
            }

            const pad = (n) => String(n).padStart(2, '0')

            return this.seconds
                ? `${pad(hour)}:${pad(minute)}:${pad(second)}`
                : `${pad(hour)}:${pad(minute)}`
        },

        // ---- display formatting (PHP date() token subset) ----
        toDisplay(canonical) {
            const parts = canonical.split(':')
            const hour = parseInt(parts[0], 10)
            const minute = parseInt(parts[1], 10)
            const second = parseInt(parts[2] || '0', 10)
            const hour12 = hour % 12 || 12
            const pad = (n) => String(n).padStart(2, '0')

            const tokens = {
                g: String(hour12),
                G: String(hour),
                h: pad(hour12),
                H: pad(hour),
                i: pad(minute),
                s: pad(second),
                A: hour < 12 ? 'AM' : 'PM',
                a: hour < 12 ? 'am' : 'pm',
            }

            let out = ''
            for (const ch of this.displayFormat) {
                out += ch in tokens ? tokens[ch] : ch
            }

            return out
        },

        minutesOf(canonical) {
            const parts = canonical.split(':')

            return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10)
        },

        fromMinutes(total) {
            const pad = (n) => String(n).padStart(2, '0')
            const value = `${pad(Math.floor(total / 60))}:${pad(total % 60)}`

            return this.seconds ? `${value}:00` : value
        },

        formatDuration(mins) {
            if (mins <= 0) {
                return ''
            }

            const hours = Math.floor(mins / 60)
            const minutes = mins % 60
            const parts = []

            if (hours) {
                parts.push(`${hours} ${hours === 1 ? 'hour' : 'hours'}`)
            }

            if (minutes) {
                parts.push(`${minutes} ${minutes === 1 ? 'min' : 'mins'}`)
            }

            return parts.join(' ')
        },

        generateOptions() {
            const step = this.interval
            const start = this.min ? this.minutesOf(this.min) : 0
            const end = this.max ? this.minutesOf(this.max) : 24 * 60 - 1
            const options = []

            for (let m = start; m <= end && m < 24 * 60; m += step) {
                const value = this.fromMinutes(m)
                options.push({ value, label: this.toDisplay(value), minutes: m })
            }

            return options
        },

        referenceMinutes() {
            if (!this.relativeStatePath) {
                return null
            }

            const parsed = this.parse(this.$wire.get(this.relativeStatePath))

            return parsed === null ? null : this.minutesOf(parsed)
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

            this.filtered = this.visibleOptions()
            this.highlight = Math.max(0, this.filtered.findIndex((o) => o.value === this.parse(this.state)))
            this.isOpen = true
            this.positionPanel()
            this.$nextTick(() => this.scrollToHighlight())
        },

        close() {
            this.isOpen = false
        },

        onInput(value) {
            this.display = value

            const needle = value.trim().toLowerCase().replace(/\s/g, '')
            const base = this.visibleOptions()
            // Run the text through the parser too, so shorthand like "9pm" or
            // "330" (which never prefix-matches a formatted label) still surfaces
            // its corresponding slot.
            const parsed = this.parse(value)

            this.filtered =
                needle === ''
                    ? base
                    : base.filter(
                          (option) =>
                              option.label.toLowerCase().replace(/\s/g, '').startsWith(needle) ||
                              option.value.startsWith(needle) ||
                              (parsed !== null && option.value === parsed),
                      )

            this.highlight = 0

            if (!this.isOpen) {
                this.open()
            }
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

            this.highlight = (this.highlight + direction + count) % count
            this.scrollToHighlight()
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

        // Whether a canonical value lands on a generated slot. Mirrors
        // SmartTimePicker::isOnGrid() on the PHP side.
        isOnGrid(canonical) {
            return this.options.some((option) => option.value === canonical)
        },

        commit(value) {
            const normalized = this.parse(value)

            if (normalized === null) {
                return
            }

            // In strict mode, reject an off-grid time and snap the box back to
            // the last good state instead of committing it.
            if (this.strict && !this.isOnGrid(normalized)) {
                this.syncFromState()

                return
            }

            this.state = normalized
            this.display = this.toDisplay(normalized)
        },

        positionPanel() {
            const rect = this.$refs.input.getBoundingClientRect()
            // Teleported to <body> and fixed-positioned so it escapes the
            // overflow clipping of repeater rows and modals. z-index sits above
            // Filament's modal layer.
            this.panelStyle =
                `position: fixed; left: ${rect.left}px; top: ${rect.bottom + 4}px;` +
                ` min-width: ${rect.width}px; z-index: 9999;`
        },

        scrollToHighlight() {
            const panel = this.$refs.panel

            if (!panel) {
                return
            }

            const active = panel.children[this.highlight]

            if (active) {
                active.scrollIntoView({ block: 'nearest' })
            }
        },
    }
}
