<?php

namespace App\Mail;

use App\Models\Semester;
use App\Models\Student;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/**
 * A pupil's fee statement for a term emailed to the parent: the term's bill by item, payments
 * received and what is still owed.
 */
class ParentFeesMail extends Mailable
{
    public function __construct(
        public Student $student,
        public Semester $term,
        public array $fees,
        public Collection $items,
        public Collection $payments,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->student->full_name} - {$this->term->name} Fee Statement");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.parent-fees');
    }
}
