# wp-post-writer — implementatie-playbook

Synchroniseer posts vanuit een externe bron naar WordPress: upsert wat de bron levert, verwijder wat
verdwenen is. Gebruik dit wanneer een site posts moet importeren/reconciliëren vanuit een API, CSV of
andere externe bron. Volledige optie-referentie: zie `README.md`.

## Skelet (kopieer en pas aan)

```php
final class SomeImport extends Command
{
    public function handle(): int
    {
        $report = WpPostWriter::sync('member')
            ->from($this->fetchRows())
            ->identify(fn (array $row): string => (string) ($row['id'] ?? ''), 'external_id')
            ->write(fn (array $row, ?int $existingId): PostWrite => $this->toWrite($row, $existingId))
            ->onWritten(fn (UpsertResult $r) => $this->info(ucfirst($r->action->value) . " {$r->id}"))
            ->onSkip(fn (array $row, \Throwable $e) => $this->warn("skip {$row['id']}: {$e->getMessage()}"))
            ->onFiltered(fn (array $row) => $this->line("filtered {$row['id']}"))
            ->prune()
            ->run();

        $this->info($report->summary());

        return self::SUCCESS;
    }

    private function fetchRows(): \Generator
    {
        $skip = 0;
        do {
            $rows = $this->api->page(take: 100, skip: $skip);
            yield from $rows;
            $skip += 100;
        } while (count($rows) === 100);
    }

    private function toWrite(array $row, ?int $existingId): PostWrite
    {
        // validate here; throw to skip the row
        return new PostWrite(
            title: $row['name'],
            terms: ['sector' => TermSelection::names($row['sectors'])],
        );
    }
}
```

## Beslis-checklist

- **Prune nodig?** Reconcilieer je de hele collectie (verdwenen bron-items moeten weg) → `->prune()`.
  Alleen toevoegen/bijwerken → laat prune weg.
- **`filter()` nodig?** Alleen als de bron niet server-side gefilterd kan worden. Anders: filter in
  de bron.
- **`failFast()`?** Is een rijfout een signaal dat de hele run moet stoppen → `->failFast()`. Is het
  ruis (af en toe een rare rij) → laat weg (default skip).
- **`dryRun()`?** Test eerst tegen productie-achtige data; draai met `->dryRun()` en lees het report.
  Alle callbacks (`onWritten`/`onSkip`/`onPruned`/`onFiltered`) vuren óók in dry-run; wil je die
  logregels markeren, prefix ze dan zelf (de command weet of het een dry-run is). Bij een dry-run
  create is `UpsertResult->id` nog `0`.

## Harde regels (do/don't)

- `identify()` levert de identiteit en draait vóór `write()` — zo blijft een gefaalde rij beschermd
  tegen prune. De mapper zet **geen** `matchMeta` (bestaat niet meer op `PostWrite`).
- `write()` krijgt als tweede argument de al-gevonden bestaande post-ID (`null` = nieuwe post). Doe
  in de mapper **geen** eigen `find()`/`get_posts`-lookup — de package heeft die al gedaan.
- De mapper zet de identity-meta **niet** zelf in `meta` — `identify()` is de enige bron; de package
  merget 'm in `PostWrite->meta` en overschrijft een eventuele mapper-waarde.
- `filter()` → `false` = "hoort er niet bij" (vatbaar voor prune). Een `Throwable` uit `write()` =
  skip ("hoort erbij, kon nu niet"). Verwar deze twee niet.
- De bron is een **generator die `yield`t**; nooit naar een array, nooit dubbel itereren, geen
  `iterator_to_array()`.
- `terms` altijd via `TermSelection` (`::names()` / `::ids()` / leeg = ontkoppelen). Geen kale arrays.
- `postType` staat op `sync()` — één collectie per sync. Geen per-rij post-type.
- `run()` wikkelt `bulk()` zelf — roep `bulk()` niet apart aan binnen een sync.
- Uitgelichte afbeelding: sideload in `write()`, attachment-id als `_thumbnail_id`-meta.
