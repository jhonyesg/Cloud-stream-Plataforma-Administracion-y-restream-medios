@php
    $user = auth()->user();
    $route = $user && $user->isAdmin() ? 'admin.dashboard' : 'client.dashboard';
@endphp
@extends('layouts.app')
@section('content')
<div class="py-12 text-center">
    <p>Redirigiendo...</p>
    <script>window.location = @json(route($route));</script>
    <a href="{{ route($route) }}" class="text-blue-600">Ir al panel</a>
</div>
@endsection
