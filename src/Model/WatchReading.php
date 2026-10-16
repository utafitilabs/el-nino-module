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

use Uhifadhi\ElNino\Entity\AreaWatch;
use Uhifadhi\ElNino\Entity\Bulletin;
use Uhifadhi\ElNino\Entity\Destination;
use Uhifadhi\ElNino\Entity\ExposureRun;

/**
 * THE WATCH AS THE PAGE READS IT: the newest bulletin, the current exposure
 * run, the area's own words, and every sentence and chip the settled page
 * derives from them — derived once here so the page, the tiles and the
 * attention list cannot disagree.
 */
final readonly class WatchReading
{
    public ?Outlook $outlook;

    /** @var list<Destination> cut-off destinations, most people first */
    public array $cutOff;

    /** @var list<string> the names of cut-off destinations sitting in a wet cell this window */
    public array $hot;

    public int $cutOffPeople;

    public ?RainBand $rainBand;

    public function __construct(
        public AreaWatch $watch,
        public Bulletin $bulletin,
        public ?ExposureRun $run,
    ) {
        $this->outlook = Outlook::fromRaw($bulletin->getOutlook());
        $cutOff = null === $run ? [] : array_values(array_filter($run->getDestinations(), static fn (Destination $d): bool => $d->isCutOff()));
        usort($cutOff, static fn (Destination $a, Destination $b): int => ($b->getPopulation() ?? -1) <=> ($a->getPopulation() ?? -1));
        $this->cutOff = $cutOff;
        $this->hot = $bulletin->getCutOffInWetCell();
        $this->cutOffPeople = array_sum(array_map(static fn (Destination $d): int => $d->getPopulation() ?? 0, $cutOff));
        $this->rainBand = RainBand::of($bulletin->getRainPct(), $bulletin->getWetThresholdPct());
    }

    /**
     * The sources the analysis names for its layers, as the producer's
     * provenance states them: rainfall, land cover, roads and places,
     * population, boundary — whatever it lists. Empty where it lists none.
     *
     * @return array<string, string>
     */
    public function dataSources(): array
    {
        $data = $this->run?->getProvenance()['data'] ?? null;
        if (!\is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $key => $value) {
            if (\is_string($key) && \is_string($value) && '' !== $value) {
                $out[str_replace('_', ' ', $key)] = $value;
            }
        }

        return $out;
    }

    /** The one sentence about where the roads and places came from, from the provenance or nothing. */
    public function roadsAndPlacesSentence(): ?string
    {
        $source = $this->dataSources()['roads places'] ?? null;

        return null === $source ? null : \sprintf('Roads and places come from %s.', $source);
    }

    public function ensoPlain(): string
    {
        return EnsoWords::plain($this->bulletin->getEnsoPhase(), $this->bulletin->getEnsoStrength());
    }

    public function ensoRun(): string
    {
        return EnsoWords::run($this->bulletin->getEnsoPhase(), $this->bulletin->getEnsoWeeksInRange());
    }

    public function ensoChip(): string
    {
        return EnsoWords::chip($this->bulletin->getEnsoAnomaly());
    }

    public function isElNino(): bool
    {
        return EnsoWords::EL_NINO === $this->bulletin->getEnsoPhase();
    }

    /** @return array{season: string, el_nino: int, neutral: int, la_nina: int}|null */
    public function shortRains(): ?array
    {
        return $this->outlook?->shortRains();
    }

    public function rainWords(): string
    {
        return $this->rainBand?->words() ?? 'not measured';
    }

    public function rainChip(): string
    {
        return $this->rainBand?->chip() ?? 'idle';
    }

    public function hotCount(): int
    {
        return \count($this->hot);
    }

    /** Whether a destination is in a wet cell this window. */
    public function isHot(Destination $destination): bool
    {
        return \in_array($destination->getName(), $this->hot, true);
    }

    /** How many bomas the current run says lose road access, where the producer routed households. */
    public function bomasCutOff(): ?int
    {
        $count = $this->bulletin->getExposure()['bomas_cut_off'] ?? null;

        return \is_int($count) ? $count : null;
    }

    /**
     * The page's one-paragraph verdict, built the way the settled page builds it.
     *
     * @return list<array{text: string, strong: bool}>
     */
    public function verdict(): array
    {
        $b = $this->bulletin;
        $parts = [];
        if ($this->isElNino()) {
            $parts[] = ['text' => \sprintf('A %s %s is under way', $b->getEnsoStrength(), EnsoWords::EL_NINO), 'strong' => true];
            $parts[] = ['text' => \sprintf(' in the Pacific (%s, %s).', EnsoWords::anomaly($b->getEnsoAnomaly()), $this->ensoRun()), 'strong' => false];
        } else {
            $parts[] = ['text' => \sprintf('The Pacific is in %s', $this->ensoPlain()), 'strong' => true];
            $parts[] = ['text' => \sprintf(' (%s).', EnsoWords::anomaly($b->getEnsoAnomaly())), 'strong' => false];
        }
        $short = $this->shortRains();
        if (null !== $short) {
            $parts[] = ['text' => ' The models give it a ', 'strong' => false];
            $parts[] = ['text' => \sprintf('%d %% chance of lasting through the short rains', $short['el_nino']), 'strong' => true];
            $parts[] = ['text' => \sprintf(' (%s).', EnsoWords::season($short['season'])), 'strong' => false];
        }
        $parts[] = ['text' => \sprintf(' The last %d days over the area were ', $b->getRainDays()), 'strong' => false];
        $parts[] = ['text' => $this->rainWords(), 'strong' => true];
        $parts[] = ['text' => null === $b->getRainPct() ? '.' : \sprintf(' (%d %% of normal).', (int) round($b->getRainPct())), 'strong' => false];
        $n = $this->hotCount();
        $total = \count($this->cutOff);
        if ($n > 0) {
            $parts[] = ['text' => ' ', 'strong' => false];
            $parts[] = ['text' => \sprintf('%d of the %d destinations', $n, $total), 'strong' => true];
            $parts[] = ['text' => \sprintf(' that lose road access in a flood season %s in a much-wetter-than-normal cell right now.', 1 === $n ? 'is' : 'are'), 'strong' => false];
        } elseif ($total > 0) {
            $parts[] = ['text' => \sprintf(' None of the %d destinations that lose road access in a flood season is in a much-wetter-than-normal cell right now.', $total), 'strong' => false];
        }

        return $parts;
    }
}
