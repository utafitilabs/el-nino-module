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

namespace Uhifadhi\ElNino\Shell;

use Uhifadhi\Bundle\ShellBundle\Contract\StylesheetSourceInterface;
use Uhifadhi\ElNino\UhifadhiElNinoBundle;

/**
 * THE SHEET THIS MODULE NEEDS ON A PAGE IT DOES NOT RENDER: the shell's
 * configure page draws the producer section, so the section's vocabulary
 * rides in the head of every page through the shell's stylesheet source.
 * The watch's own screens link their sheet from their base template.
 */
final readonly class ElNinoStylesheets implements StylesheetSourceInterface
{
    public function stylesheets(): iterable
    {
        yield UhifadhiElNinoBundle::CONFIGURE_STYLESHEET;
    }
}
