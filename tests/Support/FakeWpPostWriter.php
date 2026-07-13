<?php

declare(strict_types=1);

namespace Yard\PostWriter\Tests\Support;

use Yard\PostWriter\PostWrite;
use Yard\PostWriter\PruneMode;
use Yard\PostWriter\UpsertAction;
use Yard\PostWriter\UpsertResult;
use Yard\PostWriter\WpPostWriter;

final class FakeWpPostWriter extends WpPostWriter
{
    /** @var list<array{0:string,1:PostWrite,2:?int}> */
    public array $upserts = [];

    public int $findCalls = 0;

    /** @var list<array{0:string,1:string,2:array<int,string>}> */
    public array $pruneCalls = [];

    /** @var array<string,int> identity value => existing post id */
    public array $existing = [];

    /** @var list<int> */
    public array $deletedByPrune = [];

    public ?PruneMode $lastPruneMode = null;

    public int $nextId = 1;

    public bool $bulkUsed = false;

    public function upsert(string $postType, PostWrite $write, ?int $existingId = null): UpsertResult
    {
        $this->upserts[] = [$postType, $write, $existingId];

        if (null !== $existingId) {
            return new UpsertResult($existingId, UpsertAction::Updated);
        }

        return new UpsertResult($this->nextId++, UpsertAction::Created);
    }

    /** @param array<string, string> $matchMeta */
    public function find(string $postType, array $matchMeta): ?int
    {
        $this->findCalls++;
        $identity = [] === $matchMeta ? '' : (string) reset($matchMeta);

        return '' === $identity ? null : ($this->existing[$identity] ?? null);
    }

    /**
     * @param array<int, string> $keep
     *
     * @return list<int>
     */
    public function prunable(string $postType, string $metaKey, array $keep, PruneMode $mode = PruneMode::Delete): array
    {
        $this->lastPruneMode = $mode;

        return [] === $keep ? [] : $this->deletedByPrune;
    }

    /**
     * @param array<int, string> $keep
     *
     * @return list<int>
     */
    public function prune(string $postType, string $metaKey, array $keep, PruneMode $mode = PruneMode::Delete): array
    {
        $this->pruneCalls[] = [$postType, $metaKey, $keep];

        return $this->prunable($postType, $metaKey, $keep, $mode);
    }

    public function bulk(callable $callback): mixed
    {
        $this->bulkUsed = true;

        return $callback();
    }
}
