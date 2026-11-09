<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sirve los clips del walkthrough público (los .mp4 del módulo de emisión).
 *
 * nginx solo entrega como estáticos las extensiones listadas en su vhost
 * (css|js|jpg|jpeg|png|gif|ico|svg|woff...); los .mp4 no están en esa lista,
 * así que pasan por Laravel para no depender de una edición del servidor.
 */
class PublicVideoController extends Controller
{
    public function show(Request $request, string $slug, string $file)
    {
        $walkthrough = (array) config('seo.walkthrough', []);
        $id = pathinfo($file, PATHINFO_FILENAME);
        $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));

        if ($extension !== 'mp4' || $slug !== ($walkthrough['slug'] ?? '')) {
            abort(404);
        }

        $allowed = array_merge(
            (array) ($walkthrough['chapters'] ?? []),
            array_filter([$walkthrough['full'] ?? null])
        );

        if (! in_array($id, $allowed, true)) {
            abort(404);
        }

        $path = public_path("videos/{$slug}/{$id}.mp4");
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
