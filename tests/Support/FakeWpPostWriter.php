<?php

declare(strict_types=1);

namespace Yard\PostWriter\Tests\Support;

use Yard\PostWriter\PostWrite;
use Yard\PostWriter\UpsertAction;
use Yard\PostWriter\UpsertResult;
use Yard\PostWriter\WpPostWriter;

final class FakeWpPostWriter extends WpPostWriter
{
    /** @var list<array{0:string,1:PostWrite,2:array<string,string>}> */
    public array $upserts = [];

    /** @var list<array{0:string,1:string,2:array<int,string>}> */
    public array $pruneCalls = [];

    /** @var array<string,int> identity value => existing post id */
    public array $existing = [];

    /** @var list<int> */
    public array $deletedByPrune = [];

    public int $nextId = 1;

    public bool $bulkUsed = false;

    /** @param array<string, string> $matchMeta */
    public function upsert(string $postType, PostWrite $write, array $matchMeta = []): UpsertResult
    {
        $this->upserts[] = [$postType, $write, $matchMeta];
        $identity = [] === $matchMeta ? '' : (string) reset($matchMeta);

        if ('' !== $identity && isset($this->existing[$identity])) {
            return new UpsertResult($this->existing[$identity], UpsertAction::Updated);
        }

        $id = $this->nextId++;
        if ('' !== $identity) {
            $this->existing[$identity] = $id;
        }

        return new UpsertResult($id, UpsertAction::Created);
    }

    /** @param array<string, string> $matchMeta */
    public function find(string $postType, array $matchMeta): ?int
    {
        $identity = [] === $matchMeta ? '' : (string) reset($matchMeta);

        return '' === $identity ? null : ($this->existing[$identity] ?? null);
    }

    /**
     * @param array<int, string> $keep
     *
     * @return list<int>
     */
    public function prunable(string $postType, string $metaKey, array $keep): array
    {
        return [] === $keep ? [] : $this->deletedByPrune;
    }

    /**
     * @param array<int, string> $keep
     *
     * @return list<int>
     */
    public function prune(string $postType, string $metaKey, array $keep): array
    {
        $this->pruneCalls[] = [$postType, $metaKey, $keep];

        return $this->prunable($postType, $metaKey, $keep);
    }

    public function bulk(callable $callback): mixed
    {
        $this->bulkUsed = true;

        return $callback();
    }
}
