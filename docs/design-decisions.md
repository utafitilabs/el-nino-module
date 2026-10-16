# Design decisions

Each deliberate modelling choice, why it was made, and the trigger that reopens
it.

## Contents

- [The producer pushes; the module computes nothing](#the-producer-pushes-the-module-computes-nothing)
- [A bulletin is a row, never edited](#a-bulletin-is-a-row-never-edited)
- [Exposure layers are keyed by provenance](#exposure-layers-are-keyed-by-provenance)
- [The token is the producer's whole authentication](#the-token-is-the-producers-whole-authentication)
- [The rain grid is drawn as cells on the plate](#the-rain-grid-is-drawn-as-cells-on-the-plate)
- [Week by week is an address, not a script](#week-by-week-is-an-address-not-a-script)
- [The area's record is the area's words](#the-areas-record-is-the-areas-words)
- [What is decided and not yet built](#what-is-decided-and-not-yet-built)

## The producer pushes; the module computes nothing

The science — the regex over NOAA's text, the recovery of IRI's percentages
from an SVG, the raster window over CHIRPS, the routing over OpenStreetMap —
stays in the producer, a Python build the organization runs weekly. The module
reads one contract (`el-nino-producer/1`) and stores what it says. **Why:** the
installations run one PHP container and a PostGIS database, with no Python and
no raster extension; the analysis is revised by its author, not by a deploy;
and a second producer for a second area is a second run of the same build, not
a second module. **Reopens** if a producer cannot run on a schedule anywhere, or
if two producers disagree about the contract: then the CPC and IRI parsers are
ported to PHP first, the raster window last.

## A bulletin is a row, never edited

Every push is a new `el_nino_bulletin` row keyed by the producer's build time;
the newest is what every page reads, the earlier ones stay. **Why:** a figure
true on a Monday is a fact about that Monday, and the producer revises
preliminary CHIRPS values later, so a page that edited in place would quietly
change its past. **Reopens** when an installation holds years of bulletins and
asks for a retention window.

## Exposure layers are keyed by provenance

The roads, destinations, susceptibility patches and bomas belong to an
`el_nino_exposure_run` named by the analysis's run date and commit. A push whose
provenance names the current run keeps the layers; a different run becomes
current and the superseded run drops its layers but keeps its provenance.
**Why:** the producer re-sends two megabytes of polygons every week whether or
not the analysis moved, and an old bulletin should still say which analysis it
was read against. **Reopens** if a producer stops sending provenance, in which
case the layers are replaced on every push.

## The token is the producer's whole authentication

The ingest door sits outside every firewall by the installation's own rule, and
a 32-byte token, kept as a SHA-256 hash and shown once, names the area. The
bulletin must also name that area's producer slug. **Why:** the producer is a
scheduled job, not a person with a position; the team's field token is the
precedent, and the crash ingest of the telemetry module is the precedent for an
open door with a limiter and a cap. **Reopens** if an installation wants the
producer to be a team account with a pair: then the door moves under `/api`
and the bearer becomes a field token.

## The rain grid is drawn as cells on the plate

A week's `grid_pct` becomes one square feature per cell with a band property,
styled by rule with plate tokens, on the atlas's own map. **Why:** every map in
the product is the atlas's plate, and a layer names a token rather than a
colour; a continuous ramp is not a token, so the five bands the settled page
reads a percent into are the five legend rows. The producer's PNG overlays are
not used. **Reopens** if the atlas gains a raster or ramp layer.

## Week by week is an address, not a script

The picked week is `?week=N` and the unit `?field=mm`; the select, the arrows,
the table rows and the range input all navigate to that address, and the page
is whole without JavaScript. The play button of the settled page is not
ported. **Why:** a week somebody can send as a link, nothing drawn twice, and
the atlas plate re-rendered by the server with the week's layer. **Reopens** if
a reader asks for the animation; then the plate's swap hook carries the week's
layer without a reload.

## The area's record is the area's words

The settled page carries a paragraph about what past El Niño seasons did on one
authority's ground. That paragraph is content, not code: it is a text the area
writes on its producer section and the page prints where written. **Why:** no
real organization, place or figure lives in a package. **Reopens** when the
producer's contract carries the record itself.

## What is decided and not yet built

- **The per-zone summary** the producer sends in `status.json → zones` is
  stored on the bulletin and not yet drawn; a zone figure provider is the seam
  it belongs on.
- **The bomas layer** is stored and counted in the attention items where the
  producer routed households; it is not yet drawn on the plate.
- **The narrated animation** is not served; it arrives as a static asset of the
  module once its final files exist.
- **Alerts** on a threshold rule are out of scope until the platform has an
  alert mechanism to feed.
