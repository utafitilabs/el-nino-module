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

namespace Uhifadhi\ElNino\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Uhifadhi\ElNino\Model\EnsoWords;

/**
 * THE PAGE'S WORDS, REACHABLE FROM TWIG: the same functions the tiles and the
 * attention list call in PHP, so a template never spells a threshold of its own.
 */
final class ElNinoExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('el_nino_chance', EnsoWords::chance(...)),
            new TwigFunction('el_nino_chance_chip', EnsoWords::chanceChip(...)),
            new TwigFunction('el_nino_season', EnsoWords::season(...)),
            new TwigFunction('el_nino_anomaly', EnsoWords::anomaly(...)),
        ];
    }
}
