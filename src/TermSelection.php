<?php

declare(strict_types=1);

namespace Yard\PostWriter;

final class TermSelection
{
	/**
	 * @param list<string> $names term names; matched on name/slug, created when absent
	 * @param list<int>    $ids   existing term ids
	 */
	public function __construct(
		public array $names = [],
		public array $ids = [],
	) {
	}

	/** @param list<string> $names */
	public static function names(array $names): self
	{
		return new self(names: $names);
	}

	/** @param list<int> $ids */
	public static function ids(array $ids): self
	{
		return new self(ids: $ids);
	}
}
