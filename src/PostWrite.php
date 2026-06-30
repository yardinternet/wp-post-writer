<?php

declare(strict_types=1);

namespace Yard\PostWriter;

final class PostWrite
{
	/**
	 * @param array<string, mixed>              $core
	 * @param array<string, mixed>              $meta
	 * @param array<string, array<int, string>> $terms
	 * @param array<string, string>             $matchMeta
	 */
	public function __construct(
		public array $core,
		public array $meta,
		public array $terms,
		public array $matchMeta,
		public string $insertStatus = 'publish',
	) {
	}
}
