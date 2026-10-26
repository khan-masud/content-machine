<?php

namespace App\Console\Commands;

use App\Jobs\PublishFacebookPostJob;
use App\Models\FacebookPublication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Content Machine's own Facebook scheduler, run every minute:
 * - scheduled publications that are due move to queued and get a job;
 * - queued rows whose job never ran (lost queue insert) get a new job;
 * - publishing rows stuck past the job timeout become uncertain, because the
 *   post may already be on the Page.
 */
class PublishDueFacebookPostsCommand extends Command
{
    public const STALE_QUEUED_MINUTES = 10;

    public const STALE_PUBLISHING_MINUTES = 10;

    protected $signature = 'facebook:publish-due {--limit=50 : Maximum publications to dispatch}';

    protected $description = 'Queue due Facebook Page posts and recover stuck ones';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        /** @var list<int> $ids */
        $ids = DB::transaction(function () use ($limit): array {
            $due = FacebookPublication::query()
                ->where(function ($query): void {
                    $query->where('state', FacebookPublication::SCHEDULED)
                        ->where('scheduled_for', '<=', now());
                })
                ->orWhere(function ($query): void {
                    $query->where('state', FacebookPublication::QUEUED)
                        ->where('updated_at', '<=', now()->subMinutes(self::STALE_QUEUED_MINUTES));
                })
                ->orderBy('id')
                ->limit($limit)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            foreach ($due as $publication) {
                // Touch queued rows too, so a recovered row is not re-sent every minute.
                $publication->forceFill(['state' => FacebookPublication::QUEUED, 'updated_at' => now()])->save();
            }

            return $due->pluck('id')->all();
        });

        foreach ($ids as $id) {
            PublishFacebookPostJob::dispatch($id);
        }

        $stuck = FacebookPublication::query()
            ->where('state', FacebookPublication::PUBLISHING)
            ->where('claimed_at', '<=', now()->subMinutes(self::STALE_PUBLISHING_MINUTES))
            ->update([
                'state' => FacebookPublication::UNCERTAIN,
                'lease_id' => null,
                'error' => 'The publish job stopped before Facebook answered. Check the Page, then mark it published or retry.',
                'updated_at' => now(),
            ]);

        $this->info(count($ids).' dispatched, '.$stuck.' marked uncertain.');

        return self::SUCCESS;
    }
}
