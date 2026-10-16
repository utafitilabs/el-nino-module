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

use Uhifadhi\ElNino\Model\PayloadException;
use Uhifadhi\ElNino\Model\ProducerBulletin;

/**
 * READS THE PRODUCER'S FILES INTO ONE CHECKED BULLETIN.
 *
 * The parts are named as the producer names its files — `status`, `series`,
 * `roads`, `destinations`, `susceptibility`, `provenance` — and each is the
 * file's text. `status` and `series` are required; the exposure parts are
 * optional, so a producer that re-sends only the weekly figures is still a
 * bulletin. Every refusal names the part and the fault.
 *
 * @phpstan-import-type Grid from ProducerBulletin
 * @phpstan-import-type FeatureCollection from ProducerBulletin
 * @phpstan-import-type WeekRow from ProducerBulletin
 */
final class ProducerPayloadReader
{
    public const string STATUS = 'status';
    public const string SERIES = 'series';
    public const string ROADS = 'roads';
    public const string DESTINATIONS = 'destinations';
    public const string SUSCEPTIBILITY = 'susceptibility';
    public const string BOMAS = 'bomas';
    public const string PROVENANCE = 'provenance';

    /** @var list<string> */
    public const array REQUIRED = [self::STATUS, self::SERIES];

    /** @var list<string> */
    public const array OPTIONAL = [self::ROADS, self::DESTINATIONS, self::SUSCEPTIBILITY, self::BOMAS, self::PROVENANCE];

    /**
     * @param array<string, string> $parts part name => the file's text
     */
    public function read(array $parts): ProducerBulletin
    {
        foreach (self::REQUIRED as $name) {
            if (!isset($parts[$name]) || '' === trim($parts[$name])) {
                throw PayloadException::part($name, 'is missing');
            }
        }

        $status = self::object($parts[self::STATUS], self::STATUS);
        $series = self::object($parts[self::SERIES], self::SERIES);

        $contract = self::contractOf($status, self::STATUS);
        if ($contract !== self::contractOf($series, self::SERIES)) {
            throw PayloadException::part(self::SERIES, 'names a different contract from status');
        }
        $producer = self::producerOf($status, self::STATUS);
        if ($producer['slug'] !== self::producerOf($series, self::SERIES)['slug']) {
            throw PayloadException::part(self::SERIES, 'names a different area from status');
        }
        $builtAt = self::builtAt($status, self::STATUS);

        $enso = self::block($status, 'enso', self::STATUS);
        $rain = self::block($status, 'rain', self::STATUS);
        $threshold = self::int($status['wet_threshold_pct'] ?? ($series['wet_threshold_pct'] ?? 150), 150);
        $grid = self::block($series, 'grid', self::SERIES);
        self::centres($grid, 'x_centres');
        self::centres($grid, 'y_centres');

        $recent = $series['recent'] ?? null;
        if (!\is_array($recent)) {
            throw PayloadException::part(self::SERIES, 'carries no recent window');
        }
        $recentMm = self::grid($recent['grid_mm'] ?? null, self::SERIES.'.recent.grid_mm');
        $recentPct = self::grid($recent['grid_pct'] ?? null, self::SERIES.'.recent.grid_pct');

        $weeks = [];
        $rows = $series['weeks'] ?? null;
        if (!\is_array($rows)) {
            throw PayloadException::part(self::SERIES, 'carries no weeks');
        }
        foreach (array_values($rows) as $index => $row) {
            if (!\is_array($row)) {
                throw PayloadException::part(self::SERIES, \sprintf('week %d is not an object', $index));
            }
            $weeks[] = self::week($row, $index);
        }
        usort($weeks, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        $outlook = $status['iri_outlook'] ?? null;
        $exposure = $status['exposure'] ?? [];
        $notebookRun = $status['notebook_run'] ?? null;
        $cutOff = self::strings($status['cut_off_in_wet_cell'] ?? []);

        $roads = isset($parts[self::ROADS]) ? self::featureCollection($parts[self::ROADS], self::ROADS, $producer['slug']) : null;
        $destinations = isset($parts[self::DESTINATIONS]) ? self::featureCollection($parts[self::DESTINATIONS], self::DESTINATIONS, $producer['slug']) : null;
        $susceptibility = isset($parts[self::SUSCEPTIBILITY]) ? self::featureCollection($parts[self::SUSCEPTIBILITY], self::SUSCEPTIBILITY, $producer['slug']) : null;
        $bomas = isset($parts[self::BOMAS]) ? self::featureCollection($parts[self::BOMAS], self::BOMAS, $producer['slug']) : null;
        $zones = $status['zones'] ?? null;
        $provenance = isset($parts[self::PROVENANCE]) ? self::object($parts[self::PROVENANCE], self::PROVENANCE) : null;

        return new ProducerBulletin(
            contract: $contract,
            producerSlug: $producer['slug'],
            producer: $producer,
            builtAt: $builtAt,
            enso: $enso,
            rain: $rain,
            wetThresholdPct: $threshold,
            cutOffInWetCell: $cutOff,
            outlook: \is_array($outlook) ? self::stringKeys($outlook) : null,
            exposure: \is_array($exposure) ? self::stringKeys($exposure) : [],
            notebookRun: \is_array($notebookRun) ? self::stringKeys($notebookRun) : null,
            zones: \is_array($zones) ? self::stringKeys($zones) : null,
            grid: $grid,
            recentGridMm: $recentMm,
            recentGridPct: $recentPct,
            weeks: $weeks,
            roads: $roads,
            destinations: $destinations,
            susceptibility: $susceptibility,
            bomas: $bomas,
            provenance: $provenance,
        );
    }

    /** @return array<string, mixed> */
    private static function object(string $text, string $part): array
    {
        try {
            $decoded = json_decode($text, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw PayloadException::part($part, 'is not JSON ('.$e->getMessage().')');
        }
        if (!\is_array($decoded)) {
            throw PayloadException::part($part, 'is not a JSON object');
        }

        return self::stringKeys($decoded);
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function stringKeys(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }

    /** @param array<string, mixed> $object */
    private static function contractOf(array $object, string $part): string
    {
        $contract = $object['contract'] ?? null;
        if (!\is_string($contract)) {
            throw PayloadException::part($part, 'names no contract');
        }
        if (ProducerBulletin::CONTRACT !== $contract) {
            throw PayloadException::part($part, \sprintf('is written against "%s"; this module reads "%s"', $contract, ProducerBulletin::CONTRACT));
        }

        return $contract;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @return array<string, mixed>&array{slug: string}
     */
    private static function producerOf(array $object, string $part): array
    {
        $area = $object['area'] ?? null;
        if (!\is_array($area) || !\is_string($area['slug'] ?? null) || '' === $area['slug']) {
            throw PayloadException::part($part, 'names no area slug');
        }
        $out = self::stringKeys($area);
        $out['slug'] = $area['slug'];

        return $out;
    }

    /** @param array<string, mixed> $object */
    private static function builtAt(array $object, string $part): \DateTimeImmutable
    {
        $built = $object['built_utc'] ?? null;
        if (!\is_string($built)) {
            throw PayloadException::part($part, 'carries no built_utc');
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $built, new \DateTimeZone('UTC'))
            ?: \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $built, new \DateTimeZone('UTC'));
        if (false === $parsed) {
            throw PayloadException::part($part, \sprintf('built_utc "%s" is not a UTC "Y-m-d H:i"', $built));
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @return array<string, mixed>
     */
    private static function block(array $object, string $key, string $part): array
    {
        $block = $object[$key] ?? null;
        if (!\is_array($block)) {
            throw PayloadException::part($part, \sprintf('carries no %s block', $key));
        }

        return self::stringKeys($block);
    }

    /** @param array<string, mixed> $grid */
    private static function centres(array $grid, string $key): void
    {
        $values = $grid[$key] ?? null;
        if (!\is_array($values) || [] === $values) {
            throw PayloadException::part(self::SERIES, \sprintf('grid names no %s', $key));
        }
        foreach ($values as $value) {
            if (!\is_int($value) && !\is_float($value)) {
                throw PayloadException::part(self::SERIES, \sprintf('grid %s holds a value that is not a number', $key));
            }
        }
    }

    /**
     * @return Grid
     */
    private static function grid(mixed $rows, string $where): array
    {
        if (!\is_array($rows)) {
            throw PayloadException::part($where, 'is not a grid');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                throw PayloadException::part($where, 'holds a row that is not a list');
            }
            $cells = [];
            foreach ($row as $cell) {
                if (null !== $cell && !\is_int($cell) && !\is_float($cell)) {
                    throw PayloadException::part($where, 'holds a cell that is neither a number nor null');
                }
                $cells[] = null === $cell ? null : (float) $cell;
            }
            $out[] = $cells;
        }

        return $out;
    }

    /**
     * @param array<mixed> $row
     *
     * @return WeekRow
     */
    private static function week(array $row, int $index): array
    {
        $where = \sprintf('%s.weeks[%d]', self::SERIES, $index);
        $start = self::date($row['start'] ?? null, $where.'.start');
        $end = self::date($row['end'] ?? null, $where.'.end');
        if (null === $start || null === $end) {
            throw PayloadException::part($where, 'has no start or end date');
        }
        $label = $row['label'] ?? null;

        return [
            'label' => \is_string($label) && '' !== $label ? $label : $start->format('Y-m-d').' to '.$end->format('Y-m-d'),
            'start' => $start,
            'end' => $end,
            'days' => self::int($row['days'] ?? 0, 0),
            'mm' => self::float($row['mm'] ?? 0.0) ?? 0.0,
            'normal_mm' => self::float($row['normal_mm'] ?? 0.0) ?? 0.0,
            'pct' => self::float($row['pct'] ?? null),
            'wet_cells' => self::int($row['wet_cells'] ?? 0, 0),
            'hot' => self::strings($row['hot'] ?? []),
            'nino_date' => self::date($row['nino_date'] ?? null, $where.'.nino_date'),
            'nino_anomaly' => self::float($row['nino_anomaly'] ?? null),
            'grid_mm' => self::grid($row['grid_mm'] ?? null, $where.'.grid_mm'),
            'grid_pct' => self::grid($row['grid_pct'] ?? null, $where.'.grid_pct'),
        ];
    }

    /**
     * @return FeatureCollection
     */
    private static function featureCollection(string $text, string $part, string $slug): array
    {
        $object = self::object($text, $part);
        if ('FeatureCollection' !== ($object['type'] ?? null) || !\is_array($object['features'] ?? null)) {
            throw PayloadException::part($part, 'is not a GeoJSON FeatureCollection');
        }
        $area = $object['area'] ?? null;
        if (\is_array($area) && isset($area['slug']) && $area['slug'] !== $slug) {
            throw PayloadException::part($part, 'names a different area from status');
        }
        $features = [];
        foreach ($object['features'] as $feature) {
            if (!\is_array($feature) || !\is_array($feature['geometry'] ?? null)) {
                continue;
            }
            $features[] = self::stringKeys($feature);
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    private static function date(mixed $value, string $where): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!\is_string($value)) {
            throw PayloadException::part($where, 'is not a date');
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10), new \DateTimeZone('UTC'));
        if (false === $parsed) {
            throw PayloadException::part($where, \sprintf('"%s" is not a Y-m-d date', $value));
        }

        return $parsed;
    }

    private static function float(mixed $value): ?float
    {
        return \is_int($value) || \is_float($value) ? (float) $value : null;
    }

    private static function int(mixed $value, int $default): int
    {
        return \is_int($value) ? $value : (\is_float($value) ? (int) round($value) : $default);
    }

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        if (!\is_array($values)) {
            return [];
        }
        $out = [];
        foreach ($values as $value) {
            if (\is_string($value) && '' !== $value) {
                $out[] = $value;
            }
        }

        return $out;
    }
}
