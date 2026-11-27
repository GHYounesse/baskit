<?php

/*
 * Demo review text by category and rating (1 to 5). A null body is a rating
 * with no comment, which is common on real storefronts.
 */

return [
    'electronics' => [
        5 => [
            'Sound and build quality are far better than the price suggests. Paired in seconds and the battery just keeps going.',
            'Bought it for work and it has quietly become my favourite gadget. Setup took two minutes.',
            'Excellent. Feels premium, works with all my devices and shipping was fast.',
            null,
        ],
        4 => [
            'Very good overall. The app is a bit basic, but the hardware itself is great.',
            'Does everything I need. I would love a slightly longer cable in the box.',
            null,
        ],
        3 => [
            'Works fine, but I expected a bit more at this price. Fine for everyday use.',
            'Okay. Connectivity is occasionally flaky, though a firmware update helped.',
        ],
        2 => ['It works, but the build feels lighter than the photos suggest.'],
        1 => ['Stopped charging after two weeks. Support was polite but slow.'],
    ],
    'clothing' => [
        5 => [
            'Fits true to size and the fabric is lovely. Already ordered a second colour.',
            'Really comfortable and it still looks new after many washes. Great value.',
            'Exactly as pictured, and the stitching is better than I expected.',
            null,
        ],
        4 => [
            'Nice quality. I sized up for a looser fit and it works well.',
            'Comfortable and well made. The colour is a touch darker than in the photos.',
            null,
        ],
        3 => [
            'Decent, but it runs a little small. Check the sizing before ordering.',
            'Fine for the price. The fabric is thinner than I hoped.',
        ],
        2 => ['The fit was off for me and the material feels a bit rough.'],
        1 => ['Came with a loose thread and shrank after the first wash.'],
    ],
    'home-kitchen' => [
        5 => [
            'Beautiful and solid. It looks great in our kitchen and I use it every day.',
            'Heavier and better made than I expected. Easy to clean, too.',
            'A lovely little upgrade for the home. The finish is excellent.',
            null,
        ],
        4 => [
            'Very nice. It took a little care to get started, but I am very happy now.',
            'Good quality and good looking. The packaging could be more compact.',
            null,
        ],
        3 => [
            'Does the job. Nothing remarkable, but nothing wrong either.',
            'Okay, though the colour was slightly different from the picture.',
        ],
        2 => ['Smaller than I imagined from the photos. Usable, but not what I hoped.'],
        1 => ['Arrived with a chip, and the replacement took a while.'],
    ],
    'books' => [
        5 => [
            'Could not put it down. Smart, warm and beautifully written.',
            'One of the best I have read this year. Already lent it to a friend.',
            'Clear, practical and enjoyable. I took notes on nearly every chapter.',
            null,
        ],
        4 => [
            'Really good. The second half drags a little, but the ideas are worth it.',
            'Thoughtful and well paced. I would happily read more from this author.',
            null,
        ],
        3 => [
            'An interesting premise, though it did not fully hold my attention.',
            'Decent read. A few chapters could have been shorter.',
        ],
        2 => ['Not for me. The ending felt rushed and the characters flat.'],
        1 => ['Slow and repetitive. I gave up halfway through.'],
    ],
    'toys' => [
        5 => [
            'My kids have played with this every day since it arrived. Sturdy and well made.',
            'Bought it as a gift and it was a huge hit. Great quality for the price.',
            'Safe, solid and fun. It has already survived a few drops.',
            null,
        ],
        4 => [
            'Lots of fun. The instructions could be clearer, but the kids did not mind.',
            'Good quality and the right size. Our little one loves it.',
            null,
        ],
        3 => [
            'Cute, but it lost its novelty after a week or two.',
            'Okay for the price. A couple of small parts feel a bit flimsy.',
        ],
        2 => ['Smaller than I expected and the paint chipped quickly.'],
        1 => ['A piece broke on the first day. Disappointing.'],
    ],
];
