# yard/wp-post-writer

Synchroniseer WordPress-posts vanuit een externe bron: **maak aan of werk bij** wat de bron levert,
en **verwijder** posts die niet meer in de bron staan. Bron-agnostisch — jij beschrijft *wat* je
schrijft en *hoe* je een rij herkent; de package regelt het opslaan en het opruimen.

## Requirements

PHP 8.2+, WordPress, **Advanced Custom Fields PRO** (voor meta).

## Installatie

```bash
composer require yard/wp-post-writer
```

## Quickstart

```php
use Yard\PostWriter\PostWrite;
use Yard\PostWriter\PruneMode;
use Yard\PostWriter\TermSelection;
use Yard\PostWriter\UpsertResult;
use Yard\PostWriter\WpPostWriter;

$report = WpPostWriter::sync('member')
    ->from($this->fetchRows())                                    // iterable of generator
    ->filter(fn (array $row): bool => 'active' === $row['status'])
    ->identify(fn (array $row): string => (string) $row['id'], 'external_id')
    ->write(fn (array $row, ?int $existingId): PostWrite => new PostWrite(
        title: $row['name'],
        terms: ['sector' => TermSelection::names($row['sectors'])],
    ))
    ->onWritten(fn (UpsertResult $r, array $row) => printf("%s %d (%s)\n", $r->action->value, $r->id, $row['id']))
    ->onSkip(fn (array $row, \Throwable $e) => printf("skip %s: %s\n", $row['id'], $e->getMessage()))
    ->prune(PruneMode::Draft)
    ->run();

echo $report->summary();
```

## Opties (builder)

- `from(iterable $items)` — de bron. Gebruik bij voorkeur een lazy generator (zie *Generator & yield*).
- `filter(callable $fn): bool` — `false` laat de rij vallen. Zo'n rij hoort niet bij de collectie en
  wordt daarom ook door `prune()` verwijderd.
- `identify(callable $fn, string $metaKey)` — de identiteit per rij. Bepaalt welke post `write()`
  bijwerkt en welke posts `prune()` behoudt. Draait vóór `write()`, zodat een rij die in `write()`
  faalt niet per ongeluk wordt verwijderd. De package schrijft de identity-meta zelf op de post —
  zet 'm niet ook in `meta` van de `PostWrite` (een mapper-waarde wordt overschreven door de
  identify-waarde).
- `write(callable $fn): PostWrite` — zet de rij om naar een `PostWrite`; signatuur
  `fn (mixed $item, ?int $existingId): PostWrite`. Het tweede argument is de al-gevonden bestaande
  post-ID (`null` = nieuwe post) — doe geen eigen lookup in de mapper. Gooi een `Throwable` om de
  rij over te slaan.
- `onWritten(callable(UpsertResult, mixed $item = null))`, `onSkip(callable($item, \Throwable))`,
  `onPruned(callable(int $id))`, `onFiltered(callable($item))` — callbacks per verwerkte rij.
  `onWritten` mag de bron-rij als optioneel tweede argument opnemen; bestaande callbacks met alleen
  `UpsertResult` blijven werken.
- `failFast()` — de eerste fout stopt de run meteen; `prune()` draait dan niet (er wordt niets verwijderd).
- `prune(PruneMode $mode = PruneMode::Delete)` — ruim posts op die niet in de bron voorkwamen. Is er
  geen enkele rij gezien, dan slaat prune over met een waarschuwing. Drie modes:
  - `PruneMode::Delete` (standaard) — verwijdert de post definitief.
  - `PruneMode::Trash` — verplaatst de post naar de prullenbak.
  - `PruneMode::Draft` — zet de post op status `draft`. Posts die al `draft` zijn, worden niet
    opnieuw aangeboden: ze tellen niet mee in `prunable()`/`prune()` en vuren dus geen `onPruned()`
    en geen extra write, ook niet bij herhaalde runs.
- `dryRun()` — schrijf niets; het report toont wat er zóú gebeuren. Alle callbacks vuren óók in dry-run.
  Bij een nog-aan-te-maken post is `UpsertResult->id` nog `0` en is `action` gelijk aan `Created`.
  Wil je die logregels als dry-run markeren, prefix ze dan zelf.
- `run(): SyncReport` — draait alles binnen `bulk()` (indexers en tellers tijdelijk uit).

### `PostWrite`

```php
new PostWrite(
    title: 'Acme',
    content: null, excerpt: null, slug: null,
    date: new DateTimeImmutable('2026-01-02 03:04:05'),   // ?DateTimeInterface; site-lokale tijd
    author: null, parent: null, menuOrder: null,          // null = veld niet aanraken
    meta: ['website' => 'https://acme.example'],          // null of '' wist een veld; de identity-meta zet de package zelf
    terms: ['sector' => TermSelection::names(['Bouw'])],
    insertStatus: 'publish',                              // alleen bij insert; update raakt status niet
);
```

`menuOrder` en alle `?`-velden: niet meegeven = niet aanraken (de redacteur behoudt z'n waarde).
`insertStatus` geldt alleen bij aanmaken; een herhaalde sync overschrijft een handmatige status niet.

### `TermSelection`

```php
TermSelection::names(['Bouw', 'Infra']);          // namen; maakt ontbrekende aan
TermSelection::ids([42, 43]);                     // bestaande term-id's
new TermSelection(names: ['Noord'], ids: [42]);   // gemengd
new TermSelection();                              // leeg → ontkoppelt de termen van die taxonomie
```

Een taxonomie die je niet in `terms` opneemt, blijft ongemoeid. Een taxonomie die je meegeeft maar
leeg laat, ontkoppelt de termen van die taxonomie van de post (de taxonomie en de termen zelf blijven
bestaan).

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
$exists = $writer->find('member', ['external_id' => '42']);             // ?int
$result = $writer->upsert('member', $write, $exists);                   // UpsertResult; null = insert
$deleted = $writer->prune('member', 'external_id', keep: ['42', '43'], mode: PruneMode::Draft); // list<int>
$writer->bulk(fn () => /* meerdere writes met indexers opgeschort */);
```

## Wat in de site blijft (tips)

- **De bron** (paginatie, auth) en **de mapping** naar `PostWrite` blijven van jou.
- Filter bij voorkeur al in de bron (server-side); gebruik `filter()` alleen als dat niet kan.
- **Uitgelichte afbeelding**: doe de sideload in `write()` (`media_handle_sideload`) en zet de
  resulterende attachment-id als `_thumbnail_id`-meta. Zo blijft de package bron-agnostisch.
- Draai eerst een `dryRun()` en maak een DB-backup vóór een productie-sync.

## Generator & `yield`

`from()` doorloopt de bron **precies één keer** en bouwt tijdens diezelfde doorloop de lijst van
te-behouden posts op; de hele bron wordt **nooit** als array in het geheugen geladen. Gebruik daarom
een lazy generator, dan blijft het geheugengebruik gelijk ongeacht de grootte van de dataset:

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
- Doe geen `iterator_to_array()` op de bron — dat haalt het voordeel onderuit.
