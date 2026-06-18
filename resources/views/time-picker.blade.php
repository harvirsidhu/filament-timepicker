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
    $relativeStatePath = $getRelativeStatePath();
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
            x-data="smartTimePicker({
                state: $wire.$entangle('{{ $statePath }}'),
                interval: @js($getInterval()),
                min: @js($getMinTime()),
                max: @js($getMaxTime()),
                seconds: @js($getSeconds()),
                displayFormat: @js($getDisplayFormat()),
                isDisabled: @js($isDisabled),
                relativeStatePath: @js($relativeStatePath),
            })"
            x-on:keydown.escape.stop="isOpen && (close(), $event.preventDefault())"
            x-on:scroll.window="isOpen && positionPanel()"
            x-on:resize.window="isOpen && positionPanel()"
            {{ $getExtraAlpineAttributeBag()->class(['fi-input-wrp-content', 'w-full']) }}
        >
            <input
                x-ref="input"
                x-model="display"
                x-on:focus="open()"
                x-on:click="open()"
                x-on:input="onInput($event.target.value)"
                x-on:blur="onBlur()"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent.stop="selectHighlighted()"
                x-on:keydown.tab="isOpen && selectHighlighted()"
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
                    role="listbox"
                    class="fi-dropdown-panel fi-ti-panel max-h-60 w-max max-w-xs overflow-y-auto rounded-lg bg-white py-1 shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                >
                    <template x-if="! filtered.length">
                        <li class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('No matching time') }}
                        </li>
                    </template>

                    <template x-for="(option, index) in filtered" :key="option.value">
                        <li
                            role="option"
                            x-on:mousedown.prevent="select(option)"
                            x-on:mousemove="highlight = index"
                            :class="{
                                'bg-primary-500/10 text-primary-600 dark:text-primary-400': index === highlight,
                                'text-gray-700 dark:text-gray-200': index !== highlight,
                            }"
                            class="flex cursor-pointer items-center justify-between gap-4 px-3 py-1.5 text-sm"
                        >
                            <span x-text="option.label" class="font-medium tabular-nums"></span>
                            <span
                                x-show="option.duration"
                                x-text="option.duration"
                                class="text-xs text-gray-400 dark:text-gray-500 tabular-nums"
                            ></span>
                        </li>
                    </template>
                </ul>
            </template>
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
