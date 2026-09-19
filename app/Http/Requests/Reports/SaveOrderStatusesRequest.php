<?php

namespace App\Http\Requests\Reports;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveOrderStatusesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Deciding what counts as revenue changes every figure the organization
     * reports, so it sits with the people who can change the organization.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->organization());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'statuses' => ['required', 'array'],
            // Only statuses the organization's own orders actually use. A
            // slug posted from outside that set has nothing to describe.
            'statuses.*.status' => ['required', 'string', Rule::in(array_keys($this->organization()->orderStatuses()))],
            'statuses.*.label' => ['nullable', 'string', 'max:60'],
            'statuses.*.countsAsRevenue' => ['required', 'boolean'],
        ];
    }

    /**
     * Get the decisions being saved.
     *
     * @return array<int, array{status: string, label: string|null, countsAsRevenue: bool}>
     */
    public function statuses(): array
    {
        $statuses = [];

        foreach ((array) $this->validated('statuses') as $status) {
            if (! is_array($status) || ! is_string($status['status'] ?? null)) {
                continue;
            }

            $label = is_string($status['label'] ?? null) ? trim($status['label']) : '';

            $statuses[] = [
                'status' => $status['status'],
                // An empty box means "no name of our own", not an empty name.
                'label' => $label === '' ? null : $label,
                'countsAsRevenue' => (bool) ($status['countsAsRevenue'] ?? false),
            ];
        }

        return $statuses;
    }

    /**
     * Get the organization being configured.
     */
    private function organization(): Organization
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return $organization;
    }
}
