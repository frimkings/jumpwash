<div class="module-page">
    <section class="module-header">
        <div>
            <p class="dashboard-eyebrow">Section 4</p>
            <h2>Organization Settings</h2>
        </div>
        @if (session('status'))
            <span class="notice notice--success">{{ session('status') }}</span>
        @endif
    </section>

    <form wire:submit="save" class="module-panel">
        <div class="settings-layout">
            <section class="settings-logo">
                <div class="logo-preview">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="Organization logo preview">
                    @elseif ($logo_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($logo_path) }}" alt="Organization logo">
                    @else
                        <span>JW</span>
                    @endif
                </div>
                <label class="field">
                    <span>Logo</span>
                    <input type="file" wire:model="logo" accept="image/*">
                    @error('logo') <small>{{ $message }}</small> @enderror
                </label>
            </section>

            <section class="form-grid">
                <label class="field field--wide">
                    <span>Organization Name</span>
                    <input type="text" wire:model="organization_name">
                    @error('organization_name') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Phone</span>
                    <input type="text" wire:model="phone">
                    @error('phone') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Email</span>
                    <input type="email" wire:model="email">
                    @error('email') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field field--wide">
                    <span>Address</span>
                    <textarea rows="3" wire:model="address"></textarea>
                    @error('address') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>City</span>
                    <input type="text" wire:model="city">
                    @error('city') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>State</span>
                    <input type="text" wire:model="state">
                    @error('state') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Postal Code</span>
                    <input type="text" wire:model="postal_code">
                    @error('postal_code') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Country</span>
                    <input type="text" wire:model="country">
                    @error('country') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Tax Percentage</span>
                    <input type="number" step="0.01" wire:model="tax_percentage">
                    @error('tax_percentage') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Currency</span>
                    <input type="text" wire:model="currency">
                    @error('currency') <small>{{ $message }}</small> @enderror
                </label>
                <label class="toggle-field">
                    <input type="checkbox" wire:model="loyalty_enabled">
                    <span>Loyalty Active</span>
                </label>
                <label class="field">
                    <span>Spend Per Point</span>
                    <input type="number" step="0.01" wire:model="loyalty_spend_per_point">
                    @error('loyalty_spend_per_point') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Value Per Point</span>
                    <input type="number" step="0.01" wire:model="loyalty_value_per_point">
                    @error('loyalty_value_per_point') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Minimum Redemption</span>
                    <input type="number" min="1" step="1" wire:model="loyalty_min_redeem_points">
                    @error('loyalty_min_redeem_points') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field field--wide">
                    <span>Business Hours</span>
                    <textarea rows="3" wire:model="business_hours"></textarea>
                    @error('business_hours') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field field--wide">
                    <span>Receipt Footer</span>
                    <textarea rows="3" wire:model="receipt_footer"></textarea>
                    @error('receipt_footer') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field field--wide">
                    <span>Terms & Conditions</span>
                    <textarea rows="5" wire:model="terms_conditions"></textarea>
                    @error('terms_conditions') <small>{{ $message }}</small> @enderror
                </label>
            </section>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Save Settings</button>
        </div>
    </form>
</div>
