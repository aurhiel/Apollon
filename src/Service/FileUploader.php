<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;

class FileUploader
{
    private const MAX_WIDTH = 2000;
    private const MAX_HEIGHT = 2000;

    private $targetDirectory;
    private $imagine;

    public function __construct($targetDirectory)
    {
        $this->targetDirectory  = $targetDirectory;
        $this->imagine          = new Imagine();
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
                if (function_exists('mime_content_type') && preg_match('/image/', mime_content_type($finalPath . '/' . $fileName))) {
                    // Resize new uploaded file
                    $this->resize($finalPath . '/' . $fileName);
                }
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

    public function resize(string $filename): void
    {
        list($iwidth, $iheight) = getimagesize($filename);
        $ratio = $iwidth / $iheight;
        $width = self::MAX_WIDTH;
        $height = self::MAX_HEIGHT;
        if ($width / $height > $ratio) {
            $width = $height * $ratio;
        } else {
            $height = $width / $ratio;
        }

        $photo = $this->imagine->open($filename);
        $photo->resize(new Box($width, $height))->save($filename);
    }
}
