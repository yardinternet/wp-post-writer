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
			wp_set_object_terms($id, [...$selection->names, ...$selection->ids], $taxonomy, false);
		}
	}
}
