<?php

declare(strict_types=1);

use Yard\PostWriter\TermSelection;

it('names factory sets only names', function () {
    $selection = TermSelection::names(['Bouw', 'Infra']);

    expect($selection->names)->toBe(['Bouw', 'Infra'])
        ->and($selection->ids)->toBe([]);
});

it('ids factory sets only ids', function () {
    $selection = TermSelection::ids([42, 43]);

    expect($selection->ids)->toBe([42, 43])
        ->and($selection->names)->toBe([]);
});

it('constructor allows mixed names and ids', function () {
    $selection = new TermSelection(names: ['Noord'], ids: [42]);

    expect($selection->names)->toBe(['Noord'])
        ->and($selection->ids)->toBe([42]);
});

it('empty selection has no terms', function () {
    $selection = new TermSelection();

    expect($selection->names)->toBe([])
        ->and($selection->ids)->toBe([]);
});
