@extends('layouts.client')

@section('title', 'Support Ticket')
@section('page-title', 'Support Ticket')

@section('content')
    @include('client.support-tickets.partials.main', ['ticket' => $ticket])
@endsection
