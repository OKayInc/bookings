<?php
namespace App\Domain\Questionnaires;
use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
class QuestionnairePersistenceService {
 public function persist(Booking $booking, QuestionnaireSubmission $submission): void {
   $disk=(string)config('questionnaire.file_disk','local');
   foreach ($submission->answers as $row) {
     $q=$row['question'];
     $answer=$booking->answers()->create(['appointment_question_id'=>$q->getKey(),'question_uuid_snapshot'=>$q->uuid,'question_label'=>$q->label,'question_type'=>$q->type->value,'value_json'=>$row['value']===null?null:['value'=>$row['value']],'normalized_json'=>$row['normalized'],'position'=>$q->position]);
     foreach ($row['files'] as $i=>$file) { /** @var UploadedFile $file */ $path=$file->store('questionnaire/'.$booking->organization->uuid.'/'.$booking->uuid.'/'.$answer->uuid,$disk); $answer->files()->create(['booking_id'=>$booking->getKey(),'disk'=>$disk,'path'=>$path,'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),'size_bytes'=>$file->getSize(),'sha256'=>hash_file('sha256',$file->getRealPath(),true),'position'=>$i+1]); }
   }
   foreach ($submission->quote->lines as $i=>$line) {
     $booking->priceLines()->create(['source_type'=>$line->sourceType,'source_uuid'=>$line->sourceUuid,'label'=>$line->label,'line_type'=>$line->lineType,'quantity'=>$line->quantity,'amount_minor'=>$line->amountMinor,'metadata'=>$line->metadata ?: null,'position'=>$i+1]);
     if ($line->lineType !== 'resource_deposit') continue;
     $resource=$line->sourceUuid === null ? null : Resource::whereUuid($line->sourceUuid)->first();
     $booking->resourceDeposits()->create([
       'resource_id'=>$resource?->getKey(),
       'resource_uuid_snapshot'=>$line->sourceUuid,
       'resource_name'=>(string)($line->metadata['resource_name'] ?? $line->label),
       'question_uuid_snapshot'=>$line->metadata['question_uuid'] ?? null,
       'question_label'=>$line->metadata['question_label'] ?? null,
       'quantity'=>max(1,(int)$line->quantity),
       'unit_amount_minor'=>(int)($line->metadata['unit_amount_minor'] ?? $line->amountMinor),
       'amount_minor'=>$line->amountMinor,
       'currency'=>$booking->currency,
       'configuration_source'=>(string)($line->metadata['configuration_source'] ?? 'resource_default'),
     ]);
   }
 }
 public function deleteFilesForBooking(Booking $booking): void { foreach($booking->answers()->with('files')->get() as $answer) foreach($answer->files as $file) Storage::disk($file->disk)->delete($file->path); }
}
