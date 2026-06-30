<?php

declare(strict_types=1);

use Yard\PostWriter\SyncReport;

it('lists all counters in the summary', function () {
    $report = new SyncReport(created: 12, updated: 130, skipped: 2, filtered: 5, pruned: 3);

    expect($report->summary())
        ->toBe('Aangemaakt: 12, bijgewerkt: 130, overgeslagen: 2, gefilterd: 5, verwijderd: 3');
});

it('marks dry-run in the summary', function () {
    $report = new SyncReport(created: 1, dryRun: true);

    expect($report->summary())->toStartWith('(dry-run) ');
});

it('appends warnings to the summary', function () {
    $report = new SyncReport(warnings: ['prune overgeslagen — geen items uit de bron ontvangen']);

    expect($report->summary())->toContain('— prune overgeslagen');
});
