<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MembershipExpiredNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $updatedCount;
    public array $details;

    public function __construct(int $updatedCount, array $details)
    {
        $this->updatedCount = $updatedCount;
        $this->details = $details;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notifikasi: Membership Expired - ' . now()->format('d M Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.memberships.expired',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
