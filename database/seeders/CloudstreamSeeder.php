<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\EmissionState;
use App\Models\MediaItem;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\User;
use App\Models\VirtualScreen;
use Illuminate\Database\Seeder;

class CloudstreamSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@cloudstream.local')->first();
        $cliente = User::where('email', 'cliente@cloudstream.local')->first();
        $cinedios = User::where('email', 'cinedios@cloudstream.local')->first();
        $redplanet = User::where('email', 'redplanet@redplanet.com')->first();

        $cineDios = Channel::updateOrCreate(
            ['slug' => 'cine-dios'],
            [
                'id' => '44444444-4444-4444-4444-444444444444',
                'owner_id' => $cinedios?->id ?? $cliente?->id,
                'display_name' => 'Cine Dios',
                'description' => 'Películas cristianas',
                'status' => 'active',
                'default_width' => 1280,
                'default_height' => 720,
                'root_path' => '/mnt/multimedia/Cine Dios',
            ]
        );

        $redPlane = Channel::updateOrCreate(
            ['slug' => 'red-plane'],
            [
                'id' => '55555555-5555-5555-5555-555555555555',
                'owner_id' => $redplanet?->id ?? $cliente?->id,
                'display_name' => 'Red Plane',
                'description' => 'Canal alterno',
                'status' => 'active',
                'default_width' => 1280,
                'default_height' => 720,
                'root_path' => '/mnt/multimedia/Red Plane',
            ]
        );

        if ($cinedios) $cineDios->assignedUsers()->syncWithoutDetaching([$cinedios->id]);
        if ($redplanet) $redPlane->assignedUsers()->syncWithoutDetaching([$redplanet->id]);

        $items = [
            ['Cine Dios', 'Dios no esta muerto.mp4', 'video', 'Dios no está muerto', 5400, 1280, 720],
            ['Cine Dios', 'El Evangelio de Lucas.mp4', 'video', 'El Evangelio de Lucas', 7200, 1920, 1080],
            ['Cine Dios', 'El_camino_de_la_redencion.mp4', 'video', 'El camino de la redención', 3600, 1280, 720],
            ['Red Plane', 'Inicio canal.mp4', 'video', 'Inicio canal', 600, 1280, 720],
            ['Cine Dios', 'DEMO CINE DIOS.mp4', 'ad', 'Demo Cine Dios (promo)', 45, 1280, 720],
        ];

        $channelMap = [
            'Cine Dios' => $cineDios->id,
            'Red Plane' => $redPlane->id,
        ];

        $created = [];
        foreach ($items as $i => [$channelName, $filename, $kind, $title, $duration, $w, $h]) {
            $mi = MediaItem::updateOrCreate(
                ['channel_id' => $channelMap[$channelName], 'filename' => $filename],
                [
                    'id' => sprintf('77777777-7777-7777-7777-%012d', $i + 1),
                    'filename' => $filename,
                    'channel_id' => $channelMap[$channelName],
                    'sha256' => null,
                    'size_bytes' => 50_000_000,
                    'mime_type' => 'video/mp4',
                    'kind' => $kind,
                    'status' => 'ready',
                    'duration_sec' => $duration,
                    'width' => $w,
                    'height' => $h,
                    'codec_video' => 'h264',
                    'codec_audio' => 'aac',
                    'bitrate_kbps' => 2500,
                    'metadata' => ['title' => $title, 'source' => 'seed'],
                ]
            );
            $created[$channelName . '/' . $filename] = $mi;
        }

        $playlistCine = Playlist::updateOrCreate(
            ['channel_id' => $cineDios->id, 'name' => 'Loop Cine Dios'],
            [
                'id' => '88888888-8888-8888-8888-888888888881',
                'description' => 'Programación 24/7 de Cine Dios',
                'loop' => true,
                'is_default' => true,
            ]
        );

        Playlist::where('channel_id', $cineDios->id)->where('is_default', true)->where('id', '!=', $playlistCine->id)->update(['is_default' => false]);

        $itemsCine = [
            ['Cine Dios/Dios no esta muerto.mp4', 1],
            ['Cine Dios/El Evangelio de Lucas.mp4', 2],
            ['Cine Dios/El_camino_de_la_redencion.mp4', 3],
        ];
        foreach ($itemsCine as [$key, $pos]) {
            $mi = $created[$key] ?? null;
            if (! $mi) continue;
            PlaylistItem::updateOrCreate(
                ['playlist_id' => $playlistCine->id, 'position' => $pos],
                [
                    'id' => sprintf('99999999-9999-9999-9999-%012d', $pos),
                    'media_item_id' => $mi->id,
                    'transition_in' => 'fade',
                ]
            );
        }
        Playlist::recalculateDuration($playlistCine->fresh());

        $playlistRed = Playlist::updateOrCreate(
            ['channel_id' => $redPlane->id, 'name' => 'Loop Red Plane'],
            [
                'id' => '88888888-8888-8888-8888-888888888882',
                'description' => 'Programación corta de Red Plane',
                'loop' => true,
                'is_default' => true,
            ]
        );

        Playlist::where('channel_id', $redPlane->id)->where('is_default', true)->where('id', '!=', $playlistRed->id)->update(['is_default' => false]);

        $mi = $created['Red Plane/Inicio canal.mp4'];
        PlaylistItem::updateOrCreate(
            ['playlist_id' => $playlistRed->id, 'position' => 1],
            [
                'id' => '99999999-9999-9999-9999-000000000099',
                'media_item_id' => $mi->id,
                'transition_in' => 'cut',
            ]
        );
        Playlist::recalculateDuration($playlistRed->fresh());

        VirtualScreen::updateOrCreate(
            ['channel_id' => $cineDios->id],
            [
                'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaa1',
                'name' => 'Preview Cine Dios',
                'width' => 1280,
                'height' => 720,
                'layout' => ['background' => '#000000', 'scale_mode' => 'fit'],
            ]
        );

        VirtualScreen::updateOrCreate(
            ['channel_id' => $redPlane->id],
            [
                'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaa2',
                'name' => 'Preview Red Plane',
                'width' => 1280,
                'height' => 720,
                'layout' => ['background' => '#000000', 'scale_mode' => 'fit'],
            ]
        );

        EmissionState::updateOrCreate(
            ['channel_id' => $cineDios->id],
            [
                'status' => 'offline',
                'updated_at' => now(),
            ]
        );

        EmissionState::updateOrCreate(
            ['channel_id' => $redPlane->id],
            [
                'status' => 'offline',
                'updated_at' => now(),
            ]
        );
    }
}