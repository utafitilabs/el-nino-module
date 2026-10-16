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
 * A producer bulletin the module cannot take: the wrong contract, a part
 * missing, a shape that is not the one the contract states. The message names
 * the part and the fault, because the producer's author is who reads it.
 */
final class PayloadException extends \RuntimeException
{
    public static function part(string $part, string $fault): self
    {
        return new self(\sprintf('%s: %s', $part, $fault));
    }
}
