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
        $this->targetDirectory = $targetDirectory;
        $this->imagine = new Imagine();
    }

    public function upload($file, string $additionnalPath = '', string $newFilename = ''): ?string
    {
        $finalPath = $this->getTargetDirectory() . (!empty($additionnalPath) ? $additionnalPath : '');
        $fileName = null;

        if (gettype($file) == 'string') {
            $newFilenamePI = pathinfo($newFilename);
            $fileName = $this->slugify($newFilenamePI['filename']) . '-' . uniqid() . '.' . $newFilenamePI['extension'];

            try {
                // Copy file from URL (type string = external URL)
                copy($file, $finalPath . '/' . $fileName);
            } catch (\Exception $e) {
                // ... handle exception if something happens during file upload
                // dump($file, $finalPath . '/' . $fileName, $e);
                // exit;
                $fileName = null;
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
                // dump($e);
                // exit;
                $fileName = null;
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

        // Don't resize image if it's smaller than max height and width
        if ($iwidth < $width && $iheight < $height) {
            return;
        // Recalculate $width because $height reached MAX_HEIGHT
        } elseif ($width / $height > $ratio) {
            $width = $height * $ratio;
        // Recalculate $height because $width reached MAX_WIDTH
        } else {
            $height = $width / $ratio;
        }

        $photo = $this->imagine->open($filename);
        $photo->resize(new Box($width, $height))->save($filename);
    }

    private function grabImageWithCurl($url, $saveto){
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

        $raw=curl_exec($ch);
        curl_close ($ch);

        dump($url, $saveto, $raw);
        exit;

        if(file_exists($saveto)) {
            unlink($saveto);
        }

        $fp = fopen($saveto, 'x');

        fwrite($fp, $raw);
        fclose($fp);
    }
}
