@extends('layouts.admin')

@section('title', 'Apptimatic Email')
@section('page-title', 'Apptimatic Email')

@section('content')
    @include('admin.apptimatic-email.partials.mail-layout', [
        'messages' => $messages,
        'selectedMessage' => $selectedMessage,
        'threadMessages' => $threadMessages,
        'unreadCount' => $unreadCount,
        'portalLabel' => $portalLabel,
        'profileName' => $profileName,
        'profileAvatarPath' => $profileAvatarPath,
    ])
@endsection
