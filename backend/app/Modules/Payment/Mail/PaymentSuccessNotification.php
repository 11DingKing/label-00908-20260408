<?php

namespace App\Modules\Payment\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '支付成功通知');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-success',
            with: [
                'payment' => $this->payment,
                'user' => $this->payment->user,
                'bill' => $this->payment->bill,
            ],
        );
    }
}
