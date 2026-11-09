@props([
    'chapters' => [],
    'src' => null,
    'poster' => null,
    'label' => 'Demostración del flujo de emisión a YouTube',
])

@php
    $jsChapters = array_values(array_map(fn ($chapter) => [
        'start' => (float) ($chapter['start'] ?? 0),
        'image' => $chapter['image'] ?? ($chapter['poster'] ?? ''),
    ], $chapters));
    $firstImage = $poster ?: ($chapters[0]['image'] ?? null);
@endphp

@once
    <script>
        window.videoWalkthrough = window.videoWalkthrough || function (chapters) {
            return {
                chapters: chapters,
                current: 0,
                init() {
                    this.video = this.$refs.video;
                },
                goTo(index) {
                    const chapter = this.chapters[index];
                    const video = this.video || this.$refs.video;
                    if (!chapter || !video) {
                        return;
                    }

                    this.current = index;
                    video.currentTime = chapter.start || 0;

                    const playing = video.play();
                    if (playing && typeof playing.catch === 'function') {
                        playing.catch(() => {});
                    }
                },
            };
        };
    </script>
@endonce

<div class="walkthrough" x-data="videoWalkthrough({{ Illuminate\Support\Js::from($jsChapters) }})">
    <div class="glass-card rounded-2xl overflow-hidden">
        <video
            x-ref="video"
            class="w-full h-auto block bg-slate-950"
            @if ($src)
                src="{{ $src }}"
            @endif
            @if ($firstImage)
                poster="{{ $firstImage }}"
            @endif
            preload="none"
            playsinline
            muted
            controls
            aria-label="{{ $label }}"
            :poster="chapters[current] ? chapters[current].image : '{{ $firstImage }}'"
        ></video>
        <p class="px-5 py-4 text-xs text-slate-400">
            Grabación real del panel de Cloudstream. Video sin sonido: los pasos están descritos abajo.
        </p>
    </div>

    <ol class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach ($chapters as $index => $chapter)
            <li>
                <button
                    type="button"
                    data-start="{{ (float) ($chapter['start'] ?? 0) }}"
                    data-image="{{ $chapter['image'] ?? '' }}"
                    @click="goTo({{ $index }})"
                    @keydown.enter.prevent="goTo({{ $index }})"
                    aria-label="Ver el paso {{ $index + 1 }}: {{ $chapter['title'] ?? '' }}"
                    class="w-full text-left glass-card rounded-2xl overflow-hidden transition hover:border-rose-400/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400"
                    :class="current === {{ $index }} ? 'border-rose-400/60' : ''"
                >
                    @if (! empty($chapter['image']))
                        <img
                            src="{{ $chapter['image'] }}"
                            alt="{{ $chapter['alt'] ?? ('Captura del paso ' . ($index + 1) . ': ' . ($chapter['title'] ?? '')) }}"
                            loading="lazy"
                            decoding="async"
                            width="1280"
                            height="640"
                            class="w-full h-auto block"
                        >
                    @endif
                    <span class="block p-5">
                        <span class="flex items-start gap-3">
                            <span class="w-8 h-8 shrink-0 rounded-full bg-gradient-to-br from-rose-400 to-rose-600 text-white flex items-center justify-center text-sm font-bold">{{ $index + 1 }}</span>
                            <span class="min-w-0">
                                <span class="block text-lg font-semibold text-white">{{ $chapter['title'] ?? '' }}</span>
                                <span class="block mt-1 text-sm text-slate-300 leading-relaxed">{{ $chapter['text'] ?? '' }}</span>
                            </span>
                        </span>
                    </span>
                </button>
            </li>
        @endforeach
    </ol>
</div>
