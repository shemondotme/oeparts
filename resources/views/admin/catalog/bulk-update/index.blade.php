@extends('layouts.admin')

@section('title', __('Bulk Update Products'))

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('Bulk Update Products') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.catalog.bulk-update.logs') }}" class="btn btn-outline-secondary">
                <i class="fas fa-history me-1"></i> {{ __('View Logs') }}
            </a>
            <a href="{{ route('admin.catalog.products.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Products') }}
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Step 1: Select Filters & Action') }}</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.catalog.bulk-update.preview') }}" id="bulkUpdateForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="action_type" class="form-label">{{ __('Action Type') }} *</label>
                                    <select class="form-select" id="action_type" name="action_type" required>
                                        <option value="">{{ __('Select an action') }}</option>
                                        @foreach($actionTypes as $action)
                                        <option value="{{ $action->value }}">{{ __(str_replace('_', ' ', ucfirst($action->value))) }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">{{ __('Choose what you want to update') }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="manufacturer_id" class="form-label">{{ __('Manufacturer') }}</label>
                                    <select class="form-select" id="manufacturer_id" name="manufacturer_id">
                                        <option value="">{{ __('All Manufacturers') }}</option>
                                        @foreach($manufacturers as $manufacturer)
                                        <option value="{{ $manufacturer->id }}">{{ trans_field($manufacturer->name) }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">{{ __('Filter by specific manufacturer') }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="condition" class="form-label">{{ __('Condition') }}</label>
                                    <select class="form-select" id="condition" name="condition">
                                        <option value="">{{ __('All Conditions') }}</option>
                                        <option value="new">{{ __('New') }}</option>
                                        <option value="used">{{ __('Used') }}</option>
                                        <option value="refurbished">{{ __('Refurbished') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="stock_status" class="form-label">{{ __('Stock Status') }}</label>
                                    <select class="form-select" id="stock_status" name="stock_status">
                                        <option value="">{{ __('All Stock Status') }}</option>
                                        <option value="in_stock">{{ __('In Stock') }}</option>
                                        <option value="out_of_stock">{{ __('Out of Stock') }}</option>
                                        <option value="low_stock">{{ __('Low Stock (< 10)') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="apply_value" class="form-label">{{ __('Apply Value') }} *</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="apply_value" name="apply_value" 
                                               step="0.01" min="0.01" required>
                                        <span class="input-group-text" id="value_unit">-</span>
                                    </div>
                                    <div class="form-text" id="value_help">{{ __('Enter the value to apply') }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price_min" class="form-label">{{ __('Minimum Price') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ settings('currency_symbol') }}</span>
                                        <input type="number" class="form-control" id="price_min" name="price_min" 
                                               step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price_max" class="form-label">{{ __('Maximum Price') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ settings('currency_symbol') }}</span>
                                        <input type="number" class="form-control" id="price_max" name="price_max" 
                                               step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="apply_percentage" name="apply_percentage" value="1">
                                <label class="form-check-label" for="apply_percentage">
                                    {{ __('Apply as percentage (%)') }}
                                </label>
                            </div>
                            <div class="form-text" id="percentage_help">{{ __('Check to apply value as percentage instead of fixed amount') }}</div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="reset" class="btn btn-secondary">{{ __('Reset Form') }}</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-eye me-1"></i> {{ __('Preview Changes') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Available Actions') }}</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary">{{ __('Price Actions') }}</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-arrow-up text-success me-2"></i> <strong>{{ __('Price Increase') }}</strong> – {{ __('Increase prices by fixed amount or percentage') }}</li>
                            <li><i class="fas fa-arrow-down text-danger me-2"></i> <strong>{{ __('Price Decrease') }}</strong> – {{ __('Decrease prices by fixed amount or percentage') }}</li>
                        </ul>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-primary">{{ __('Stock Actions') }}</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-plus-circle text-success me-2"></i> <strong>{{ __('Stock In') }}</strong> – {{ __('Add stock quantity to products') }}</li>
                            <li><i class="fas fa-minus-circle text-danger me-2"></i> <strong>{{ __('Stock Out') }}</strong> – {{ __('Remove stock quantity from products') }}</li>
                        </ul>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>{{ __('Workflow') }}</strong><br>
                        1. {{ __('Select filters and action') }}<br>
                        2. {{ __('Preview affected products') }}<br>
                        3. {{ __('Confirm and execute') }}<br>
                        4. {{ __('Log created automatically') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const actionTypeSelect = document.getElementById('action_type');
    const valueUnit = document.getElementById('value_unit');
    const valueHelp = document.getElementById('value_help');
    const percentageHelp = document.getElementById('percentage_help');
    const applyPercentage = document.getElementById('apply_percentage');

    function updateActionDetails() {
        const action = actionTypeSelect.value;
        const isPercentage = applyPercentage.checked;
        
        switch(action) {
            case 'price_increase':
            case 'price_decrease':
                valueUnit.textContent = isPercentage ? '%' : '{{ settings('currency_symbol') }}';
                valueHelp.textContent = isPercentage 
                    ? '{{ __('Enter percentage to increase/decrease prices') }}'
                    : '{{ __('Enter fixed amount to increase/decrease prices') }}';
                percentageHelp.textContent = '{{ __('Check to apply as percentage of current price') }}';
                break;
            case 'stock_in':
            case 'stock_out':
                valueUnit.textContent = '{{ __('units') }}';
                valueHelp.textContent = '{{ __('Enter quantity to add/remove from stock') }}';
                percentageHelp.textContent = '{{ __('Percentage not applicable for stock actions') }}';
                applyPercentage.disabled = true;
                applyPercentage.checked = false;
                break;
            default:
                valueUnit.textContent = '-';
                valueHelp.textContent = '{{ __('Enter the value to apply') }}';
                percentageHelp.textContent = '{{ __('Check to apply value as percentage instead of fixed amount') }}';
                applyPercentage.disabled = false;
        }
    }

    actionTypeSelect.addEventListener('change', updateActionDetails);
    applyPercentage.addEventListener('change', updateActionDetails);
    
    // Initial update
    updateActionDetails();
});
</script>
@endpush