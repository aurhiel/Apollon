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
        if ($image->getAdvert()) {
            $img_filepath = $this->uploads_directory . '/adverts/' . $image->getFilename();
            if (file_exists($img_filepath))
                unlink($img_filepath);
        } else {
            // TODO others delete
        }
    }
}
