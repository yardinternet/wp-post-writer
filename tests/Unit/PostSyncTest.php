<?php

declare(strict_types=1);

use Yard\PostWriter\PostSync;
use Yard\PostWriter\PostWrite;
use Yard\PostWriter\Tests\Support\FakeWpPostWriter;
use Yard\PostWriter\UpsertResult;

it('upserts each item and counts created and updated', function () {
    $writer = new FakeWpPostWriter();
    $writer->existing = ['b' => 55];

    $report = (new PostSync($writer, 'member'))
        ->from([['id' => 'a'], ['id' => 'b']])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (array $row): PostWrite => new PostWrite(title: $row['id']))
        ->run();

    expect($report->created)->toBe(1)
        ->and($report->updated)->toBe(1)
        ->and($writer->upserts)->toHaveCount(2);
});

it('passes match meta from identify to upsert', function () {
    $writer = new FakeWpPostWriter();

    (new PostSync($writer, 'member'))
        ->from([['id' => 'a']])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (array $row): PostWrite => new PostWrite(title: 'x'))
        ->run();

    expect($writer->upserts[0][2])->toBe(['external_id' => 'a']);
});

it('skips on write exception and calls onSkip', function () {
    $writer = new FakeWpPostWriter();
    $skipped = [];

    $report = (new PostSync($writer, 'member'))
        ->from([['id' => 'a'], ['id' => 'bad']])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(function (array $row): PostWrite {
            if ('bad' === $row['id']) {
                throw new RuntimeException('invalid');
            }

            return new PostWrite(title: $row['id']);
        })
        ->onSkip(function (array $row, Throwable $e) use (&$skipped): void {
            $skipped[] = $row['id'] . ':' . $e->getMessage();
        })
        ->run();

    expect($report->created)->toBe(1)
        ->and($report->skipped)->toBe(1)
        ->and($skipped)->toBe(['bad:invalid']);
});

it('rethrows the first exception in failFast mode', function () {
    $writer = new FakeWpPostWriter();

    $sync = (new PostSync($writer, 'member'))
        ->from([['id' => 'bad']])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (): PostWrite => throw new RuntimeException('boom'))
        ->failFast();

    expect(fn () => $sync->run())->toThrow(RuntimeException::class);
});

it('drops filtered items before identify', function () {
    $writer = new FakeWpPostWriter();

    $report = (new PostSync($writer, 'member'))
        ->from([['id' => 'a', 'keep' => true], ['id' => 'b', 'keep' => false]])
        ->filter(fn (array $row): bool => $row['keep'])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (array $row): PostWrite => new PostWrite(title: $row['id']))
        ->run();

    expect($report->created)->toBe(1)
        ->and($report->filtered)->toBe(1)
        ->and($writer->upserts)->toHaveCount(1);
});

it('fires onFiltered for each filtered item', function () {
    $writer = new FakeWpPostWriter();
    $seen = [];

    $report = (new PostSync($writer, 'member'))
        ->from([['id' => 'a', 'keep' => true], ['id' => 'b', 'keep' => false]])
        ->filter(fn (array $row): bool => $row['keep'])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (array $row): PostWrite => new PostWrite(title: $row['id']))
        ->onFiltered(function (array $row) use (&$seen): void {
            $seen[] = $row['id'];
        })
        ->run();

    expect($seen)->toBe(['b'])
        ->and($report->filtered)->toBe(1);
});

it('passes the UpsertResult to onWritten', function () {
    $writer = new FakeWpPostWriter();
    $seen = [];

    (new PostSync($writer, 'member'))
        ->from([['id' => 'a']])
        ->identify(fn (array $row): string => $row['id'], 'external_id')
        ->write(fn (array $row): PostWrite => new PostWrite(title: 'x'))
        ->onWritten(function (UpsertResult $r) use (&$seen): void {
            $seen[] = $r->action->value;
        })
        ->run();

    expect($seen)->toBe(['created']);
});

it('wraps the work in bulk', function () {
    $writer = new FakeWpPostWriter();

    (new PostSync($writer, 'member'))
        ->from([])
        ->write(fn ($row): PostWrite => new PostWrite(title: 'x'))
        ->run();

    expect($writer->bulkUsed)->toBeTrue();
});
