<?php

namespace App\Modules\Billing\Mail;

use App\Modules\Billing\Models\Bill;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Bill $bill) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "账单通知 - {$this->bill->bill_number}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.bill-notification', with: ['bill' => $this->bill, 'user' => $this->bill->user]);
    }
}
