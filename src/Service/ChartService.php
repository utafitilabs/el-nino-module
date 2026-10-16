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

use Uhifadhi\Bundle\AtlasBundle\Model\AtlasChart;
use Uhifadhi\Bundle\AtlasBundle\Model\AxisScale;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartFigures;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartKind;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartLegend;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartSeries;
use Uhifadhi\Contracts\Atlas\PlatePalette;
use Uhifadhi\ElNino\Entity\Bulletin;
use Uhifadhi\ElNino\Entity\Week;
use Uhifadhi\ElNino\Model\EnsoWords;
use Uhifadhi\ElNino\Model\Outlook;

/**
 * THE CHARTS, STATED: the module fills in the atlas's chart value and the
 * atlas draws it. Nothing here knows what a canvas is.
 */
final class ChartService
{
    /** The Niño 3.4 reading of each week the bulletin carries — the Pacific over the last half year. */
    public function nino(Bulletin $bulletin): AtlasChart
    {
        $labels = [];
        $points = [];
        foreach ($bulletin->getWeeks() as $week) {
            $labels[] = $week->getStart()->format('j M');
            $points[] = $week->getNinoAnomaly();
        }

        return new AtlasChart(
            ChartKind::Line,
            $labels,
            [new ChartSeries('Niño 3.4 anomaly', $points, accent: true)],
            target: EnsoWords::THRESHOLD,
            unit: '°C',
            targetLabel: 'El Niño threshold',
            legend: ChartLegend::None,
        );
    }

    /** The outlook as parts of a whole per season: El Niño, neutral, La Niña. */
    public function outlook(Outlook $outlook): AtlasChart
    {
        $labels = [];
        $elNino = [];
        $neutral = [];
        $laNina = [];
        foreach ($outlook->seasons as $row) {
            $labels[] = $row['season'];
            $elNino[] = (float) $row['el_nino'];
            $neutral[] = (float) $row['neutral'];
            $laNina[] = (float) $row['la_nina'];
        }

        return new AtlasChart(
            ChartKind::Stacked,
            $labels,
            [
                new ChartSeries(EnsoWords::EL_NINO, $elNino, swatch: PlatePalette::FAIL),
                new ChartSeries('Neutral', $neutral, swatch: PlatePalette::DIM),
                new ChartSeries(EnsoWords::LA_NINA, $laNina, swatch: PlatePalette::ACCENT),
            ],
            unit: '%',
            axis: new AxisScale(100.0, 25.0),
            legend: ChartLegend::Chips,
        );
    }

    /**
     * Rain over the area week by week, against the normal for those dates.
     *
     * @param list<Week> $weeks
     */
    public function weeklyRain(array $weeks): AtlasChart
    {
        $labels = [];
        $rain = [];
        $normal = [];
        $largest = 1.0;
        foreach ($weeks as $week) {
            $labels[] = $week->getStart()->format('j M');
            $rain[] = round($week->getMm(), 1);
            $normal[] = round($week->getNormalMm(), 1);
            $largest = max($largest, $week->getMm(), $week->getNormalMm());
        }

        return new AtlasChart(
            ChartKind::Line,
            $labels,
            [
                new ChartSeries('Rain over the area', $rain, accent: true),
                new ChartSeries('Normal for those dates', $normal, swatch: PlatePalette::DIM),
            ],
            unit: 'mm',
            axis: AxisScale::covering($largest, 4),
            figures: null,
            legend: ChartLegend::Chips,
        );
    }

    /**
     * The Pacific week by week, on the same weeks as the rain chart.
     *
     * @param list<Week> $weeks
     */
    public function weeklyNino(array $weeks): AtlasChart
    {
        $labels = [];
        $points = [];
        foreach ($weeks as $week) {
            $labels[] = $week->getStart()->format('j M');
            $points[] = $week->getNinoAnomaly();
        }

        return new AtlasChart(
            ChartKind::Line,
            $labels,
            [new ChartSeries('Niño 3.4 anomaly', $points, accent: true)],
            target: EnsoWords::THRESHOLD,
            unit: '°C',
            targetLabel: 'El Niño threshold',
            figures: new ChartFigures('°C', 1),
            legend: ChartLegend::None,
        );
    }
}
