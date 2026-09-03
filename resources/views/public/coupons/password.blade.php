@extends('layouts.public')
@section('title', 'Protected gift card')
@section('content')
<div class="card" style="max-width:560px;margin:auto"><h1>Protected gift card / coupon</h1><p>Enter the password supplied separately by the purchaser.</p><form method="post" action="{{ route('public.coupons.unlock', $token) }}" class="form-stack">@csrf<div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autofocus maxlength="200"></div><button class="btn btn-primary">View</button></form></div>
@endsection
