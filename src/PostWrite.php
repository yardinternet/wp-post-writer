<?php

declare(strict_types=1);

namespace Yard\PostWriter;

final class PostWrite
{
    /**
     * @param array<string, mixed>         $meta  meta-key => value; null/'' clears the field
     * @param array<string, TermSelection> $terms taxonomy slug => TermSelection; empty selection detaches all terms of that taxonomy from the post
     */
    public function __construct(
        public string $title,
        public ?string $content = null,
        public ?string $excerpt = null,
        public ?string $slug = null,
        public ?\DateTimeInterface $date = null,
        public ?int $author = null,
        public ?int $parent = null,
        public ?int $menuOrder = null,
        public array $meta = [],
        public array $terms = [],
        public string $insertStatus = 'publish',
    ) {
    }
}
