<?php

namespace App\EventListener;

use App\Entity\Image;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class ImageListener
{
    private $publicDirectory;

    public function __construct(string $publicDirectory)
    {
        $this->publicDirectory = $publicDirectory;
    }

    public function postRemove(Image $image, LifecycleEventArgs $event): void
    {
          $imgFilepath = $this->publicDirectory . '/' . $image->getPath();
          if (file_exists($imgFilepath)) {
              unlink($imgFilepath);
          }
    }
}
