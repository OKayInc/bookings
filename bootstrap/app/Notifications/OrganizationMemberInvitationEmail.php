<?php

namespace App\Notifications;

use App\Models\OrganizationMemberInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationMemberInvitationEmail extends Notification
{
    use Queueable;

    public function __construct(
        private readonly OrganizationMemberInvitation $invitation,
        private readonly string $token,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invitation = $this->invitation->loadMissing(['organization', 'invitedBy']);
        $inviter = $invitation->invitedBy?->full_name;

        $message = (new MailMessage)
            ->subject('Invitation to join '.$invitation->organization->name)
            ->greeting('Hello,');

        if ($inviter) {
            $message->line($inviter.' invited you to join '.$invitation->organization->name.' on '.config('app.name').'.');
        } else {
            $message->line('You have been invited to join '.$invitation->organization->name.' on '.config('app.name').'.');
        }

        return $message
            ->line('Organization role: '.ucfirst($invitation->role->value))
            ->action('Accept invitation', route('organization-invitations.show', $this->token))
            ->line('This invitation expires '.$invitation->expires_at_utc->utc()->format('M j, Y \a\t g:i A').' UTC. If you were not expecting it, you can ignore this email.');
    }
}
