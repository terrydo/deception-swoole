<?php 

// include 'sides.php';

const ROLES = [
    // #0
    [
        "name"      => "Murderer",
        "codename"  => "M",
        'image'     => "murderer.jpg",
        'side'      => BAD_SIDE,
    ],
    // #1
    [
        "name"      => "Accomplice",
        "codename"  => "A",
        'image'     => "accomplice.jpg",
        'side'      => BAD_SIDE,
    ],
    // #2
    [
        "name"      => "Forensic Scientist",
        "codename"  => "FS",
        'image'     => "forensic-scientist.jpg",
        'side'      => NO_SIDE,
    ],
    // #3
    [
        "name"      => "Witness",
        "codename"  => "W",
        'image'     => "witness.jpg",
        'side'      => GOOD_SIDE,
    ],
    // #4
    [
        "name"      => "Investigator",
        "codename"  => "I",
        'image'     => "investigator.jpg",
        'side'      => GOOD_SIDE,
    ],
];

?>