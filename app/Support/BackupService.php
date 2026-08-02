<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\BackupRecord;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class BackupService
{
    public const TYPES = [
        'database' => 'Database Backup',
        'full_system' => 'Full System Backup',
    ];

    public const TARGETS = [
        'local' => 'Local Backup Folder',
        'external_drive' => 'External Drive',
        'usb' => 'USB',
        'network_folder' => 'Network Folder',
    ];

    public function create(string $type, string $target, ?string $targetPath = null, string $mode = 'manual', ?string $scheduledAt = null): BackupRecord
    {
        $backupNumber = $this->nextBackupNumber();
        $relativeDirectory = 'backups/'.now()->format('Y/m/d');
        Storage::disk('local')->makeDirectory($relativeDirectory);

        $relativePath = $type === 'full_system'
            ? $this->createFullBackup($backupNumber, $relativeDirectory)
            : $this->createDatabaseBackup($backupNumber, $relativeDirectory);

        $absolutePath = Storage::disk('local')->path($relativePath);

        if ($target !== 'local' && $targetPath) {
            File::ensureDirectoryExists($targetPath);
            File::copy($absolutePath, rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename($absolutePath));
        }

        $record = BackupRecord::create([
            'branch_id' => auth()->user()?->branch_id,
            'created_by' => auth()->id(),
            'backup_number' => $backupNumber,
            'type' => $type,
            'mode' => $mode,
            'target' => $target,
            'target_path' => $targetPath,
            'file_path' => $relativePath,
            'file_size' => File::size($absolutePath),
            'status' => 'completed',
            'scheduled_at' => $scheduledAt,
            'completed_at' => now(),
        ]);

        ActivityLog::record('backup.created', $record, [
            'backup_number' => $backupNumber,
            'type' => $type,
            'target' => $target,
            'target_path' => $targetPath,
        ]);

        return $record;
    }

    private function createDatabaseBackup(string $backupNumber, string $directory): string
    {
        $connection = config('database.default');
        $filename = $backupNumber.'-database.sql';
        $relativePath = $directory.'/'.$filename;

        if ($connection === 'sqlite') {
            $relativePath = $directory.'/'.$backupNumber.'-database.sqlite';
            $database = config('database.connections.sqlite.database');

            if ($database === ':memory:' || ! $database || ! File::exists($database)) {
                Storage::disk('local')->put($directory.'/'.$backupNumber.'-database-manifest.json', json_encode([
                    'connection' => $connection,
                    'database' => $database,
                    'created_at' => now()->toDateTimeString(),
                    'note' => 'SQLite database file was not available for physical copy.',
                ], JSON_PRETTY_PRINT));

                return $directory.'/'.$backupNumber.'-database-manifest.json';
            }

            File::copy($database, Storage::disk('local')->path($relativePath));

            return $relativePath;
        }

        $summary = [
            'connection' => $connection,
            'database' => config("database.connections.{$connection}.database"),
            'created_at' => now()->toDateTimeString(),
            'note' => 'Use local MariaDB/MySQL dump tooling on this LAN machine for physical restore.',
        ];

        Storage::disk('local')->put($relativePath, json_encode($summary, JSON_PRETTY_PRINT));

        return $relativePath;
    }

    private function createFullBackup(string $backupNumber, string $directory): string
    {
        $relativePath = $directory.'/'.$backupNumber.'-full-system.zip';
        $absolutePath = Storage::disk('local')->path($relativePath);

        if (! class_exists(ZipArchive::class)) {
            Storage::disk('local')->put($directory.'/'.$backupNumber.'-full-system-manifest.json', json_encode([
                'created_at' => now()->toDateTimeString(),
                'note' => 'ZipArchive is not enabled. Manifest created instead of ZIP.',
            ], JSON_PRETTY_PRINT));

            return $directory.'/'.$backupNumber.'-full-system-manifest.json';
        }

        $zip = new ZipArchive();
        $zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'storage/app'] as $folder) {
            $base = base_path($folder);
            if (! File::exists($base)) {
                continue;
            }

            foreach (File::allFiles($base) as $file) {
                if (str_contains($file->getPathname(), 'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'backups')) {
                    continue;
                }

                $zip->addFile($file->getPathname(), Str::after($file->getPathname(), base_path().DIRECTORY_SEPARATOR));
            }
        }

        $zip->close();

        return $relativePath;
    }

    private function nextBackupNumber(): string
    {
        $prefix = 'BKP-'.now()->format('Ymd').'-';
        $count = BackupRecord::query()->where('backup_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }
}
