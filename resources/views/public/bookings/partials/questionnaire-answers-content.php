<?php if ($booking->answers->isNotEmpty()): ?>
<div class="card"><h2>Your questionnaire answers</h2>
<?php foreach ($booking->answers as $answer): ?>
<div class="answer-block"><strong><?= e($answer->question_label) ?></strong>
<?php $v = data_get($answer->value_json, 'value'); ?>
<?php if ($answer->question_type === 'file'): ?>
 <ul><?php foreach ($answer->files as $file): ?><li><a href="<?= e(route('public.bookings.answer-file', [$booking, $manageToken, $file])) ?>"><?= e($file->original_name) ?></a></li><?php endforeach; ?></ul>
<?php elseif ($answer->question_type === 'textarea'): ?>
 <div class="rich-text"><?= $answer->safeRichTextValueHtml() ?></div>
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
<?php if ($booking->taxLines->isNotEmpty()): ?>
<div class="price-line subtotal"><span>Subtotal before tax</span><strong><?= e(app(\App\Domain\Money\MoneyService::class)->format($booking->subtotal_minor, $booking->currency)) ?></strong></div>
<?php foreach ($booking->taxLines as $tax): ?>
<div class="price-line"><span><?= e($tax->name) ?> (<?= e(\App\Domain\Taxes\TaxRate::percentage($tax->rate_millionths)) ?>%, <?= $booking->tax_price_mode?->value === 'inclusive' ? 'included' : 'added' ?>)</span><strong><?= e(app(\App\Domain\Money\MoneyService::class)->format($tax->amount_minor, $booking->currency)) ?></strong></div>
<?php endforeach; ?>
<?php if ($booking->taxLines->count() > 1): ?><div class="price-line"><span>Total tax</span><strong><?= e(app(\App\Domain\Money\MoneyService::class)->format($booking->tax_total_minor, $booking->currency)) ?></strong></div><?php endif; ?>
<?php if ($booking->tax_identifier): ?><p class="muted mt-2">Tax ID: <?= e($booking->tax_identifier) ?></p><?php endif; ?>
<?php endif; ?>
<div class="price-line total"><span>Total</span><strong><?= e(app(\App\Domain\Money\MoneyService::class)->format($booking->price_minor, $booking->currency)) ?></strong></div></div>
<?php endif; ?>
