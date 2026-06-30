# yard/wp-post-writer

Write-spec based persistence for WordPress posts: **upsert**, **prune** and **bulk** indexing.

Generic and domain-agnostic: you describe *what* to write with a `PostWrite` value object, and
`WpPostWriter` persists it. Only the fields you list are touched — everything else on the post is
left intact.

## Requirements

**Advanced Custom Fields PRO**

## Installation

```bash
composer require yard/wp-post-writer
```

## Usage

### Upsert

Match an existing post by meta and update it, or insert a new one.

```php
use Yard\PostWriter\PostWrite;
use Yard\PostWriter\WpPostWriter;

$writer = new WpPostWriter();

$write = new PostWrite(
    core: [
        'post_title'   => 'Hello world',
        'post_content' => 'Body text',
    ],
    meta: [
        'external_id' => '42',          // null or '' clears the field
        'subtitle'    => 'A subtitle',
    ],
    terms: [
        'category' => ['News', 'Updates'],   // empty array clears the taxonomy
    ],
    matchMeta: [
        'external_id' => '42',          // used to find an existing post
    ],
    insertStatus: 'publish',            // status used only on insert
);

$postId = $writer->upsert('post', $write);
```

**Semantics**

- `core` — post fields applied on both insert and update (`post_title`, `post_content`, ...).
- `meta` — `meta-key => value`. A `null`/`''` value clears the field.
- `terms` — `taxonomy => term names`. An empty array clears the taxonomy.
- `matchMeta` — `meta-key => value` used to locate an existing post. Empty (or any empty value)
  forces an insert.
- `insertStatus` — post status applied only when inserting (default `publish`).

### Prune

Delete posts whose meta value is no longer in the set you want to keep. Posts without the meta key
(e.g. manually created) are never touched.

```php
$deleted = $writer->prune('post', 'external_id', keep: ['42', '43', '44']);
```

### Bulk

Run a batch of writes with FacetWP real-time indexing suspended, then reindex once at the end.

```php
$writer->bulk(function () use ($writer, $writes) {
    foreach ($writes as $write) {
        $writer->upsert('post', $write);
    }
});
```