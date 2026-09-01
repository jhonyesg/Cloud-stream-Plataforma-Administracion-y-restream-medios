@php
    $heroH1 = 'Restream a Facebook, YouTube y TikTok al Mismo Tiempo — Colombia';
    $heroBadge = 'Hasta 4 destinos simultáneos · Failover automático · Monitoreo desde panel';
    $heroIntro = 'Transmite tu canal <strong>simultáneamente a Facebook Live, YouTube Live y TikTok Live</strong> desde un solo origen. Cloudstream toma la señal de tu canal (o de tu equipo de emisión) y la replica hasta 4 destinos en paralelo, con la misma calidad y sin necesidad de tener 4 computadores encendidos. Compatible con destinos RTMP personalizados (Twitch, Instagram Live, Kick, tu propio servidor). El plan incluye failover automático: si un destino se cae, los demás siguen emitiendo sin interrupción. Monitoreo en tiempo real desde tu panel de control.';
    $heroCtaText = 'Hola, quiero información sobre el plan de restream a Facebook, YouTube y TikTok de Cloudstream';
    $features = [
        ['icon' => '🔁', 'title' => '4 destinos simultáneos', 'text' => 'Facebook Live + YouTube Live + TikTok Live + un destino RTMP personalizado (Twitch, Instagram, Kick, servidor propio). Todo desde un solo origen.'],
        ['icon' => '🛡️', 'title' => 'Failover automático', 'text' => 'Si Facebook rechaza una conexión, los demás destinos siguen emitiendo. Si YouTube se desconecta, nuestro daemon lo reconecta automáticamente sin que pierdas audiencia.'],
        ['icon' => '📊', 'title' => 'Monitoreo en tiempo real', 'text' => 'Panel de control con estado de cada destino, bitrate, frames por segundo, viewers y errores. Si algo falla, lo ves inmediatamente.'],
        ['icon' => '🔐', 'title' => 'Tokens encriptados', 'text' => 'Las claves de stream de Facebook, YouTube y TikTok se guardan cifradas en nuestra base de datos. Nadie más tiene acceso. Tú solo las pegas una vez al configurar.'],
        ['icon' => '⏯️', 'title' => 'Start/Stop desde el panel', 'text' => 'Inicia y detén el restream de cada destino individualmente desde el panel de Cloudstream, sin entrar a configurar OBS o vMix cada vez.'],
        ['icon' => '💰', 'title' => 'Precio gradual por conexión', 'text' => 'Si solo necesitas 1 destino adicional (por ejemplo Facebook), pagas menos. El precio escala según cuántos destinos activos uses simultáneamente.'],
    ];
    $faqs = [
        ['q' => '¿Cuántos destinos puedo transmitir al mismo tiempo?', 'a' => 'El plan Plataforma + Restream soporta hasta 4 destinos simultáneos: Facebook Live, YouTube Live, TikTok Live y un destino RTMP personalizado adicional. Si necesitas más (por ejemplo Twitch + Instagram + LinkedIn), podemos ampliar bajo cotización.'],
        ['q' => '¿Qué pasa si Facebook o YouTube rechazan la conexión?', 'a' => 'Nuestro daemon detecta la falla y reintenta automáticamente con backoff exponencial. Los demás destinos siguen emitiendo sin interrupción. En el panel verás el estado de cada conexión y recibirás una alerta si algún destino lleva más de 2 minutos caído.'],
        ['q' => '¿Necesito una cuenta de empresa en Facebook y YouTube?', 'a' => 'Para transmitir en vivo a Facebook necesitas una página o grupo (no perfil personal). Para YouTube necesitas un canal verificado. Para TikTok necesitas una cuenta con al menos 1.000 seguidores para hacer Live. Te guiamos en el proceso de verificación si aún no lo tienes.'],
        ['q' => '¿Puedo usar un destino RTMP personalizado además de las redes sociales?', 'a' => 'Sí. El cuarto slot puede ser cualquier URL RTMP: tu propio servidor (Nginx-RTMP, Ant Media), Twitch, Instagram Live, LinkedIn Live, o una plataforma OTT como Dacast. Solo necesitas la URL y la clave de stream.'],
    ];
    $ctaTitle = 'Llega a todas las redes con un solo origen';
    $ctaSubtitle = 'Facebook + YouTube + TikTok + RTMP personalizado. Failover automático. Monitoreo en vivo.';
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'landing', 'data' => [
        'service_name' => 'Restream a Facebook, YouTube y TikTok en Colombia',
        'service_type' => 'Restream de señal de TV a múltiples plataformas sociales',
        'service_description' => 'Servicio de restream en Colombia para transmitir simultáneamente a Facebook Live, YouTube Live, TikTok Live y destinos RTMP personalizados, hasta 4 conexiones con failover automático.',
        'faqs' => $faqs,
        'breadcrumbs' => [
            ['name' => 'Inicio', 'path' => '/'],
            ['name' => 'Restream a Redes Sociales', 'path' => '/restream-facebook-youtube-tiktok'],
        ],
    ]]"
>
    @include('public.landings._layout')
</x-public-layout>
