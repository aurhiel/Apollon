<?php

namespace App\Entity;

use App\Repository\AdvertRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AdvertRepository::class)
 */
class Advert
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $title;

    /**
     * @ORM\Column(type="text")
     */
    private $description;

    /**
     * @ORM\Column(type="float")
     */
    private $price;

    /**
     * @ORM\OneToMany(targetEntity=InSale::class, mappedBy="advert")
     */
    private $inSales;

    /**
     * @ORM\OneToMany(targetEntity=Image::class, mappedBy="advert")
     */
    private $images;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $is_sold;

    public function __construct()
    {
        $this->inSales = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return Collection|InSale[]
     */
    public function getInSales(): Collection
    {
        return $this->inSales;
    }

    public function addInSale(InSale $inSale): self
    {
        if (!$this->inSales->contains($inSale)) {
            $this->inSales[] = $inSale;
            $inSale->setAdvert($this);
        }

        return $this;
    }

    public function removeInSale(InSale $inSale): self
    {
        if ($this->inSales->removeElement($inSale)) {
            // set the owning side to null (unless already changed)
            if ($inSale->getAdvert() === $this) {
                $inSale->setAdvert(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Image[]
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(Image $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images[] = $image;
            $image->setAdvert($this);
        }

        return $this;
    }

    public function removeImage(Image $image): self
    {
        if ($this->images->removeElement($image)) {
            // set the owning side to null (unless already changed)
            if ($image->getAdvert() === $this) {
                $image->setAdvert(null);
            }
        }

        return $this;
    }

    public function getIsSold(): ?bool
    {
        return $this->is_sold;
    }

    public function setIsSold(?bool $is_sold): self
    {
        $this->is_sold = $is_sold;

        return $this;
    }
}
