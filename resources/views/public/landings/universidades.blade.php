@php
    $heroH1 = 'Streaming para Universidades y Educación en Colombia — Clases Híbridas y Conferencias en Vivo';
    $heroBadge = 'Grabación automática · Protección con login · Integración con LMS';
    $heroIntro = 'Transmite <strong>clases, conferencias y eventos académicos en vivo o bajo demanda</strong> con Cloudstream. El plan Plataforma Completa incluye grabación automática de cada sesión, protección por login con credenciales de tu universidad y compatibilidad con Moodle, Canvas, Google Classroom y Microsoft Teams. Ideal para universidades, instituciones técnicas, colegios con modalidad híbrida y academias de formación virtual. Tu señal puede llegar a estudiantes desde cualquier ciudad con calidad HD y sin cortes.';
    $heroCtaText = 'Hola, quiero información sobre el plan para universidades y educación de Cloudstream';
    $features = [
        ['icon' => '🎓', 'title' => 'Clases híbridas y a distancia', 'text' => 'Emite tu clase presencial y compártela con estudiantes remotos en tiempo real. El estudiante asiste desde casa con chat para preguntas.'],
        ['icon' => '🔐', 'title' => 'Acceso protegido por login', 'text' => 'Solo tus estudiantes matriculados pueden ver la clase. Cada usuario entra con las credenciales de tu universidad o con un código de acceso por materia.'],
        ['icon' => '📼', 'title' => 'Grabación automática de cada sesión', 'text' => 'Todas las clases quedan grabadas en tu biblioteca. Los estudiantes pueden repasar después o ver la grabación si faltaron a la sesión en vivo.'],
        ['icon' => '🔗', 'title' => 'Integración con Moodle, Canvas y Classroom', 'text' => 'Incrustamos el reproductor dentro de tu LMS como una actividad LTI. Tus estudiantes ven la clase sin salir de la plataforma académica.'],
        ['icon' => '📚', 'title' => 'Biblioteca de clases grabadas', 'text' => 'Organiza las grabaciones por materia, profesor y semestre. Los estudiantes buscan y reproducen cuando necesitan repasar.'],
        ['icon' => '📊', 'title' => 'Reportes de asistencia virtual', 'text' => 'Visualiza cuántos estudiantes se conectaron a cada sesión, cuánto tiempo permanecieron y desde qué dispositivo accedieron.'],
    ];
    $faqs = [
        ['q' => '¿Cómo protegen el acceso a las clases?', 'a' => 'El plan Plataforma Completa permite configurar autenticación por usuario y contraseña. Podemos integrar el login con el SSO de tu universidad (LDAP, SAML) o entregarte una base de usuarios independiente cargada desde un CSV.'],
        ['q' => '¿Las clases grabadas quedan disponibles para siempre?', 'a' => 'Sí. Cada sesión se guarda automáticamente en tu biblioteca. Tú decides cuándo archivar o eliminar. El almacenamiento incluido en el plan cubre varios semestres de clases para una universidad de tamaño medio.'],
        ['q' => '¿Funciona con Moodle y Google Classroom?', 'a' => 'Sí. Integramos el reproductor como una actividad LTI en Moodle, Canvas, Blackboard y Brightspace. Para Google Classroom y Microsoft Teams entregamos un enlace directo que puedes compartir como material.'],
        ['q' => '¿Qué ancho de banda necesita un estudiante para ver una clase en HD?', 'a' => 'Recomendamos 3-5 Mbps de descarga por estudiante en HD 720p. Para 480p (calidad estándar) es suficiente con 1.5 Mbps. Cloudstream ajusta automáticamente la calidad según la conexión del estudiante (ABR).'],
    ];
    $ctaTitle = 'Lleva tus clases a Internet con calidad profesional';
    $ctaSubtitle = 'Clases en vivo, grabadas y protegidas. Integración con tu LMS actual. Soporte técnico para tu equipo de TI.';
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'landing', 'data' => [
        'service_name' => 'Streaming para Universidades y Educación en Colombia',
        'service_type' => 'Plataforma de streaming educativo con grabación y autenticación',
        'service_description' => 'Servicio de streaming 24/7 para universidades y educación en Colombia: clases híbridas en vivo, grabación automática, protección con login, integración con Moodle, Canvas, Google Classroom.',
        'faqs' => $faqs,
        'breadcrumbs' => [
            ['name' => 'Inicio', 'path' => '/'],
            ['name' => 'Streaming para Universidades', 'path' => '/streaming-para-universidades-y-educacion'],
        ],
    ]]"
>
    @include('public.landings._layout')
</x-public-layout>
