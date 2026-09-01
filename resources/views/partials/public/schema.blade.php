@php
    $type = $schema['type'] ?? 'home';
    $data = $schema['data'] ?? [];
    $site = rtrim(config('seo.site_url'), '/');
    $company = config('seo.company');
    $plans = config('seo.plans');

    $orgId = $site . '/#organization';
    $websiteId = $site . '/#website';

    $baseOrg = [
        '@type' => 'Organization',
        '@id' => $orgId,
        'name' => $company['name'],
        'alternateName' => $company['brand'],
        'url' => $site . '/',
        'logo' => $site . '/og/home-1200x630.jpg',
        'email' => $company['email'],
        'telephone' => $company['phone_e164'],
        'areaServed' => $company['country_code'],
        'foundingDate' => ($company['founding_year'] ?? 2020) . '-01-01',
        'founder' => [
            '@type' => 'Organization',
            'name' => $company['founder'] ?? $company['name'],
        ],
        'knowsAbout' => [
            'Streaming de video en vivo',
            'Protocolo RTMP',
            'Protocolo HLS',
            'Restream a redes sociales',
            'Datacenter americano',
            'Emisión de señal de TV por Internet',
            'Programador de contenido para TV',
            'Cuñas publicitarias',
        ],
        'award' => $company['awards'] ?? [],
        'sameAs' => $company['social'] ?? [],
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => $company['city'],
            'addressCountry' => $company['country_code'],
        ],
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'telephone' => $company['phone_e164'],
            'contactType' => 'customer support',
            'areaServed' => $company['country_code'],
            'availableLanguage' => ['Spanish'],
            'email' => $company['email'],
        ],
    ];

    $website = [
        '@type' => 'WebSite',
        '@id' => $websiteId,
        'url' => $site . '/',
        'name' => $company['brand'],
        'publisher' => ['@id' => $orgId],
        'inLanguage' => 'es-CO',
    ];

    $planToOffer = function ($key, $plan) use ($site, $orgId) {
        return [
            '@type' => 'Offer',
            'name' => $plan['name'],
            'description' => $plan['description'],
            'price' => (string) $plan['price_cop'],
            'priceCurrency' => 'COP',
            'seller' => ['@id' => $orgId],
            'url' => $site . '/#plan-' . $key,
        ];
    };

    $breadcrumb = function ($items) use ($site) {
        $list = [];
        foreach ($items as $i => $item) {
            $list[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['item'] ?? ($site . $item['path']),
            ];
        }
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    };

    $graph = [];

    switch ($type) {
        case 'home':
            $graph = array_merge($graph, [
                $baseOrg,
                $website,
                [
                    '@type' => 'Service',
                    '@id' => $site . '/#service-streaming',
                    'name' => 'Señal de TV en vivo por Internet en Colombia',
                    'serviceType' => 'Streaming de señal de TV en vivo',
                    'provider' => ['@id' => $orgId],
                    'areaServed' => $company['country_code'],
                    'description' => 'Transmite tu canal de TV en vivo 24/7 desde un datacenter americano. Programador de contenido, cuñas publicitarias, logo y restream a Facebook, YouTube y TikTok.',
                    'offers' => [
                        '@type' => 'AggregateOffer',
                        'priceCurrency' => 'COP',
                        'lowPrice' => (string) $plans['plan1']['price_cop'],
                        'highPrice' => (string) $plans['plan2']['price_cop'],
                        'offerCount' => '3',
                        'offers' => [
                            $planToOffer('plan1', $plans['plan1']),
                            $planToOffer('plan2', $plans['plan2']),
                            $planToOffer('plan3', $plans['plan3']),
                        ],
                    ],
                ],
                [
                    '@type' => 'FAQPage',
                    '@id' => $site . '/#faq',
                    'mainEntity' => [
                        ['@type' => 'Question', 'name' => '¿Cómo transmito mi canal de TV en vivo por Internet en Colombia?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Con Cloudstream tu señal se emite 24/7 desde un datacenter americano. Solo necesitas el plan de Señal Streaming y nosotros nos encargamos de la transmisión con protocolos RTMP y HLS, sin que tengas que montar tu propia infraestructura.']],
                        ['@type' => 'Question', 'name' => '¿Puedo emitir desde mi propio equipo?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Sí, en el plan Señal Streaming. Tú pones tu equipo con OBS, vMix o tu aplicación de emisión, y nosotros te damos el servidor al que debes emitir. En los planes Plataforma Completa y Plataforma + Restream la emisión se hace desde la nube.']],
                        ['@type' => 'Question', 'name' => '¿Cuál es la diferencia entre el plan Señal Streaming y la Plataforma Completa?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'En el plan Señal Streaming el cliente emite desde su propio equipo hacia nuestro servidor. En la Plataforma Completa la emisión se hace desde la nube: 30 GB de almacenamiento, sistema de emisión en la nube, programador de contenido, cuñas publicitarias, logo y respaldo diario.']],
                        ['@type' => 'Question', 'name' => '¿Qué incluye el servicio de streaming para canales de TV?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'El plan base incluye 1 señal streaming 24/7, codecs H264, MP3 y AAC+, formato de 320p a 720p, calidad de emisión de 1000 KBPS, protocolos RTMP + HLS, hasta 300 televidentes, señal con SSL y 720 horas de emisión al mes.']],
                        ['@type' => 'Question', 'name' => '¿Puedo transmitir mi canal a Facebook, YouTube y TikTok al mismo tiempo?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Sí. Con el plan Plataforma + Restream puedes transmitir simultáneamente a Facebook Live, YouTube Live, TikTok Live y destinos RTMP personalizados, con hasta 4 salidas simultáneas.']],
                        ['@type' => 'Question', 'name' => '¿Puedo programar cuñas publicitarias en mi señal?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Sí. El plan Plataforma Completa incluye un programador de contenido que te permite organizar tu parrilla de programación por días y horas, e insertar cuñas publicitarias de forma automática.']],
                        ['@type' => 'Question', 'name' => '¿Emiten factura electrónica?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Sí. Emitimos factura electrónica vigente en Colombia y también cuenta de cobro. El servicio es prestado por Media Clouding SAS, empresa legalmente constituida.']],
                    ],
                ],
                $breadcrumb([
                    ['name' => 'Inicio', 'path' => '/'],
                ]),
            ]);
            break;

        case 'landing':
            $svcName = $data['service_name'] ?? 'Servicio de streaming de TV';
            $svcType = $data['service_type'] ?? 'Streaming de TV';
            $svcDesc = $data['service_description'] ?? '';
            $faqs = $data['faqs'] ?? [];
            $crumbs = $data['breadcrumbs'] ?? [['name' => 'Inicio', 'path' => '/'], ['name' => $svcName, 'path' => '/' . request()->path()]];
            $faqEntities = array_map(function ($q) {
                return [
                    '@type' => 'Question',
                    'name' => $q['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q['a']],
                ];
            }, $faqs);
            $graph = array_merge($graph, [
                $baseOrg,
                $website,
                [
                    '@type' => 'Service',
                    '@id' => $site . '/' . request()->path() . '#service',
                    'name' => $svcName,
                    'serviceType' => $svcType,
                    'provider' => ['@id' => $orgId],
                    'areaServed' => $company['country_code'],
                    'description' => $svcDesc,
                    'offers' => [
                        '@type' => 'AggregateOffer',
                        'priceCurrency' => 'COP',
                        'lowPrice' => (string) $plans['plan1']['price_cop'],
                        'highPrice' => (string) $plans['plan2']['price_cop'],
                        'offerCount' => '3',
                    ],
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => $faqEntities,
                ],
                $breadcrumb($crumbs),
            ]);
            break;

        case 'blog':
            $crumbs = $data['breadcrumbs'] ?? [['name' => 'Inicio', 'path' => '/'], ['name' => 'Blog', 'path' => '/blog'], ['name' => $data['title'] ?? '', 'path' => '/' . request()->path()]];
            $articleSchema = [
                '@type' => 'Article',
                '@id' => $site . '/' . request()->path() . '#article',
                'headline' => $data['title'] ?? '',
                'description' => $data['description'] ?? '',
                'datePublished' => ($data['date_published'] ?? '2026-08-24') . 'T08:00:00-05:00',
                'dateModified' => ($data['date_modified'] ?? $data['date_published'] ?? '2026-08-24') . 'T08:00:00-05:00',
                'inLanguage' => 'es-CO',
                'image' => [
                    '@type' => 'ImageObject',
                    'url' => $site . '/images/blog/' . basename(request()->path()) . '.jpg',
                    'width' => 1200,
                    'height' => 600,
                ],
                'keywords' => $data['keywords'] ?? 'streaming, TV, Colombia, señal de TV, RTMP, HLS, restream',
                'author' => [
                    '@type' => 'Organization',
                    'name' => 'Equipo editorial de ' . $company['brand'],
                    'url' => $site . '/sobre-nosotros',
                    'email' => $company['email'],
                ],
                'editor' => [
                    '@type' => 'Person',
                    'name' => 'Equipo técnico de ' . $company['brand'],
                    'worksFor' => ['@type' => 'Organization', 'name' => $company['name']],
                ],
                'reviewedBy' => [
                    '@type' => 'Person',
                    'name' => 'Equipo técnico de ' . $company['brand'],
                    'worksFor' => ['@id' => $orgId],
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    '@id' => $orgId,
                    'name' => $company['brand'],
                    'logo' => ['@type' => 'ImageObject', 'url' => $site . '/og/home-1200x630.jpg'],
                ],
                'isAccessibleForFree' => true,
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $site . '/' . request()->path()],
                'speakable' => [
                    '@type' => 'SpeakableSpecification',
                    'xpath' => ['/html/head/title', '/html/body//h1', '/html/body//h2[1]'],
                ],
            ];
            $graph = array_merge($graph, [
                $baseOrg,
                $website,
                $articleSchema,
            ]);
            if (! empty($data['faqs'])) {
                $faqEntities = array_map(fn($q) => ['@type' => 'Question', 'name' => $q['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q['a']]], $data['faqs']);
                $graph[] = ['@type' => 'FAQPage', 'mainEntity' => $faqEntities];
            }
            $graph[] = $breadcrumb($crumbs);
            break;

        case 'blog-index':
            $articles = $data['articles'] ?? [];
            $itemList = [];
            foreach ($articles as $i => $a) {
                $itemList[] = [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'url' => $site . $a['path'],
                    'name' => $a['title'],
                ];
            }
            $graph = array_merge($graph, [
                $baseOrg,
                $website,
                [
                    '@type' => 'Blog',
                    '@id' => $site . '/blog#blog',
                    'name' => 'Blog de Cloudstream',
                    'publisher' => ['@id' => $orgId],
                    'inLanguage' => 'es-CO',
                    'blogPost' => array_map(fn($a) => ['@type' => 'BlogPosting', 'headline' => $a['title'], 'url' => $site . $a['path']], $articles),
                ],
                [
                    '@type' => 'ItemList',
                    'itemListElement' => $itemList,
                ],
                $breadcrumb([['name' => 'Inicio', 'path' => '/'], ['name' => 'Blog', 'path' => '/blog']]),
            ]);
            break;

        case 'faq':
            $faqs = $data['faqs'] ?? [];
            $faqEntities = array_map(fn($q) => ['@type' => 'Question', 'name' => $q['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q['a']]], $faqs);
            $graph = array_merge($graph, [
                $baseOrg,
                $website,
                ['@type' => 'FAQPage', '@id' => $site . '/preguntas-frecuentes#faq', 'mainEntity' => $faqEntities],
                $breadcrumb([['name' => 'Inicio', 'path' => '/'], ['name' => 'Preguntas frecuentes', 'path' => '/preguntas-frecuentes']]),
            ]);
            break;

        case 'about':
            $graph = array_merge($graph, [
                $baseOrg,
                $website,
                [
                    '@type' => 'AboutPage',
                    '@id' => $site . '/sobre-nosotros#about',
                    'url' => $site . '/sobre-nosotros',
                    'name' => 'Sobre nosotros — Media Clouding SAS',
                    'primaryImageOfPage' => $site . '/og/home-1200x630.jpg',
                    'isPartOf' => ['@id' => $websiteId],
                    'about' => ['@id' => $orgId],
                    'description' => 'Media Clouding SAS es la empresa colombiana detrás de Cloudstream, servicio de streaming de señal de TV por Internet con datacenter americano y uptime del 99%.',
                ],
                $breadcrumb([['name' => 'Inicio', 'path' => '/'], ['name' => 'Sobre nosotros', 'path' => '/sobre-nosotros']]),
            ]);
            break;

        default:
            $graph = [$baseOrg, $website];
    }

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ];
@endphp

<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
