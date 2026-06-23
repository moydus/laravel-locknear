<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Meilisearch\Client;

class SetupMeilisearch extends Command
{
    protected $signature = 'locknear:setup-meilisearch {--fresh : Flush and re-import all companies}';

    protected $description = 'Configure Meilisearch index settings and import searchable companies';

    public function handle(): int
    {
        if (config('scout.driver') !== 'meilisearch') {
            $this->error('SCOUT_DRIVER must be set to meilisearch in .env');

            return self::FAILURE;
        }

        $host = config('scout.meilisearch.host');
        $key = config('scout.meilisearch.key');

        if (!$host) {
            $this->error('MEILISEARCH_HOST is not configured');

            return self::FAILURE;
        }

        $client = new Client($host, $key);
        $indexName = (new Company)->searchableAs();
        $index = $client->index($indexName);

        $this->info("Configuring index: {$indexName}");

        $index->updateSettings([
            'searchableAttributes' => ['name', 'city', 'state', 'zip', 'description', 'address'],
            'filterableAttributes' => ['is_active', 'state', 'city', 'zip', 'is_claimed', 'is_verified'],
            'sortableAttributes' => ['rating', 'review_count', 'name'],
            'rankingRules' => [
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
                'rating:desc',
                'review_count:desc',
            ],
        ]);

        if ($this->option('fresh')) {
            $this->warn('Flushing existing documents…');
            $index->deleteAllDocuments();
        }

        $this->info('Importing companies via Scout…');
        $this->call('scout:import', ['model' => Company::class]);

        $this->info('Meilisearch setup complete.');

        return self::SUCCESS;
    }
}
