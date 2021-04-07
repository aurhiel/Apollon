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
    private $track_face_A;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $track_face_B;

    /**
     * @ORM\ManyToMany(targetEntity=Artist::class, inversedBy="vinyls")
     */
    private $artists;

    /**
     * @ORM\Column(type="smallint")
     */
    private $quantity = 1;

    /**
     * @ORM\Column(type="smallint")
     */
    private $quantity_sold = 0;

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
        return $this->track_face_A;
    }

    public function setTrackFaceA(string $track_face_A): self
    {
        $this->track_face_A = $track_face_A;

        return $this;
    }

    public function getTrackFaceB(): ?string
    {
        return $this->track_face_B;
    }

    public function setTrackFaceB(string $track_face_B): self
    {
        $this->track_face_B = $track_face_B;

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
        return $this->quantity_sold;
    }

    public function setQuantitySold(int $quantity_sold): self
    {
        $this->quantity_sold = $quantity_sold;

        return $this;
    }
}
