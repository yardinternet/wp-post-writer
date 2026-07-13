<?php

declare(strict_types=1);

use Yard\PostWriter\PruneMode;
use Yard\PostWriter\WpPostWriter;

it('returns prunable ids whose meta is not kept', function () {
    WP_Mock::userFunction('get_posts')->andReturn([1, 2, 3]);
    WP_Mock::userFunction('get_post_meta')->andReturnUsing(fn (int $id): string => (string) $id);

    $prunable = (new WpPostWriter())->prunable('member', 'external_id', ['1', '3']);

    expect(array_values($prunable))->toBe([2]);
});

it('ignores posts with an empty meta value', function () {
    WP_Mock::userFunction('get_posts')->andReturn([1, 2]);
    WP_Mock::userFunction('get_post_meta')->andReturnUsing(fn (int $id): string => 1 === $id ? '' : '99');

    expect(array_values((new WpPostWriter())->prunable('member', 'external_id', ['1'])))->toBe([2]);
});

it('returns empty when keep is empty', function () {
    WP_Mock::userFunction('get_posts')->never();

    expect((new WpPostWriter())->prunable('member', 'external_id', []))->toBe([]);
});

it('deletes each prunable id and returns them', function () {
    WP_Mock::userFunction('get_posts')->andReturn([1, 2, 3]);
    WP_Mock::userFunction('get_post_meta')->andReturnUsing(fn (int $id): string => (string) $id);
    WP_Mock::userFunction('wp_delete_post')->once()->with(2, true)->andReturn(true);

    expect(array_values((new WpPostWriter())->prune('member', 'external_id', ['1', '3'])))->toBe([2]);
});

it('trashes prunable ids in trash mode', function () {
    WP_Mock::userFunction('get_posts')->andReturn([1, 2]);
    WP_Mock::userFunction('get_post_meta')->andReturnUsing(fn (int $id): string => (string) $id);
    WP_Mock::userFunction('wp_trash_post')->once()->with(2)->andReturn(true);
    WP_Mock::userFunction('wp_delete_post')->never();

    $pruned = (new WpPostWriter())->prune('member', 'external_id', ['1'], PruneMode::Trash);

    expect(array_values($pruned))->toBe([2]);
});

it('drafts prunable ids in draft mode', function () {
    WP_Mock::userFunction('get_posts')->andReturn([1, 2]);
    WP_Mock::userFunction('get_post_meta')->andReturnUsing(fn (int $id): string => (string) $id);
    WP_Mock::userFunction('get_post_status')->with(2)->andReturn('publish');
    WP_Mock::userFunction('wp_update_post')->once()->with(['ID' => 2, 'post_status' => 'draft'])->andReturn(2);
    WP_Mock::userFunction('wp_delete_post')->never();

    $pruned = (new WpPostWriter())->prune('member', 'external_id', ['1'], PruneMode::Draft);

    expect(array_values($pruned))->toBe([2]);
});

it('skips posts that are already draft in draft mode', function () {
    WP_Mock::userFunction('get_posts')->andReturn([1, 2, 3]);
    WP_Mock::userFunction('get_post_meta')->andReturnUsing(fn (int $id): string => (string) $id);
    WP_Mock::userFunction('get_post_status')->andReturnUsing(fn (int $id): string => 2 === $id ? 'draft' : 'publish');
    WP_Mock::userFunction('wp_update_post')->once()->with(['ID' => 3, 'post_status' => 'draft'])->andReturn(3);

    $pruned = (new WpPostWriter())->prune('member', 'external_id', ['1'], PruneMode::Draft);

    expect(array_values($pruned))->toBe([3]);
});

it('excludes already-draft posts from prunable in draft mode', function () {
    WP_Mock::userFunction('get_posts')->andReturn([2]);
    WP_Mock::userFunction('get_post_meta')->andReturnUsing(fn (): string => '99');
    WP_Mock::userFunction('get_post_status')->with(2)->andReturn('draft');

    expect((new WpPostWriter())->prunable('member', 'external_id', ['1'], PruneMode::Draft))->toBe([]);
});
