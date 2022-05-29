<?php

namespace App\Entity;

use App\Repository\ImageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ImageRepository::class)
 */
class Image
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
    private $filename;

    /**
     * @ORM\ManyToOne(targetEntity=Advert::class, inversedBy="images")
     */
    private $advert;

    /**
     * @ORM\ManyToOne(targetEntity=Vinyl::class, inversedBy="images")
     */
    private $vinyl;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getPath(): string
    {
        $path = 'uploads/' . ($this->advert ? 'adverts/' . $this->advert->getId() : ($this->vinyl ? 'vinyls/' . $this->vinyl->getId() : ''));
        $fullpath = $path . '/' . $this->filename;
        if ($this->advert || $this->vinyl) {
            if (!file_exists($fullpath)) {
                $oldPath = 'uploads/' . ($this->advert ? 'adverts' : 'vinyls');
                if (file_exists($oldPath . '/' . $this->filename)) {
                    if (!is_dir($path)) {
                      mkdir($path);
                    }
                    rename($oldPath . '/' . $this->filename, $fullpath);
                }
            }
        }
        return $fullpath;
    }

    public function getAdvert(): ?Advert
    {
        return $this->advert;
    }

    public function setAdvert(?Advert $advert): self
    {
        $this->advert = $advert;

        return $this;
    }

    public function getVinyl(): ?Vinyl
    {
        return $this->vinyl;
    }

    public function setVinyl(?Vinyl $vinyl): self
    {
        $this->vinyl = $vinyl;

        return $this;
    }
}
