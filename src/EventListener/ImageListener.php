<?php

namespace App\EventListener;

use App\Entity\Image;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class ImageListener
{
    private $uploads_directory;

    public function __construct(string $uploads_directory)
    {
        $this->uploads_directory = $uploads_directory;
    }

    public function postRemove(Image $image, LifecycleEventArgs $event): void
    {

        $isDeletable = ($image->getAdvert() || $image->getVinyl());
        $folder = $image->getAdvert() ? '/adverts/' : ($image->getVinyl() ? '/vinyls/' : null);

        if (null !== $folder) {
            $img_filepath = $this->uploads_directory . $folder . $image->getFilename();
            if (file_exists($img_filepath))
                unlink($img_filepath);
        } else {
            throw new \Exception("Can't guess where to delete image, implement it !");
        }
    }
}
