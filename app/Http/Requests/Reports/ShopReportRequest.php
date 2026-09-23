<?php

namespace App\Http\Requests\Reports;

use App\Enums\ShopReportFigure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The report filters, plus which figures the by-shop report shows.
 */
class ShopReportRequest extends ReportFilterRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'figures' => ['nullable', 'array'],
            'figures.*' => [Rule::enum(ShopReportFigure::class)],
        ];
    }

    /**
     * Get the figures asked for, in a fixed order, or all of them when none were.
     *
     * @return array<int, ShopReportFigure>
     */
    public function figures(): array
    {
        $requested = array_map(ShopReportFigure::from(...), (array) $this->query('figures', []));

        return array_values(array_filter(
            ShopReportFigure::cases(),
            fn (ShopReportFigure $figure) => $requested === [] || in_array($figure, $requested, true),
        ));
    }
}
