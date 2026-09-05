<?php if ($booking->answers->isNotEmpty()): ?>
<div class="card"><h2>Your questionnaire answers</h2>
<?php foreach ($booking->answers as $answer): ?>
<div class="answer-block"><strong><?= e($answer->question_label) ?></strong>
<?php $v = data_get($answer->value_json, 'value'); ?>
<?php if ($answer->question_type === 'file'): ?>
 <ul><?php foreach ($answer->files as $file): ?><li><a href="<?= e(route('public.bookings.answer-file', [$booking, $manageToken, $file])) ?>" download><?= e($file->original_name) ?></a></li><?php endforeach; ?></ul>
<?php elseif (is_array($v)): ?>
 <div><?= e(collect($v)->map(fn ($x) => is_array($x) ? ($x['label'] ?? json_encode($x)) : $x)->implode(', ')) ?></div>
<?php else: ?>
 <div><?= e($v) ?></div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($booking->priceLines->isNotEmpty()): ?>
<div class="card"><h2>Price breakdown</h2>
<?php foreach ($booking->priceLines as $line): ?>
<div class="price-line"><span><?= e($line->label) ?></span><strong><?= $line->line_type === 'coupon_discount' ? '−' : '' ?><?= e(app(\App\Domain\Money\MoneyService::class)->format($line->amount_minor, $booking->currency)) ?></strong></div>
<?php endforeach; ?>
<div class="price-line total"><span>Total</span><strong><?= e(app(\App\Domain\Money\MoneyService::class)->format($booking->price_minor, $booking->currency)) ?></strong></div></div>
<?php endif; ?>
