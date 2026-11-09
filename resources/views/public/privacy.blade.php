@php
    $company = config('seo.company');
    $email = $company['email'];
    $phone = $company['phone_display'];
    $city = $company['city'];
    $country = $company['country'];
    $nit = $company['nit'];
    $brand = $company['brand'];
@endphp

<x-public-layout
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :schema="['type' => 'privacy', 'data' => []]"
>
    <section class="max-w-3xl mx-auto px-4 sm:px-6 pt-16 pb-12">
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 text-xs font-medium text-blue-200 bg-blue-500/10 border border-blue-500/20 rounded-full px-4 py-1.5 mb-6">
                <span>Legal</span>
            </div>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight title-grad leading-tight mb-6">
                Política de Privacidad
            </h1>
            <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto">
                Cómo recopilamos, usamos y protegemos tus datos personales al usar Cloudstream.
            </p>
            <p class="text-xs text-slate-500 mt-4">Última actualización: {{ $meta['lastmod'] }}</p>
        </div>

        <div class="prose-public">
            <h2>1. ¿Quién es el responsable del tratamiento de tus datos?</h2>
            <p>
                <strong>{{ $company['name'] }}</strong>, sociedad legalmente constituida en Colombia, identificada con NIT
                <strong>{{ $nit }}</strong>, con domicilio en {{ $city }}, {{ $country }}, es la responsable del tratamiento
                de los datos personales que se recopilan a través del servicio <strong>{{ $brand }}</strong> (en adelante,
                "el Servicio"). Para cualquier asunto relacionado con el tratamiento de tus datos, puedes escribirnos a
                <a href="mailto:{{ $email }}">{{ $email }}</a>.
            </p>

            <h2>2. ¿Qué datos personales recopilamos?</h2>
            <p>Recopilamos las siguientes categorías de datos personales:</p>
            <ul>
                <li><strong>Datos de identificación y contacto</strong>: nombre, apellido, tipo y número de documento, dirección, correo electrónico, teléfono y ciudad de residencia, que nos entregas al registrarte en el Servicio.</li>
                <li><strong>Datos de la empresa o canal</strong>: razón social, NIT, nombre del canal de TV o emisora, representante legal y datos de facturación.</li>
                <li><strong>Datos de pago</strong>: información necesaria para procesar pagos y expedir facturas electrónicas conforme a la normativa colombiana. <strong>No almacenamos</strong> los datos completos de tarjetas de crédito ni débito: estos son procesados directamente por nuestro proveedor de pagos certificado PCI-DSS.</li>
                <li><strong>Datos técnicos y de uso</strong>: dirección IP, tipo de navegador, sistema operativo, páginas visitadas, tiempos de acceso, identificadores de sesión y cookies estrictamente necesarias para el funcionamiento del Servicio.</li>
                <li><strong>Contenido del usuario</strong>: videos, audios, imágenes, logos y archivos de configuración que el usuario carga al Servicio para la operación de su canal. Este contenido se almacena en infraestructura de datacenter ubicada fuera de Colombia y se trata de manera cifrada en tránsito y en reposo.</li>
                <li><strong>Datos de autenticación de terceros</strong>: cuando el usuario conecta cuentas de redes sociales (Google, Facebook, TikTok, RTMP personalizado) para el servicio de restream, almacenamos los tokens de acceso cifrados mediante el módulo <code>Crypt</code> de Laravel y únicamente los usamos para emitir la señal hacia esos destinos. Nunca los compartimos ni los usamos para publicar en nombre del usuario.</li>
            </ul>

            <h2>3. ¿Para qué usamos tus datos personales?</h2>
            <p>Usamos los datos personales para las siguientes finalidades:</p>
            <ul>
                <li><strong>Prestar el Servicio contratado</strong>: crear y mantener tu cuenta, operar tu canal de TV en vivo, almacenar tu contenido, generar programaciones y emitir la señal a los destinos RTMP que nos indiques.</li>
                <li><strong>Facturación y soporte</strong>: emitir facturas electrónicas válidas en Colombia, atender peticiones, quejas y reclamos (PQR), y prestar soporte técnico.</li>
                <li><strong>Mejorar el Servicio</strong>: analizar el uso agregado y anónimo del Servicio para identificar oportunidades de mejora, sin identificar individualmente a los usuarios.</li>
                <li><strong>Cumplimiento legal</strong>: conservar registros contables y fiscales por el término exigido por la ley colombiana, atender requerimientos de autoridades competentes y ejercer nuestros derechos legales.</li>
                <li><strong>Comunicaciones comerciales</strong>: enviarte información sobre planes, nuevas funcionalidades y promociones, únicamente si nos diste tu consentimiento expreso. Puedes retirarlo en cualquier momento.</li>
            </ul>

            <h2>4. ¿Cuál es la base legal para el tratamiento?</h2>
            <p>
                Tratamos tus datos personales con base en (i) la ejecución del contrato de prestación de servicios de
                streaming que celebras con nosotros al adquirir un plan; (ii) tu consentimiento libre, expreso e informado,
                para las finalidades que lo requieren (comunicaciones comerciales, cookies no esenciales, conexión de
                cuentas de terceros); (iii) el cumplimiento de obligaciones legales y regulatorias colombianas, en
                particular las previstas en la <em>Ley 1581 de 2012</em>, el <em>Decreto 1377 de 2013</em> y demás
                normas concordantes; y (iv) el interés legítimo de {{ $company['name'] }} en mantener la seguridad y
                operación adecuada del Servicio.
            </p>

            <h2>5. ¿Con quién compartimos tus datos?</h2>
            <p>
                No vendemos, alquilamos ni comercializamos tus datos personales. Los compartimos únicamente con:
            </p>
            <ul>
                <li><strong>Proveedores de infraestructura y pago</strong>: datacenter, proveedor de pagos y proveedores de certificados de seguridad, quienes actúan como encargados del tratamiento conforme a contratos que imponen obligaciones equivalentes a las nuestras.</li>
                <li><strong>Plataformas de destino del restream</strong>: cuando configuras un destino de restream, transmitimos la señal de video y audio hacia el servidor RTMP, YouTube, Facebook o TikTok que indiques. Estos servicios reciben únicamente la señal técnica de streaming y metadatos operativos necesarios (título del canal, ID del canal); no reciben tus credenciales de acceso a Cloudstream.</li>
                <li><strong>Autoridades colombianas</strong>: cuando exista orden judicial o requerimiento legal válido, o cuando sea necesario para proteger nuestros derechos, tu seguridad o la de terceros.</li>
            </ul>

            <h2>6. ¿Transferimos tus datos fuera de Colombia?</h2>
            <p>
                Sí. Para prestar el Servicio, parte de tu contenido y de los datos técnicos se almacenan y procesan en
                infraestructura ubicada en datacenter en Estados Unidos y Europa. Estas transferencias se realizan
                conforme a los principios de la <em>Decisión 2001/497/CE</em> y cláusulas contractuales tipo para
                encargados del tratamiento, y el país de destino ofrece un nivel adecuado de protección conforme a los
                criterios de la Superintendencia de Industria y Comercio de Colombia. Al usar el Servicio, el usuario
                acepta estas transferencias necesarias para la prestación del servicio contratado.
            </p>

            <h2>7. ¿Por cuánto tiempo conservamos tus datos?</h2>
            <p>
                Conservamos tus datos personales mientras mantengas una cuenta activa en el Servicio y durante los plazos
                legales y contables aplicables con posterioridad a la terminación de la relación contractual. Una vez
                cumplidos dichos plazos, los datos se eliminan o anonimizan de manera irreversible. El contenido de
                video, audio e imágenes que cargues al Servicio se conserva mientras tu plan esté vigente; al cancelar,
                cuentas con 30 días para descargarlo, tras los cuales se elimina definitivamente.
            </p>

            <h2>8. ¿Cuáles son tus derechos como titular?</h2>
            <p>
                Conforme a la <em>Ley 1581 de 2012</em>, tienes los siguientes derechos:
            </p>
            <ul>
                <li><strong>Conocer</strong>, <strong>actualizar</strong> y <strong>rectificar</strong> tus datos personales.</li>
                <li><strong>Solicitar prueba de la autorización</strong> otorgada para el tratamiento.</li>
                <li>Ser <strong>informado</strong> sobre el uso dado a tus datos.</li>
                <li><strong>Presentar quejas</strong> ante la Superintendencia de Industria y Comercio por infracciones a la normatividad vigente.</li>
                <li><strong>Revocar la autorización</strong> y/o <strong>solicitar la supresión</strong> de tus datos, salvo cuando exista un deber legal o contractual de mantenerlos.</li>
                <li><strong>Acceder</strong> en forma gratuita a tus datos personales al menos una vez al mes.</li>
            </ul>
            <p>
                Para ejercer estos derechos, escríbenos a <a href="mailto:{{ $email }}">{{ $email }}</a> con tu
                nombre completo, número de documento, descripción de la solicitud y documentos de soporte. Responderemos
                en un plazo máximo de quince (15) días hábiles contados desde la fecha de recibo.
            </p>

            <h2>9. Cookies y tecnologías similares</h2>
            <p>
                Usamos cookies estrictamente necesarias para el funcionamiento del Servicio (sesión, autenticación,
                preferencia de idioma). No usamos cookies de publicidad ni de rastreo de terceros. Puedes configurar tu
                navegador para bloquearlas, aunque hacerlo puede impedir el correcto funcionamiento del Servicio.
            </p>

            <h2>10. Seguridad de la información</h2>
            <p>
                Implementamos medidas técnicas, humanas y administrativas razonables para proteger tus datos personales
                contra acceso no autorizado, pérdida, alteración o destrucción. Entre estas medidas se incluyen: cifrado
                TLS para todas las comunicaciones, cifrado en reposo del contenido del usuario, autenticación de dos
                factores opcional, control de acceso basado en roles, registros de auditoría inmutables, copias de
                seguridad cifradas y pruebas periódicas de vulnerabilidades. Aun así, ningún sistema es completamente
                seguro; por ello te recomendamos usar contraseñas robustas y no compartirlas.
            </p>

            <h2>11. Tratamiento de datos de menores de edad</h2>
            <p>
                El Servicio no está dirigido a menores de 18 años. Si llegamos a tener conocimiento de que hemos
                recopilado datos de un menor sin la autorización de sus representantes legales, los eliminaremos de
                manera inmediata. Si eres representante legal de un menor y consideras que tenemos datos suyos,
                escríbenos a <a href="mailto:{{ $email }}">{{ $email }}</a>.
            </p>

            <h2>12. Cambios a esta política</h2>
            <p>
                Podemos actualizar esta política de privacidad para reflejar cambios legales, técnicos o del Servicio.
                Publicaremos la versión actualizada en esta misma URL, indicando la fecha de la última actualización. Si
                los cambios son sustanciales, te los notificaremos por correo electrónico con al menos quince (15) días
                calendario de anticipación.
            </p>

            <h2>13. Marco normativo aplicable</h2>
            <p>
                Esta política se rige por la <em>Constitución Política de Colombia</em> (arts. 15 y 20),
                la <em>Ley 1581 de 2012</em>, el <em>Decreto 1377 de 2013</em>, el <em>Decreto 886 de 2014</em>,
                la <em>Ley 1266 de 2008</em> (datos financieros y crediticios, cuando aplique), y demás normas
                concordantes sobre protección de datos personales en Colombia.
            </p>

            <h2>14. Contacto del área de protección de datos</h2>
            <p>
                <strong>Responsable:</strong> {{ $company['name'] }}<br>
                <strong>Correo:</strong> <a href="mailto:{{ $email }}">{{ $email }}</a><br>
                <strong>Teléfono:</strong> {{ $phone }}<br>
                <strong>Dirección:</strong> {{ $city }}, {{ $country }}
            </p>
        </div>
    </section>
</x-public-layout>
