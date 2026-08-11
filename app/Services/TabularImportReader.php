<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;
use SplFileObject;
use Throwable;
use ZipArchive;

class TabularImportReader
{
    /**
     * @return list<array{row_number: int, values: list<string>}>
     */
    public function read(UploadedFile $file): array
    {
        return match ($this->extension($file)) {
            'csv' => $this->readCsv($file),
            'xlsx' => $this->readXlsx($file),
            default => throw ValidationException::withMessages(['file' => 'Upload a CSV or XLSX source file.']),
        };
    }

    public function extension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => 'Upload a CSV or XLSX source file.']);
        }

        return $extension;
    }

    /** @return list<array{row_number: int, values: list<string>}> */
    private function readCsv(UploadedFile $file): array
    {
        $csv = new SplFileObject($this->path($file));
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $rows = [];
        $rowNumber = 0;

        while (! $csv->eof()) {
            $values = $csv->fgetcsv();
            $rowNumber++;

            if (! is_array($values) || $values === [null]) {
                continue;
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'values' => array_map(fn (mixed $value): string => trim((string) $value), $values),
            ];
        }

        return $rows;
    }

    /** @return list<array{row_number: int, values: list<string>}> */
    private function readXlsx(UploadedFile $file): array
    {
        $this->validateXlsxArchive($file);
        $reader = new Reader;
        $rows = [];
        $sheetCount = 0;

        try {
            $reader->open($this->path($file));

            foreach ($reader->getSheetIterator() as $sheet) {
                $sheetCount++;
                if ($sheetCount > 1) {
                    throw ValidationException::withMessages([
                        'file' => 'The XLSX source must contain exactly one worksheet.',
                    ]);
                }

                $rowNumber = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    if ($row->isEmpty()) {
                        continue;
                    }

                    foreach ($row->cells as $cell) {
                        if ($cell instanceof FormulaCell) {
                            throw ValidationException::withMessages([
                                'file' => "Spreadsheet formulas are not allowed (row {$rowNumber}). Replace formulas with their approved values.",
                            ]);
                        }
                    }

                    $rows[] = [
                        'row_number' => $rowNumber,
                        'values' => array_values(array_map(
                            fn (mixed $value): string => $this->cellValue($value, $rowNumber),
                            $row->toArray(),
                        )),
                    ];
                }
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => 'The XLSX source could not be read. Use an unencrypted workbook generated from the controlled template.',
            ]);
        } finally {
            $reader->close();
        }

        if ($sheetCount !== 1) {
            throw ValidationException::withMessages(['file' => 'The XLSX source must contain exactly one worksheet.']);
        }

        return $rows;
    }

    private function validateXlsxArchive(UploadedFile $file): void
    {
        $archive = new ZipArchive;
        if ($archive->open($this->path($file)) !== true) {
            throw ValidationException::withMessages(['file' => 'The XLSX source is not a valid workbook archive.']);
        }

        try {
            if ($archive->numFiles > 500) {
                throw ValidationException::withMessages(['file' => 'The XLSX source contains too many archive entries.']);
            }

            $uncompressedBytes = 0;
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                if (! is_array($entry)) {
                    throw ValidationException::withMessages(['file' => 'The XLSX source archive could not be inspected.']);
                }

                $entryName = strtolower($entry['name']);
                if (str_starts_with($entryName, 'xl/externallinks/') || str_ends_with($entryName, 'vbaproject.bin')) {
                    throw ValidationException::withMessages([
                        'file' => 'External workbook links and executable spreadsheet content are not allowed.',
                    ]);
                }

                $uncompressedBytes += $entry['size'];
                if ($uncompressedBytes > 50 * 1024 * 1024) {
                    throw ValidationException::withMessages([
                        'file' => 'The XLSX source expands beyond the 50 MB processing limit.',
                    ]);
                }
            }
        } finally {
            $archive->close();
        }
    }

    private function cellValue(mixed $value, int $rowNumber): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        throw ValidationException::withMessages([
            'file' => "The XLSX source contains an unsupported value at row {$rowNumber}.",
        ]);
    }

    private function path(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded source file is no longer available.']);
        }

        return $path;
    }
}
