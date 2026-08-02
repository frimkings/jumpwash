<?php

namespace App\Livewire;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class OrganizationSettings extends Component
{
    use WithFileUploads;

    public string $organization_name = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $city = '';
    public string $state = '';
    public string $postal_code = '';
    public string $country = '';
    public string $receipt_footer = '';
    public string $terms_conditions = '';
    public string $tax_percentage = '0';
    public string $currency = 'PHP';
    public bool $loyalty_enabled = true;
    public string $loyalty_spend_per_point = '10';
    public string $loyalty_value_per_point = '0.10';
    public string $loyalty_min_redeem_points = '10';
    public string $business_hours = '';
    public ?string $logo_path = null;
    public ?TemporaryUploadedFile $logo = null;

    public function mount(): void
    {
        $settings = Setting::query()
            ->where('branch_id', $this->branchId())
            ->pluck('value', 'key');

        foreach ($this->settingKeys() as $key) {
            if ($key === 'loyalty_enabled') {
                $this->loyalty_enabled = (bool) (int) ($settings[$key] ?? $this->loyalty_enabled);
                continue;
            }

            $this->{$key} = (string) ($settings[$key] ?? $this->{$key});
        }

        $this->logo_path = $settings['logo_path'] ?? null;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'organization_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'terms_conditions' => ['nullable', 'string', 'max:2000'],
            'tax_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'currency' => ['required', 'string', 'max:10'],
            'loyalty_enabled' => ['boolean'],
            'loyalty_spend_per_point' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'loyalty_value_per_point' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'loyalty_min_redeem_points' => ['required', 'integer', 'min:1', 'max:999999'],
            'business_hours' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        unset($validated['logo']);

        if ($this->logo) {
            if ($this->logo_path) {
                Storage::disk('public')->delete($this->logo_path);
            }

            $this->logo_path = $this->logo->store('organization', 'public');
            $validated['logo_path'] = $this->logo_path;
            $this->logo = null;
        }

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['branch_id' => $this->branchId(), 'key' => $key],
                ['value' => is_bool($value) ? (string) (int) $value : (string) $value, 'type' => in_array($key, ['tax_percentage', 'loyalty_spend_per_point', 'loyalty_value_per_point', 'loyalty_min_redeem_points'], true) ? 'number' : 'string'],
            );
        }

        session()->flash('status', 'Organization settings saved.');
    }

    public function render()
    {
        return view('livewire.organization-settings')->layout('layouts.app', ['title' => 'Organization Settings']);
    }

    private function branchId(): ?int
    {
        return auth()->user()?->branch_id;
    }

    private function settingKeys(): array
    {
        return [
            'organization_name',
            'phone',
            'email',
            'address',
            'city',
            'state',
            'postal_code',
            'country',
            'receipt_footer',
            'terms_conditions',
            'tax_percentage',
            'currency',
            'loyalty_enabled',
            'loyalty_spend_per_point',
            'loyalty_value_per_point',
            'loyalty_min_redeem_points',
            'business_hours',
        ];
    }
}
