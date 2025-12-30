@extends('emails.layout')

@section('content')
    <div style="font-family:Arial, sans-serif;font-size:14px;line-height:1.6;color:#334155;">
        {!! $bodyHtml !!}
    </div>
@endsection
