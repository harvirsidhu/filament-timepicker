<?php

declare(strict_types=1);

return [

    // Shown in the suggestion dropdown when nothing matches the typed text.
    'no_matching_time' => 'No matching time',

    // Accessible label for the suggestion listbox (screen readers).
    'listbox_label' => 'Time suggestions',

    // Validation message in strict() mode for an off-grid time.
    'off_grid' => 'Choose a time at :interval-minute intervals.',

    // Validation messages for a time outside minTime() / maxTime(). ":time" is
    // rendered with the field's own displayFormat, so it reads the same way the
    // input does ("9:00 am").
    'min_time' => 'Choose a time at or after :time.',
    'max_time' => 'Choose a time at or before :time.',

    // Duration labels for durationFrom() options. Up to an hour the long words are
    // used ("30 mins", "1 hour"); past an hour it switches to the compact form
    // ("1h 30m", "2h") so longer gaps stay short. Shown in brackets in the list.
    'duration' => [
        'hour' => 'hour',
        'hours' => 'hours',
        'minute' => 'min',
        'minutes' => 'mins',
        'short_hour' => 'h',
        'short_minute' => 'm',
    ],

];
