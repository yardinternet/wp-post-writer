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
