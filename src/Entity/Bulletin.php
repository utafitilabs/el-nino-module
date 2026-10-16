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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\ElNino\Repository\BulletinRepository;

/**
 * ONE WEEKLY BULLETIN, AS THE PRODUCER BUILT IT: the Pacific that week, the
 * outlook, the rain window over the area, the counts of what is exposed, and
 * the grid every week's rain is drawn on.
 *
 * A bulletin is never edited. A rebuild by the producer is a new row keyed by
 * its build time, and the newest row is what every page reads; the earlier
 * ones stay, because a figure that was true on a Monday is a fact about that
 * Monday. The figures the pages decide on — the anomaly, the phase, the
 * percent of normal — are columns; the rest rides as JSON exactly as it came.
 */
#[ORM\Entity(repositoryClass: BulletinRepository::class)]
#[ORM\Table(name: 'el_nino_bulletin')]
#[ORM\UniqueConstraint(name: 'uniq_el_nino_bulletin_area_built', columns: ['area_id', 'built_at'])]
#[ORM\Index(name: 'idx_el_nino_bulletin_area_built', columns: ['area_id', 'built_at'])]
class Bulletin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $uuid;

    #[ORM\ManyToOne(targetEntity: AreaOfInterest::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AreaOfInterest $area;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $builtAt;

    #[ORM\Column(length: 40)]
    private string $contract;

    /** @var array<string, mixed> the producer's `area` block: slug, name, and whatever else it says */
    #[ORM\Column(type: 'json')]
    private array $producer;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $receivedAt;

    // ---- the Pacific

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $ensoWeek = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $ensoSst = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $ensoAnomaly = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $ensoState = null;

    /** `El Niño`, `La Niña` or `neutral`, the producer's word. */
    #[ORM\Column(length: 20)]
    private string $ensoPhase = 'neutral';

    /** `weak`, `moderate`, `strong`, `very strong` or `neutral`. */
    #[ORM\Column(length: 20)]
    private string $ensoStrength = 'neutral';

    #[ORM\Column(type: 'integer')]
    private int $ensoWeeksInRange = 0;

    // ---- the rain window

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $rainStart = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $rainEnd = null;

    #[ORM\Column(type: 'integer')]
    private int $rainDays = 0;

    /** @var list<string> the days CHIRPS had not published when the bulletin was built */
    #[ORM\Column(type: 'json')]
    private array $rainMissing = [];

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $rainMm = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $rainNormalMm = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $rainPct = null;

    #[ORM\Column(type: 'integer')]
    private int $wetThresholdPct = 150;

    /** @var list<string> the cut-off destinations sitting in a wet cell this window */
    #[ORM\Column(type: 'json')]
    private array $cutOffInWetCell = [];

    // ---- the outlook, the exposure counts, the provenance — as they came

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $outlook = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $exposure = [];

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $notebookRun = null;

    /** @var array<string, mixed>|null per zone: bomas, cut-off bomas, destinations cut off, road km cut, the wet-cell bomas this week */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $zones = null;

    // ---- the grid

    /** @var array<string, mixed> rows, cols, x_centres, y_centres, cell_deg, origin_nw_corner */
    #[ORM\Column(type: 'json')]
    private array $grid = [];

    /** @var list<list<float|null>> */
    #[ORM\Column(type: 'json')]
    private array $recentGridMm = [];

    /** @var list<list<float|null>> */
    #[ORM\Column(type: 'json')]
    private array $recentGridPct = [];

    /** @var Collection<int, Week> */
    #[ORM\OneToMany(targetEntity: Week::class, mappedBy: 'bulletin', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['start' => 'ASC'])]
    private Collection $weeks;

    /**
     * @param array<string, mixed> $producer
     */
    public function __construct(AreaOfInterest $area, \DateTimeImmutable $builtAt, string $contract, array $producer, \DateTimeImmutable $receivedAt)
    {
        $this->uuid = Uuid::v7();
        $this->area = $area;
        $this->builtAt = $builtAt;
        $this->contract = $contract;
        $this->producer = $producer;
        $this->receivedAt = $receivedAt;
        $this->weeks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getArea(): AreaOfInterest
    {
        return $this->area;
    }

    public function getBuiltAt(): \DateTimeImmutable
    {
        return $this->builtAt;
    }

    public function getContract(): string
    {
        return $this->contract;
    }

    /** @return array<string, mixed> */
    public function getProducer(): array
    {
        return $this->producer;
    }

    public function getProducerSlug(): ?string
    {
        $slug = $this->producer['slug'] ?? null;

        return \is_string($slug) ? $slug : null;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setEnso(?\DateTimeImmutable $week, ?float $sst, ?float $anomaly, ?string $state, string $phase, string $strength, int $weeksInRange): static
    {
        $this->ensoWeek = $week;
        $this->ensoSst = $sst;
        $this->ensoAnomaly = $anomaly;
        $this->ensoState = $state;
        $this->ensoPhase = $phase;
        $this->ensoStrength = $strength;
        $this->ensoWeeksInRange = max(0, $weeksInRange);

        return $this;
    }

    public function getEnsoWeek(): ?\DateTimeImmutable
    {
        return $this->ensoWeek;
    }

    public function getEnsoSst(): ?float
    {
        return $this->ensoSst;
    }

    public function getEnsoAnomaly(): ?float
    {
        return $this->ensoAnomaly;
    }

    public function getEnsoState(): ?string
    {
        return $this->ensoState;
    }

    public function getEnsoPhase(): string
    {
        return $this->ensoPhase;
    }

    public function getEnsoStrength(): string
    {
        return $this->ensoStrength;
    }

    public function getEnsoWeeksInRange(): int
    {
        return $this->ensoWeeksInRange;
    }

    /**
     * @param list<string> $missing
     */
    public function setRain(?\DateTimeImmutable $start, ?\DateTimeImmutable $end, int $days, array $missing, ?float $mm, ?float $normalMm, ?float $pct): static
    {
        $this->rainStart = $start;
        $this->rainEnd = $end;
        $this->rainDays = max(0, $days);
        $this->rainMissing = $missing;
        $this->rainMm = $mm;
        $this->rainNormalMm = $normalMm;
        $this->rainPct = $pct;

        return $this;
    }

    public function getRainStart(): ?\DateTimeImmutable
    {
        return $this->rainStart;
    }

    public function getRainEnd(): ?\DateTimeImmutable
    {
        return $this->rainEnd;
    }

    public function getRainDays(): int
    {
        return $this->rainDays;
    }

    /** @return list<string> */
    public function getRainMissing(): array
    {
        return $this->rainMissing;
    }

    public function getRainMm(): ?float
    {
        return $this->rainMm;
    }

    public function getRainNormalMm(): ?float
    {
        return $this->rainNormalMm;
    }

    public function getRainPct(): ?float
    {
        return $this->rainPct;
    }

    public function setWetThresholdPct(int $pct): static
    {
        $this->wetThresholdPct = max(1, $pct);

        return $this;
    }

    public function getWetThresholdPct(): int
    {
        return $this->wetThresholdPct;
    }

    /** @param list<string> $names */
    public function setCutOffInWetCell(array $names): static
    {
        $this->cutOffInWetCell = $names;

        return $this;
    }

    /** @return list<string> */
    public function getCutOffInWetCell(): array
    {
        return $this->cutOffInWetCell;
    }

    /** @param array<string, mixed>|null $outlook */
    public function setOutlook(?array $outlook): static
    {
        $this->outlook = $outlook;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getOutlook(): ?array
    {
        return $this->outlook;
    }

    /** @param array<string, mixed> $exposure */
    public function setExposure(array $exposure): static
    {
        $this->exposure = $exposure;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getExposure(): array
    {
        return $this->exposure;
    }

    /** @param array<string, mixed>|null $run */
    public function setNotebookRun(?array $run): static
    {
        $this->notebookRun = $run;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getNotebookRun(): ?array
    {
        return $this->notebookRun;
    }

    /** @param array<string, mixed>|null $zones */
    public function setZones(?array $zones): static
    {
        $this->zones = $zones;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getZones(): ?array
    {
        return $this->zones;
    }

    /**
     * @param array<string, mixed>   $grid
     * @param list<list<float|null>> $recentMm
     * @param list<list<float|null>> $recentPct
     */
    public function setGrid(array $grid, array $recentMm, array $recentPct): static
    {
        $this->grid = $grid;
        $this->recentGridMm = $recentMm;
        $this->recentGridPct = $recentPct;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getGrid(): array
    {
        return $this->grid;
    }

    /** @return list<list<float|null>> */
    public function getRecentGridMm(): array
    {
        return $this->recentGridMm;
    }

    /** @return list<list<float|null>> */
    public function getRecentGridPct(): array
    {
        return $this->recentGridPct;
    }

    public function addWeek(Week $week): static
    {
        if (!$this->weeks->contains($week)) {
            $this->weeks->add($week);
        }

        return $this;
    }

    /** @return list<Week> oldest first */
    public function getWeeks(): array
    {
        return array_values($this->weeks->toArray());
    }
}
