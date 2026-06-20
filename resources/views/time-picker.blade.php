@php
    $fieldWrapperView = $getFieldWrapperView();
    $extraAttributeBag = $getExtraAttributeBag();
    $isDisabled = $isDisabled();
    $isPrefixInline = $isPrefixInline();
    $isSuffixInline = $isSuffixInline();
    $prefixActions = $getPrefixActions();
    $prefixIcon = $getPrefixIcon();
    $prefixIconColor = $getPrefixIconColor();
    $prefixLabel = $getPrefixLabel();
    $suffixActions = $getSuffixActions();
    $suffixIcon = $getSuffixIcon();
    $suffixIconColor = $getSuffixIconColor();
    $suffixLabel = $getSuffixLabel();
    $statePath = $getStatePath();
    $placeholder = $getPlaceholder();
    $durationFromStatePath = $getDurationFromStatePath();
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    :inline-label-vertical-alignment="\Filament\Support\Enums\VerticalAlignment::Center"
>
    <x-filament::input.wrapper
        :disabled="$isDisabled"
        :inline-prefix="$isPrefixInline"
        :inline-suffix="$isSuffixInline"
        :prefix="$prefixLabel"
        :prefix-actions="$prefixActions"
        :prefix-icon="$prefixIcon"
        :prefix-icon-color="$prefixIconColor"
        :suffix="$suffixLabel"
        :suffix-actions="$suffixActions"
        :suffix-icon="$suffixIcon"
        :suffix-icon-color="$suffixIconColor"
        :valid="! $errors->has($statePath)"
        x-on:focus-input.stop="$el.querySelector('input')?.focus()"
        :attributes="
            \Filament\Support\prepare_inherited_attributes($extraAttributeBag)
                ->class('fi-ti-time-picker')
        "
    >
        <div
            x-load
            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('smart-time-picker', 'harvirsidhu/filament-timepicker') }}"
            {{-- Livewire must not morph this Alpine-managed subtree: morphing
                 re-processes the `x-teleport` panel and re-inits the teleported
                 <ul> outside the component scope (every binding then throws
                 "… is not defined"). State stays in sync via $entangle + the
                 init() $watch, exactly like Filament's own Alpine inputs. --}}
            wire:ignore
            x-data="smartTimePicker({
                state: $wire.$entangle('{{ $statePath }}'),
                interval: @js($getInterval()),
                min: @js($getMinTime()),
                max: @js($getMaxTime()),
                seconds: @js($getSeconds()),
                strict: @js($isStrict()),
                displayFormat: @js($getDisplayFormat()),
                isDisabled: @js($isDisabled),
                durationFromStatePath: @js($durationFromStatePath),
                defaultDuration: @js($getDefaultDuration()),
                fieldId: @js($getId()),
                durationLabels: @js([
                    'hour' => __('harvirsidhu-filament-timepicker::time-picker.duration.hour'),
                    'hours' => __('harvirsidhu-filament-timepicker::time-picker.duration.hours'),
                    'minute' => __('harvirsidhu-filament-timepicker::time-picker.duration.minute'),
                    'minutes' => __('harvirsidhu-filament-timepicker::time-picker.duration.minutes'),
                    'shortHour' => __('harvirsidhu-filament-timepicker::time-picker.duration.short_hour'),
                    'shortMinute' => __('harvirsidhu-filament-timepicker::time-picker.duration.short_minute'),
                ]),
            })"
            x-on:keydown.escape.stop="isOpen && (close(), $event.preventDefault())"
            x-on:scroll.window.capture="isOpen && positionPanel()"
            x-on:resize.window="isOpen && positionPanel()"
            {{ $getExtraAlpineAttributeBag()->class(['fi-input-wrp-content', 'w-full']) }}
        >
            <input
                x-ref="input"
                x-model="display"
                x-on:focus="open(); selectAll()"
                x-on:click="open(); selectAll()"
                x-on:input="onInput($event.target.value)"
                x-on:blur="onBlur()"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent.stop="selectHighlighted()"
                x-on:keydown.tab="isOpen && selectHighlighted()"
                role="combobox"
                aria-haspopup="listbox"
                aria-autocomplete="list"
                :aria-expanded="isOpen ? 'true' : 'false'"
                :aria-controls="listboxId()"
                :aria-activedescendant="activeDescendantId()"
                {{
                    $getExtraInputAttributeBag()
                        ->merge([
                            'autocomplete' => 'off',
                            'disabled' => $isDisabled,
                            'id' => $getId(),
                            'inputmode' => 'text',
                            'placeholder' => filled($placeholder) ? e($placeholder) : null,
                            'required' => $isRequired() && (! $isConcealed()),
                            'type' => 'text',
                        ], escape: false)
                        ->class([
                            'fi-input',
                            'fi-input-has-inline-prefix' => $isPrefixInline && (count($prefixActions) || $prefixIcon || filled($prefixLabel)),
                            'fi-input-has-inline-suffix' => $isSuffixInline && (count($suffixActions) || $suffixIcon || filled($suffixLabel)),
                        ])
                }}
            />

            <template x-teleport="body">
                <ul
                    x-ref="panel"
                    x-show="isOpen"
                    x-cloak
                    x-transition.opacity.duration.100ms
                    :style="panelStyle"
                    :id="listboxId()"
                    role="listbox"
                    aria-label="{{ __('harvirsidhu-filament-timepicker::time-picker.listbox_label') }}"
                    {{-- Mirrors Filament's own dropdown panel + list chrome
                         (rounded-lg bg-white/gray-900 ring + shadow, p-1 grid).
                         fi-dropdown-panel is intentionally NOT used: its
                         max-w-[14rem]! would override the input-matched width. --}}
                    class="fi-ti-panel max-h-60 space-y-px overflow-y-auto touch-pan-y rounded-lg bg-white p-1 shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                >
                    <template x-if="! filtered.length">
                        <li class="rounded-md px-2 py-2.5 text-sm text-gray-500 dark:text-gray-400 sm:py-2">
                            {{ __('harvirsidhu-filament-timepicker::time-picker.no_matching_time') }}
                        </li>
                    </template>

                    <template x-for="(option, index) in filtered" :key="option.value">
                        <li
                            :id="optionId(index)"
                            role="option"
                            :aria-selected="index === highlight ? 'true' : 'false'"
                            {{-- Select on pointerup only if it was a tap, not a
                                 scroll-drag (a drag starting on a row scrolls the
                                 list instead of committing). pointerdown keeps
                                 input focus so blur doesn't close the panel. --}}
                            x-on:pointerdown="onOptionPointerDown($event)"
                            x-on:pointerup="onOptionPointerUp($event, option)"
                            x-on:mousemove="highlight = index"
                            {{-- Neutral gray highlight matching Filament's Select
                                 option (bg-gray-50 / dark:white-5), not a primary
                                 tint; text colour stays constant. --}}
                            :class="{ 'bg-gray-50 dark:bg-white/5': index === highlight }"
                            {{-- Roomier rows on touch (≈44px); compact on sm+ pointers. --}}
                            class="flex cursor-pointer items-center justify-between gap-2 rounded-md px-2 py-2.5 text-sm text-gray-700 transition-colors duration-75 dark:text-gray-200 sm:py-2"
                        >
                            <span x-text="option.label" class="tabular-nums"></span>
                            <span
                                x-show="option.duration"
                                x-text="'(' + option.duration + ')'"
                                class="text-xs text-gray-400 dark:text-gray-500 tabular-nums"
                            ></span>
                        </li>
                    </template>
                </ul>
            </template>
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
