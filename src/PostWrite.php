<?php

declare(strict_types=1);

namespace Yard\PostWriter;

/**
 * Describes what to write to a WordPress post. Transient, generic (no domain knowledge).
 *
 * Only the fields present here are written; anything absent is left untouched. An
 * empty/null meta value clears the field; an empty terms array clears the taxonomy.
 */
final class PostWrite
{
	/**
	 * @param array<string, mixed>              $core      post fields applied on insert and update
	 * @param array<string, mixed>              $meta      meta-key => value (null/'' clears)
	 * @param array<string, array<int, string>> $terms     taxonomy => term names (empty array clears)
	 * @param array<string, string>             $matchMeta meta-key => value used to find an existing post
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
