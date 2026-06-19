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
- Tooling: add `phpstan.neon.dist` so `composer analyse` works; `export-ignore`
  `package-lock.json`.
