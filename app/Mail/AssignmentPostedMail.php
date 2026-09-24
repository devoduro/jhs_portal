<?php

namespace App\Mail;

use App\Models\Assignment;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously (not queued - QUEUE_CONNECTION is 'database' with no worker
 * running in this environment, so a queued mail would just sit unsent) when a
 * teacher posts a new assignment. Deliberately links to the login page rather
 * than deep-linking to the assignment, since the student portal has no public,
 * unauthenticated view of assignment details to link to.
 */
class AssignmentPostedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Assignment $assignment,
        public Student $student,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Assignment: '.$this->assignment->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.assignment-posted',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
