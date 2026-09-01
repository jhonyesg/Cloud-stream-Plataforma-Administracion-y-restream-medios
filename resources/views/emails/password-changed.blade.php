@component('mail::message')
# Hola {{ $user->display_name ?? $user->username ?? $user->email }},

@if ($changedBy === 'admin')
Tu contraseña fue **restablecida por un administrador** ({{ $actor?->display_name ?? $actor?->username ?? 'administrador' }}).
@else
Tu contraseña fue **actualizada** correctamente.
@endif

Para tu tranquilidad, te informamos los detalles del cambio:

- **Cuándo:** {{ $at->format('Y-m-d H:i:s') }} ({{ config('app.timezone', 'UTC') }})
- **Desde:** {{ $ip ?? 'IP desconocida' }}

Si **no reconoces** este cambio, [restablece tu contraseña ahora]({{ url('/forgot-password') }}) y revisa la actividad reciente de tu cuenta.

@if ($changedBy === 'admin')
Tu sesión fue cerrada en todos los dispositivos. Deberás iniciar sesión con la nueva contraseña.
@else
Las demás sesiones iniciadas con tu cuenta fueron cerradas. Tu sesión actual sigue activa.
@endif

Gracias por mantener tu cuenta segura.

@endcomponent
