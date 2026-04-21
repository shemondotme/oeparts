@extends('layouts.admin')

@section('title', __('Bulk Update Preview'))

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('Bulk Update Preview') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.catalog.bulk-update.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Filters') }}
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Step 2: Preview Changes') }}</h6>
                    <span class="badge bg-info">{{ __(':count products affected', ['count' => $totalCount]) }}</span>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>{{ __('Important') }}</strong>: {{ __('This is a preview only. No changes have been made yet.') }}
                    </div>

                    <div class="mb-4">
                        <h6 class="text-primary">{{ __('Action Summary') }}</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong>{{ __('Action Type') }}:</strong>
                                    <span class="badge bg-primary ms-2">{{ __(str_replace('_', ' ', ucfirst($actionType->value))) }}</span>
                                </div>
                                <div class="mb-2">
                                    <strong>{{ __('Apply Value') }}:</strong>
                                    <span class="ms-2">{{ $filters['apply_value'] }} {{ $filters['apply_percentage'] ? '%' : ($actionType->value === 'price_increase' || $actionType->value === 'price_decrease' ? settings('currency_symbol') : __('units')) }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                @if($filters['manufacturer_id'])
                                <div class="mb-2">
                                    <strong>{{ __('Manufacturer') }}:</strong>
                                    <span class="ms-2">{{ $products->first()->manufacturer ? trans_field($products->first()->manufacturer->name) : __('Unknown') }}</span>
                                </div>
                                @endif
                                @if($filters['condition'])
                                <div class="mb-2">
                                    <strong>{{ __('Condition') }}:</strong>
                                    <span class="ms-2">{{ __(ucfirst($filters['condition'])) }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($products->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('OEM') }}</th>
                                    <th>{{ __('Manufacturer') }}</th>
                                    <th>{{ __('Current') }}</th>
                                    <th><i class="fas fa-arrow-right"></i></th>
                                    <th>{{ __('New') }}</th>
                                    <th>{{ __('Change') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($previewData as $item)
                                <tr>
                                    <td>{{ Str::limit(trans_field($item['product']->name), 30) }}</td>
                                    <td><code>{{ $item['product']->oem_number }}</code></td>
                                    <td>{{ $item['product']->manufacturer ? trans_field($item['product']->manufacturer->name) : __('N/A') }}</td>
                                    <td>{{ $item['current_value'] }}</td>
                                    <td><i class="fas fa-arrow-right text-muted"></i></td>
                                    <td class="fw-bold">{{ $item['new_value'] }}</td>
                                    <td>
                                        @php
                                            $changeClass = 'text-success';
                                            if($actionType->value === 'price_decrease' || $actionType->value === 'stock_out') {
                                                $changeClass = 'text-danger';
                                            }
                                        @endphp
                                        <span class="{{ $changeClass }}">
                                            @if($actionType->value === 'price_increase' || $actionType->value === 'stock_in')
                                            <i class="fas fa-arrow-up me-1"></i>
                                            @else
                                            <i class="fas fa-arrow-down me-1"></i>
                                            @endif
                                            {{ $filters['apply_value'] }}{{ $filters['apply_percentage'] ? '%' : '' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center mt-3">
                        {{ $products->links() }}
                    </div>

                    <div class="mt-4">
                        <form method="POST" action="{{ route('admin.catalog.bulk-update.execute') }}">
                            @csrf
                            
                            <!-- Hidden fields to pass filter data -->
                            <input type="hidden" name="action_type" value="{{ $filters['action_type'] }}">
                            <input type="hidden" name="manufacturer_id" value="{{ $filters['manufacturer_id'] ?? '' }}">
                            <input type="hidden" name="condition" value="{{ $filters['condition'] ?? '' }}">
                            <input type="hidden" name="stock_status" value="{{ $filters['stock_status'] ?? '' }}">
                            <input type="hidden" name="price_min" value="{{ $filters['price_min'] ?? '' }}">
                            <input type="hidden" name="price_max" value="{{ $filters['price_max'] ?? '' }}">
                            <input type="hidden" name="apply_value" value="{{ $filters['apply_value'] }}">
                            <input type="hidden" name="apply_percentage" value="{{ $filters['apply_percentage'] ?? 0 }}">
                            
                            <div class="alert alert-danger">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmation" name="confirmation" value="1" required>
                                    <label class="form-check-label" for="confirmation">
                                        <strong>{{ __('I understand that this action will affect :count products and cannot be undone.', ['count' => $totalCount]) }}</strong>
                                    </label>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        {{ __('A log entry will be created for audit purposes.') }}
                                    </small>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('admin.catalog.bulk-update.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> {{ __('Cancel') }}
                                </a>
                                <button type="submit" class="btn btn-danger" id="executeButton" disabled>
                                    <i class="fas fa-bolt me-1"></i> {{ __('Execute Bulk Update') }}
                                </button>
                            </div>
                        </form>
                    </div>
                    @else
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5>{{ __('No products match your filters') }}</h5>
                        <p class="text-muted">{{ __('Try adjusting your filter criteria.') }}</p>
                        <a href="{{ route('admin.catalog.bulk-update.index') }}" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i> {{ __('Adjust Filters') }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Filter Summary') }}</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <strong>{{ __('Action') }}:</strong><br>
                            <span class="badge bg-primary">{{ __(str_replace('_', ' ', ucfirst($actionType->value))) }}</span>
                        </li>
                        
                        @if($filters['manufacturer_id'])
                        <li class="mb-2">
                            <strong>{{ __('Manufacturer') }}:</strong><br>
                            <span>{{ $products->first()->manufacturer ? trans_field($products->first()->manufacturer->name) : __('Unknown') }}</span>
                        </li>
                        @endif
                        
                        @if($filters['condition'])
                        <li class="mb-2">
                            <strong>{{ __('Condition') }}:</strong><br>
                            <span>{{ __(ucfirst($filters['condition'])) }}</span>
                        </li>
                        @endif
                        
                        @if($filters['stock_status'])
                        <li class="mb-2">
                            <strong>{{ __('Stock Status') }}:</strong><br>
                            <span>{{ __(str_replace('_', ' ', ucfirst($filters['stock_status']))) }}</span>
                        </li>
                        @endif
                        
                        @if($filters['price_min'] || $filters['price_max'])
                        <li class="mb-2">
                            <strong>{{ __('Price Range') }}:</strong><br>
                            <span>
                                @if($filters['price_min'] && $filters['price_max'])
                                {{ settings('currency_symbol') }}{{ number_format($filters['price_min'], 2) }} - {{ settings('currency_symbol') }}{{ number_format($filters['price_max'], 2) }}
                                @elseif($filters['price_min'])
                                {{ __('Min') }}: {{ settings('currency_symbol') }}{{ number_format($filters['price_min'], 2) }}
                                @elseif($filters['price_max'])
                                {{ __('Max') }}: {{ settings('currency_symbol') }}{{ number_format($filters['price_max'], 2) }}
                                @endif
                            </span>
                        </li>
                        @endif
                        
                        <li class="mb-2">
                            <strong>{{ __('Apply Value') }}:</strong><br>
                            <span class="fw-bold">{{ $filters['apply_value'] }}{{ $filters['apply_percentage'] ? '%' : ($actionType->value === 'price_increase' || $actionType->value === 'price_decrease' ? settings('currency_symbol') : __(' units')) }}</span>
                        </li>
                        
                        <li class="mb-2">
                            <strong>{{ __('Affected Products') }}:</strong><br>
                            <span class="fw-bold">{{ $totalCount }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('What Happens Next') }}</h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">{{ __('Execution') }}</h6>
                                <p class="small text-muted mb-0">{{ __('All matching products will be updated in a single database transaction.') }}</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">{{ __('Inventory Logs') }}</h6>
                                <p class="small text-muted mb-0">{{ __('Stock changes will create inventory log entries.') }}</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">{{ __('Audit Log') }}</h6>
                                <p class="small text-muted mb-0">{{ __('A bulk update log entry will be created for audit purposes.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.timeline {
    position: relative;
    padding-left: 20px;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.timeline-marker {
    position: absolute;
    left: -20px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}
.timeline-content {
    padding-left: 10px;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmationCheckbox = document.getElementById('confirmation');
    const executeButton = document.getElementById('executeButton');
    
    if (confirmationCheckbox && executeButton) {
        confirmationCheckbox.addEventListener('change', function() {
            executeButton.disabled = !this.checked;
        });
    }
    
    // Add confirmation dialog on form submit
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!confirm('{{ __("Are you sure you want to execute this bulk update? This action cannot be undone.") }}')) {
                e.preventDefault();
            }
        });
    }
});
</script>
@endpush