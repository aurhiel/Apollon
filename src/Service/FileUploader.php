<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploader
{
    private $targetDirectory;

    public function __construct($targetDirectory)
    {
        $this->targetDirectory = $targetDirectory;
    }

    public function upload($file, string $additionnalPath = '', string $newFilename = '')
    {
        $finalPath = $this->getTargetDirectory() . (!empty($additionnalPath) ? $additionnalPath : '');
        if (gettype($file) == 'string') {
            $newFilenamePI = pathinfo($newFilename);
            $fileName = $this->slugify($newFilenamePI['filename']) . '-' . uniqid() . '.' . $newFilenamePI['extension'];

            try {
                // Copy file from URL (type string = external URL)
                copy($file, $finalPath . '/' . $fileName);
            } catch (FileException $e) {
                // ... handle exception if something happens during file upload
                dump($e);
            }
        } else {
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $fileName = $this->slugify($originalFilename) . '-' . uniqid() . '.' . $file->guessExtension();

            try {
                $file->move($finalPath, $fileName);
            } catch (FileException $e) {
                // ... handle exception if something happens during file upload
                dump($e);
                exit;
            }
        }

        return $fileName;
    }

    public function getTargetDirectory()
    {
        return $this->targetDirectory;
    }

    public function slugify($string)
    {
        return transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $string);
    }
}
