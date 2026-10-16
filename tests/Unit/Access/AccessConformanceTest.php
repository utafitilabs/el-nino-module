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

namespace Uhifadhi\ElNino\Tests\Unit\Access;

use Uhifadhi\Bundle\TeamBundle\Test\AccessConformanceTestCase;
use Uhifadhi\Contracts\Access\ConcernSourceInterface;
use Uhifadhi\ElNino\Access\ElNinoConcerns;
use Uhifadhi\ElNino\Module\ElNinoModuleProvider;

/**
 * The same rules every module is held to, run over this module's own
 * declarations, doors and route gates.
 */
final class AccessConformanceTest extends AccessConformanceTestCase
{
    protected static function source(): ConcernSourceInterface
    {
        return new ElNinoConcerns();
    }

    protected static function bundlePath(): string
    {
        return \dirname(__DIR__, 3);
    }

    protected static function moduleSlug(): string
    {
        return ElNinoModuleProvider::SLUG;
    }

    /** @return list<string> */
    protected static function sensitiveConcerns(): array
    {
        return [];
    }
}
