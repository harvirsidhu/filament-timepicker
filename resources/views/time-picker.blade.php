@php
    use Harvirsidhu\FilamentTimepicker\Support\TimeParser;

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
    $id = $getId();
    $isInvalid = $errors->has($statePath);

    // Everything the Alpine component needs except the entangled state. The
    // error flag is a view concern ($errors), so it joins the field's own
    // config here rather than in getAlpineConfig().
    $alpineConfig = [...$getAlpineConfig(), 'isInvalid' => $isInvalid];

    // Render the committed value into the input's `value` up front. The Alpine
    // component is lazily loaded (x-load), so without this an edit form shows
    // empty time boxes until the chunk arrives and init() runs syncFromState().
    $initialDisplay = TimeParser::format($getState(), $alpineConfig['displayFormat']);
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
        {{-- Reactive-config bridge. The Alpine root below carries `wire:ignore`
             (see the note there), so Livewire can never morph its x-data — a
             reactive minTime()/maxTime()/interval() closure would otherwise stay
             frozen at whatever it evaluated to on first render. This sibling
             sits OUTSIDE the ignored subtree, so Livewire does morph it; the
             component watches `data-config` and rebuilds its option grid. --}}
        <div
            id="{{ $id }}-config"
            class="fi-ti-config"
            data-config="{{ json_encode($alpineConfig) }}"
            hidden
        ></div>

        <div
            x-load
            x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('smart-time-picker', 'harvirsidhu/filament-timepicker') }}"
            {{-- Livewire must not morph this Alpine-managed subtree: morphing
                 re-processes the `x-teleport` panel and re-inits the teleported
                 <ul> outside the component scope (every binding then throws
                 "… is not defined"). State stays in sync via $entangle + the
                 init() $watch; config changes arrive via the bridge above. --}}
            wire:ignore
            x-data="smartTimePicker({
                {{-- applyStateBindingModifiers turns this into $entangle(path, true)
                     when the field is ->live(), which is what makes live(),
                     afterStateUpdated() and reactive siblings fire on commit. --}}
                state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
                ...@js($alpineConfig),
            })"
            {{-- No scroll/resize listeners: while the panel is open the
                 component re-measures every frame (startAutoPosition), which
                 also catches movement no event reports. --}}
            x-on:keydown.escape.stop="isOpen && (close(), $event.preventDefault())"
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
                x-on:keydown.home="isOpen && (moveTo(0), $event.preventDefault())"
                x-on:keydown.end="isOpen && (moveTo(filtered.length - 1), $event.preventDefault())"
                x-on:keydown.page-down="isOpen && (movePage(1), $event.preventDefault())"
                x-on:keydown.page-up="isOpen && (movePage(-1), $event.preventDefault())"
                {{-- Open: pick the highlight and swallow the key. Closed (after
                     Escape): commit the typed text but let the form submit. --}}
                x-on:keydown.enter="onEnter($event)"
                {{-- Tab commits the highlighted option, but only once the user
                     has actually typed or arrowed. Merely focusing highlights
                     the slot nearest to now — tabbing straight past an untouched
                     field must not silently fill it. --}}
                x-on:keydown.tab="isOpen && hasInteracted && selectHighlighted()"
                role="combobox"
                aria-haspopup="listbox"
                aria-autocomplete="list"
                :aria-expanded="isOpen ? 'true' : 'false'"
                :aria-controls="listboxId()"
                :aria-activedescendant="activeDescendantId()"
                {{-- These two live inside wire:ignore, so their server-rendered
                     attributes below only cover the first paint. Alpine keeps
                     them current from the bridge config after every roundtrip
                     — otherwise a validation error would never set aria-invalid
                     (or clear it), and a reactive disabled() would stick. --}}
                :aria-invalid="isInvalid ? 'true' : null"
                :disabled="isDisabled"
                {{
                    $getExtraInputAttributeBag()
                        ->merge([
                            'aria-invalid' => $isInvalid ? 'true' : null,
                            'autocomplete' => 'off',
                            'disabled' => $isDisabled,
                            'id' => $id,
                            'inputmode' => 'text',
                            'placeholder' => filled($placeholder) ? e($placeholder) : null,
                            'required' => $isRequired() && (! $isConcealed()),
                            'type' => 'text',
                            'value' => filled($initialDisplay) ? e($initialDisplay) : null,
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
                    {{-- Position is written straight onto the element by
                         positionPanel(); a `:style` string binding would
                         setAttribute("style", …) and wipe the display/opacity
                         x-show and x-transition put on this same element. --}}
                    :id="listboxId()"
                    role="listbox"
                    aria-label="{{ __('harvirsidhu-filament-timepicker::time-picker.listbox_label') }}"
                    {{-- Styled by the package's own registered CSS asset
                         (resources/dist/filament-timepicker.css), not Tailwind
                         utilities — so a consuming app needs no `@source` line
                         to make the dropdown look right. --}}
                    class="fi-ti-panel"
                >
                    <template x-if="! filtered.length">
                        <li class="fi-ti-empty">
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
                                 option, not a primary tint; text colour stays
                                 constant. --}}
                            :class="index === highlight && 'fi-ti-option-active'"
                            class="fi-ti-option"
                        >
                            <span x-text="option.label" class="fi-ti-option-label"></span>
                            <span
                                x-show="option.duration"
                                x-text="'(' + option.duration + ')'"
                                class="fi-ti-option-duration"
                            ></span>
                        </li>
                    </template>
                </ul>
            </template>
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
