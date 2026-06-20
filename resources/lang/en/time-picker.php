<?php

return [

    // Shown in the suggestion dropdown when nothing matches the typed text.
    'no_matching_time' => 'No matching time',

    // Accessible label for the suggestion listbox (screen readers).
    'listbox_label' => 'Time suggestions',

    // Validation message in strict() mode for an off-grid time.
    'off_grid' => 'Choose a time at :interval-minute intervals.',

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
