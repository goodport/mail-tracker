<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes expired sent emails in bounded batches, honoring per-type retention.
 */
class SentEmailPurger
{
    /**
     * Maximum number of emails removed per batch.
     */
    private const BATCH_SIZE = 1000;

    /**
     * Maximum number of batches processed per type group in one run.
     */
    private const MAX_BATCHES = 10;

    /**
     * Purge expired emails.
     */
    public function purge(): void
    {
        $this->purgeExpiredEmails();
    }

    /**
     * Delete emails for every retention group that keeps data for a limited time.
     */
    private function purgeExpiredEmails(): void
    {
        foreach ($this->groupStoredTypesByExpireDays() as $expireDays => $types) {
            if ($expireDays <= 0) {
                continue;
            }

            $this->purgeTypes($types, $expireDays);
        }
    }

    /**
     * Group the types present in the database by their configured retention period.
     *
     * @return array<int, list<string>>
     */
    private function groupStoredTypesByExpireDays(): array
    {
        $defaultDays = (int) config('mail-tracker.expire-days');
        $daysByType = (array) config('mail-tracker.expire-days-by-type');

        $groups = [];

        foreach ($this->getStoredTypes() as $type) {
            $days = array_key_exists($type, $daysByType)
                ? (int) $daysByType[$type]
                : $defaultDays;

            $groups[$days][] = $type;
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    private function getStoredTypes(): array
    {
        return MailTracker::sentEmailModel()
            ->newQuery()
            ->distinct()
            ->pluck('type')
            ->all();
    }

    /**
     * @param  list<string>  $types
     */
    private function purgeTypes(array $types, int $expireDays): void
    {
        $cutoff = Carbon::now()->subDays($expireDays);

        for ($batch = 0; $batch < self::MAX_BATCHES; $batch++) {
            $emails = MailTracker::sentEmailModel()
                ->newQuery()
                ->whereIn('type', $types)
                ->where('created_at', '<', $cutoff)
                ->select(['id', 'meta'])
                ->limit(self::BATCH_SIZE)
                ->get();

            if ($emails->isEmpty()) {
                return;
            }

            $this->deleteContentFiles($emails);
            $this->deleteEmails($emails);
        }
    }

    /**
     * @param  Collection<int, Model>  $emails
     */
    private function deleteContentFiles(Collection $emails): void
    {
        $paths = $emails
            ->map(fn ($email) => $email->meta?->get('content_file_path'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($paths === []) {
            return;
        }

        Storage::disk(config('mail-tracker.tracker-filesystem'))->delete($paths);
    }

    /**
     * @param  Collection<int, Model>  $emails
     */
    private function deleteEmails(Collection $emails): void
    {
        $ids = $emails->modelKeys();

        MailTracker::sentEmailUrlClickedModel()
            ->newQuery()
            ->whereIn('sent_email_id', $ids)
            ->delete();

        MailTracker::sentEmailModel()
            ->newQuery()
            ->whereIn('id', $ids)
            ->delete();
    }
}
