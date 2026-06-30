<?php

declare(strict_types=1);

namespace Yard\PostWriter;

final class PostSync
{
	/** @var iterable<mixed> */
	private iterable $items = [];

	/** @var null|callable(mixed):bool */
	private $filterFn = null;

	/** @var null|callable(mixed):string */
	private $identifyFn = null;

	private ?string $identityMeta = null;

	/** @var null|callable(mixed):PostWrite */
	private $writeFn = null;

	/** @var null|callable(UpsertResult):void */
	private $onWrittenFn = null;

	/** @var null|callable(mixed,\Throwable):void */
	private $onSkipFn = null;

	/** @var null|callable(int):void */
	private $onPrunedFn = null;

	private bool $failFast = false;

	private bool $prune = false;

	private bool $dryRun = false;

	private int $created = 0;

	private int $updated = 0;

	private int $skipped = 0;

	private int $filtered = 0;

	/** @var list<string> */
	private array $keep = [];

	/** @var list<string> */
	private array $warnings = [];

	private int $pruned = 0;

	public function __construct(
		private WpPostWriter $writer,
		private string $postType,
	) {
	}

	/** @param iterable<mixed> $items */
	public function from(iterable $items): self
	{
		$this->items = $items;

		return $this;
	}

	/** @param callable(mixed):bool $fn */
	public function filter(callable $fn): self
	{
		$this->filterFn = $fn;

		return $this;
	}

	/** @param callable(mixed):string $fn */
	public function identify(callable $fn, string $metaKey): self
	{
		$this->identifyFn = $fn;
		$this->identityMeta = $metaKey;

		return $this;
	}

	/** @param callable(mixed):PostWrite $fn */
	public function write(callable $fn): self
	{
		$this->writeFn = $fn;

		return $this;
	}

	/** @param callable(UpsertResult):void $fn */
	public function onWritten(callable $fn): self
	{
		$this->onWrittenFn = $fn;

		return $this;
	}

	/** @param callable(mixed,\Throwable):void $fn */
	public function onSkip(callable $fn): self
	{
		$this->onSkipFn = $fn;

		return $this;
	}

	/** @param callable(int):void $fn */
	public function onPruned(callable $fn): self
	{
		$this->onPrunedFn = $fn;

		return $this;
	}

	public function failFast(): self
	{
		$this->failFast = true;

		return $this;
	}

	public function prune(): self
	{
		$this->prune = true;

		return $this;
	}

	public function dryRun(): self
	{
		$this->dryRun = true;

		return $this;
	}

	public function run(): SyncReport
	{
		return $this->writer->bulk(fn (): SyncReport => $this->execute());
	}

	private function execute(): SyncReport
	{
		foreach ($this->items as $item) {
			$this->handle($item);
		}

		$this->runPrune();

		return new SyncReport(
			$this->created,
			$this->updated,
			$this->skipped,
			$this->filtered,
			$this->pruned,
			$this->dryRun,
			$this->warnings,
		);
	}

	private function runPrune(): void
	{
		if (! $this->prune || null === $this->identityMeta) {
			return;
		}

		$keep = array_values(array_unique($this->keep));
		if ([] === $keep) {
			$this->warnings[] = 'prune overgeslagen — geen items uit de bron ontvangen';

			return;
		}

		if ($this->dryRun) {
			$this->pruned = count($this->writer->prunable($this->postType, (string) $this->identityMeta, $keep));

			return;
		}

		$deleted = $this->writer->prune($this->postType, (string) $this->identityMeta, $keep);
		foreach ($deleted as $id) {
			if (null !== $this->onPrunedFn) {
				($this->onPrunedFn)($id);
			}
		}
		$this->pruned = count($deleted);
	}

	private function handle(mixed $item): void
	{
		if (null !== $this->filterFn && ! ($this->filterFn)($item)) {
			$this->filtered++;

			return;
		}

		if (null === $this->writeFn) {
			return;
		}

		$matchMeta = $this->matchMeta($item);

		try {
			$this->persist(($this->writeFn)($item), $matchMeta);
		} catch (\Throwable $e) {
			if ($this->failFast) {
				throw $e;
			}
			$this->skipped++;
			if (null !== $this->onSkipFn) {
				($this->onSkipFn)($item, $e);
			}
		}
	}

	/** @return array<string, string> */
	private function matchMeta(mixed $item): array
	{
		if (null === $this->identifyFn) {
			return [];
		}

		$identity = (string) ($this->identifyFn)($item);
		if ('' === $identity) {
			return [];
		}

		$this->keep[] = $identity;

		return [$this->identityMeta => $identity];
	}

	/** @param array<string, string> $matchMeta */
	private function persist(PostWrite $write, array $matchMeta): void
	{
		if ($this->dryRun) {
			null === $this->writer->find($this->postType, $matchMeta) ? $this->created++ : $this->updated++;

			return;
		}

		$result = $this->writer->upsert($this->postType, $write, $matchMeta);
		UpsertAction::Created === $result->action ? $this->created++ : $this->updated++;
		if (null !== $this->onWrittenFn) {
			($this->onWrittenFn)($result);
		}
	}
}
