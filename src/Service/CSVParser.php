<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class CSVParser
{
    public const RELATIVE_DIRECTORY = '/uploads/csv-parser';
    public const MAIN_DIRECTORY = '../public/uploads/csv-parser';

    public function __construct()
    {
    }

    public function parse(string $filename, bool $ignoreFirstLine = true, string $delimiter = ','): array
    {
        $finder = new Finder();
        $finder->files()
            ->in(self::MAIN_DIRECTORY)
            ->name($filename)
        ;
        foreach ($finder as $file) { $csv = $file; }

        if (!isset($csv)) {
            throw new \RuntimeException(sprintf('Cannot find CSV file to import (in: %s, name: %s)', self::MAIN_DIRECTORY, $filename));
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

    public function list(): array
    {
        $finder = new Finder();
        $finder->files()
            ->in(self::MAIN_DIRECTORY)
        ;

        $CSVs = [];
        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            if('csv' === $file->getExtension()) {
                $CSVs[] = $file->getFilename();
            }
        }

        sort($CSVs);

        return $CSVs;
    }

    public function export(string $filename, array $rows): string
    {
        if (count($rows) < 1) {
            throw new \RuntimeException('No rows to export !');
        }

        $filepath = self::MAIN_DIRECTORY . '/' . $filename;
        $stream = fopen($filepath, 'w');

        $headers = array_keys($rows[0]);
        // Write first line as headers if givens $rows have associative keys
        if (isset($headers[0]) && is_string($headers[0])) {
            fputcsv($stream, $headers);
        }

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        fclose($stream);

        return self::RELATIVE_DIRECTORY . '/' . $filename;
    }
}
