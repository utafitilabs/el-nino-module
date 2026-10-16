<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi El Niño Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\ElNino\Model;

/**
 * A PRODUCER BULLETIN, READ AND CHECKED: what `status.json`, `series.json`
 * and the map files of the contract `el-nino-producer/1` say, as one value
 * the ingest stores. Built only by {@see \Uhifadhi\ElNino\Service\ProducerPayloadReader},
 * which is where every shape is checked, so nothing here needs to be.
 *
 * @phpstan-type Grid list<list<float|null>>
 * @phpstan-type FeatureCollection array{type: string, features: list<array<string, mixed>>}
 * @phpstan-type WeekRow array{label: string, start: \DateTimeImmutable, end: \DateTimeImmutable, days: int, mm: float, normal_mm: float, pct: float|null, wet_cells: int, hot: list<string>, nino_date: \DateTimeImmutable|null, nino_anomaly: float|null, grid_mm: Grid, grid_pct: Grid}
 */
final readonly class ProducerBulletin
{
    public const string CONTRACT = 'el-nino-producer/1';

    /**
     * @param array<string, mixed>      $producer        the `area` block: slug, name, …
     * @param array<string, mixed>      $enso
     * @param array<string, mixed>      $rain
     * @param list<string>              $cutOffInWetCell
     * @param array<string, mixed>|null $outlook
     * @param array<string, mixed>      $exposure
     * @param array<string, mixed>|null $notebookRun
     * @param array<string, mixed>|null $zones           the producer's per-zone summary, keyed by zone name
     * @param array<string, mixed>      $grid
     * @param Grid                      $recentGridMm
     * @param Grid                      $recentGridPct
     * @param list<WeekRow>             $weeks           oldest first
     * @param FeatureCollection|null    $roads
     * @param FeatureCollection|null    $destinations
     * @param FeatureCollection|null    $susceptibility
     * @param FeatureCollection|null    $bomas
     * @param array<string, mixed>|null $provenance
     */
    public function __construct(
        public string $contract,
        public string $producerSlug,
        public array $producer,
        public \DateTimeImmutable $builtAt,
        public array $enso,
        public array $rain,
        public int $wetThresholdPct,
        public array $cutOffInWetCell,
        public ?array $outlook,
        public array $exposure,
        public ?array $notebookRun,
        public ?array $zones,
        public array $grid,
        public array $recentGridMm,
        public array $recentGridPct,
        public array $weeks,
        public ?array $roads,
        public ?array $destinations,
        public ?array $susceptibility,
        public ?array $bomas,
        public ?array $provenance,
    ) {
    }

    /** Whether the bulletin carries exposure layers at all. */
    public function hasExposure(): bool
    {
        return null !== $this->roads || null !== $this->destinations || null !== $this->susceptibility || null !== $this->bomas;
    }

    public function provenanceRunDate(): ?\DateTimeImmutable
    {
        $date = $this->provenance['run_date'] ?? null;
        if (!\is_string($date)) {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return false === $parsed ? null : $parsed;
    }

    public function provenanceCommit(): ?string
    {
        $commit = $this->provenance['notebook_commit'] ?? null;

        return \is_string($commit) && '' !== $commit ? $commit : null;
    }
}
