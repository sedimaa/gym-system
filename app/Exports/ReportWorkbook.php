<?php

namespace App\Exports;

use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Excel workbook for one report type and date range. */
class ReportWorkbook implements Export, WithMultipleSheets
{
    public function __construct(
        private readonly string $type,
        private readonly array $data,
        private readonly CarbonImmutable $from,
        private readonly CarbonImmutable $to,
    ) {}

    public function sheets(): array
    {
        return match ($this->type) {
            'income' => $this->incomeSheets(),
            'attendance' => $this->attendanceSheets(),
            'members' => $this->memberSheets(),
        };
    }

    private function incomeSheets(): array
    {
        $d = $this->data;

        return [
            new ArraySheet('Daily income', ['Date', 'Members (₱)', 'Walk-ins (₱)', 'Total (₱)'],
                [...$d['daily']->map(fn ($r) => [$r['date'], $r['members'], $r['walkins'], $r['total']])->all(),
                    ['TOTAL', $d['totals']['members'], $d['totals']['walkins'], $d['totals']['total']]]),
            new ArraySheet('By plan', ['Plan', 'Count', 'Amount (₱)'],
                $d['byPlan']->map(fn ($r) => [$r['label'], $r['count'], $r['amount']])->all()),
            new ArraySheet('By payment method', ['Paid via', 'Count', 'Amount (₱)'],
                $d['byMethod']->map(fn ($r) => [$r['label'], $r['count'], $r['amount']])->all()),
            new ArraySheet('Member payments', ['Receipt', 'Date', 'Member code', 'Member', 'Plan', 'Type', 'Amount (₱)', 'Paid via', 'Reference', 'Received by'],
                $d['payments']->map(fn ($p) => [$p->receipt_no, $p->paid_on->toDateString(), $p->member->member_code, $p->member->full_name, $p->plan->name, $p->kindLabel(), (float) $p->amount, $p->method->label(), $p->reference_no, $p->receiver->name])->all()),
            new ArraySheet('Walk-ins', ['Date', 'Time', 'Guest', 'Amount (₱)', 'Paid via', 'Reference', 'Recorded by'],
                $d['walkins']->map(fn ($w) => [$w->visited_at->toDateString(), $w->visited_at->format('g:i A'), $w->displayName(), (float) $w->amount, $w->method->label(), $w->reference_no, $w->recorder->name])->all()),
        ];
    }

    private function attendanceSheets(): array
    {
        $d = $this->data;

        return [
            new ArraySheet('Daily attendance', ['Date', 'Members', 'Walk-ins', 'Total', 'Denied'],
                [...$d['daily']->map(fn ($r) => [$r['date'], $r['members'], $r['walkins'], $r['total'], $r['denied']])->all(),
                    ['TOTAL', $d['totals']['members'], $d['totals']['walkins'], $d['totals']['total'], $d['totals']['denied']]]),
            new ArraySheet('Member check-ins', ['Date', 'Time', 'Member code', 'Member', 'Status', 'Note', 'Recorded by'],
                $d['records']->map(fn ($a) => [$a->checked_in_at->toDateString(), $a->checked_in_at->format('g:i A'), $a->member->member_code, $a->member->full_name, $a->status->label(), $a->note, $a->recorder->name])->all()),
        ];
    }

    private function memberSheets(): array
    {
        $d = $this->data;

        return [
            new ArraySheet('New members', ['Joined', 'Member code', 'Name', 'Phone'],
                $d['newMembers']->map(fn ($m) => [$m->joined_on->toDateString(), $m->member_code, $m->full_name, $m->phone])->all()),
            new ArraySheet('First plans', ['Date', 'Receipt', 'Member code', 'Member', 'Plan', 'Amount (₱)'],
                $d['firstPlans']->map(fn ($p) => [$p->paid_on->toDateString(), $p->receipt_no, $p->member->member_code, $p->member->full_name, $p->plan->name, (float) $p->amount])->all()),
            new ArraySheet('Renewals', ['Date', 'Receipt', 'Member code', 'Member', 'Plan', 'Amount (₱)'],
                $d['renewals']->map(fn ($p) => [$p->paid_on->toDateString(), $p->receipt_no, $p->member->member_code, $p->member->full_name, $p->plan->name, (float) $p->amount])->all()),
        ];
    }
}
