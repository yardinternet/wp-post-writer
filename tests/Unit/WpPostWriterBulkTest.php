<?php

declare(strict_types=1);

use Yard\PostWriter\WpPostWriter;

it('returns the callback result', function () {
    WP_Mock::userFunction('add_filter')->andReturn(true);
    WP_Mock::userFunction('remove_filter')->andReturn(true);
    WP_Mock::userFunction('wp_defer_term_counting')->andReturn(null);
    WP_Mock::userFunction('wp_defer_comment_counting')->andReturn(null);
    WP_Mock::userFunction('wp_suspend_cache_invalidation')->andReturn(null);

    expect((new WpPostWriter())->bulk(fn (): string => 'done'))->toBe('done');
});

it('defers and restores term counting around the callback', function () {
    WP_Mock::userFunction('add_filter')->andReturn(true);
    WP_Mock::userFunction('remove_filter')->andReturn(true);
    WP_Mock::userFunction('wp_defer_comment_counting')->andReturn(null);
    WP_Mock::userFunction('wp_suspend_cache_invalidation')->andReturn(null);

    $calls = [];
    WP_Mock::userFunction('wp_defer_term_counting')->andReturnUsing(function (bool $defer) use (&$calls): void {
        $calls[] = $defer;
    });

    (new WpPostWriter())->bulk(function () use (&$calls): void {
        $calls[] = 'callback';
    });

    expect($calls)->toBe([true, 'callback', false]);
});

it('restores state even when the callback throws', function () {
    WP_Mock::userFunction('add_filter')->andReturn(true);
    WP_Mock::userFunction('remove_filter')->andReturn(true);
    WP_Mock::userFunction('wp_defer_term_counting')->andReturn(null);
    WP_Mock::userFunction('wp_defer_comment_counting')->andReturn(null);

    $restored = false;
    WP_Mock::userFunction('wp_suspend_cache_invalidation')->andReturnUsing(function (bool $suspend) use (&$restored): void {
        if (false === $suspend) {
            $restored = true;
        }
    });

    try {
        (new WpPostWriter())->bulk(function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect($restored)->toBeTrue();
});
