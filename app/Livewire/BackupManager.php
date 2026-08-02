<?php

namespace App\Livewire;

use App\Models\BackupRecord;
use App\Support\BackupService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class BackupManager extends Component
{
    public string $type = 'database';
    public string $target = 'local';
    public string $target_path = '';
    public string $mode = 'manual';
    public string $scheduled_at = '';

    public function createBackup(BackupService $backupService): void
    {
        $validated = $this->validate([
            'type' => ['required', Rule::in(array_keys(BackupService::TYPES))],
            'target' => ['required', Rule::in(array_keys(BackupService::TARGETS))],
            'target_path' => ['nullable', 'string', 'max:1000'],
            'mode' => ['required', Rule::in(['manual', 'scheduled'])],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        if ($validated['target'] !== 'local' && blank($validated['target_path'])) {
            $this->addError('target_path', 'Target path is required for external, USB, or network backups.');
            return;
        }

        $backupService->create(
            $validated['type'],
            $validated['target'],
            $validated['target_path'] ?: null,
            $validated['mode'],
            $validated['mode'] === 'scheduled' ? ($validated['scheduled_at'] ?: now()->toDateTimeString()) : null,
        );

        session()->flash('status', 'Backup completed.');
    }

    public function render()
    {
        return view('livewire.backup-manager', [
            'types' => BackupService::TYPES,
            'targets' => BackupService::TARGETS,
            'backups' => BackupRecord::query()->with('creator')->latest()->limit(30)->get(),
        ])->layout('layouts.app', ['title' => 'Backups']);
    }
}
