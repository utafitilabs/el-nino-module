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
use Uhifadhi\ElNino\Repository\ExposureRunRepository;

/**
 * ONE RUN OF THE EXPOSURE ANALYSIS, AND THE LAYERS IT PRODUCED: the
 * susceptibility patches, the flagged roads and the destinations, keyed by the
 * run date and the analysis commit the producer states as provenance.
 *
 * A re-run arrives as a new row and becomes the current one; the layers of
 * the run it replaces are dropped with it, and the provenance of every run
 * is kept so a figure on an old bulletin can still say which analysis it
 * was read against.
 */
#[ORM\Entity(repositoryClass: ExposureRunRepository::class)]
#[ORM\Table(name: 'el_nino_exposure_run')]
#[ORM\Index(name: 'idx_el_nino_exposure_run_area_current', columns: ['area_id', 'current'])]
class ExposureRun
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

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $runDate;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $analysisCommit;

    /** @var array<string, mixed> everything the producer said about how the layers were made */
    #[ORM\Column(type: 'json')]
    private array $provenance;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    #[ORM\Column(type: 'boolean')]
    private bool $current = true;

    /** @var Collection<int, Road> */
    #[ORM\OneToMany(targetEntity: Road::class, mappedBy: 'run', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $roads;

    /** @var Collection<int, Destination> */
    #[ORM\OneToMany(targetEntity: Destination::class, mappedBy: 'run', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $destinations;

    /** @var Collection<int, SusceptibilityPatch> */
    #[ORM\OneToMany(targetEntity: SusceptibilityPatch::class, mappedBy: 'run', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $patches;

    /** @var Collection<int, Boma> */
    #[ORM\OneToMany(targetEntity: Boma::class, mappedBy: 'run', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $bomas;

    /**
     * @param array<string, mixed> $provenance
     */
    public function __construct(AreaOfInterest $area, ?\DateTimeImmutable $runDate, ?string $analysisCommit, array $provenance, \DateTimeImmutable $importedAt)
    {
        $this->uuid = Uuid::v7();
        $this->area = $area;
        $this->runDate = $runDate;
        $this->analysisCommit = $analysisCommit;
        $this->provenance = $provenance;
        $this->importedAt = $importedAt;
        $this->roads = new ArrayCollection();
        $this->destinations = new ArrayCollection();
        $this->patches = new ArrayCollection();
        $this->bomas = new ArrayCollection();
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

    public function getRunDate(): ?\DateTimeImmutable
    {
        return $this->runDate;
    }

    public function getAnalysisCommit(): ?string
    {
        return $this->analysisCommit;
    }

    /** @return array<string, mixed> */
    public function getProvenance(): array
    {
        return $this->provenance;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function isCurrent(): bool
    {
        return $this->current;
    }

    public function supersede(): static
    {
        $this->current = false;
        $this->roads->clear();
        $this->destinations->clear();
        $this->patches->clear();
        $this->bomas->clear();

        return $this;
    }

    /** Whether this run is the one a provenance block describes. */
    public function matches(?\DateTimeImmutable $runDate, ?string $analysisCommit): bool
    {
        $sameDate = $this->runDate?->format('Y-m-d') === $runDate?->format('Y-m-d');

        return $sameDate && $this->analysisCommit === $analysisCommit;
    }

    public function addRoad(Road $road): static
    {
        if (!$this->roads->contains($road)) {
            $this->roads->add($road);
        }

        return $this;
    }

    public function addDestination(Destination $destination): static
    {
        if (!$this->destinations->contains($destination)) {
            $this->destinations->add($destination);
        }

        return $this;
    }

    public function addPatch(SusceptibilityPatch $patch): static
    {
        if (!$this->patches->contains($patch)) {
            $this->patches->add($patch);
        }

        return $this;
    }

    public function addBoma(Boma $boma): static
    {
        if (!$this->bomas->contains($boma)) {
            $this->bomas->add($boma);
        }

        return $this;
    }

    /** @return list<Boma> */
    public function getBomas(): array
    {
        return array_values($this->bomas->toArray());
    }

    /** @return list<Road> */
    public function getRoads(): array
    {
        return array_values($this->roads->toArray());
    }

    /** @return list<Destination> */
    public function getDestinations(): array
    {
        return array_values($this->destinations->toArray());
    }

    /** @return list<SusceptibilityPatch> */
    public function getPatches(): array
    {
        return array_values($this->patches->toArray());
    }
}
