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

use Uhifadhi\Contracts\Shell\ModuleTab;
use Uhifadhi\Contracts\Shell\ModuleTabsInterface;
use Uhifadhi\ElNino\Module\ElNinoModuleProvider;

/**
 * THE MODULE'S DATA PLACES: the two tabs the settled page carries. The shell
 * draws the strip and lights the tab; this class only says what the tabs are.
 */
final readonly class ElNinoModuleTabs implements ModuleTabsInterface
{
    public function slug(): string
    {
        return ElNinoModuleProvider::SLUG;
    }

    public function tabs(): array
    {
        return [
            new ModuleTab('Overview', 'el_nino_watch'),
            new ModuleTab('Week by week', 'el_nino_weeks'),
        ];
    }
}
