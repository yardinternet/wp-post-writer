<?php

declare(strict_types=1);

use Yard\PostWriter\PostSync;
use Yard\PostWriter\PostWrite;
use Yard\PostWriter\PruneMode;
use Yard\PostWriter\Tests\Support\FakeWpPostWriter;

it('prunes with the collected keep set and counts', function () {
    $writer = new FakeWpPostWriter();
    $writer->deletedByPrune = [7, 8];
    $pruned = [];

    $report = (new PostSync($writer, 'member'))
        ->from([['id' => 'a']])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (array $row): PostWrite => new PostWrite(title: 'x'))
        ->onPruned(function (int $id) use (&$pruned): void {
            $pruned[] = $id;
        })
        ->prune()
        ->run();

    expect($writer->pruneCalls[0])->toBe(['member', 'external_id', ['a']])
        ->and($report->pruned)->toBe(2)
        ->and($pruned)->toBe([7, 8]);
});

it('skips prune with a warning on an empty keep set', function () {
    $writer = new FakeWpPostWriter();

    $report = (new PostSync($writer, 'member'))
        ->from([])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (array $row): PostWrite => new PostWrite(title: 'x'))
        ->prune()
        ->run();

    expect($writer->pruneCalls)->toBe([])
        ->and($report->pruned)->toBe(0)
        ->and($report->warnings)->toBe(['prune overgeslagen — geen items uit de bron ontvangen']);
});

it('does not prune when not requested', function () {
    $writer = new FakeWpPostWriter();

    (new PostSync($writer, 'member'))
        ->from([['id' => 'a']])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (array $row): PostWrite => new PostWrite(title: 'x'))
        ->run();

    expect($writer->pruneCalls)->toBe([]);
});

it('deduplicates the keep set', function () {
    $writer = new FakeWpPostWriter();

    (new PostSync($writer, 'member'))
        ->from([['id' => 'a'], ['id' => 'a']])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (array $row): PostWrite => new PostWrite(title: 'x'))
        ->prune()
        ->run();

    expect($writer->pruneCalls[0][2])->toBe(['a']);
});

it('passes the prune mode to the writer', function () {
    $writer = new FakeWpPostWriter();

    (new PostSync($writer, 'member'))
        ->from([['id' => 'a']])
        ->identify(fn (array $r): string => $r['id'], 'external_id')
        ->write(fn (): PostWrite => new PostWrite(title: 'x'))
        ->prune(PruneMode::Draft)
        ->run();

    expect($writer->lastPruneMode)->toBe(PruneMode::Draft);
});
