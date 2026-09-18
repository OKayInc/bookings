<?php
namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasBinaryUuid;
    protected $guarded = ['id'];
    protected $hidden = ['id', 'organization_id', 'webhook_endpoint_id', 'claim_token'];
    protected function casts(): array { return ['available_at' => 'immutable_datetime', 'claimed_at' => 'immutable_datetime', 'delivered_at' => 'immutable_datetime', 'attempts' => 'integer', 'cycle_attempts' => 'integer', 'endpoint_version' => 'integer']; }
    public function endpoint() { return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id'); }
    public function attemptsLog() { return $this->hasMany(WebhookAttempt::class)->orderByDesc('created_at'); }
}
