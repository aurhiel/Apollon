<?php

namespace App\Entity;

use App\Repository\SampleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SampleRepository::class)
 */
class Sample
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private int $id;

    /**
     * @ORM\Column(type="integer")
     */
    private int $rateFaceA;

    /**
     * @ORM\Column(type="integer")
     */
    private int $rateFaceB;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $hasCover;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $hasGenericCover;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $rateCover = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $details = null;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $price;

    /**
     * @ORM\ManyToOne(targetEntity=Vinyl::class, inversedBy="samples")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Vinyl $vinyl;

    public function __toString(): string
    {
        return sprintf(
            '[#%d] face A: %d / face B: %d',
            $this->getId(),
            $this->getRateFaceA(),
            $this->getRateFaceB()
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRateFaceA(): int
    {
        return $this->rateFaceA;
    }

    public function setRateFaceA(int $rateFaceA): self
    {
        $this->rateFaceA = $rateFaceA;

        return $this;
    }

    public function getRateFaceB(): int
    {
        return $this->rateFaceB;
    }

    public function setRateFaceB(int $rateFaceB): self
    {
        $this->rateFaceB = $rateFaceB;

        return $this;
    }

    public function getHasCover(): bool
    {
        return $this->hasCover;
    }

    public function setHasCover(bool $hasCover): self
    {
        $this->hasCover = $hasCover;

        return $this;
    }

    public function getHasGenericCover(): bool
    {
        return $this->hasGenericCover;
    }

    public function setHasGenericCover(bool $hasGenericCover): self
    {
        $this->hasGenericCover = $hasGenericCover;

        return $this;
    }

    public function getRateCover(): ?int
    {
        return $this->rateCover;
    }

    public function setRateCover(?int $rateCover): self
    {
        $this->rateCover = $rateCover;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): self
    {
        $this->details = $details;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getVinyl(): Vinyl
    {
        return $this->vinyl;
    }

    public function setVinyl(?Vinyl $vinyl): self
    {
        $this->vinyl = $vinyl;

        return $this;
    }
}
