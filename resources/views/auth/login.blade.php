@extends('layouts.app')
@section('title', 'Log in')
@section('content')
<div class="card" style="max-width:560px;margin:auto">
    <h1>Log in</h1>
    <form method="post" action="{{ url('/login') }}">
        @csrf
        <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email') }}" required autofocus></div>
        <div class="field"><label>Password</label><input type="password" name="password" required></div>
        <div class="field checkbox-list"><label><input type="checkbox" name="remember" value="1"> Remember me</label></div>
        <button class="btn btn-primary" type="submit">Log in</button>
    </form>
</div>
@endsection
