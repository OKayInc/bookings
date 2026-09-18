<?php
namespace App\Models;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
class WebhookAttempt extends Model
{
    use HasBinaryUuid;
    protected $guarded = ['id'];
}
