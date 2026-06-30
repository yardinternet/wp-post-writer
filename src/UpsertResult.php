<?php

declare(strict_types=1);

namespace Yard\PostWriter;

final class UpsertResult
{
	public function __construct(
		public int $id,
		public UpsertAction $action,
	) {
	}
}
