<?php
namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;

class WebhookEndpoint extends Model
{
    use HasBinaryUuid;
    protected $guarded = ['id'];
    protected $hidden = ['id', 'organization_id', 'secret'];
    protected function casts(): array { return ['secret' => 'encrypted', 'events' => 'array', 'is_active' => 'boolean', 'version' => 'integer']; }
    public function organization() { return $this->belongsTo(Organization::class); }
    public function deliveries() { return $this->hasMany(WebhookDelivery::class); }
}
