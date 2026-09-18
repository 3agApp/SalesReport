<?php

namespace App\Http\Requests\Reports;

use App\Data\ReportFilters;
use App\Enums\ReportPeriod;
use App\Models\Order;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('viewReports', $this->organization());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::enum(ReportPeriod::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'shops' => ['nullable', 'array'],
            'shops.*' => ['integer'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', Rule::in(Order::OPEN_STATUSES + ['cancelled', 'failed', 'trash'])],
        ];
    }

    /**
     * Turn the query string into a resolved set of filters.
     *
     * Shops are intersected with the organization's own, so a shop id guessed
     * in the query string can never widen the report beyond what the viewer is
     * allowed to see.
     */
    public function filters(): ReportFilters
    {
        $organization = $this->organization();
        $timezone = $organization->reportingTimezone();

        $period = ReportPeriod::tryFrom((string) $this->query('period')) ?? ReportPeriod::Last12Months;
        [$from, $to] = $this->resolveRange($period, $timezone);

        $ownShopIds = $organization->shops()->pluck('id')->all();
        $requested = array_map('intval', (array) $this->query('shops', []));
        $shopIds = $requested === [] ? $ownShopIds : array_values(array_intersect($ownShopIds, $requested));

        $statuses = array_values(array_filter(
            (array) $this->query('statuses', []),
            fn (mixed $status) => is_string($status) && $status !== '',
        ));

        return new ReportFilters(
            period: $period,
            from: $from,
            to: $to,
            timezone: $timezone,
            shopIds: $shopIds === [] ? $ownShopIds : $shopIds,
            statuses: $statuses === [] ? ReportFilters::defaultStatuses() : $statuses,
        );
    }

    /**
     * Work out the start and end of the range being reported on.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(ReportPeriod $period, string $timezone): array
    {
        if ($period !== ReportPeriod::Custom) {
            return $period->resolve($timezone);
        }

        $from = $this->dateInput('from', $timezone)?->startOfDay();
        $to = $this->dateInput('to', $timezone)?->endOfDay();

        // A half-filled custom range still has to produce something sensible.
        $from ??= ($to ?? CarbonImmutable::now($timezone))->startOfMonth();
        $to ??= CarbonImmutable::now($timezone)->endOfDay();

        return $from->greaterThan($to) ? [$to->startOfDay(), $from->endOfDay()] : [$from, $to];
    }

    /**
     * Read a Y-m-d query parameter in the organization's timezone.
     */
    private function dateInput(string $key, string $timezone): ?CarbonImmutable
    {
        $value = $this->query($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        // The date_format rule above has already vouched for the shape.
        return CarbonImmutable::createFromFormat('Y-m-d', $value, $timezone);
    }

    /**
     * Get the organization being reported on.
     */
    private function organization(): Organization
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return $organization;
    }
}
