<div class="module-page tag-page">
    <section class="module-header tag-hero">
        <div>
            <p class="dashboard-eyebrow">Section 12</p>
            <h2>Garment Tagging System</h2>
            <p class="tag-hero__copy">Create physical garment tags, scan movement, and close the workflow only when every item is accounted for.</p>
        </div>
        <div class="module-actions">
            <span class="notice">Next: {{ $nextTagCode }}</span>
            @if (session('status'))
                <span class="notice notice--success">{{ session('status') }}</span>
            @endif
            @if (session('error'))
                <span class="notice notice--error">{{ session('error') }}</span>
            @endif
        </div>
    </section>

    <section class="tagging-grid">
        <form wire:submit="generateTags" class="module-panel tag-create-panel">
            <div class="tag-panel-title">
                <div>
                    <p class="dashboard-eyebrow">Create / Print</p>
                    <h3>Create Garment Tags</h3>
                </div>
                <span>{{ collect($tagRows)->sum(fn ($row) => (int) ($row['quantity'] ?? 1)) }} queued</span>
            </div>

            <div class="form-grid">
                <label class="field">
                    <span>Order</span>
                    <select wire:model.live="order_id">
                        <option value="">Select order</option>
                        @foreach ($orders as $order)
                            <option value="{{ $order->id }}">{{ $order->order_number }} - {{ $order->customer?->name }}</option>
                        @endforeach
                    </select>
                    @error('order_id') <small>{{ $message }}</small> @enderror
                </label>
                <label class="field">
                    <span>Expected Items</span>
                    <input type="number" min="1" wire:model="expected_garment_count" placeholder="25">
                    @error('expected_garment_count') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            @if ($selectedOrder)
                <div class="tag-order-summary">
                    <div>
                        <span>Selected Order</span>
                        <strong>{{ $selectedOrder->order_number }}</strong>
                    </div>
                    <div>
                        <span>Customer</span>
                        <strong>{{ $selectedOrder->customer?->name ?? 'Walk-in customer' }}</strong>
                    </div>
                    <div>
                        <span>Rows</span>
                        <strong>{{ $selectedOrder->items->count() }} order rows</strong>
                    </div>
                </div>
            @endif

            <div class="garment-counter">
                <div><span>Expected</span><strong>{{ $expected }} Items</strong></div>
                <div><span>Scanned</span><strong>{{ $scanned }} Items</strong></div>
                <div><span>Generated</span><strong>{{ $selectedOrder?->garmentTags->count() ?? 0 }} Tags</strong></div>
                <div class="{{ $missing > 0 ? 'counter-alert' : 'counter-ok' }}"><span>Missing</span><strong>{{ $missing }} Item{{ $missing === 1 ? '' : 's' }}</strong></div>
            </div>

            <div class="tag-row-list">
                @foreach ($tagRows as $index => $row)
                    <article class="tag-row" wire:key="tag-row-{{ $index }}">
                        <input type="hidden" wire:model="tagRows.{{ $index }}.order_item_id">
                        <div class="tag-row__top">
                            <strong>Garment {{ $index + 1 }}</strong>
                            <button type="button" wire:click="removeTagRow({{ $index }})" class="tag-icon-btn tag-icon-btn--delete" title="Remove garment row" aria-label="Remove garment row">
                                <span>Remove</span>
                            </button>
                        </div>
                        <div class="tag-row__fields">
                            <label class="field">
                                <span>Garment Type</span>
                                <input type="text" wire:model="tagRows.{{ $index }}.garment_type" placeholder="Shirt">
                                @error("tagRows.$index.garment_type") <small>{{ $message }}</small> @enderror
                            </label>
                            <label class="field tag-qty-field">
                                <span>Qty</span>
                                <input type="number" min="1" max="250" wire:model="tagRows.{{ $index }}.quantity">
                                @error("tagRows.$index.quantity") <small>{{ $message }}</small> @enderror
                            </label>
                            <label class="field">
                                <span>Color</span>
                                <input type="text" wire:model="tagRows.{{ $index }}.color" placeholder="White">
                            </label>
                            <label class="field">
                                <span>Brand</span>
                                <input type="text" wire:model="tagRows.{{ $index }}.brand" placeholder="Optional">
                            </label>
                            <label class="field">
                                <span>Size</span>
                                <input type="text" wire:model="tagRows.{{ $index }}.size" placeholder="M">
                            </label>
                            <label class="field">
                                <span>Gender</span>
                                <select wire:model="tagRows.{{ $index }}.gender">
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Unisex">Unisex</option>
                                    <option value="Kids">Kids</option>
                                </select>
                            </label>
                            <label class="field field--wide">
                                <span>Condition</span>
                                <input type="text" wire:model="tagRows.{{ $index }}.condition" placeholder="Good / stained / torn / button missing">
                            </label>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="form-actions form-actions--between">
                <button type="button" wire:click="addTagRow" class="btn-secondary">+ Add Garment</button>
                <button type="submit" class="btn-primary">Generate & Preview Tags</button>
            </div>
        </form>

        <section class="module-panel tag-scan-panel">
            <div class="tag-panel-title">
                <div>
                    <p class="dashboard-eyebrow">Scan / Update</p>
                    <h3>Scan Garment Tag</h3>
                </div>
            </div>

            <form wire:submit="scanTag" class="scan-box">
                <label class="field">
                    <span>Tag Code</span>
                    <input type="text" wire:model="scan_code" placeholder="TAG-20260621-000001" autofocus>
                    @error('scan_code') <small>{{ $message }}</small> @enderror
                </label>
                <button type="submit" class="btn-primary">Scan</button>
            </form>

            <div class="current-scan-card {{ $lastScannedTag ? '' : 'current-scan-card--empty' }}">
                @if ($lastScannedTag)
                    <span class="badge badge--success">Current Scan</span>
                    <h4>{{ $lastScannedTag->tag_code }}</h4>
                    <p>{{ $lastScannedTag->order?->order_number }} - {{ $lastScannedTag->order?->customer?->name ?? 'Walk-in customer' }}</p>
                    <dl>
                        <div><dt>Garment</dt><dd>{{ $lastScannedTag->garment_type }}</dd></div>
                        <div><dt>Details</dt><dd>{{ collect([$lastScannedTag->color, $lastScannedTag->brand, $lastScannedTag->size, $lastScannedTag->gender, $lastScannedTag->condition])->filter()->join(' / ') ?: 'No details' }}</dd></div>
                        <div><dt>Status</dt><dd>{{ $allStatuses[$lastScannedTag->status] ?? ucfirst(str_replace('_', ' ', $lastScannedTag->status)) }}</dd></div>
                    </dl>
                @else
                    <span class="badge badge--muted">Waiting</span>
                    <h4>No active scan</h4>
                    <p>Scan a garment tag to show order, customer, garment details, and workflow actions here.</p>
                @endif
            </div>

            <div class="workflow-stepper">
                @foreach ($workflow as $status => $label)
                    <button type="button" wire:click="updateScannedStatus('{{ $status }}')" class="{{ $lastScannedTag && $lastScannedTag->status === $status ? 'is-active' : '' }}">
                        <span>{{ $loop->iteration }}</span>
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="tag-exception-actions">
                @foreach ($exceptionStatuses as $status => $label)
                    <button type="button" wire:click="updateScannedStatus('{{ $status }}')" class="tag-exception-btn tag-exception-btn--{{ $status }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <button type="button" wire:click="closeOrder" class="btn-primary close-order-button">Close Garment Workflow</button>

            <div class="tag-activity">
                <div class="tag-section-heading">
                    <span>Recent Activity</span>
                </div>
                @forelse ($recentActivity as $activity)
                    <div class="tag-activity__item">
                        <strong>{{ str_replace('.', ' ', $activity->action) }}</strong>
                        <span>{{ $activity->properties['tag_code'] ?? $activity->properties['scan_code'] ?? $activity->properties['order_number'] ?? 'Garment tags' }}</span>
                        <small>{{ $activity->created_at->diffForHumans() }}</small>
                    </div>
                @empty
                    <p class="empty-state">No tag activity yet.</p>
                @endforelse
            </div>
        </section>
    </section>

    <section class="module-panel tag-list-panel">
        <div class="tag-list-header">
            <div>
                <p class="dashboard-eyebrow">Tag Register</p>
                <h3>Garment Tags</h3>
            </div>
            <div class="list-toolbar tag-list-toolbar">
                <label class="field">
                    <span>Search Orders</span>
                    <input type="search" wire:model.live="search" placeholder="Order number or customer">
                </label>
                <label class="field">
                    <span>Status</span>
                    <select wire:model.live="statusFilter">
                        <option value="all">All</option>
                        @foreach ($allStatuses as $status => $label)
                            <option value="{{ $status }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>

        <div class="tag-table">
            <div class="tag-table__head">
                <span>Tag</span>
                <span>Order</span>
                <span>Garment</span>
                <span>Details</span>
                <span>Status</span>
                <span>Scan</span>
                <span>Actions</span>
            </div>
            @forelse ($tags as $tag)
                <article class="tag-table__row">
                    <strong>{{ $tag->tag_code }}</strong>
                    <span>{{ $tag->order?->order_number }}</span>
                    <span>{{ $tag->garment_type }}</span>
                    <span>{{ collect([$tag->color, $tag->brand, $tag->size, $tag->gender, $tag->condition])->filter()->join(' / ') ?: 'No details' }}</span>
                    <select wire:change="updateStatus({{ $tag->id }}, $event.target.value)">
                        @foreach ($allStatuses as $status => $label)
                            <option value="{{ $status }}" @selected($tag->status === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="{{ $tag->is_scanned ? 'badge badge--success' : 'badge badge--muted' }}">
                        {{ $tag->is_scanned ? 'Scanned' : 'Not Scanned' }}
                    </span>
                    <div class="tag-table-actions">
                        <button type="button" wire:click="reprintTag({{ $tag->id }})" class="tag-icon-btn tag-icon-btn--print" title="Log reprint" aria-label="Log reprint">
                            <span>Reprint</span>
                        </button>
                    </div>
                </article>
            @empty
                <p class="empty-state">No garment tags found.</p>
            @endforelse
        </div>
    </section>

    @if ($showGeneratedTagsModal)
        <div class="modal-backdrop tag-print-backdrop" wire:key="generated-tags-modal">
            <section class="tag-print-modal" role="dialog" aria-modal="true" aria-labelledby="generated-tags-title">
                <header class="tag-print-header">
                    <div>
                        <p class="dashboard-eyebrow">Generated Tags</p>
                        <h3 id="generated-tags-title">{{ $generatedTags->count() }} tag{{ $generatedTags->count() === 1 ? '' : 's' }} ready to print</h3>
                    </div>
                    <div class="tag-print-actions">
                        <button type="button" class="btn-primary" onclick="const area = document.getElementById('generated-tag-print-area'); const win = window.open('', '_blank', 'width=480,height=700'); win.document.write('<html><head><title>Print Garment Tags</title><style>body{font-family:Arial,sans-serif;margin:12px}.tag-print-ticket{border:1px dashed #111;margin:0 0 10px;padding:10px;width:260px}.tag-print-ticket strong{display:block;font-size:15px}.tag-print-ticket span{display:block;font-size:11px;margin-top:4px}.tag-print-code{font-family:Consolas,monospace;font-size:16px;font-weight:900;margin-top:8px}</style></head><body>' + area.innerHTML + '</body></html>'); win.document.close(); win.focus(); win.print();">
                            Print All Tags
                        </button>
                        <button type="button" wire:click="closeGeneratedTagsModal" class="btn-secondary">Close</button>
                    </div>
                </header>
                <div id="generated-tag-print-area" class="tag-print-sheet">
                    @foreach ($generatedTags as $tag)
                        <article class="tag-print-ticket">
                            <strong>{{ $tag->order?->order_number }}</strong>
                            <span>{{ $tag->order?->customer?->name ?? 'Walk-in customer' }}</span>
                            <span>{{ $tag->garment_type }}{{ $tag->color ? ' - '.$tag->color : '' }}{{ $tag->size ? ' - '.$tag->size : '' }}</span>
                            <div class="tag-print-code">{{ $tag->tag_code }}</div>
                            <span>{{ $tag->condition ?: 'No condition note' }}</span>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    @endif
</div>
