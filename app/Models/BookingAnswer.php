<?php
namespace App\Models;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class BookingAnswer extends Model {
 use HasBinaryUuid;
 protected $fillable=['booking_id','appointment_question_id','question_uuid_snapshot','question_label','question_type','value_json','normalized_json','position'];
 protected $hidden=['id','booking_id','appointment_question_id']; protected $appends=['uuid'];
 protected function casts(): array { return ['value_json'=>'array','normalized_json'=>'array','position'=>'integer']; }
 public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
 public function question(): BelongsTo { return $this->belongsTo(AppointmentQuestion::class,'appointment_question_id'); }
 public function files(): HasMany { return $this->hasMany(BookingAnswerFile::class)->orderBy('position'); }
}
