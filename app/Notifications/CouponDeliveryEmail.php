<?php

namespace App\Notifications;

use App\Enums\CouponDeliveryMethod;
use App\Models\Coupon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CouponDeliveryEmail extends Notification
{
    use Queueable;

    public function __construct(private readonly Coupon $coupon)
    {
    }

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $coupon = $this->coupon->loadMissing('organization');
        $url = route('public.coupons.view', $coupon->view_token);
        $mail = (new MailMessage)
            ->subject('A gift card / coupon from '.$coupon->organization->name)
            ->greeting('Hello'.($coupon->recipient_name ? ' '.$coupon->recipient_name : '').',')
            ->line(($coupon->purchaser_name ?: $coupon->organization->name).' sent you a gift card / coupon.');
        if ($coupon->message) {
            $mail->line($coupon->message);
        }
        $mail->action('View gift card / coupon', $url)
            ->line('The page is password protected. Ask the purchaser for the password; it is intentionally not included in this email.');
        if ($coupon->delivery_method === CouponDeliveryMethod::EmailQr) {
            $mail->line('A QR image for the protected link is attached.')
                ->attachData(app(\App\Domain\Coupons\CouponQrCodeService::class)->svg($url), 'gift-card-qr.svg', ['mime' => 'image/svg+xml']);
        }

        return $mail;
    }
}
