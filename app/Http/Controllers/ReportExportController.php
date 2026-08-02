<?php

namespace App\Http\Controllers;

use App\Exports\GenericReportExport;
use App\Support\ReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
    public function export(Request $request, string $format, ReportBuilder $builder)
    {
        abort_unless(in_array($format, ['pdf', 'excel'], true), 404);

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(ReportBuilder::TYPES))],
            'period' => ['required', 'in:'.implode(',', array_keys(ReportBuilder::PERIODS))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $report = $builder->build(
            $validated['type'],
            $validated['period'],
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            auth()->user()?->branch_id,
        );
        $filename = Str::slug($report['title']).'-'.now()->format('Ymd-His');

        if ($format === 'pdf') {
            return Pdf::loadView('reports.export-pdf', ['report' => $report])->download($filename.'.pdf');
        }

        return Excel::download(new GenericReportExport($report), $filename.'.xlsx');
    }
}
