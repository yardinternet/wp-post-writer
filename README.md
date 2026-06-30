# yard/wp-post-writer

Synchroniseer WordPress-posts vanuit een externe bron: **upsert** wat de bron levert en **verwijder**
posts die niet meer uit de bron komen. Bron-agnostisch — jij beschrijft *wat* te schrijven en *hoe*
een rij te identificeren; de package regelt de persistentie en de reconciliatie.

## Requirements

PHP 8.2+, WordPress, **Advanced Custom Fields PRO** (voor meta).

## Installatie

```bash
composer require yard/wp-post-writer
```

## Quickstart

```php
use Yard\PostWriter\PostWrite;
use Yard\PostWriter\TermSelection;
use Yard\PostWriter\UpsertResult;
use Yard\PostWriter\WpPostWriter;

$report = WpPostWriter::sync('member')
    ->from($this->fetchRows())                                   // iterable/generator
    ->filter(fn (array $row): bool => 'active' === $row['status'])
    ->identify(fn (array $row): string => (string) $row['id'], 'external_id')
    ->write(fn (array $row): PostWrite => new PostWrite(
        title: $row['name'],
        meta: ['external_id' => (string) $row['id']],
        terms: ['sector' => TermSelection::names($row['sectors'])],
    ))
    ->onWritten(fn (UpsertResult $r) => printf("%s %d\n", $r->action->value, $r->id))
    ->onSkip(fn (array $row, \Throwable $e) => printf("skip %s: %s\n", $row['id'], $e->getMessage()))
    ->prune()
    ->run();

echo $report->summary();
```

## Opties (builder)

- `from(iterable $items)` — de bron. Lazy generators aanbevolen (zie *Generator & yield*).
- `filter(callable $fn): bool` — `false` laat de rij volledig vallen; die rij is dáármee vatbaar
  voor prune (hij hoort niet bij de collectie).
- `identify(callable $fn, string $metaKey)` — identiteit per rij. Levert de match voor upsert én de
  keep-waarde voor prune. Draait vóór `write()`, zodat een rij die in `write()` faalt tóch tegen
  prune beschermd blijft.
- `write(callable $fn): PostWrite` — map de rij naar een `PostWrite`. Een `Throwable` = skip.
- `onWritten(callable(UpsertResult))`, `onSkip(callable($item, \Throwable))`,
  `onPruned(callable(int $id))` — live callbacks.
- `failFast()` — de eerste fout borrelt op uit `run()`; prune draait dan niet (niets wordt verwijderd).
- `prune()` — verwijder posts die niet zijn gezien. Lege keep-set → prune overgeslagen + waarschuwing.
- `dryRun()` — niets schrijven; het report toont wat er zóú gebeuren. `onSkip` vuurt nog (validatie),
  `onWritten`/`onPruned` niet.
- `run(): SyncReport` — draait alles binnen `bulk()` (indexers/tellers opgeschort).

### `PostWrite`

```php
new PostWrite(
    title: 'Acme',
    content: null, excerpt: null, slug: null,
    date: new DateTimeImmutable('2026-01-02 03:04:05'),  // ?DateTimeInterface; site-lokale tijd
    author: null, parent: null, menuOrder: null,         // null = veld niet aanraken
    meta: ['external_id' => '42'],                        // null/'' wist een veld
    terms: ['sector' => TermSelection::names(['Bouw'])],
    insertStatus: 'publish',                              // alleen bij insert; update raakt status niet
);
```

`menuOrder` en alle `?`-velden: niet meegeven = niet aanraken (redacteur behoudt z'n waarde).
`insertStatus` geldt alleen bij aanmaken; een herhaalde sync overschrijft een handmatige status niet.

### `TermSelection`

```php
TermSelection::names(['Bouw', 'Infra']);          // namen; aangemaakt indien afwezig
TermSelection::ids([42, 43]);                      // bestaande term-id's
new TermSelection(names: ['Noord'], ids: [42]);    // gemengd
new TermSelection();                               // leeg → ontkoppelt alle termen van die taxonomie van de post
```

Een taxonomie die je niet in `terms` opneemt, blijft ongemoeid; aanwezig-maar-leeg ontkoppelt de
termen van die taxonomie van de post (de taxonomie en termen zelf blijven bestaan).

### `SyncReport` / `UpsertResult`

```php
$report->created; $report->updated; $report->skipped; $report->filtered; $report->pruned;
$report->summary();   // "Aangemaakt: .., bijgewerkt: .., overgeslagen: .., gefilterd: .., verwijderd: .."

$result->id; $result->action;   // UpsertAction::Created | UpsertAction::Updated
```

## Low-level API

Zonder de builder, voor losse schrijfacties:

```php
$writer = new WpPostWriter();
$result = $writer->upsert('member', $write, ['external_id' => '42']);   // UpsertResult
$exists = $writer->find('member', ['external_id' => '42']);             // ?int
$deleted = $writer->prune('member', 'external_id', keep: ['42', '43']); // list<int>
$writer->bulk(fn () => /* meerdere writes met indexers opgeschort */);
```

## Wat in de site blijft (tips)

- **De bron** (paginatie, auth) en **de mapping** naar `PostWrite` blijven van jou.
- Filter bij voorkeur in de bron (server-side); gebruik `filter()` alleen als dat niet kan.
- **Uitgelichte afbeelding**: doe de sideload in `write()` (`media_handle_sideload`) en zet de
  resulterende attachment-id als `_thumbnail_id`-meta. De package blijft zo bron-agnostisch.
- Draai eerst een `dryRun()` en maak een DB-backup vóór een productie-sync.

## Generator & `yield`

`from()` itereert de bron **precies één keer** en bouwt de keep-set in diezelfde pass op; hij
materialiseert de bron **nooit** tot een array. Gebruik daarom een lazy generator, zodat het geheugen
vlak blijft ongeacht de datasetgrootte:

```php
private function fetchRows(): \Generator
{
    $skip = 0;
    do {
        $rows = $this->api->page(take: 100, skip: $skip);
        yield from $rows;            // één pagina tegelijk in geheugen
        $skip += 100;
    } while (count($rows) === 100);
}
```

Gotcha's:
- Een generator is **eenmalig** itereerbaar — de package doet dat; itereer 'm niet zelf nogmaals.
- Gebruik `yield from` per pagina; verzamel niet alles in een array.
- Doe geen `iterator_to_array()` op de bron — dat verslaat het doel.
