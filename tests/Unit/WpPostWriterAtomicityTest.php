<?php

declare(strict_types=1);

use Yard\PostWriter\PostWrite;
use Yard\PostWriter\TermSelection;
use Yard\PostWriter\WpPostWriter;

it('throws when term assignment returns a wp error', function () {
    WP_Mock::userFunction('wp_insert_post')->andReturn(9);
    WP_Mock::userFunction('update_field')->andReturn(true);

    $wpError = new class {
        public function get_error_message(): string
        {
            return 'invalid taxonomy';
        }
    };
    WP_Mock::userFunction('wp_set_object_terms')->andReturn($wpError);
    WP_Mock::userFunction('is_wp_error')->andReturnUsing(fn ($value): bool => is_object($value) && method_exists($value, 'get_error_message'));

    $write = new PostWrite(title: 'Acme', terms: ['sector' => TermSelection::names(['Bouw'])]);

    expect(fn () => (new WpPostWriter())->upsert('member', $write))
        ->toThrow(RuntimeException::class, 'invalid taxonomy');
});
