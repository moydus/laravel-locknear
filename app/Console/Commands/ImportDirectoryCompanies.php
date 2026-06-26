<?php

namespace App\Console\Commands;

use App\Services\DirectoryImportPipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use JsonException;

class ImportDirectoryCompanies extends Command
{
    protected $signature = 'locknear:import-directory
        {file : JSON or CSV file exported from a directory scraper/provider}
        {--source=google_maps : Data source label, e.g. google_maps, yelp, apple_maps, manual}
        {--owner-user-id= : Internal owner user id used for unclaimed imported companies}
        {--format=auto : auto, json, or csv}
        {--limit=0 : Stop after N records; 0 imports all}';

    protected $description = 'Import directory company data through the identity matcher and claim pipeline';

    public function handle(DirectoryImportPipeline $pipeline): int
    {
        $file = (string) $this->argument('file');
        $source = (string) $this->option('source');
        $ownerUserId = (int) $this->option('owner-user-id');
        $limit = (int) $this->option('limit');

        if ($ownerUserId <= 0) {
            $this->error('--owner-user-id is required and must be a valid user id.');

            return self::FAILURE;
        }

        if (!is_file($file) || !is_readable($file)) {
            $this->error("File is not readable: {$file}");

            return self::FAILURE;
        }

        try {
            $records = $this->readRecords($file, (string) $this->option('format'));
        } catch (JsonException $exception) {
            $this->error("Invalid JSON: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $imported = 0;
        $failed = 0;

        foreach ($records as $record) {
            if ($limit > 0 && $imported >= $limit) {
                break;
            }

            try {
                $company = $pipeline->import($record, $source, $ownerUserId);
                $imported++;
                $this->line("Imported #{$company->id}: {$company->name}");
            } catch (\Throwable $exception) {
                $failed++;
                $name = Arr::get($record, 'name', Arr::get($record, 'title', 'unknown'));
                $this->warn("Skipped {$name}: {$exception->getMessage()}");
            }
        }

        $this->info("Directory import complete. Imported: {$imported}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function readRecords(string $file, string $format): iterable
    {
        $format = $format === 'auto' ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : $format;

        return match ($format) {
            'json' => $this->readJson($file),
            'csv' => $this->readCsv($file),
            default => throw new \InvalidArgumentException("Unsupported import format: {$format}"),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws JsonException
     */
    private function readJson(string $file): array
    {
        $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }

        if (isset($decoded['results']) && is_array($decoded['results'])) {
            return $decoded['results'];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCsv(string $file): array
    {
        $handle = fopen($file, 'rb');

        if (!$handle) {
            throw new \RuntimeException("Unable to open CSV: {$file}");
        }

        $header = fgetcsv($handle);
        $records = [];

        if (!$header) {
            fclose($handle);

            return $records;
        }

        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);

        while (($row = fgetcsv($handle)) !== false) {
            $record = [];

            foreach ($header as $index => $key) {
                $record[$this->mapCsvKey($key)] = $row[$index] ?? null;
            }

            $records[] = $record;
        }

        fclose($handle);

        return $records;
    }

    private function mapCsvKey(string $key): string
    {
        return match ($key) {
            'title', 'business_name', 'company_name' => 'name',
            'phone_number', 'phone' => 'phone',
            'website_url', 'site', 'url' => 'website',
            'reviews', 'reviews_count' => 'review_count',
            'place_id', 'google_place_id' => 'google_place_id',
            'latitude', 'lat' => 'latitude',
            'longitude', 'lng', 'lon' => 'longitude',
            'postal_code', 'zipcode' => 'zip',
            default => $key,
        };
    }
}
