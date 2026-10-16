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
 * A ROAD SEGMENT THE ANALYSIS FLAGGED: likely cut, or at risk, once the
 * ground under it floods. Only the flagged segments arrive; the rest of the
 * network is nobody's business here.
 */
#[ORM\Entity]
#[ORM\Table(name: 'el_nino_road')]
#[ORM\Index(name: 'idx_el_nino_road_run', columns: ['run_id'])]
class Road
{
    public const string CUT = 'cut';
    public const string RISK = 'risk';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne(targetEntity: ExposureRun::class, inversedBy: 'roads')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ExposureRun $run;

    /** The segment, as GeoJSON on the way in and out — the column is PostGIS geometry. */
    #[ORM\Column(type: 'linestring')]
    private string $geom;

    #[ORM\Column(length: 10)]
    private string $cls;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $highway;

    /** How many metres of the segment run through the very-high class, contiguously. */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $runVeryHighM;

    public function __construct(ExposureRun $run, string $geom, string $cls, ?string $highway, ?int $runVeryHighM)
    {
        $this->run = $run;
        $this->geom = $geom;
        $this->cls = self::CUT === $cls ? self::CUT : self::RISK;
        $this->highway = $highway;
        $this->runVeryHighM = $runVeryHighM;
        $run->addRoad($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRun(): ExposureRun
    {
        return $this->run;
    }

    public function getGeom(): string
    {
        return $this->geom;
    }

    public function getCls(): string
    {
        return $this->cls;
    }

    public function isCut(): bool
    {
        return self::CUT === $this->cls;
    }

    public function getHighway(): ?string
    {
        return $this->highway;
    }

    public function getRunVeryHighM(): ?int
    {
        return $this->runVeryHighM;
    }
}
