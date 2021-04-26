<?php

namespace App\Entity;

use App\Repository\VinylRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VinylRepository::class)
 */
class Vinyl
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $rpm = 45;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $trackFaceA;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $trackFaceAYoutubeID;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $trackFaceB;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $trackFaceBYoutubeID;

    /**
     * @ORM\ManyToMany(targetEntity=Artist::class, inversedBy="vinyls")
     * @ORM\OrderBy({"name" = "ASC"})
     */
    private $artists;

    /**
     * @ORM\Column(type="smallint")
     */
    private $quantity = 1;

    /**
     * @ORM\Column(type="smallint")
     */
    private $quantitySold = 0;

    public function __construct()
    {
        $this->artists = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRpm(): ?int
    {
        return $this->rpm;
    }

    public function setRpm(int $rpm): self
    {
        $this->rpm = $rpm;

        return $this;
    }

    public function getTrackFaceA(): ?string
    {
        return $this->trackFaceA;
    }

    public function setTrackFaceA(string $trackFaceA): self
    {
        $this->trackFaceA = $trackFaceA;

        return $this;
    }

    public function getTrackFaceAYoutubeID(): ?string
    {
        return $this->trackFaceAYoutubeID;
    }

    public function setTrackFaceAYoutubeID(?string $trackFaceAYoutubeID): self
    {
        $this->trackFaceAYoutubeID = $trackFaceAYoutubeID;

        return $this;
    }

    public function getTrackFaceB(): ?string
    {
        return $this->trackFaceB;
    }

    public function setTrackFaceB(string $trackFaceB): self
    {
        $this->trackFaceB = $trackFaceB;

        return $this;
    }

    public function getTrackFaceBYoutubeID(): ?string
    {
        return $this->trackFaceBYoutubeID;
    }

    public function setTrackFaceBYoutubeID(?string $trackFaceBYoutubeID): self
    {
        $this->trackFaceBYoutubeID = $trackFaceBYoutubeID;

        return $this;
    }

    /**
     * @return Collection|Artist[]
     */
    public function getArtists(): Collection
    {
        return $this->artists;
    }

    public function addArtist(Artist $artist): self
    {
        if (!$this->artists->contains($artist)) {
            $this->artists[] = $artist;
        }

        return $this;
    }

    public function removeArtist(Artist $artist): self
    {
        $this->artists->removeElement($artist);

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getQuantitySold(): ?int
    {
        return $this->quantitySold;
    }

    public function setQuantitySold(int $quantitySold): self
    {
        $this->quantitySold = $quantitySold;

        return $this;
    }
}
