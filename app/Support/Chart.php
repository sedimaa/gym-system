<?php

namespace App\Support;

/** Builds Chart.js configs for <canvas data-chart="..."> (rendered by resources/js/charts.js). */
class Chart
{
    public const COLORS = ['members' => '#1e293b', 'walkins' => '#f59e0b', 'denied' => '#dc2626', 'accent' => '#64748b'];

    /**
     * @param  list<string>  $labels
     * @param  list<array{label: string, data: list<int|float>, color: string}>  $series
     */
    public static function bar(array $labels, array $series, bool $money = false, bool $stacked = true): string
    {
        return json_encode([
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => array_map(fn ($s) => [
                    'label' => $s['label'],
                    'data' => $s['data'],
                    'backgroundColor' => $s['color'],
                    'borderRadius' => 3,
                    'maxBarThickness' => 28,
                ], $series),
            ],
            'options' => array_filter([
                'money' => $money ?: null,
                'interaction' => ['mode' => 'index', 'intersect' => false],
                'scales' => [
                    'x' => ['stacked' => $stacked, 'grid' => ['display' => false]],
                    'y' => ['stacked' => $stacked, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
                ],
                'plugins' => ['legend' => ['position' => 'bottom']],
            ]),
        ], JSON_UNESCAPED_UNICODE);
    }
}
