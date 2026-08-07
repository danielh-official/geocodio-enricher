<?php

test('normalizes casing, punctuation, and whitespace', function () {
    expect(normalizeAddress(' 123 Main St., Apt #4 '))
        ->toBe('123 MAIN ST APT 4');
});

test('collides on formatting differences but not on real ones', function () {
    expect(addressHash('123 main st.'))->toBe(addressHash('  123 MAIN ST '))
        ->and(addressHash('123 Main St'))->not->toBe(addressHash('123 Main Street'));
});
