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

namespace Uhifadhi\ElNino\Service;

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\ElNino\Entity\AreaWatch;
use Uhifadhi\ElNino\Entity\Boma;
use Uhifadhi\ElNino\Entity\Bulletin;
use Uhifadhi\ElNino\Entity\Destination;
use Uhifadhi\ElNino\Entity\ExposureRun;
use Uhifadhi\ElNino\Entity\Road;
use Uhifadhi\ElNino\Entity\SusceptibilityPatch;
use Uhifadhi\ElNino\Entity\Week;
use Uhifadhi\ElNino\Model\IngestReport;
use Uhifadhi\ElNino\Model\PayloadException;
use Uhifadhi\ElNino\Model\ProducerBulletin;
use Uhifadhi\ElNino\Repository\BulletinRepository;
use Uhifadhi\ElNino\Repository\ExposureRunRepository;

/**
 * STORES A PRODUCER BULLETIN FOR THE AREA A WATCH BINDS IT TO.
 *
 * Idempotent on the build time: a bulletin sent twice is accepted once, and
 * the second arrival answers `duplicate`. The exposure layers are imported
 * only when their provenance — run date and analysis commit — is not the
 * current run's, so a weekly re-send of two megabytes of polygons costs
 * nothing when the analysis has not moved.
 */
final class BulletinIngestService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BulletinRepository $bulletins,
        private readonly ExposureRunRepository $runs,
    ) {
    }

    public function ingest(AreaWatch $watch, ProducerBulletin $payload, ?\DateTimeImmutable $now = null): IngestReport
    {
        $now ??= new \DateTimeImmutable();
        $slug = $watch->getProducerSlug();
        if (null === $slug) {
            throw PayloadException::part('watch', 'names no producer for this area yet');
        }
        if ($slug !== $payload->producerSlug) {
            throw PayloadException::part('status', \sprintf('is about "%s"; this area is fed by "%s"', $payload->producerSlug, $slug));
        }

        $area = $watch->getArea();
        $existing = $this->bulletins->findOneByAreaAndBuiltAt($area, $payload->builtAt);
        $exposure = $this->importExposure($watch, $payload, $now);

        if (null !== $existing) {
            if (IngestReport::EXPOSURE_KEPT !== $exposure) {
                $watch->markIngested($now);
                $this->entityManager->flush();
            }

            return new IngestReport(true, $payload->builtAt, \count($existing->getWeeks()), $exposure);
        }

        $bulletin = new Bulletin($area, $payload->builtAt, $payload->contract, $payload->producer, $now);
        $enso = $payload->enso;
        $bulletin->setEnso(
            self::date($enso['week'] ?? null),
            self::float($enso['sst'] ?? null),
            self::float($enso['anomaly'] ?? null),
            self::string($enso['state'] ?? null),
            self::string($enso['phase'] ?? null) ?? 'neutral',
            self::string($enso['strength'] ?? null) ?? 'neutral',
            self::int($enso['weeks_in_range'] ?? 0),
        );
        $rain = $payload->rain;
        $bulletin->setRain(
            self::date($rain['start'] ?? null),
            self::date($rain['end'] ?? null),
            self::int($rain['days'] ?? 0),
            self::strings($rain['missing'] ?? []),
            self::float($rain['nca_total_mm'] ?? ($rain['total_mm'] ?? null)),
            self::float($rain['nca_normal_mm'] ?? ($rain['normal_mm'] ?? null)),
            self::float($rain['nca_pct'] ?? ($rain['pct'] ?? null)),
        );
        $bulletin
            ->setWetThresholdPct($payload->wetThresholdPct)
            ->setCutOffInWetCell($payload->cutOffInWetCell)
            ->setOutlook($payload->outlook)
            ->setExposure($payload->exposure)
            ->setNotebookRun($payload->notebookRun)
            ->setZones($payload->zones)
            ->setGrid($payload->grid, $payload->recentGridMm, $payload->recentGridPct);

        foreach ($payload->weeks as $row) {
            new Week(
                $bulletin,
                $row['label'],
                $row['start'],
                $row['end'],
                $row['days'],
                $row['mm'],
                $row['normal_mm'],
                $row['pct'],
                $row['wet_cells'],
                $row['hot'],
                $row['nino_date'],
                $row['nino_anomaly'],
                $row['grid_mm'],
                $row['grid_pct'],
            );
        }

        $this->entityManager->persist($bulletin);
        $watch->markIngested($now);
        $this->entityManager->flush();

        return new IngestReport(false, $payload->builtAt, \count($payload->weeks), $exposure);
    }

    /**
     * @return IngestReport::EXPOSURE_*
     */
    private function importExposure(AreaWatch $watch, ProducerBulletin $payload, \DateTimeImmutable $now): string
    {
        if (!$payload->hasExposure()) {
            return IngestReport::EXPOSURE_NONE;
        }
        $area = $watch->getArea();
        $runDate = $payload->provenanceRunDate();
        $commit = $payload->provenanceCommit();
        $current = $this->runs->findCurrentForArea($area);
        if (null !== $current && $current->matches($runDate, $commit)) {
            return IngestReport::EXPOSURE_KEPT;
        }

        $run = new ExposureRun($area, $runDate, $commit, $payload->provenance ?? [], $now);
        foreach ($payload->roads['features'] ?? [] as $feature) {
            $geometry = self::geometry($feature, 'LineString');
            if (null === $geometry) {
                continue;
            }
            $properties = self::properties($feature);
            new Road(
                $run,
                $geometry,
                self::string($properties['cls'] ?? null) ?? Road::RISK,
                self::string($properties['highway'] ?? null),
                self::intOrNull($properties['run_very_high_m'] ?? null),
            );
        }
        foreach ($payload->destinations['features'] ?? [] as $feature) {
            $geometry = self::geometry($feature, 'Point');
            if (null === $geometry) {
                continue;
            }
            $properties = self::properties($feature);
            $name = self::string($properties['name'] ?? null);
            if (null === $name) {
                continue;
            }
            new Destination(
                $run,
                $geometry,
                mb_substr($name, 0, 160),
                self::string($properties['kind'] ?? null),
                self::string($properties['status'] ?? null) ?? 'unchanged',
                self::intOrNull($properties['population_2020'] ?? ($properties['population'] ?? null)),
            );
        }
        foreach ($payload->susceptibility['features'] ?? [] as $feature) {
            $geometry = self::geometry($feature, 'Polygon');
            if (null === $geometry) {
                continue;
            }
            $properties = self::properties($feature);
            new SusceptibilityPatch(
                $run,
                $geometry,
                self::int($properties['class'] ?? 1),
                self::string($properties['name'] ?? null) ?? 'low',
            );
        }

        foreach ($payload->bomas['features'] ?? [] as $feature) {
            $geometry = self::geometry($feature, 'Point');
            if (null === $geometry) {
                continue;
            }
            $properties = self::properties($feature);
            new Boma(
                $run,
                $geometry,
                self::string($properties['zone'] ?? null),
                self::string($properties['village'] ?? null),
                self::string($properties['status'] ?? null) ?? 'unchanged',
                self::float($properties['min_normal'] ?? null),
                self::float($properties['min_flooded'] ?? null),
            );
        }

        $current?->supersede();
        $this->entityManager->persist($run);

        return IngestReport::EXPOSURE_IMPORTED;
    }

    /**
     * @param array<string, mixed> $feature
     */
    private static function geometry(array $feature, string $type): ?string
    {
        $geometry = $feature['geometry'] ?? null;
        if (!\is_array($geometry) || ($geometry['type'] ?? null) !== $type || !\is_array($geometry['coordinates'] ?? null)) {
            return null;
        }

        return json_encode(['type' => $type, 'coordinates' => $geometry['coordinates']], \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $feature
     *
     * @return array<string, mixed>
     */
    private static function properties(array $feature): array
    {
        $properties = $feature['properties'] ?? [];
        if (!\is_array($properties)) {
            return [];
        }
        $out = [];
        foreach ($properties as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10), new \DateTimeZone('UTC'));

        return false === $parsed ? null : $parsed;
    }

    private static function float(mixed $value): ?float
    {
        return \is_int($value) || \is_float($value) ? (float) $value : null;
    }

    private static function int(mixed $value): int
    {
        return \is_int($value) ? $value : (\is_float($value) ? (int) round($value) : 0);
    }

    private static function intOrNull(mixed $value): ?int
    {
        return \is_int($value) ? $value : (\is_float($value) ? (int) round($value) : null);
    }

    private static function string(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        if (!\is_array($values)) {
            return [];
        }
        $out = [];
        foreach ($values as $value) {
            if (\is_string($value)) {
                $out[] = $value;
            }
        }

        return $out;
    }
}
