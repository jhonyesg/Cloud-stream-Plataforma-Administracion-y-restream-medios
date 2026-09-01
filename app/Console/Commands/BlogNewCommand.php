<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BlogNewCommand extends Command
{
    protected $signature = 'blog:new {slug : The blog post slug (kebab-case)}';
    protected $description = 'Scaffold a new blog blade file from the standard stub';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        if (! preg_match('/^[a-z0-9\-]+$/', $slug)) {
            $this->error('Slug must be kebab-case (lowercase letters, numbers, hyphens).');
            return self::FAILURE;
        }

        $path = resource_path('views/public/blog/' . $slug . '.blade.php');
        if (file_exists($path)) {
            $this->error("File already exists: {$path}");
            return self::FAILURE;
        }

        $stub = <<<'BLADE'
@php
    $wa = preg_replace('/^\+/', '', config('seo.company.phone_e164'));
    $faqs = [
        ['q' => 'Pregunta frecuente 1?', 'a' => 'Respuesta a la primera pregunta frecuente.'],
        ['q' => 'Pregunta frecuente 2?', 'a' => 'Respuesta a la segunda pregunta frecuente.'],
    ];
@endphp

<x-public-layout
    :title="'Título del artículo | Cloudstream'"
    :description="'Descripción del artículo para SEO, entre 120 y 165 caracteres.'"
    :canonical="config('seo.site_url') . '/blog/SLUG'"
    :schema="['type' => 'blog', 'data' => ['title' => 'Título del artículo', 'description' => 'Descripción del artículo', 'date_published' => '2026-08-24', 'date_modified' => '2026-08-24', 'faqs' => $faqs]]"
>
    <article class="max-w-3xl mx-auto px-4 sm:px-6 pt-12 pb-16 prose-public">
        <div class="text-xs text-slate-500 mb-3">24 de agosto de 2026 · X min de lectura</div>
        <h1>Título del artículo</h1>

        <p>Introducción del artículo. Mínimo 800 palabras en total.</p>

        <h2>Primer subtítulo</h2>
        <p>Contenido del artículo...</p>
    </article>
</x-public-layout>
BLADE;

        $stub = str_replace('SLUG', $slug, $stub);
        file_put_contents($path, $stub);

        $this->info("Created: {$path}");
        $this->line("Don't forget to add this slug to config('seo.blog_slugs') and config('seo.public_urls').");
        return self::SUCCESS;
    }
}
