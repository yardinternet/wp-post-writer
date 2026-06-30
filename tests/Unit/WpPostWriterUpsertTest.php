<?php

declare(strict_types=1);

use Yard\PostWriter\PostWrite;
use Yard\PostWriter\TermSelection;
use Yard\PostWriter\UpsertAction;
use Yard\PostWriter\WpPostWriter;

it('inserts when no match is found and returns created', function () {
    WP_Mock::userFunction('get_posts')->andReturn([]);
    WP_Mock::userFunction('is_wp_error')->andReturn(false);
    WP_Mock::userFunction('update_field')->andReturn(true);
    WP_Mock::userFunction('wp_set_object_terms')->andReturn([1]);
    WP_Mock::userFunction('wp_insert_post')->once()->andReturn(100);

    $write = new PostWrite(
        title: 'Acme',
        meta: ['external_id' => '42'],
        terms: ['sector' => TermSelection::names(['Bouw'])],
    );

    $result = (new WpPostWriter())->upsert('member', $write, ['external_id' => '42']);

    expect($result->id)->toBe(100)
        ->and($result->action)->toBe(UpsertAction::Created);
});

it('updates when a match is found and returns updated', function () {
    WP_Mock::userFunction('get_posts')->andReturn([55]);
    WP_Mock::userFunction('is_wp_error')->andReturn(false);
    WP_Mock::userFunction('update_field')->andReturn(true);
    WP_Mock::userFunction('wp_update_post')->once()->andReturnUsing(fn (array $arr): int => $arr['ID']);

    $write = new PostWrite(title: 'Acme', meta: ['external_id' => '42']);

    $result = (new WpPostWriter())->upsert('member', $write, ['external_id' => '42']);

    expect($result->id)->toBe(55)
        ->and($result->action)->toBe(UpsertAction::Updated);
});

it('forces insert on empty match meta', function () {
    WP_Mock::userFunction('is_wp_error')->andReturn(false);
    WP_Mock::userFunction('get_posts')->never();
    WP_Mock::userFunction('wp_insert_post')->once()->andReturn(7);

    $result = (new WpPostWriter())->upsert('member', new PostWrite(title: 'X'), []);

    expect($result->action)->toBe(UpsertAction::Created);
});

it('maps typed fields to wp_insert_post arguments', function () {
    WP_Mock::userFunction('get_posts')->andReturn([]);
    WP_Mock::userFunction('is_wp_error')->andReturn(false);
    WP_Mock::userFunction('wp_insert_post')->once()->andReturnUsing(function (array $arr): int {
        expect($arr['post_title'])->toBe('Acme')
            ->and($arr['post_date'])->toBe('2026-01-02 03:04:05')
            ->and($arr['menu_order'])->toBe(5)
            ->and($arr['post_type'])->toBe('member')
            ->and($arr['post_status'])->toBe('draft')
            ->and($arr)->not->toHaveKey('post_content');

        return 1;
    });

    $write = new PostWrite(
        title: 'Acme',
        date: new DateTimeImmutable('2026-01-02 03:04:05'),
        menuOrder: 5,
        insertStatus: 'draft',
    );

    (new WpPostWriter())->upsert('member', $write);
});

it('resolves a term selection to names and ids', function () {
    WP_Mock::userFunction('get_posts')->andReturn([]);
    WP_Mock::userFunction('is_wp_error')->andReturn(false);
    WP_Mock::userFunction('wp_insert_post')->andReturn(9);

    $captured = null;
    WP_Mock::userFunction('wp_set_object_terms')->once()->andReturnUsing(
        function (int $id, array $terms, string $taxonomy) use (&$captured): array {
            $captured = [$id, $terms, $taxonomy];

            return [1, 2];
        },
    );

    $write = new PostWrite(
        title: 'Acme',
        terms: ['sector' => new TermSelection(names: ['Bouw'], ids: [42])],
    );

    (new WpPostWriter())->upsert('member', $write);

    expect($captured)->toBe([9, ['Bouw', 42], 'sector']);
});
