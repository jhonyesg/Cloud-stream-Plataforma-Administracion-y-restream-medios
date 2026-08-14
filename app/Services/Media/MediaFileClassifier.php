<?php

namespace App\Services\Media;

class MediaFileClassifier
{
    public const KIND_VIDEO = 'video';
    public const KIND_IMAGE = 'image';
    public const KIND_AUDIO = 'audio';
    public const KIND_OTHER = 'other';

    public const PLAYLIST_FILENAME = 'playlist.txt';

    public static function videoExtensions(): array
    {
        return ['mp4', 'webm', 'mov', 'mkv', 'avi', 'flv', 'wmv', 'm4v', 'mpg', 'mpeg', 'ts', '3gp'];
    }

    public static function imageExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'tiff', 'ico'];
    }

    public static function audioExtensions(): array
    {
        return ['mp3', 'wav', 'aac', 'flac', 'ogg', 'm4a', 'wma', 'opus'];
    }

    public static function deniedExtensions(): array
    {
        return ['tmp', 'py', 'log', 'lock'];
    }

    public static function isUploadTmp(string $filename): bool
    {
        return (bool) preg_match('/\.upload\.tmp$/', $filename);
    }

    public static function isPlaylistFile(string $filename): bool
    {
        return $filename === self::PLAYLIST_FILENAME;
    }

    public static function isDenied(string $filename): bool
    {
        if (self::isUploadTmp($filename)) {
            return true;
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === '') {
            return false;
        }
        return in_array($ext, self::deniedExtensions(), true);
    }

    public static function extension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    public static function classify(string $filename): ?string
    {
        if (self::isPlaylistFile($filename)) {
            return self::KIND_OTHER;
        }
        $ext = self::extension($filename);
        if ($ext === '') {
            return null;
        }
        if (in_array($ext, self::videoExtensions(), true)) {
            return self::KIND_VIDEO;
        }
        if (in_array($ext, self::imageExtensions(), true)) {
            return self::KIND_IMAGE;
        }
        if (in_array($ext, self::audioExtensions(), true)) {
            return self::KIND_AUDIO;
        }
        return null;
    }

    public static function isAccepted(string $filename): bool
    {
        if (self::isPlaylistFile($filename)) {
            return true;
        }
        if (self::isDenied($filename)) {
            return false;
        }
        return self::classify($filename) !== null;
    }
}