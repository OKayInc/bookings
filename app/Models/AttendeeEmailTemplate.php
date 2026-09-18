<?php
namespace App\Models;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
class AttendeeEmailTemplate extends Model {
    use HasBinaryUuid;
    protected $fillable = ['organization_id', 'kind', 'format', 'subject', 'body'];
}
