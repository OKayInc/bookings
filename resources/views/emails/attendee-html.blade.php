<!doctype html>
<html><head><meta charset="utf-8"></head><body>
{!! $templateBody !!}
@if($templateActionUrl)
<p><a href="{{ $templateActionUrl }}">{{ $templateActionText }}</a></p>
@endif
</body></html>
