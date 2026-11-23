<?php

namespace jdavidbakr\MailTracker;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes expired sent emails in bounded batches, honoring per-class retention.
 */
class SentEmailPurger
{
    /**
     * Maximum number of emails removed per batch.
     */
    private const BATCH_SIZE = 1000;

    /**
     * Maximum number of batches processed per retention group in one run.
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
        foreach ($this->groupStoredClassesByExpireDays() as $expireDays => $group) {
            if ($expireDays <= 0) {
                continue;
            }

            $this->purgeGroup($group['classes'], $group['has_null'], $expireDays);
        }
    }

    /**
     * Group the classes present in the database by their configured retention period.
     *
     * @return array<int, array{classes: list<string>, has_null: bool}>
     */
    private function groupStoredClassesByExpireDays(): array
    {
        $defaultDays = (int) config('mail-tracker.expire-days');
        $daysByClass = $this->getDaysByClass();

        $groups = [];

        foreach ($this->getStoredClasses() as $mailClass) {
            $days = $mailClass !== null && isset($daysByClass[$mailClass])
                ? $daysByClass[$mailClass]
                : $defaultDays;

            $groups[$days] ??= ['classes' => [], 'has_null' => false];

            if ($mailClass === null) {
                $groups[$days]['has_null'] = true;
            } else {
                $groups[$days]['classes'][] = $mailClass;
            }
        }

        return $groups;
    }

    /**
     * Build a class-to-days map from the grouped configuration.
     *
     * @return array<string, int>
     */
    private function getDaysByClass(): array
    {
        $daysByClass = [];

        foreach ((array) config('mail-tracker.expire-days-by-class') as $days => $classes) {
            foreach ((array) $classes as $mailClass) {
                $daysByClass[$mailClass] = (int) $days;
            }
        }

        return $daysByClass;
    }

    /**
     * @return list<string|null>
     */
    private function getStoredClasses(): array
    {
        return MailTracker::sentEmailModel()
            ->newQuery()
            ->distinct()
            ->pluck('mail_class')
            ->all();
    }

    /**
     * @param  list<string>  $classes
     */
    private function purgeGroup(array $classes, bool $hasNull, int $expireDays): void
    {
        if ($classes === [] && ! $hasNull) {
            return;
        }

        $cutoff = Carbon::now()->subDays($expireDays);

        for ($batch = 0; $batch < self::MAX_BATCHES; $batch++) {
            $emails = $this->getExpiredBatch($classes, $hasNull, $cutoff);

            if ($emails->isEmpty()) {
                return;
            }

            $this->deleteContentFiles($emails);
            $this->deleteEmails($emails);
        }
    }

    /**
     * @param  list<string>  $classes
     * @return Collection<int, Model>
     */
    private function getExpiredBatch(array $classes, bool $hasNull, Carbon $cutoff): Collection
    {
        return MailTracker::sentEmailModel()
            ->newQuery()
            ->where('created_at', '<', $cutoff)
            ->where(function ($query) use ($classes, $hasNull) {
                if ($classes === []) {
                    $query->whereNull('mail_class');

                    return;
                }

                $query->whereIn('mail_class', $classes);

                if ($hasNull) {
                    $query->orWhereNull('mail_class');
                }
            })
            ->select(['id', 'meta'])
            ->limit(self::BATCH_SIZE)
            ->get();
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
