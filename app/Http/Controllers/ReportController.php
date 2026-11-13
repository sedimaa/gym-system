<?php

namespace App\Http\Controllers;

use App\Exports\ReportWorkbook;
use App\Http\Requests\ReportRequest;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(ReportRequest $request): View
    {
        return view('reports.index', $this->viewData($request));
    }

    public function export(ReportRequest $request, string $format): Response
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $data = $this->viewData($request);
        $name = sprintf('%s-report_%s_to_%s', $request->type(), $request->from()->toDateString(), $request->to()->toDateString());

        if ($format === 'xlsx') {
            return Excel::download(new ReportWorkbook($request->type(), $data['report'], $request->from(), $request->to()), "{$name}.xlsx");
        }

        return Pdf::loadView('reports.pdf', $data + ['gymName' => setting('gym_name')])
            ->setPaper('a4')
            ->download("{$name}.pdf");
    }

    private function viewData(ReportRequest $request): array
    {
        $from = $request->from();
        $to = $request->to();

        return [
            'type' => $request->type(),
            'types' => ReportRequest::TYPES,
            'presets' => ReportRequest::PRESETS,
            'from' => $from,
            'to' => $to,
            'report' => match ($request->type()) {
                'income' => $this->reports->income($from, $to),
                'attendance' => $this->reports->attendance($from, $to),
                'members' => $this->reports->members($from, $to),
            },
        ];
    }
}
