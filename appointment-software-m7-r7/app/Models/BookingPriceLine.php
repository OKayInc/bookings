<?php
namespace App\Models;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BookingPriceLine extends Model {
 use HasBinaryUuid;
 protected $fillable=['booking_id','source_type','source_uuid','label','line_type','quantity','amount_minor','metadata','position'];
 protected $hidden=['id','booking_id']; protected $appends=['uuid'];
 protected function casts(): array { return ['amount_minor'=>'integer','metadata'=>'array','position'=>'integer']; }
 public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
}
