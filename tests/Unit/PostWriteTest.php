<?php

declare(strict_types=1);

use Yard\PostWriter\PostWrite;
use Yard\PostWriter\TermSelection;

it('leaves optional fields null by default', function () {
    $write = new PostWrite(title: 'Hello');

    expect($write->title)->toBe('Hello')
        ->and($write->content)->toBeNull()
        ->and($write->menuOrder)->toBeNull()
        ->and($write->meta)->toBe([])
        ->and($write->terms)->toBe([])
        ->and($write->insertStatus)->toBe('publish');
});

it('accepts typed fields', function () {
    $date = new DateTimeImmutable('2026-01-02 03:04:05');
    $write = new PostWrite(
        title: 'Hello',
        date: $date,
        menuOrder: 5,
        meta: ['external_id' => '42'],
        terms: ['sector' => TermSelection::names(['Bouw'])],
        insertStatus: 'draft',
    );

    expect($write->date)->toBe($date)
        ->and($write->menuOrder)->toBe(5)
        ->and($write->meta['external_id'])->toBe('42')
        ->and($write->terms['sector'])->toBeInstanceOf(TermSelection::class)
        ->and($write->insertStatus)->toBe('draft');
});
