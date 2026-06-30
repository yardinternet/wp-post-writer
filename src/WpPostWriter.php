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

		$this->persistMeta($id, $write->meta, array_keys($matchMeta), UpsertAction::Created === $action);
		$this->persistTerms($id, $write->terms);

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

	/**
	 * @param array<string, mixed> $meta
	 * @param list<string>         $identityKeys
	 */
	private function persistMeta(int $id, array $meta, array $identityKeys, bool $inserted): void
	{
		if ([] !== $meta && ! function_exists('update_field')) {
			throw new RuntimeException(
				'ACF (update_field) is required to persist meta fields but is not available.',
			);
		}

		// The identity meta is the anchor for matching and pruning; write it first so any later
		// failure leaves a findable, prunable post that self-heals on the next run.
		foreach ($identityKeys as $key) {
			if (array_key_exists($key, $meta)) {
				$this->writeMetaField($id, $key, $meta[$key], $inserted);
			}
		}
		foreach ($meta as $key => $value) {
			if (in_array($key, $identityKeys, true)) {
				continue;
			}
			$this->writeMetaField($id, $key, $value, false);
		}
	}

	private function writeMetaField(int $id, string $key, mixed $value, bool $deleteOnFailure): void
	{
		if (false !== update_field($key, $value ?? '', $id)) {
			return;
		}
		if ($deleteOnFailure) {
			wp_delete_post($id, true);
		}

		throw new RuntimeException(sprintf('Failed to write meta field "%s".', $key));
	}

	/** @param array<string, TermSelection> $terms */
	private function persistTerms(int $id, array $terms): void
	{
		foreach ($terms as $taxonomy => $selection) {
			$result = wp_set_object_terms($id, [...$selection->names, ...$selection->ids], $taxonomy, false);
			if (is_wp_error($result)) {
				throw new RuntimeException($result->get_error_message());
			}
		}
	}
}
