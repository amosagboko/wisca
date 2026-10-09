<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SchemeOfWorkBulkUpload
{
    public const FILENAME = 'WISCA-scheme-of-work-bulk-upload-template.csv';

    public function templateCsv(): string
    {
        $lines = [
            '# WISCA PEMS — Scheme of Work bulk-upload template',
            '# How to use:',
            '# 1. Keep the header row (week_number,title,learning_objectives).',
            '# 2. Add one row per weekly topic. Do not leave title blank.',
            '# 3. week_number must be a whole number from 1 to 52.',
            '# 4. Put each learning objective on its own line inside the cell, or separate objectives with " | ".',
            '# 5. Session, term, class and subject are chosen on the form — not in this file.',
            '# 6. Save as CSV (UTF-8) if you edit this in Excel, then upload it under "Bulk upload from template".',
            '# 7. A PDF or Word file on the form is still optional supplementary evidence only. It does not create topics.',
            'week_number,title,learning_objectives',
        ];

        $examples = [
            [1, 'Number counting', "Count numbers 1 to 20\nUnderstand place value\nUse counting in daily activities"],
            [2, 'Addition of whole numbers', "Add 2-digit numbers without regrouping | Add 2-digit numbers with regrouping | Solve simple word problems"],
            [3, 'Shapes in the environment', "Identify common 2D shapes\nDescribe sides and corners of shapes"],
        ];

        $stream = fopen('php://temp', 'r+');
        foreach ($lines as $line) {
            fwrite($stream, $line."\n");
        }
        foreach ($examples as $example) {
            fputcsv($stream, $example);
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return "\xEF\xBB\xBF".$csv;
    }

    /**
     * @return list<array{week_number: int, title: string, learning_objectives: string}>
     */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt'], true)) {
            throw ValidationException::withMessages([
                'bulk_template' => 'Upload the CSV template (save as CSV UTF-8 if you used Excel). PDF or Word files belong in supplementary evidence and do not create topics.',
            ]);
        }

        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'bulk_template' => 'The bulk-upload file could not be read. Download the template and try again.',
            ]);
        }

        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = null;
        $map = [];
        $topics = [];
        $rowNumber = 0;

        while (($row = fgetcsv($stream)) !== false) {
            $rowNumber++;
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $first = trim((string) ($row[0] ?? ''));
            if (str_starts_with($first, '#')) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($cell) => $this->normalizeHeader((string) $cell), $row);
                $map = $this->headerMap($header);
                if ($map['week_number'] === null || $map['title'] === null || $map['learning_objectives'] === null) {
                    throw ValidationException::withMessages([
                        'bulk_template' => 'The CSV header must include week_number, title, and learning_objectives. Download the template and keep those column names.',
                    ]);
                }

                continue;
            }

            $week = trim((string) ($row[$map['week_number']] ?? ''));
            $title = trim((string) ($row[$map['title']] ?? ''));
            $objectives = trim((string) ($row[$map['learning_objectives']] ?? ''));

            if ($week === '' && $title === '' && $objectives === '') {
                continue;
            }

            if (! ctype_digit($week) || (int) $week < 1 || (int) $week > 52) {
                throw ValidationException::withMessages([
                    'bulk_template' => "Row {$rowNumber}: week_number must be a whole number from 1 to 52.",
                ]);
            }

            if ($title === '') {
                throw ValidationException::withMessages([
                    'bulk_template' => "Row {$rowNumber}: every topic needs a title.",
                ]);
            }

            $topics[] = [
                'week_number' => (int) $week,
                'title' => $title,
                'learning_objectives' => $this->normalizeObjectives($objectives),
            ];
        }

        fclose($stream);

        if ($topics === []) {
            throw ValidationException::withMessages([
                'bulk_template' => 'The bulk-upload file has no topic rows. Keep the header and add at least one weekly topic.',
            ]);
        }

        return $topics;
    }

    /**
     * @param  list<string|null>  $row
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = str_replace(['-', ' '], '_', $header);

        return preg_replace('/_+/', '_', $header) ?? $header;
    }

    /**
     * @param  list<string>  $header
     * @return array{week_number: int|null, title: int|null, learning_objectives: int|null}
     */
    protected function headerMap(array $header): array
    {
        $aliases = [
            'week_number' => ['week_number', 'week', 'week_no', 'weekno'],
            'title' => ['title', 'topic', 'topic_title', 'topic_name'],
            'learning_objectives' => ['learning_objectives', 'learning_objective', 'objectives', 'objective', 'los'],
        ];

        $map = ['week_number' => null, 'title' => null, 'learning_objectives' => null];

        foreach ($header as $index => $name) {
            foreach ($aliases as $field => $options) {
                if ($map[$field] === null && in_array($name, $options, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    protected function normalizeObjectives(string $value): string
    {
        $parts = preg_split('/\s*\|\s*|\n/', $value) ?: [];
        $lines = array_values(array_filter(array_map('trim', $parts), fn ($line) => $line !== ''));

        return implode("\n", $lines);
    }
}
