<?php

declare(strict_types=1);

namespace Yard\PostWriter;

use RuntimeException;

class WpPostWriter
{
	public static function sync(string $postType): PostSync
	{
		return new PostSync(new self(), $postType);
	}

	/** @param array<string, string> $matchMeta */
	public function upsert(string $postType, PostWrite $write, array $matchMeta = []): UpsertResult
	{
		$existingId = $this->find($postType, $matchMeta);
		$core = $this->core($write);

		if (null !== $existingId) {
			$result = wp_update_post(['ID' => $existingId] + $core, true);
			$action = UpsertAction::Updated;
		} else {
			$result = wp_insert_post(
				$core + ['post_type' => $postType, 'post_status' => $write->insertStatus],
				true,
			);
			$action = UpsertAction::Created;
		}

		if (is_wp_error($result)) {
			throw new RuntimeException($result->get_error_message());
		}
		$id = (int) $result;

		$this->writeMeta($id, $write->meta);
		$this->writeTerms($id, $write->terms);

		return new UpsertResult($id, $action);
	}

	/** @param array<string, string> $matchMeta */
	public function find(string $postType, array $matchMeta): ?int
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

	/**
	 * @param array<int, string> $keep
	 *
	 * @return list<int>
	 */
	public function prunable(string $postType, string $metaKey, array $keep): array
	{
		if ([] === $keep) {
			return [];
		}

		$ids = get_posts([
			'post_type' => $postType,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
			'meta_query' => [['key' => $metaKey, 'compare' => 'EXISTS']],
		]);

		$prunable = [];
		foreach ($ids as $id) {
			$value = (string) get_post_meta($id, $metaKey, true);
			if ('' === $value || in_array($value, $keep, true)) {
				continue;
			}
			$prunable[] = (int) $id;
		}

		return $prunable;
	}

	/**
	 * @param array<int, string> $keep
	 *
	 * @return list<int>
	 */
	public function prune(string $postType, string $metaKey, array $keep): array
	{
		$deleted = $this->prunable($postType, $metaKey, $keep);
		foreach ($deleted as $id) {
			wp_delete_post($id, true);
		}

		return $deleted;
	}

	public function bulk(callable $callback): mixed
	{
		add_filter('facetwp_indexer_is_enabled', '__return_false');
		if (class_exists('SearchWP')) {
			\SearchWP::$indexer->pause();
		}
		if (! defined('WP_IMPORTING')) {
			define('WP_IMPORTING', true);
		}
		wp_defer_term_counting(true);
		wp_defer_comment_counting(true);
		wp_suspend_cache_invalidation(true);

		try {
			return $callback();
		} finally {
			wp_suspend_cache_invalidation(false);
			wp_defer_comment_counting(false);
			wp_defer_term_counting(false);
			remove_filter('facetwp_indexer_is_enabled', '__return_false');
			if (class_exists('SearchWP')) {
				\SearchWP::$indexer->unpause();
			}
			if (function_exists('FWP')) {
				FWP()->indexer->index();
			}
		}
	}

	/** @return array<string, mixed> */
	private function core(PostWrite $write): array
	{
		$core = ['post_title' => $write->title];

		if (null !== $write->content) {
			$core['post_content'] = $write->content;
		}
		if (null !== $write->excerpt) {
			$core['post_excerpt'] = $write->excerpt;
		}
		if (null !== $write->slug) {
			$core['post_name'] = $write->slug;
		}
		if (null !== $write->date) {
			$core['post_date'] = $write->date->format('Y-m-d H:i:s');
		}
		if (null !== $write->author) {
			$core['post_author'] = $write->author;
		}
		if (null !== $write->parent) {
			$core['post_parent'] = $write->parent;
		}
		if (null !== $write->menuOrder) {
			$core['menu_order'] = $write->menuOrder;
		}

		return $core;
	}

	/** @param array<string, mixed> $meta */
	private function writeMeta(int $id, array $meta): void
	{
		if ([] !== $meta && ! function_exists('update_field')) {
			throw new RuntimeException(
				'ACF (update_field) is required to persist meta fields but is not available.',
			);
		}
		foreach ($meta as $key => $value) {
			update_field($key, $value ?? '', $id);
		}
	}

	/** @param array<string, TermSelection> $terms */
	private function writeTerms(int $id, array $terms): void
	{
		foreach ($terms as $taxonomy => $selection) {
			$result = wp_set_object_terms($id, [...$selection->names, ...$selection->ids], $taxonomy, false);
			if (is_wp_error($result)) {
				throw new RuntimeException($result->get_error_message());
			}
		}
	}
}
