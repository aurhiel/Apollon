<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;

class CSVParser
{
    public function __construct()
    {
    }

    public function parse(string $directory, string $filename, bool $ignoreFirstLine = true, string $delimiter = ','): array
    {
        $finder = new Finder();
        $finder->files()
            ->in($directory)
            ->name($filename)
        ;
        foreach ($finder as $file) { $csv = $file; }

        if (!isset($csv)) {
            throw new \RuntimeException(sprintf('Cannot find CSV file to import (in: %s, name: %s)', $directory, $filename));
        }

        $rows = array();
        if (($handle = fopen($csv->getRealPath(), "r")) !== FALSE) {
            $i = 0;
            while (($data = fgetcsv($handle, null, $delimiter)) !== FALSE) {
                $i++;
                if ($ignoreFirstLine && $i == 1) { continue; }
                $rows[] = $data;
            }
            fclose($handle);
        }

        return $rows;
    }
}
