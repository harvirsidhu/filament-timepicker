# Changelog

All notable changes to `filament-timepicker` will be documented in this file.

## Unreleased

- Initial development: `SmartTimePicker` form field with free-text parsing,
  interval-based suggestion dropdown, keyboard navigation, and `relativeTo`
  duration labels.
- Fix: live filtering now normalizes `.`/`h` separators, so partial dotted/French
  input (`9.`, `9.3`, `9h3`) narrows the dropdown the same way `9:3` does.
- Accessibility: implement the ARIA combobox/listbox pattern (`role`,
  `aria-expanded`, `aria-controls`, `aria-activedescendant`, `aria-selected`).
- i18n: move all user-facing strings (dropdown empty state, strict-mode validation
  message, `relativeTo` duration words) into publishable translations under the
  `harvirsidhu-filament-timepicker` namespace.
- UX: the suggestion panel now flips above the input when there isn't room below.
- Mobile: the panel is measured against the visual viewport, so it stays in view
  when the on-screen keyboard opens, and caps its height to the visible space
  (scrolling internally) instead of clipping off-screen; it re-positions on
  `visualViewport` resize/scroll (keyboard show/hide, pinch-zoom).
- Mobile: dropdown options use a touch-friendly row height (≈44px), compact on
  larger pointers (`sm:` breakpoint).
- Mobile: a drag that starts on an option now scrolls the list instead of
  selecting — options commit on `pointerup` only when the pointer barely moved
  (tap vs. scroll), and `touch-pan-y` guarantees vertical scrolling.
- UI: match Filament's own Select dropdown — the panel is pinned to the input
  width, options use the neutral `bg-gray-50`/`dark:bg-white/5` highlight (not a
  primary tint) with rounded rows and `p-1` list padding.
- UX: an empty field opens with the slot nearest the current time highlighted
  (instead of always the first option); a committed value still highlights itself.
- Fix: scroll the highlighted option into view on open (the previous index counted
  the `<template>`/empty-state nodes, so it never scrolled to the right row).
- UX: in loose (non-strict) mode a validly-typed off-grid time (e.g. `9:20 AM`, or
  the partial `9:20 A`) now appears as a selectable "Custom" suggestion in the
  dropdown instead of "No matching time".
- Fix: add `wire:ignore` to the Alpine root so a Livewire DOM morph no longer
  re-inits the teleported dropdown outside its scope ("… is not defined" errors).
- Docs: document Filament's `->default()` (static and closure) for pre-filling.
- Tooling: add `phpstan.neon.dist` so `composer analyse` works; `export-ignore`
  `package-lock.json`.
