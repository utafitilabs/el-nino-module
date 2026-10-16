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
use Uhifadhi\Bundle\AtlasBundle\Model\ChartKind;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartLegend;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartPanel;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartRange;
use Uhifadhi\Bundle\AtlasBundle\Model\ChartSeries;
use Uhifadhi\Bundle\AtlasBundle\Model\LineStyle;
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
            [new ChartSeries('Niño 3.4 anomaly', $points, accent: true, marked: [] === $points ? null : \count($points) - 1)],
            target: EnsoWords::THRESHOLD,
            unit: '°C',
            targetLabel: 'El Niño threshold',
            legend: ChartLegend::None,
            ranges: self::ensoRanges(),
        );
    }

    /**
     * THE TWO RANGES EVERY NIÑO 3.4 CHART IS READ AGAINST: El Niño above the
     * threshold, La Niña below its mirror, each in the tone the page gives
     * that state.
     *
     * @return list<ChartRange>
     */
    private static function ensoRanges(): array
    {
        return [
            new ChartRange(EnsoWords::THRESHOLD, null, \sprintf('EL NIÑO ≥ +%.1f °C', EnsoWords::THRESHOLD), swatch: PlatePalette::FAIL),
            new ChartRange(null, -EnsoWords::THRESHOLD, \sprintf('LA NIÑA ≤ −%.1f °C', EnsoWords::THRESHOLD), swatch: PlatePalette::ACCENT),
        ];
    }

    /**
     * THE RAIN AXIS TOPS OUT ON A ROUND NUMBER — the next multiple of ten,
     * fifty or two hundred above the wettest week, in two steps — so the
     * gridlines read as figures and not as the data's own maximum.
     */
    private static function roundAxis(float $largest): AxisScale
    {
        $unit = $largest > 200.0 ? 200.0 : ($largest > 50.0 ? 50.0 : 10.0);
        $top = max($unit, ceil($largest / $unit) * $unit);

        return new AxisScale($top, $top / 2);
    }

    /**
     * THE WEEK AXIS WRITES THE MONTHS, not twenty-six dates: the first week of
     * each month carries its name, every other week an empty label.
     *
     * @param list<Week> $weeks
     *
     * @return list<string>
     */
    private static function monthLabels(array $weeks): array
    {
        $labels = [];
        $month = '';
        foreach ($weeks as $week) {
            $of = $week->getStart()->format('Y-m');
            $labels[] = $of === $month ? '' : $week->getStart()->format('M');
            $month = $of;
        }

        return $labels;
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
     * THE LOWER PANEL OF THE TWO SIGNALS: rain over the area week by week as
     * bars, against the normal for those dates as a dashed line over them,
     * with the picked week banded.
     *
     * @param list<Week> $weeks
     */
    public function weeklyRain(array $weeks, ?int $picked = null): AtlasChart
    {
        $rain = [];
        $normal = [];
        $largest = 1.0;
        foreach ($weeks as $week) {
            $rain[] = round($week->getMm(), 1);
            $normal[] = round($week->getNormalMm(), 1);
            $largest = max($largest, $week->getMm(), $week->getNormalMm());
        }

        return new AtlasChart(
            ChartKind::Bar,
            self::monthLabels($weeks),
            [
                new ChartSeries('Rain over the area', $rain, accent: true),
                new ChartSeries('Normal for those dates', $normal, swatch: PlatePalette::DIM, line: LineStyle::Dashed),
            ],
            unit: 'mm',
            axis: self::roundAxis($largest),
            legend: ChartLegend::Chips,
            highlight: $picked,
            panel: ChartPanel::Lower,
        );
    }

    /**
     * THE UPPER PANEL OF THE TWO SIGNALS: the Pacific week by week on the
     * same weeks as the rain, the El Niño range shaded, the picked week
     * banded (the column is the one mark; a dot on the line read as a
     * boundary), and no week names of its own — the
     * lower panel writes them once for both.
     *
     * @param list<Week> $weeks
     */
    public function weeklyNino(array $weeks, ?int $picked = null): AtlasChart
    {
        $points = [];
        foreach ($weeks as $week) {
            $points[] = $week->getNinoAnomaly();
        }

        return new AtlasChart(
            ChartKind::Line,
            self::monthLabels($weeks),
            [new ChartSeries('Niño 3.4 anomaly', $points, accent: true)],
            target: EnsoWords::THRESHOLD,
            unit: '°C',
            targetLabel: 'El Niño threshold',
            legend: ChartLegend::None,
            ranges: [new ChartRange(EnsoWords::THRESHOLD, null, \sprintf('EL NIÑO ≥ +%.1f °C', EnsoWords::THRESHOLD), swatch: PlatePalette::FAIL)],
            highlight: $picked,
            panel: ChartPanel::Upper,
        );
    }
}
