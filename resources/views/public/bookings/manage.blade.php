@extends('layouts.public')
@section('title', 'Manage booking '.$booking->reference)
@section('content')
<?php echo view('public.bookings.manage-content', get_defined_vars())->render(); ?>
@include('public.bookings.offline-payments')
@endsection
