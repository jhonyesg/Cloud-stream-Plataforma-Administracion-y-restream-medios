<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Filesystem browse endpoint for the admin channel-path picker.
 *
 * Sandboxed to config('cloudstream.fs_browse_root') (env FS_BROWSE_ROOT,
 * default /mnt/multimedia). Paths outside the root — including via ".."
 * traversal — are rejected. Only directories are returned; files are excluded.
 */
class FsBrowseController extends Controller
{
    public function browse(Request $request): JsonResponse
    {
        try {
            $root = realpath(config('cloudstream.fs_browse_root', '/mnt/multimedia'));
        } catch (\Throwable $e) {
            $root = false;
        }
        if ($root === false) {
            throw ValidationException::withMessages([
                'path' => ['Browse root is not accessible from this PHP process (check open_basedir or FS_BROWSE_ROOT config).'],
            ])->status(422);
        }
        $root = rtrim($root, '/') . '/';

        $requested = (string) $request->query('path', '');
        if ($requested === '') {
            $requested = $root;
        }

        if (! Str::startsWith($requested, '/')) {
            throw ValidationException::withMessages([
                'path' => ['Path must be absolute.'],
            ])->status(422);
        }

        try {
            $resolved = realpath($requested);
        } catch (\Throwable $e) {
            $resolved = false;
        }
        if ($resolved === false || ! is_dir($resolved)) {
            throw ValidationException::withMessages([
                'path' => ['Path not found or is not a directory.'],
            ])->status(422);
        }

        $resolved = rtrim($resolved, '/') . '/';
        if (! Str::startsWith($resolved, $root) && $resolved !== rtrim($root, '/')) {
            throw ValidationException::withMessages([
                'path' => ['Path is outside the browse root.'],
            ])->status(422);
        }

        try {
            $dirs = File::directories($resolved);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'path' => ['Cannot read directory contents: '.$e->getMessage()],
            ])->status(422);
        }

        $entries = [];
        foreach ($dirs as $dir) {
            $entries[] = [
                'name' => basename($dir),
                'path' => $dir,
                'type' => 'dir',
            ];
        }
        usort($entries, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        $parent = $resolved === $root ? null : dirname(rtrim($resolved, '/'));

        return response()->json([
            'path' => rtrim($resolved, '/'),
            'parent' => $parent,
            'entries' => $entries,
        ]);
    }
}