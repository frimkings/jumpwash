<?php

namespace App\Livewire;

use App\Support\ReportBuilder;
use Livewire\Component;

class ReportsManager extends Component
{
    public string $type = 'sales';
    public string $period = 'daily';
    public string $start_date = '';
    public string $end_date = '';

    public function mount(): void
    {
        $this->start_date = today()->toDateString();
        $this->end_date = today()->toDateString();
    }

    public function render(ReportBuilder $builder)
    {
        $report = $builder->build($this->type, $this->period, $this->start_date, $this->end_date, auth()->user()?->branch_id);

        return view('livewire.reports-manager', [
            'report' => $report,
            'types' => ReportBuilder::TYPES,
            'periods' => ReportBuilder::PERIODS,
            'exportQuery' => http_build_query([
                'type' => $this->type,
                'period' => $this->period,
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
            ]),
        ])->layout('layouts.app', ['title' => 'Reports']);
    }
}
