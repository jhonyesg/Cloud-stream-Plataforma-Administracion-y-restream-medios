<?php

namespace App\Services\Media;

class MediaHygieneReport
{
    public int $channelsScanned = 0;

    /** @var array<int, array<string, mixed>> */
    public array $fileMissing = [];

    /** @var array<int, array<string, mixed>> */
    public array $misclassified = [];

    /** @var array<int, array<string, mixed>> */
    public array $unindexedUploads = [];

    /** @var array<int, array<string, mixed>> */
    public array $unindexedDiskFiles = [];

    public function addFileMissing(array $row): void
    {
        $this->fileMissing[] = $row;
    }

    public function addMisclassified(array $row): void
    {
        $this->misclassified[] = $row;
    }

    public function addUnindexedUpload(array $row): void
    {
        $this->unindexedUploads[] = $row;
    }

    public function addUnindexedDiskFile(array $row): void
    {
        $this->unindexedDiskFiles[] = $row;
    }

    public function totals(): array
    {
        return [
            'channels_scanned' => $this->channelsScanned,
            'file_missing' => count($this->fileMissing),
            'misclassified' => count($this->misclassified),
            'unindexed_uploads' => count($this->unindexedUploads),
            'unindexed_disk_files' => count($this->unindexedDiskFiles),
        ];
    }

    public function toArray(): array
    {
        return [
            'channels_scanned' => $this->channelsScanned,
            'file_missing' => $this->fileMissing,
            'misclassified' => $this->misclassified,
            'unindexed_uploads' => $this->unindexedUploads,
            'unindexed_disk_files' => $this->unindexedDiskFiles,
        ];
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->toArray(), $flags);
    }
}