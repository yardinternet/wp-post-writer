<?php

declare(strict_types=1);

namespace Yard\PostWriter;

final class SyncReport
{
	/** @param list<string> $warnings */
	public function __construct(
		public int $created = 0,
		public int $updated = 0,
		public int $skipped = 0,
		public int $filtered = 0,
		public int $pruned = 0,
		public bool $dryRun = false,
		public array $warnings = [],
	) {
	}

	public function summary(): string
	{
		$line = sprintf(
			'Aangemaakt: %d, bijgewerkt: %d, overgeslagen: %d, gefilterd: %d, verwijderd: %d',
			$this->created,
			$this->updated,
			$this->skipped,
			$this->filtered,
			$this->pruned,
		);

		$prefix = $this->dryRun ? '(dry-run) ' : '';
		$suffix = [] === $this->warnings ? '' : ' — ' . implode('; ', $this->warnings);

		return $prefix . $line . $suffix;
	}
}
