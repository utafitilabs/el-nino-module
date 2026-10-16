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

namespace Uhifadhi\ElNino\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ONE CALENDAR WEEK OF A BULLETIN: the rain over the area and its grid, the
 * cells that were much wetter than normal, the cut-off destinations inside
 * them, and the Niño 3.4 reading of that week.
 */
#[ORM\Entity]
#[ORM\Table(name: 'el_nino_week')]
#[ORM\UniqueConstraint(name: 'uniq_el_nino_week_bulletin_start', columns: ['bulletin_id', 'week_start'])]
class Week
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne(targetEntity: Bulletin::class, inversedBy: 'weeks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Bulletin $bulletin;

    #[ORM\Column(length: 40)]
    private string $label;

    /** Named explicitly: `start` and `end` are words the database keeps for itself. */
    #[ORM\Column(name: 'week_start', type: 'date_immutable')]
    private \DateTimeImmutable $start;

    #[ORM\Column(name: 'week_end', type: 'date_immutable')]
    private \DateTimeImmutable $end;

    /** How many days CHIRPS had published for this week: 7, or fewer for the current week and for gaps. */
    #[ORM\Column(type: 'smallint')]
    private int $days;

    #[ORM\Column(type: 'float')]
    private float $mm;

    #[ORM\Column(type: 'float')]
    private float $normalMm;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $pct;

    #[ORM\Column(type: 'integer')]
    private int $wetCells;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $hot;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $ninoDate;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $ninoAnomaly;

    /** @var list<list<float|null>> */
    #[ORM\Column(type: 'json')]
    private array $gridMm;

    /** @var list<list<float|null>> */
    #[ORM\Column(type: 'json')]
    private array $gridPct;

    /**
     * @param list<string>           $hot
     * @param list<list<float|null>> $gridMm
     * @param list<list<float|null>> $gridPct
     */
    public function __construct(
        Bulletin $bulletin,
        string $label,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        int $days,
        float $mm,
        float $normalMm,
        ?float $pct,
        int $wetCells,
        array $hot,
        ?\DateTimeImmutable $ninoDate,
        ?float $ninoAnomaly,
        array $gridMm,
        array $gridPct,
    ) {
        $this->bulletin = $bulletin;
        $this->label = $label;
        $this->start = $start;
        $this->end = $end;
        $this->days = $days;
        $this->mm = $mm;
        $this->normalMm = $normalMm;
        $this->pct = $pct;
        $this->wetCells = $wetCells;
        $this->hot = $hot;
        $this->ninoDate = $ninoDate;
        $this->ninoAnomaly = $ninoAnomaly;
        $this->gridMm = $gridMm;
        $this->gridPct = $gridPct;
        $bulletin->addWeek($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBulletin(): Bulletin
    {
        return $this->bulletin;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getStart(): \DateTimeImmutable
    {
        return $this->start;
    }

    public function getEnd(): \DateTimeImmutable
    {
        return $this->end;
    }

    public function getDays(): int
    {
        return $this->days;
    }

    public function getMm(): float
    {
        return $this->mm;
    }

    public function getNormalMm(): float
    {
        return $this->normalMm;
    }

    public function getPct(): ?float
    {
        return $this->pct;
    }

    public function getWetCells(): int
    {
        return $this->wetCells;
    }

    /** @return list<string> */
    public function getHot(): array
    {
        return $this->hot;
    }

    public function getNinoDate(): ?\DateTimeImmutable
    {
        return $this->ninoDate;
    }

    public function getNinoAnomaly(): ?float
    {
        return $this->ninoAnomaly;
    }

    /** @return list<list<float|null>> */
    public function getGridMm(): array
    {
        return $this->gridMm;
    }

    /** @return list<list<float|null>> */
    public function getGridPct(): array
    {
        return $this->gridPct;
    }
}
