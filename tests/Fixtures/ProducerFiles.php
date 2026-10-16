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

namespace Uhifadhi\ElNino\Tests\Fixtures;

use Uhifadhi\ElNino\Model\ProducerBulletin;
use Uhifadhi\ElNino\Service\ProducerPayloadReader;

/**
 * A PRODUCER BUILD AS FILES: the texts a producer writes under the contract,
 * made from a bulletin value so a test can push exactly what the producer
 * would and read it back through the real reader.
 */
final class ProducerFiles
{
    private function __construct()
    {
    }

    /**
     * @return array<string, string> the reader's part names => file text
     */
    public static function parts(ProducerBulletin $b): array
    {
        $head = [
            'contract' => $b->contract,
            'area' => $b->producer,
            'built_utc' => $b->builtAt->format('Y-m-d H:i'),
        ];
        $status = [
            ...$head,
            'enso' => $b->enso,
            'rain' => $b->rain,
            'wet_threshold_pct' => $b->wetThresholdPct,
            'cut_off_in_wet_cell' => $b->cutOffInWetCell,
            'iri_outlook' => $b->outlook,
            'exposure' => $b->exposure,
            'notebook_run' => $b->notebookRun,
            'zones' => $b->zones,
        ];
        $weeks = [];
        foreach ($b->weeks as $row) {
            $weeks[] = [
                'label' => $row['label'],
                'start' => $row['start']->format('Y-m-d'),
                'end' => $row['end']->format('Y-m-d'),
                'days' => $row['days'],
                'mm' => $row['mm'],
                'normal_mm' => $row['normal_mm'],
                'pct' => $row['pct'],
                'wet_cells' => $row['wet_cells'],
                'hot' => $row['hot'],
                'nino_date' => $row['nino_date']?->format('Y-m-d'),
                'nino_anomaly' => $row['nino_anomaly'],
                'grid_mm' => $row['grid_mm'],
                'grid_pct' => $row['grid_pct'],
            ];
        }
        $series = [
            ...$head,
            'wet_threshold_pct' => $b->wetThresholdPct,
            'grid' => $b->grid,
            'weeks' => $weeks,
            'recent' => ['grid_mm' => $b->recentGridMm, 'grid_pct' => $b->recentGridPct, 'hot' => $b->cutOffInWetCell],
        ];
        $parts = [
            ProducerPayloadReader::STATUS => self::json($status),
            ProducerPayloadReader::SERIES => self::json($series),
        ];
        foreach ([
            ProducerPayloadReader::ROADS => $b->roads,
            ProducerPayloadReader::DESTINATIONS => $b->destinations,
            ProducerPayloadReader::SUSCEPTIBILITY => $b->susceptibility,
            ProducerPayloadReader::BOMAS => $b->bomas,
        ] as $name => $collection) {
            if (null !== $collection) {
                $parts[$name] = self::json([...$head, 'layer' => $name, ...$collection]);
            }
        }
        if (null !== $b->provenance) {
            $parts[ProducerPayloadReader::PROVENANCE] = self::json([...$head, ...$b->provenance]);
        }

        return $parts;
    }

    /** @param array<string, mixed> $value */
    private static function json(array $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
