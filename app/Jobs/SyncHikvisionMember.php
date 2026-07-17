<?php

namespace App\Jobs;

use App\HikvisionUserService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncHikvisionMember implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $userId,
        public string $validityStart,
        public string $validityEnd,
    ) {}

    public function uniqueId(): string
    {
        return "hikvision-member:{$this->userId}";
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('hikvision-user-sync'))
                ->releaseAfter(5)
                ->expireAfter(70),
        ];
    }

    public function handle(HikvisionUserService $hikvisionUserService): void
    {
        $user = User::query()
            ->where('role', 'member')
            ->find($this->userId, ['id', 'name']);

        if ($user === null) {
            return;
        }

        $existingEmployeeNumbers = $hikvisionUserService->existingEmployeeNumbers([$user->id]);

        if (in_array((string) $user->id, $existingEmployeeNumbers, true)) {
            return;
        }

        $hikvisionUserService->sync(
            $user,
            Carbon::parse($this->validityStart)->startOfDay(),
            Carbon::parse($this->validityEnd)->endOfDay(),
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Failed to sync member to Hikvision from bulk queue', [
            'user_id' => $this->userId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
