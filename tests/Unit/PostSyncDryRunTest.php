<?php

declare(strict_types=1);

use Yard\PostWriter\PostSync;
use Yard\PostWriter\PostWrite;
use Yard\PostWriter\Tests\Support\FakeWpPostWriter;

it('does not upsert or prune in dry-run mode', function () {
    $writer = new FakeWpPostWriter();
    $writer->existing = ['b' => 55];
    $writer->deletedByPrune = [7];

    $report = (new PostSync($writer, 'member'))
        ->from([['id' => 'a'], ['id' => 'b']])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (array $row): PostWrite => new PostWrite(title: $row['id']))
        ->prune()
        ->dryRun()
        ->run();

    expect($writer->upserts)->toBe([])
        ->and($writer->pruneCalls)->toBe([])
        ->and($report->created)->toBe(1)
        ->and($report->updated)->toBe(1)
        ->and($report->pruned)->toBe(1)
        ->and($report->dryRun)->toBeTrue();
});

it('fires onWritten in dry-run with a synthetic result', function () {
    $writer = new FakeWpPostWriter();
    $writer->existing = ['b' => 55];
    $seen = [];

    (new PostSync($writer, 'member'))
        ->from([['id' => 'a'], ['id' => 'b']])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (array $row): PostWrite => new PostWrite(title: $row['id']))
        ->onWritten(function (\Yard\PostWriter\UpsertResult $r) use (&$seen): void {
            $seen[] = $r->action->value . ':' . $r->id;
        })
        ->dryRun()
        ->run();

    expect($seen)->toBe(['created:0', 'updated:55'])
        ->and($writer->upserts)->toBe([]);
});

it('fires onPruned in dry-run for each prunable id', function () {
    $writer = new FakeWpPostWriter();
    $writer->deletedByPrune = [7, 8];
    $seen = [];

    $report = (new PostSync($writer, 'member'))
        ->from([['id' => 'a']])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (array $row): PostWrite => new PostWrite(title: 'x'))
        ->onPruned(function (int $id) use (&$seen): void {
            $seen[] = $id;
        })
        ->prune()
        ->dryRun()
        ->run();

    expect($seen)->toBe([7, 8])
        ->and($writer->pruneCalls)->toBe([])
        ->and($report->pruned)->toBe(2);
});
