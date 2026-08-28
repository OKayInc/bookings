<?php
namespace App\Models;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BookingAnswerFile extends Model {
 use HasBinaryUuid;
 protected $fillable=['booking_answer_id','booking_id','disk','path','original_name','mime_type','size_bytes','sha256','position'];
 protected $hidden=['id','booking_answer_id','booking_id','sha256']; protected $appends=['uuid'];
 protected function casts(): array { return ['size_bytes'=>'integer','position'=>'integer']; }
 public function answer(): BelongsTo { return $this->belongsTo(BookingAnswer::class,'booking_answer_id'); }
 public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
}
