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
 * THE THREE STATES THE BACKGROUND PLATE DRAWS: a normal year, El Niño, and a
 * positive Indian Ocean Dipole — the two circulations along the equator, in
 * plan view, with the one-paragraph reading of each.
 */
enum Circulation: string
{
    case Normal = 'normal';
    case ElNino = 'nino';
    case Dipole = 'iod';

    public static function fromQuery(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Normal;
    }

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal year',
            self::ElNino => 'El Niño',
            self::Dipole => 'Positive Indian Ocean Dipole',
        };
    }

    /** The lead of the caption, in bold on the page. */
    public function lead(): string
    {
        return match ($this) {
            self::Normal => 'Normal year.',
            self::ElNino => 'El Niño.',
            self::Dipole => 'Positive Indian Ocean Dipole.',
        };
    }

    /** The caption under the plate, the settled page's words. */
    public function caption(): string
    {
        return match ($this) {
            self::Normal => 'The trade winds push the warm surface water west into a pool around Indonesia. Air rises over it with heavy rain and splits into two loops: east aloft across the Pacific, sinking over the cool eastern Pacific, and west aloft across the Indian Ocean, sinking dry over East Africa. Sinking air is one reason the October-to-December short rains are usually modest.',
            self::ElNino => 'The trade winds weaken, the warm water spreads east across the Pacific and the rising branch with its rain moves to the central Pacific; Indonesia turns dry. The Indian Ocean loop weakens, the sinking over East Africa relaxes, the western Indian Ocean warms, and moist air from it reaches the coast: the October-to-December short rains are enhanced [6]. The effect is weak in the March-to-May long rains.',
            self::Dipole => 'The western Indian Ocean is warmer and the eastern, off Sumatra, cooler than normal. Convection shifts west over the warm water and moist air flows onto East Africa, the same direction as the El Niño effect. It often coincides with El Niño and in some years acts alone [7].',
        };
    }
}
