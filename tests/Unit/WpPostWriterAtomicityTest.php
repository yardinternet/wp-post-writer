<?php

declare(strict_types=1);

use Yard\PostWriter\PostWrite;
use Yard\PostWriter\TermSelection;
use Yard\PostWriter\WpPostWriter;

it('writes identity meta before other meta', function () {
    WP_Mock::userFunction('get_posts')->andReturn([]);
    WP_Mock::userFunction('is_wp_error')->andReturn(false);
    WP_Mock::userFunction('wp_insert_post')->andReturn(9);

    $order = [];
    WP_Mock::userFunction('update_field')->andReturnUsing(function (string $key) use (&$order): bool {
        $order[] = $key;

        return true;
    });

    $write = new PostWrite(title: 'Acme', meta: ['name' => 'Acme', 'external_id' => '42']);
    (new WpPostWriter())->upsert('member', $write, ['external_id' => '42']);

    expect($order[0])->toBe('external_id');
});

it('throws when a meta write fails', function () {
    WP_Mock::userFunction('get_posts')->andReturn([]);
    WP_Mock::userFunction('is_wp_error')->andReturn(false);
    WP_Mock::userFunction('wp_insert_post')->andReturn(9);
    WP_Mock::userFunction('wp_delete_post')->andReturn(true);
    WP_Mock::userFunction('update_field')->andReturn(false);

    $write = new PostWrite(title: 'Acme', meta: ['external_id' => '42']);

    expect(fn () => (new WpPostWriter())->upsert('member', $write, ['external_id' => '42']))
        ->toThrow(RuntimeException::class);
});

it('deletes the inserted post when identity meta fails', function () {
    WP_Mock::userFunction('get_posts')->andReturn([]);
    WP_Mock::userFunction('is_wp_error')->andReturn(false);
    WP_Mock::userFunction('wp_insert_post')->andReturn(9);
    WP_Mock::userFunction('update_field')->andReturn(false);
    WP_Mock::userFunction('wp_delete_post')->once()->with(9, true)->andReturn(true);

    $write = new PostWrite(title: 'Acme', meta: ['external_id' => '42']);

    expect(fn () => (new WpPostWriter())->upsert('member', $write, ['external_id' => '42']))
        ->toThrow(RuntimeException::class);
});

it('throws when term assignment returns a wp error', function () {
    WP_Mock::userFunction('get_posts')->andReturn([]);
    WP_Mock::userFunction('wp_insert_post')->andReturn(9);
    WP_Mock::userFunction('update_field')->andReturn(true);

    $wpError = new class {
        public function get_error_message(): string
        {
            return 'invalid taxonomy';
        }
    };
    WP_Mock::userFunction('wp_set_object_terms')->andReturn($wpError);
    WP_Mock::userFunction('is_wp_error')->andReturnUsing(fn ($value): bool => is_object($value));

    $write = new PostWrite(title: 'Acme', terms: ['sector' => TermSelection::names(['Bouw'])]);

    expect(fn () => (new WpPostWriter())->upsert('member', $write))
        ->toThrow(RuntimeException::class, 'invalid taxonomy');
});
