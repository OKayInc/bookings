@if($booking->answers->isNotEmpty())
<div class="card"><h2>Questionnaire answers</h2>
@foreach($booking->answers as $answer)
<div class="answer-block"><strong>{{ $answer->question_label }}</strong>
@php $v=data_get($answer->value_json,'value'); @endphp
@if($answer->question_type === 'file')
 <ul>@foreach($answer->files as $file)<li><a href="{{ route('bookings.answer-file',[$booking,$file]) }}">{{ $file->original_name }}</a> <span class="muted">({{ number_format($file->size_bytes/1024,1) }} KiB)</span></li>@endforeach</ul>
@elseif($answer->question_type === 'textarea')
 <div class="rich-text">{!! $answer->safeRichTextValueHtml() !!}</div>
@elseif(is_array($v))
 <div>{{ collect($v)->map(fn($x)=>is_array($x)?($x['label']??json_encode($x)):($x))->implode(', ') }}</div>
@else <div>{{ $v }}</div>@endif
@if($answer->normalized_json)<details><summary>Verified/normalized data</summary><pre>{{ json_encode($answer->normalized_json,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></details>@endif
</div>
@endforeach</div>
@endif
@if($booking->priceLines->isNotEmpty())
<div class="card"><h2>Price breakdown</h2>
@foreach($booking->priceLines as $line)
<div class="price-line"><span>{{ $line->label }} @if((float)$line->quantity != 1) × {{ rtrim(rtrim($line->quantity,'0'),'.') }} @endif</span><strong>{{ $line->line_type === 'coupon_discount' ? '−' : '' }}{{ app(\App\Domain\Money\MoneyService::class)->format($line->amount_minor,$booking->currency) }}</strong></div>
@endforeach
@if($booking->taxLines->isNotEmpty())
<div class="price-line subtotal"><span>Subtotal before tax</span><strong>{{ app(\App\Domain\Money\MoneyService::class)->format($booking->subtotal_minor,$booking->currency) }}</strong></div>
@foreach($booking->taxLines as $tax)
<div class="price-line"><span>{{ $tax->name }} ({{ \App\Domain\Taxes\TaxRate::percentage($tax->rate_millionths) }}%, {{ $booking->tax_price_mode?->value === 'inclusive' ? 'included' : 'added' }})</span><strong>{{ app(\App\Domain\Money\MoneyService::class)->format($tax->amount_minor,$booking->currency) }}</strong></div>
@endforeach
@if($booking->taxLines->count() > 1)<div class="price-line"><span>Total tax</span><strong>{{ app(\App\Domain\Money\MoneyService::class)->format($booking->tax_total_minor,$booking->currency) }}</strong></div>@endif
@if($booking->tax_identifier)<p class="muted mt-2">Tax ID: {{ $booking->tax_identifier }}</p>@endif
@endif
<div class="price-line total"><span>Total</span><strong>{{ app(\App\Domain\Money\MoneyService::class)->format($booking->price_minor,$booking->currency) }}</strong></div></div>
@endif
