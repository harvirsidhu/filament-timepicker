<?php

declare(strict_types=1);

use Harvirsidhu\FilamentTimepicker\SmartTimePicker;
use Harvirsidhu\FilamentTimepicker\Tests\Fixtures\TimePickerTestComponent;

use function Pest\Livewire\livewire;

beforeEach(function () {
    TimePickerTestComponent::$configure = null;
    TimePickerTestComponent::$initialState = [];
});

/**
 * The rendered markup with HTML entities resolved. Alpine expressions live in
 * attributes, so `$entangle('…')` reaches the browser as `&#039;` — decoding
 * lets a test assert on the expression as it is authored.
 */
function renderedHtml(): string
{
    return html_entity_decode(
        livewire(TimePickerTestComponent::class)->html(),
        ENT_QUOTES | ENT_HTML5,
    );
}

it('renders the field', function () {
    livewire(TimePickerTestComponent::class)
        ->assertOk()
        ->assertSee('smartTimePicker(', escape: false)
        ->assertSee('role="combobox"', escape: false);
});

it('paints the stored value into the input before Alpine boots', function () {
    // Without a server-rendered `value`, an edit form shows empty boxes until
    // the lazily loaded component arrives and runs syncFromState().
    TimePickerTestComponent::$initialState = ['start_time' => '15:30'];

    livewire(TimePickerTestComponent::class)
        ->assertSee('value="3:30 pm"', escape: false);
});

it('honours the display format in the server-rendered value', function () {
    TimePickerTestComponent::$initialState = ['start_time' => '15:30'];
    TimePickerTestComponent::$configure = fn (SmartTimePicker $field) => $field->displayFormat('H:i');

    livewire(TimePickerTestComponent::class)
        ->assertSee('value="15:30"', escape: false);
});

it('leaves the input empty when there is no value', function () {
    livewire(TimePickerTestComponent::class)
        ->assertDontSee('value="', escape: false);
});

it('binds live state when the field is live', function () {
    // $entangle(path, true) is what makes ->live() and afterStateUpdated() fire
    // on commit; a bare $entangle() defers, and they never run.
    TimePickerTestComponent::$configure = fn (SmartTimePicker $field) => $field->live();

    expect(renderedHtml())->toContain("\$entangle('data.start_time', true)");
});

it('defers state when the field is not live', function () {
    expect(renderedHtml())->toContain("\$entangle('data.start_time', false)");
});

it('renders the reactive-config bridge outside the ignored subtree', function () {
    // wire:ignore freezes the x-data config, so this sibling is the only route
    // a reactive minTime()/maxTime() has to reach the browser.
    TimePickerTestComponent::$configure = fn (SmartTimePicker $field) => $field
        ->interval(30)
        ->minTime('09:00')
        ->maxTime('17:00');

    // Raw, not entity-decoded: the JSON's own quotes are escaped in the
    // attribute, and decoding the whole document would make it unparseable.
    $html = livewire(TimePickerTestComponent::class)->html();

    expect($html)->toMatch('/id="[^"]*start_time-config"/')
        ->and($html)->toContain('data-config=');

    preg_match('/data-config="([^"]*)"/', $html, $matches);
    $config = json_decode(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5), true);

    expect($config)->toMatchArray([
        'interval' => 30,
        'min' => '09:00',
        'max' => '17:00',
        'seconds' => false,
        'strict' => false,
        'displayFormat' => 'g:i a',
        'isDisabled' => false,
        'isInvalid' => false,
        'durationFromStatePath' => null,
        'defaultDuration' => null,
    ])
        ->and($config['durationLabels'])->toBe([
            'hour' => 'hour',
            'hours' => 'hours',
            'minute' => 'min',
            'minutes' => 'mins',
            'shortHour' => 'h',
            'shortMinute' => 'm',
        ]);

    // The bridge must sit before — not inside — the wire:ignore root.
    expect(strpos($html, 'data-config='))->toBeLessThan(strpos($html, 'wire:ignore'));
});

it('marks the input invalid when the field has an error', function () {
    TimePickerTestComponent::$configure = fn (SmartTimePicker $field) => $field->maxTime('17:00');
    TimePickerTestComponent::$initialState = ['start_time' => '18:00'];

    $component = livewire(TimePickerTestComponent::class)
        ->call('validate')
        ->assertHasErrors('data.start_time')
        ->assertSee('aria-invalid="true"', escape: false);

    // The input sits inside wire:ignore, so the static attribute above only
    // covers this first paint; the bridge is what keeps it current later.
    expect($component->html())->toContain('isInvalid&quot;:true');
});

it('does not render Tailwind utility classes on the panel', function () {
    // The panel is styled by the package's registered CSS asset, so consumers
    // need no `@source` line. Utilities creeping back in would silently
    // reintroduce that requirement.
    livewire(TimePickerTestComponent::class)
        ->assertSee('class="fi-ti-panel"', escape: false);
});
