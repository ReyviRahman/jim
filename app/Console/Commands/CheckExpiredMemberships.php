<?php

namespace App\Console\Commands;

use App\Mail\MembershipExpiredNotification;
use App\Models\Membership;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckExpiredMemberships extends Command
{
    protected $signature = 'memberships:check-expired
                            {--dry-run : Jalankan tanpa mengupdate database}';

    protected $description = 'Cek dan update membership yang sudah expired (lewat tenggat atau sesi habis).';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $now = Carbon::now()->startOfDay();

        $this->info("Memeriksa membership aktif... (Timestamp: {$now->toDateTimeString()})");

        $memberships = Membership::where('status', 'active')->with('user')->get();

        if ($memberships->isEmpty()) {
            $this->warn('Tidak ada membership aktif yang perlu dicek.');
            Log::info('[CheckExpiredMemberships] Tidak ada membership aktif.');
            return self::SUCCESS;
        }

        $updatedCount = 0;
        $details = [];

        foreach ($memberships as $membership) {
            $shouldComplete = false;
            $reason = '';

            switch ($membership->type) {
                case 'membership':
                case 'visit':
                    if ($membership->membership_end_date && Carbon::parse($membership->membership_end_date)->startOfDay()->lt($now)) {
                        $shouldComplete = true;
                        $reason = 'Membership end date expired (' . Carbon::parse($membership->membership_end_date)->format('d M Y') . ')';
                    }
                    break;

                case 'pt':
                    $ptExpired = $membership->pt_end_date && Carbon::parse($membership->pt_end_date)->startOfDay()->lt($now);
                    $noSessions = $membership->remaining_sessions !== null && $membership->remaining_sessions <= 0;

                    if ($ptExpired && $noSessions) {
                        $shouldComplete = true;
                        $reason = 'PT end date expired (' . Carbon::parse($membership->pt_end_date)->format('d M Y') . ') dan remaining sessions habis (' . $membership->remaining_sessions . ')';
                    } elseif ($ptExpired) {
                        $shouldComplete = true;
                        $reason = 'PT end date expired (' . Carbon::parse($membership->pt_end_date)->format('d M Y') . ')';
                    } elseif ($noSessions) {
                        $shouldComplete = true;
                        $reason = 'Remaining sessions habis (' . $membership->remaining_sessions . ')';
                    }
                    break;

                case 'bundle_pt_membership':
                    $gymExpired = $membership->membership_end_date && Carbon::parse($membership->membership_end_date)->startOfDay()->lt($now);
                    $ptExpired = $membership->pt_end_date && Carbon::parse($membership->pt_end_date)->startOfDay()->lt($now);
                    $noSessions = $membership->remaining_sessions !== null && $membership->remaining_sessions <= 0;

                    $reasons = [];
                    if ($gymExpired) {
                        $reasons[] = 'Gym end date expired (' . Carbon::parse($membership->membership_end_date)->format('d M Y') . ')';
                    }
                    if ($ptExpired) {
                        $reasons[] = 'PT end date expired (' . Carbon::parse($membership->pt_end_date)->format('d M Y') . ')';
                    }
                    if ($noSessions) {
                        $reasons[] = 'Remaining sessions habis (' . $membership->remaining_sessions . ')';
                    }

                    if (!empty($reasons)) {
                        $shouldComplete = true;
                        $reason = implode(' + ', $reasons);
                    }
                    break;
            }

            if ($shouldComplete) {
                if (! $dryRun) {
                    $membership->update([
                        'status' => 'completed',
                        'is_active' => false,
                    ]);
                }

                $details[] = [
                    'id' => $membership->id,
                    'type' => $membership->type,
                    'reason' => $reason,
                    'user_id' => $membership->user_id,
                    'user_name' => $membership->user->name ?? 'N/A',
                ];

                $updatedCount++;

                $actionText = $dryRun ? '[DRY-RUN] Would update' : 'Updated';
                Log::info("[CheckExpiredMemberships] {$actionText} membership #{$membership->id} (type: {$membership->type}, user: {$membership->user_id}) - {$reason}");
            }
        }

        if ($dryRun) {
            $this->warn("DRY RUN: {$updatedCount} membership akan diupdate ke completed.");
        } else {
            $this->info("{$updatedCount} membership berhasil diupdate ke completed.");
        }

        // Kirim email notifikasi jika ada yang diupdate
        if ($updatedCount > 0) {
            $this->sendEmailNotification($updatedCount, $details);
        }

        Log::info("[CheckExpiredMemberships] Selesai. Total updated: {$updatedCount}");

        return self::SUCCESS;
    }

    private function sendEmailNotification(int $updatedCount, array $details): void
    {
        try {
            $recipient = 'reyvirahman@gmail.com';

            $mail = new MembershipExpiredNotification($updatedCount, $details);

            Mail::to($recipient)->send($mail);

            $this->info('Email notifikasi telah dikirim ke: ' . $recipient);
            Log::info('[CheckExpiredMemberships] Email notifikasi dikirim ke: ' . $recipient);
        } catch (\Exception $e) {
            Log::error('[CheckExpiredMemberships] Gagal mengirim email: ' . $e->getMessage());
            $this->error('Gagal mengirim email notifikasi: ' . $e->getMessage());
        }
    }
}
