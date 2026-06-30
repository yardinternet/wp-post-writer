<?php

declare(strict_types=1);

namespace Yard\PostWriter;

use RuntimeException;

class WpPostWriter
{
	public function upsert(string $postType, PostWrite $write): int
	{
		$id = $this->findByMeta($postType, $write->matchMeta);

		if (null !== $id) {
			$result = wp_update_post(['ID' => $id] + $write->core, true);
		} else {
			$result = wp_insert_post(
				$write->core + ['post_type' => $postType, 'post_status' => $write->insertStatus],
				true,
			);
		}

		if (is_wp_error($result)) {
			throw new RuntimeException($result->get_error_message());
		}
		$id = (int) $result;

		if ([] !== $write->meta && ! function_exists('update_field')) {
			throw new RuntimeException(
				'ACF (update_field) is required to persist meta fields but is not available.',
			);
		}
		foreach ($write->meta as $key => $value) {
			update_field($key, $value ?? '', $id);
		}
		foreach ($write->terms as $taxonomy => $names) {
			wp_set_object_terms($id, $names, $taxonomy, false);
		}

		return $id;
	}

	public function prune(string $postType, string $metaKey, array $keep): int
	{
		$ids = get_posts([
			'post_type' => $postType,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
			'meta_query' => [['key' => $metaKey, 'compare' => 'EXISTS']],
		]);

		$deleted = 0;
		foreach ($ids as $id) {
			$value = (string) get_post_meta($id, $metaKey, true);
			if ('' === $value || in_array($value, $keep, true)) {
				continue;
			}
			wp_delete_post($id, true);
			$deleted++;
		}

		return $deleted;
	}

	public function bulk(callable $callback): mixed
	{
		add_filter('facetwp_indexer_is_enabled', '__return_false');

		try {
			return $callback();
		} finally {
			remove_filter('facetwp_indexer_is_enabled', '__return_false');
			if (function_exists('FWP')) {
				FWP()->indexer->index();
			}
		}
	}

	private function findByMeta(string $postType, array $matchMeta): ?int
	{
		if ([] === $matchMeta) {
			return null;
		}

		$metaQuery = [];
		foreach ($matchMeta as $key => $value) {
			if ('' === $value) {
				return null;
			}
			$metaQuery[] = ['key' => $key, 'value' => $value];
		}
		if (count($metaQuery) > 1) {
			$metaQuery['relation'] = 'AND';
		}

		$ids = get_posts([
			'post_type' => $postType,
			'post_status' => 'any',
			'numberposts' => 1,
			'fields' => 'ids',
			'meta_query' => $metaQuery,
		]);

		return $ids[0] ?? null;
	}
}
