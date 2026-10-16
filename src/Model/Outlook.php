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
 * THE OUTLOOK AS THE PAGE READS IT: the chance El Niño lasts through the
 * short rains, the long rains, and the first season the models give it less
 * than an even chance — read off the per-season table the producer sends.
 *
 * @phpstan-type Season array{season: string, el_nino: int, neutral: int, la_nina: int}
 */
final readonly class Outlook
{
    /**
     * @param list<Season> $seasons
     */
    private function __construct(
        public string $issued,
        public array $seasons,
        public ?string $figureUrl,
    ) {
    }

    /**
     * @param array<string, mixed>|null $raw the bulletin's `iri_outlook` block
     */
    public static function fromRaw(?array $raw): ?self
    {
        if (null === $raw || !\is_array($raw['seasons'] ?? null)) {
            return null;
        }
        $seasons = [];
        foreach ($raw['seasons'] as $row) {
            if (!\is_array($row) || !\is_string($row['season'] ?? null)) {
                continue;
            }
            $seasons[] = [
                'season' => $row['season'],
                'el_nino' => (int) round(self::number($row['el_nino'] ?? 0)),
                'neutral' => (int) round(self::number($row['neutral'] ?? 0)),
                'la_nina' => (int) round(self::number($row['la_nina'] ?? 0)),
            ];
        }
        if ([] === $seasons) {
            return null;
        }
        $issued = $raw['issued'] ?? null;
        $figure = $raw['figure_url'] ?? null;

        return new self(\is_string($issued) ? $issued : 'unknown issue', $seasons, \is_string($figure) ? $figure : null);
    }

    /** @return Season|null */
    public function season(string $code): ?array
    {
        foreach ($this->seasons as $row) {
            if ($row['season'] === $code) {
                return $row;
            }
        }

        return null;
    }

    /** @return Season|null */
    public function shortRains(): ?array
    {
        return $this->season(EnsoWords::SHORT_RAINS);
    }

    /** @return Season|null */
    public function longRains(): ?array
    {
        return $this->season(EnsoWords::LONG_RAINS);
    }

    /**
     * The first season in which the models give El Niño less than an even chance.
     *
     * @return Season|null
     */
    public function fade(): ?array
    {
        foreach ($this->seasons as $row) {
            if ($row['el_nino'] < 50) {
                return $row;
            }
        }

        return null;
    }

    /** The sentence the page prints under the outlook chart about the fade. */
    public function fadeSentence(): string
    {
        $fade = $this->fade();
        if (null === $fade) {
            return 'No season in the forecast window gives El Niño less than an even chance.';
        }

        return \sprintf(
            'The first season in which the models give El Niño less than an even chance is %s (%d %%).',
            EnsoWords::season($fade['season']),
            $fade['el_nino'],
        );
    }

    private static function number(mixed $value): float
    {
        return \is_int($value) || \is_float($value) ? (float) $value : 0.0;
    }
}
