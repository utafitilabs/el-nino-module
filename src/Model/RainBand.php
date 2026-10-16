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

use Uhifadhi\Contracts\Atlas\PlatePalette;

/**
 * HOW WET A FIGURE IS, IN WORDS: the five bands the settled page reads a
 * percent of normal into, and the plate token each one is drawn with.
 *
 * The thresholds are the page's own: much wetter at the area's wet threshold
 * (150 % unless the producer says otherwise), wetter above 120 %, near normal
 * from 80 %, drier from 50 %, dry below.
 */
enum RainBand: string
{
    case Dry = 'dry';
    case Drier = 'drier';
    case Near = 'near';
    case Wetter = 'wetter';
    case MuchWetter = 'much-wetter';

    public static function of(?float $pct, int $wetThresholdPct): ?self
    {
        if (null === $pct) {
            return null;
        }
        if ($pct >= $wetThresholdPct) {
            return self::MuchWetter;
        }
        if ($pct > 120.0) {
            return self::Wetter;
        }
        if ($pct >= 80.0) {
            return self::Near;
        }
        if ($pct >= 50.0) {
            return self::Drier;
        }

        return self::Dry;
    }

    /** The words the page prints: "much wetter than normal", "near normal", "dry". */
    public function words(): string
    {
        return match ($this) {
            self::MuchWetter => 'much wetter than normal',
            self::Wetter => 'wetter than normal',
            self::Near => 'near normal',
            self::Drier => 'drier than normal',
            self::Dry => 'dry',
        };
    }

    /** The shell state a chip for this band wears: warn for much wetter, idle for dry, ok between. */
    public function chip(): string
    {
        return match ($this) {
            self::MuchWetter => 'warn',
            self::Dry => 'idle',
            default => 'ok',
        };
    }

    /** The plate token a cell of this band is filled with. */
    public function swatch(): string
    {
        return match ($this) {
            self::MuchWetter, self::Wetter => PlatePalette::ACCENT,
            self::Near => PlatePalette::DIM,
            self::Drier, self::Dry => PlatePalette::WARN,
        };
    }

    /** How solid the fill is: the further from normal, the more it says. */
    public function fillOpacity(): float
    {
        return match ($this) {
            self::MuchWetter => 0.62,
            self::Wetter => 0.34,
            self::Near => 0.14,
            self::Drier => 0.22,
            self::Dry => 0.45,
        };
    }

    /** The legend row's label for this band, with the thresholds it stands for. */
    public function legendLabel(int $wetThresholdPct): string
    {
        return match ($this) {
            self::MuchWetter => \sprintf('≥ %d %% of normal', $wetThresholdPct),
            self::Wetter => '120 – '.$wetThresholdPct.' %',
            self::Near => '80 – 120 %',
            self::Drier => '50 – 80 %',
            self::Dry => '< 50 %',
        };
    }

    /** @return list<self> wettest first, the order a legend reads them in */
    public static function ordered(): array
    {
        return [self::MuchWetter, self::Wetter, self::Near, self::Drier, self::Dry];
    }
}
