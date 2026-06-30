<?php

declare(strict_types=1);

namespace Yard\PostWriter;

final class PostWrite
{
	public function __construct(
		public array $core,
		public array $meta,
		public array $terms,
		public array $matchMeta,
		public string $insertStatus = 'publish',
	) {
	}
}
