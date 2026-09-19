<?php

namespace App\Http\Requests\Shops;

use App\Enums\ShopPlatform;
use App\Models\Organization;
use App\Models\Shop;
use App\Rules\PublicShopUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveShopRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $shop = $this->shop();

        return $shop
            ? Gate::allows('update', $shop)
            : Gate::allows('create', [Shop::class, $this->organization()]);
    }

    /**
     * Normalise the shop URL before it is validated, so the uniqueness check
     * sees the same value that is stored.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('url'))) {
            $this->merge(['url' => Shop::normalizeUrl($this->string('url')->value())]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Credentials are write-only: on an update, leaving them blank keeps
        // the stored ones, since they are never sent back to the browser.
        $credentialRules = $this->shop() ? ['nullable'] : ['required'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => [
                'required',
                'string',
                'max:255',
                'url:http,https',
                app(PublicShopUrl::class),
                Rule::unique(Shop::class)
                    ->where('organization_id', $this->organization()->id)
                    ->ignore($this->shop()?->id),
            ],
            'platform' => ['required', Rule::enum(ShopPlatform::class)],
            'consumer_key' => [...$credentialRules, 'string', 'max:255', 'regex:/^ck_[A-Za-z0-9]{20,}$/'],
            'consumer_secret' => [...$credentialRules, 'string', 'max:255', 'regex:/^cs_[A-Za-z0-9]{20,}$/'],
        ];
    }

    /**
     * Get the validated data, dropping credentials that were left blank.
     *
     * @return array<string, mixed>
     */
    public function shopAttributes(): array
    {
        return array_filter(
            $this->safe()->only(['name', 'url', 'platform', 'consumer_key', 'consumer_secret']),
            fn (mixed $value) => $value !== null && $value !== '',
        );
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consumer_key.regex' => 'The consumer key must look like ck_ followed by the key WooCommerce generated.',
            'consumer_secret.regex' => 'The consumer secret must look like cs_ followed by the secret WooCommerce generated.',
            'url.unique' => 'That shop has already been added to this organization.',
        ];
    }

    /**
     * Get the organization associated with the request.
     */
    private function organization(): Organization
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return $organization;
    }

    /**
     * Get the shop being updated, if any.
     */
    private function shop(): ?Shop
    {
        $shop = $this->route('shop');

        return $shop instanceof Shop ? $shop : null;
    }
}
