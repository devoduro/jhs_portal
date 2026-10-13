<?php

namespace App\Mail;

use App\Models\Semester;
use App\Models\Student;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A pupil's terminal results emailed to the parent: subject scores, position and a signed link
 * to the full printable report card. Sent synchronously (no queue worker runs here).
 */
class ParentResultsMail extends Mailable
{
    public function __construct(
        public Student $student,
        public Semester $term,
        public array $report,
        public int $roll,
        public string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->student->full_name} - {$this->term->name} Terminal Report");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.parent-results');
    }
}
