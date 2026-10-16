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

namespace Uhifadhi\ElNino\Tests\Unit;

use Uhifadhi\Bundle\ShellBundle\Test\TimeConformanceTestCase;

/** Every instant a template prints is inside a <time> the frame rewrites to the reader's zone. */
final class TimeConformanceTest extends TimeConformanceTestCase
{
    protected static function bundlePath(): string
    {
        return \dirname(__DIR__, 2);
    }
}
