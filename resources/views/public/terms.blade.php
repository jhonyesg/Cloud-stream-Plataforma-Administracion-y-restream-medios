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
    :schema="['type' => 'terms', 'data' => []]"
>
    <section class="max-w-3xl mx-auto px-4 sm:px-6 pt-16 pb-12">
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 text-xs font-medium text-blue-200 bg-blue-500/10 border border-blue-500/20 rounded-full px-4 py-1.5 mb-6">
                <span>Legal</span>
            </div>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight title-grad leading-tight mb-6">
                Términos y Condiciones
            </h1>
            <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto">
                Reglas que rigen el uso del servicio {{ $brand }} prestado por {{ $company['name'] }}.
            </p>
            <p class="text-xs text-slate-500 mt-4">Última actualización: {{ $meta['lastmod'] }}</p>
        </div>

        <div class="prose-public">
            <h2>1. Aceptación de los términos</h2>
            <p>
                Al crear una cuenta, contratar un plan o utilizar de cualquier manera el servicio <strong>{{ $brand }}</strong>
                (en adelante, "el Servicio"), el usuario acepta estar legalmente vinculado por estos Términos y
                Condiciones, por la <a href="{{ url('/politica-de-privacidad') }}">Política de Privacidad</a> y por
                las demás políticas, manuales y reglamentos publicados por <strong>{{ $company['name'] }}</strong>
                (en adelante, "el Proveedor"). Si no está de acuerdo con alguna de estas disposiciones, el usuario debe
                abstenerse de utilizar el Servicio.
            </p>

            <h2>2. Identificación del proveedor</h2>
            <p>
                <strong>{{ $company['name'] }}</strong>, sociedad legalmente constituida en Colombia, NIT
                <strong>{{ $nit }}</strong>, con domicilio en {{ $city }}, {{ $country }}. Correo electrónico:
                <a href="mailto:{{ $email }}">{{ $email }}</a>. Teléfono: {{ $phone }}.
            </p>

            <h2>3. Objeto</h2>
            <p>
                El Proveedor pone a disposición del usuario un servicio de infraestructura en la nube para la transmisión
                de señal de televisión y audio por Internet en vivo (streaming 24/7), que incluye, entre otras
                funcionalidades: ingest del canal vía RTMP, conversión a HLS, almacenamiento del contenido del usuario,
                programador de programación, emisión de cuñas publicitarias, aplicación de logo, configuración de la
                pantalla virtual del canal y restream simultáneo hacia plataformas externas (YouTube, Facebook, TikTok y
                RTMP personalizado). El Servicio es una herramienta técnica; el Proveedor no produce, edita ni asume
                responsabilidad editorial sobre el contenido transmitido por el usuario.
            </p>

            <h2>4. Registro, elegibilidad y cuenta</h2>
            <ul>
                <li>El usuario debe ser persona natural mayor de 18 años o persona jurídica debidamente representada y tener capacidad legal para contratar.</li>
                <li>El usuario se obliga a proporcionar información veraz, completa y actualizada al momento del registro y durante toda la relación contractual.</li>
                <li>El usuario es responsable de la confidencialidad de sus credenciales de acceso y de todas las actividades que se realicen bajo su cuenta. Debe notificar inmediatamente al Proveedor cualquier uso no autorizado.</li>
                <li>Está prohibido compartir cuentas entre personas naturales. Las cuentas empresariales son intransferibles salvo autorización escrita del Proveedor.</li>
            </ul>

            <h2>5. Planes, pagos y facturación</h2>
            <ul>
                <li>El Servicio se presta mediante planes de pago recurrentes (mensuales, trimestrales, semestrales o anuales) cuyos precios, características y limitaciones se publican en la página <a href="{{ url('/#planes') }}">Planes y precios</a>.</li>
                <li>El pago se realiza por anticipado. Una vez confirmado el pago, el Servicio queda disponible para el período contratado.</li>
                <li>El Proveedor emite factura electrónica de conformidad con la normatividad tributaria colombiana (Decreto 2242 de 2015 y normas concordantes), la cual será enviada al correo electrónico registrado.</li>
                <li>La mora en el pago superior a cinco (5) días calendario suspende el Servicio y, transcurridos quince (15) días calendario adicionales, da lugar a la terminación unilateral del contrato por parte del Proveedor.</li>
                <li>Los precios pueden ser ajustados al inicio de cada período de renovación, notificando al usuario con al menos treinta (30) días calendario de anticipación.</li>
                <li>Conforme al artículo 47 del Estatuto del Consumidor (<em>Ley 1480 de 2011</em>), tratándose de un servicio de ejecución continuada, el usuario podrá solicitar la terminación del contrato en cualquier momento; sin embargo, no habrá lugar a devolución de sumas pagadas por períodos ya causados. Si el pago fue anual y se solicita la terminación anticipada, se devolverá proporcionalmente el saldo no causado, descontando una penalidad equivalente al diez por ciento (10 %) del valor no causado por gastos administrativos.</li>
            </ul>

            <h2>6. Uso aceptable del Servicio</h2>
            <p>El usuario se compromete a utilizar el Servicio de manera lícita y conforme a estos Términos. En particular, está prohibido:</p>
            <ul>
                <li>Transmitir contenido que vulnere derechos de terceros, incluyendo derechos de autor, propiedad industrial, intimidad, buen nombre, imagen u honra.</li>
                <li>Transmitir contenido pornográfico, que incite a la violencia, promueva el terrorismo, haga apología del delito, atente contra la integridad de menores de edad o constituya discurso de odio en cualquiera de sus formas.</li>
                <li>Transmitir contenido que contravenga la <em>Ley 1098 de 2006</em> (Código de Infancia y Adolescencia), la <em>Ley 1336 de 2009</em>, la <em>Ley 1801 de 2016</em> (Código de Policía) o cualquier otra norma colombiana aplicable.</li>
                <li>Utilizar el Servicio para la comisión directa o indirecta de actividades ilícitas, lavado de activos, financiación del terrorismo o cualquier conducta tipificada como delito en la legislación colombiana.</li>
                <li>Intentar acceder a cuentas, datos o áreas del Servicio sin autorización; realizar ingeniería inversa, descompilar o desensamblar el software del Servicio; realizar pruebas de vulnerabilidades sin autorización escrita previa.</li>
                <li>Utilizar el Servicio para distribuir <em>malware</em>, <em>spam</em>, suplantar la identidad de terceros o realizar actividades de phishing.</li>
                <li>Revender, sublicenciar o explotar comercialmente el Servicio sin autorización escrita del Proveedor.</li>
            </ul>

            <h2>7. Contenido del usuario y responsabilidad editorial</h2>
            <p>
                El usuario conserva la plena propiedad sobre el contenido que carga al Servicio (videos, audios, imágenes,
                logos y textos). Al cargar dicho contenido, el usuario otorga al Proveedor una licencia limitada, no
                exclusiva, revocable y suficiente para almacenar, procesar, transmitir y servir dicho contenido en
                cumplimiento del Servicio contratado. El Proveedor no revisa, supervisa ni filtra previamente el contenido
                del usuario, pero puede suspender o retirar contenido que vulnere estos Términos o la ley, previa
                calificación razonada y con notificación al usuario.
            </p>
            <p>
                El usuario es el único responsable del contenido que transmite y de obtener los derechos, autorizaciones,
                licencias y consentsimientos necesarios para su difusión (incluyendo, cuando aplique, los pagos
                correspondientes a Sayco y Cinco, derechos de autor sobre obras musicales y demás entidades de gestión
                colectiva en Colombia).
            </p>

            <h2>8. Restream hacia plataformas externas</h2>
            <p>
                El Servicio puede transmitir la señal del usuario hacia YouTube, Facebook, TikTok o cualquier servidor
                RTMP personalizado que el usuario indique. El Proveedor:
            </p>
            <ul>
                <li>No se hace responsable de las políticas, suspensiones, monetización ni de cualquier acción que las plataformas externas adopten sobre el canal del usuario.</li>
                <li>No garantiza la continuidad del restream cuando las plataformas externas realicen cambios en sus APIs, formatos o términos. El Proveedor informará con la mayor diligencia cualquier cambio conocido.</li>
                <li>Almacena las credenciales de transmisión cifradas mediante el módulo <code>Crypt</code> de Laravel y las elimina automáticamente cuando el destino se desactiva o elimina, salvo respaldo legal obligatorio.</li>
            </ul>

            <h2>9. Servicio técnico, mantenimiento y niveles de servicio (SLA)</h2>
            <ul>
                <li>El Proveedor procura una disponibilidad objetivo del <strong>99 % mensual</strong>, medida sobre la base de la infraestructura de datacenter americano donde se aloja el Servicio.</li>
                <li>El Proveedor puede realizar mantenimientos programados o de emergencia que pueden interrumpir parcialmente el Servicio, notificando al usuario por correo electrónico o mediante el panel del cliente con la mayor anticipación posible.</li>
                <li>El soporte técnico se presta en horario hábil colombiano (lunes a viernes de 8:00 a 18:00, hora de Colombia), mediante los canales publicados en el panel del cliente. No se ofrece soporte en días festivos en Colombia.</li>
                <li>El Proveedor no se hace responsable por interrupciones, retardos o fallos derivados de: (i) caso fortuito o fuerza mayor; (ii) fallos atribuibles a proveedores externos (datacenter, CDN, plataformas de restream, redes de telecomunicaciones); (iii) configuración incorrecta del canal por parte del usuario; (iv) actos de autoridades competentes; (v) ataques cibernéticos masivos.</li>
            </ul>

            <h2>10. Suspensión y terminación</h2>
            <p>El Proveedor puede suspender o terminar la prestación del Servicio, con o sin previo aviso, en los siguientes casos:</p>
            <ul>
                <li>Incumplimiento de cualquier cláusula de estos Términos, en especial las previstas en el numeral 6 (uso aceptable).</li>
                <li>Mora en el pago superior a quince (15) días calendario.</li>
                <li>Requerimiento de autoridad competente.</li>
                <li>Cese de actividades del Proveedor, evento en el cual se notificará con al menos treinta (30) días calendario de anticipación.</li>
            </ul>
            <p>
                El usuario puede solicitar la cancelación del Servicio en cualquier momento desde el panel del cliente o
                mediante solicitud escrita a <a href="mailto:{{ $email }}">{{ $email }}</a>. La cancelación surtirá
                efecto al final del período de facturación en curso. El Proveedor conservará los datos personales por los
                plazos señalados en la <a href="{{ url('/politica-de-privacidad') }}">Política de Privacidad</a>.
            </p>

            <h2>11. Limitación de responsabilidad</h2>
            <p>
                En la máxima extensión permitida por la ley colombiana, el Servicio se presta "tal cual" y "según
                disponibilidad". El Proveedor no garantiza que el Servicio estará libre de errores ni que satisfará
                requerimientos específicos del usuario. En ningún caso la responsabilidad total y agregada del Proveedor
                frente al usuario excederá el monto efectivamente pagado por el usuario en los doce (12) meses
                inmediatamente anteriores al hecho generador. El Proveedor no será responsable por daños indirectos,
                lucro cesante, pérdida de datos, pérdida de oportunidades ni daños consecuenciales.
            </p>

            <h2>12. Indemnidad</h2>
            <p>
                El usuario mantendrá indemne al Proveedor, sus administradores, empleados y aliados frente a cualquier
                reclamación, demanda o procedimiento judicial o administrativo derivado del contenido transmitido, del
                uso del Servicio o del incumplimiento de estos Términos o de la ley aplicable.
            </p>

            <h2>13. Propiedad intelectual del Servicio</h2>
            <p>
                El software, marcas, logotipos, diseños, interfaces, documentación y demás elementos del Servicio son
                propiedad del Proveedor o de sus licenciantes y están protegidos por las normas colombianas sobre
                propiedad intelectual (<em>Decisión Andina 486 de 2000</em>, <em>Ley 23 de 1982</em> y <em>Ley 1450 de
                2011</em>). El uso del Servicio no transfiere al usuario ningún derecho de propiedad sobre estos
                elementos, salvo la licencia de uso limitada necesaria para la utilización del Servicio durante la
                vigencia del contrato.
            </p>

            <h2>14. Modificaciones</h2>
            <p>
                El Proveedor puede modificar estos Términos para reflejar cambios legales, técnicos o comerciales.
                Publicaremos la versión actualizada en esta misma URL con la fecha de la última actualización. Si los
                cambios son sustanciales, se notificará al usuario por correo electrónico con al menos quince (15) días
                calendario de anticipación. El uso continuado del Servicio después de la entrada en vigencia de los
                cambios constituye aceptación de los nuevos Términos.
            </p>

            <h2>15. Ley aplicable y jurisdicción</h2>
            <p>
                Estos Términos se rigen por las leyes de la República de Colombia, en especial por el <em>Estatuto del
                Consumidor</em> (<em>Ley 1480 de 2011</em>) cuando aplique, la <em>Ley 1581 de 2012</em> sobre
                protección de datos personales, la <em>Ley 527 de 1999</em> sobre comercio electrónico y la <em>Decisión
                Andina 486 de 2000</em> sobre propiedad industrial. Para cualquier controversia derivada de estos
                Términos, las partes se someten a los jueces y tribunales competentes de {{ $city }},
                {{ $country }}, salvo las acciones de protección al consumidor que podrán ser tramitadas ante la
                Superintendencia de Industria y Comercio.
            </p>

            <h2>16. Contacto</h2>
            <p>
                <strong>{{ $company['name'] }}</strong><br>
                <strong>Correo:</strong> <a href="mailto:{{ $email }}">{{ $email }}</a><br>
                <strong>Teléfono:</strong> {{ $phone }}<br>
                <strong>Dirección:</strong> {{ $city }}, {{ $country }}<br>
                <strong>NIT:</strong> {{ $nit }}
            </p>
        </div>
    </section>
</x-public-layout>
