# uhifadhi/el-nino-module

**The El Niño watch.** Four questions, answered every week for an area from a
bulletin a producer builds: how warm the Pacific is and how strong the El Niño;
whether the models keep it through the short rains; how wet the last weeks were
over the area against their normal; and which roads and places lose access if
it floods. A module of the [uhifadhi](https://github.com/utafitilabs/uhifadhi)
platform, installed per installation and switched on per area.

## Contents

- [What it is](#what-it-is)
- [Installation](#installation)
- [The producer](#the-producer)
- [Configuration](#configuration)
- [What it contributes](#what-it-contributes)
- [Development](#development)
- [Docs](#docs)
- [License](#license)

## What it is

The module keeps no science of its own. A **producer** — a weekly build the
organization runs, from NOAA CPC's Niño 3.4 feed, IRI's ENSO forecast, CHIRPS
daily rainfall and a flood-exposure analysis of the area — writes a set of files
under the contract `el-nino-producer/1` and posts them to the installation. The
module stores each bulletin, keeps the exposure layers in PostGIS, and draws:

- the **overview**: the four tiles, the fact band, the background, the Pacific
  and the outlook charts, the rain window, the exposure map with the
  susceptibility classes, the flagged roads, the cut-off destinations and this
  window's wet cells, the sources, and the hint saying what the page is not;
- **week by week**: the Pacific and the rain over the bulletin's weeks, each
  week's rain grid on the plate, its caption and the table of weeks;
- on the **area overview**: four now-tiles, one attention item per cut-off
  destination sitting in a much-wetter-than-normal cell, and four map layers.

A bulletin is never edited: a rebuild arrives as a new row, and the newest is
what every page reads. The susceptibility is a relative ranking inside the
area, never a flood depth or a forecast; the page says so at its foot.

## Installation

```bash
composer require uhifadhi/el-nino-module
php bin/console cache:clear --no-warmup
php bin/console doctrine:migrations:migrate
php bin/console registry:sync
php bin/console cache:warmup
php bin/console asset-map:compile      # deploy step
```

Flex registers `Uhifadhi\ElNino\UhifadhiElNinoBundle`, copies
`config/packages/el_nino.yaml` and `config/routes/el_nino.yaml`, and enables
the `weeks` controller in `assets/controllers.json`. The module ships its own
migrations (five tables, `el_nino_*`); `doctrine:migrations:diff` afterwards
must report no changes.

**One line of the installation's own security rule.** The ingest door at
`/el-nino/ingest` is where the producer posts, and a producer is not a person:
its bearer token is the whole of its authentication. Open the address above the
catch-all in `config/packages/security.yaml`:

```yaml
    access_control:
        - { path: ^/el-nino/ingest$, roles: PUBLIC_ACCESS }
        # …
        - { path: ^/, roles: ROLE_USER }
```

Then, in each area that runs the module: switch it on in the area's Modules
tab, open **Configure › Producer**, name the producer's slug and issue a token.

## The producer

One `POST` per build to `/el-nino/ingest`, `multipart/form-data`, one part per
file under its contract path, the token in the `Authorization` header and the
contract in `X-El-Nino-Contract`:

| Part | Required | Content |
|---|---|---|
| `status.json` | yes | the Pacific this week, the rain window, the outlook table, the exposure counts, the cut-off destinations in a wet cell |
| `series.json` | yes | the grid definition, the 30-day window on it, and every week's rain grids, wet cells, hot destinations and Niño 3.4 reading |
| `map/roads.geojson` | no | the flagged road segments, `cls` = `cut` or `risk` |
| `map/destinations.geojson` | no | the destinations with `status` and `population_2020` |
| `map/susceptibility.geojson` | no | the susceptibility classes as polygons, `class` 1–4 |
| `map/bomas.geojson` | no | routed households with `zone`, `village`, `status` |
| `map/provenance.json` | no | the analysis run: `run_date`, `notebook_commit`, the weights and rules |

Every file carries `contract`, `area` (with the `slug` the area's producer is
named by) and `built_utc`. The exposure parts are imported only when their
provenance names a run the area has not seen; the weekly figures are stored
every time. The same build pushed twice is accepted once and answers
`200 {"duplicate": true}`; a stored bulletin answers `202`; a refused push is a
`4xx` with `error` and `message` naming the part and the fault; a store failure
is `503`, the one answer worth retrying. Each part may be at most
`ingest_body_cap` bytes (16 MiB by default); the limiter admits thirty pushes a
minute per address.

```bash
curl -sS -X POST https://example.test/el-nino/ingest \
  -H "Authorization: Bearer $EL_NINO_TOKEN" \
  -H "X-El-Nino-Contract: el-nino-producer/1" \
  -F status.json=@site/status.json -F series.json=@site/series.json \
  -F map/roads.geojson=@site/map/roads.geojson \
  -F map/destinations.geojson=@site/map/destinations.geojson \
  -F map/susceptibility.geojson=@site/map/susceptibility.geojson \
  -F map/provenance.json=@site/map/provenance.json
```

For development, `el-nino:ingest <area-uuid> <directory>` (a devkit command)
reads the same files from disk through the same reader and the same ingest.

## Configuration

```yaml
# config/packages/el_nino.yaml
el_nino:
    module_category: pressure      # where the tile is filed in each area's catalogue
    ingest_body_cap: 16777216      # bytes per part
    weeks_shown: 26                # weeks the week-by-week tab walks through
```

Everything an **area** decides is on its configure page, never here: the
producer's slug, the token, a link to the analysis, and the area's own record
of past El Niño seasons in its own words.

## What it contributes

One concern, `el-nino`, with `read` and `configure`, declared through
`ConcernSourceInterface`; every route names the pair it enforces. Two tabs
through `ModuleTabsInterface`, one configure section through
`ConfigurationSectionsInterface`, the now-tiles, the attention items and the
map layers through the area overview's seams, and two devkit providers: seed
content (a made-up bulletin for every seed area with a boundary) and the
`el-nino:ingest` command.

## Development

```bash
composer install
composer check        # cs:check + phpstan max + phpunit on real PostGIS (port 5434)
```

The suite needs the database named in `phpunit.dist.xml`
(`el_nino_bundle_test`, with the PostGIS extension). In an installation, link
the checkout with `fundi bundle:link uhifadhi/el-nino-module`, then rebuild:
`cache:clear --no-warmup`, migrate, `registry:sync`, `cache:warmup`,
`asset-map:compile`.

## Docs

- [docs/design-decisions.md](docs/design-decisions.md) — the modelling choices, why, and what reopens each.

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).
