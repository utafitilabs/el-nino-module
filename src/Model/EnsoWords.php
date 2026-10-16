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
 * THE WORDS THE PAGE SAYS ABOUT THE PACIFIC, from the figures a bulletin
 * carries. Ported from the settled page so the two never disagree.
 */
final class EnsoWords
{
    public const string EL_NINO = 'El Niño';
    public const string LA_NINA = 'La Niña';
    public const string NEUTRAL = 'neutral';

    /** The Niño 3.4 anomaly that counts as El Niño when sustained; its negative, La Niña. */
    public const float THRESHOLD = 0.5;

    /** @var array<string, string> a three-letter season and the months it spans */
    public const array SEASONS = [
        'SON' => 'Sep–Nov', 'OND' => 'Oct–Dec', 'NDJ' => 'Nov–Jan', 'DJF' => 'Dec–Feb',
        'JFM' => 'Jan–Mar', 'FMA' => 'Feb–Apr', 'MAM' => 'Mar–May', 'AMJ' => 'Apr–Jun',
        'MJJ' => 'May–Jul', 'JJA' => 'Jun–Aug', 'JAS' => 'Jul–Sep', 'ASO' => 'Aug–Oct',
    ];

    /** The short rains of the region the module was written for, by season code. */
    public const string SHORT_RAINS = 'OND';

    /** The long rains, likewise. */
    public const string LONG_RAINS = 'MAM';

    private function __construct()
    {
    }

    /** "very strong El Niño", "weak La Niña", "ENSO neutral". */
    public static function plain(string $phase, string $strength): string
    {
        return self::NEUTRAL === $phase ? 'ENSO neutral' : \sprintf('%s %s', $strength, $phase);
    }

    /** "26 weeks above the +0.5 °C threshold", "about 8 months below the −0.5 °C threshold", "inside the neutral band". */
    public static function run(string $phase, int $weeksInRange): string
    {
        $span = $weeksInRange < 9
            ? \sprintf('%d weeks', $weeksInRange)
            : \sprintf('about %d months', (int) round($weeksInRange / 4.33));

        return match ($phase) {
            self::EL_NINO => $span.' above the +0.5 °C threshold',
            self::LA_NINA => $span.' below the −0.5 °C threshold',
            default => 'inside the neutral band',
        };
    }

    /** The chip state the Pacific's word wears: fail in El Niño range, acc in La Niña, idle between. */
    public static function chip(?float $anomaly): string
    {
        if (null === $anomaly) {
            return 'idle';
        }
        if ($anomaly >= self::THRESHOLD) {
            return 'fail';
        }

        return $anomaly <= -self::THRESHOLD ? 'acc' : 'idle';
    }

    /** "likely" at 70 % or more, "possible" at 40 %, "unlikely" below. */
    public static function chance(int $percent): string
    {
        if ($percent >= 70) {
            return 'likely';
        }

        return $percent >= 40 ? 'possible' : 'unlikely';
    }

    public static function chanceChip(int $percent): string
    {
        if ($percent >= 70) {
            return 'fail';
        }

        return $percent >= 40 ? 'warn' : 'ok';
    }

    /** "+3.3 °C" — a signed figure to one decimal, the way the page prints an anomaly. */
    public static function anomaly(?float $value): string
    {
        if (null === $value) {
            return '–';
        }

        return \sprintf('%s%.1f °C', $value >= 0 ? '+' : '−', abs($value));
    }

    /** The months a season code spans, or the code itself where the module has no words for it. */
    public static function season(string $code): string
    {
        return self::SEASONS[$code] ?? $code;
    }
}
