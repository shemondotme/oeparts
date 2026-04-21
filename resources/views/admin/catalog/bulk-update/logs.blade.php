@extends('layouts.admin')

@section('title', __('Bulk Update Logs'))

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('Bulk Update Logs') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.catalog.bulk-update.index') }}" class="btn btn-primary">
                <i class="fas fa-bolt me-1"></i> {{ __('New Bulk Update') }}
            </a>
            <a href="{{ route('admin.catalog.products.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Products') }}
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('Audit Log History') }}</h6>
            <span class="badge bg-secondary">{{ __(':count total logs', ['count' => $logs->total()]) }}</span>
        </div>
        <div class="card-body">
            @if($logs->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('Date & Time') }}</th>
                            <th>{{ __('Admin') }}</th>
                            <th>{{ __('Action') }}</th>
                            <th>{{ __('Target') }}</th>
                            <th>{{ __('Affected') }}</th>
                            <th>{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr>
                            <td>
                                <div class="small text-muted">{{ $log->created_at->format('Y-m-d') }}</div>
                                <div class="small">{{ $log->created_at->format('H:i:s') }}</div>
                            </td>
                            <td>
                                <div>{{ $log->admin->name ?? __('System') }}</div>
                                <div class="small text-muted">{{ $log->admin->email ?? '' }}</div>
                            </td>
                            <td>
                                @php
                                    $actionClass = 'bg-primary';
                                    if($log->action_type->value === 'price_decrease' || $log->action_type->value === 'stock_out') {
                                        $actionClass = 'bg-danger';
                                    } elseif($log->action_type->value === 'price_increase' || $log->action_type->value === 'stock_in') {
                                        $actionClass = 'bg-success';
                                    }
                                @endphp
                                <span class="badge {{ $actionClass }}">
                                    {{ __(str_replace('_', ' ', ucfirst($log->action_type->value))) }}
                                </span>
                            </td>
                            <td>
                                @if($log->targetManufacturer)
                                <a href="{{ route('admin.catalog.manufacturers.show', $log->targetManufacturer) }}" class="text-decoration-none">
                                    {{ trans_field($log->targetManufacturer->name) }}
                                </a>
                                @else
                                <span class="text-muted">{{ __('All Manufacturers') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-info">{{ $log->affected_rows_count }} {{ __('products') }}</span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#logDetailsModal{{ $log->id }}">
                                    <i class="fas fa-eye"></i> {{ __('View') }}
                                </button>
                            </td>
                        </tr>

                        <!-- Details Modal -->
                        <div class="modal fade" id="logDetailsModal{{ $log->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">{{ __('Bulk Update Details') }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <strong>{{ __('Date') }}:</strong><br>
                                                    {{ $log->created_at->format('Y-m-d H:i:s') }}
                                                </div>
                                                <div class="mb-3">
                                                    <strong>{{ __('Admin') }}:</strong><br>
                                                    {{ $log->admin->name ?? __('System') }}<br>
                                                    <small class="text-muted">{{ $log->admin->email ?? '' }}</small>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>{{ __('Action Type') }}:</strong><br>
                                                    <span class="badge {{ $actionClass }}">
                                                        {{ __(str_replace('_', ' ', ucfirst($log->action_type->value))) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <strong>{{ __('Target Manufacturer') }}:</strong><br>
                                                    @if($log->targetManufacturer)
                                                    <a href="{{ route('admin.catalog.manufacturers.show', $log->targetManufacturer) }}" class="text-decoration-none">
                                                        {{ trans_field($log->targetManufacturer->name) }}
                                                    </a>
                                                    @else
                                                    <span class="text-muted">{{ __('All Manufacturers') }}</span>
                                                    @endif
                                                </div>
                                                <div class="mb-3">
                                                    <strong>{{ __('Affected Products') }}:</strong><br>
                                                    <span class="badge bg-info">{{ $log->affected_rows_count }}</span>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>{{ __('Log ID') }}:</strong><br>
                                                    <code>{{ $log->id }}</code>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="card mt-3">
                                            <div class="card-header">
                                                <h6 class="mb-0">{{ __('Payload Details') }}</h6>
                                            </div>
                                            <div class="card-body">
                                                <pre class="mb-0" style="max-height: 200px; overflow-y: auto;">{{ json_encode($log->payload, JSON_PRETTY_PRINT) }}</pre>
                                            </div>
                                        </div>

                                        @if($log->payload)
                                        <div class="mt-3">
                                            <h6>{{ __('Filter Summary') }}</h6>
                                            <ul class="list-unstyled">
                                                @if(isset($log->payload['condition']) && $log->payload['condition'])
                                                <li><strong>{{ __('Condition') }}:</strong> {{ __(ucfirst($log->payload['condition'])) }}</li>
                                                @endif
                                                @if(isset($log->payload['stock_status']) && $log->payload['stock_status'])
                                                <li><strong>{{ __('Stock Status') }}:</strong> {{ __(str_replace('_', ' ', ucfirst($log->payload['stock_status']))) }}</li>
                                                @endif
                                                @if(isset($log->payload['price_min']) && $log->payload['price_min'])
                                                <li><strong>{{ __('Min Price') }}:</strong> {{ settings('currency_symbol') }}{{ number_format($log->payload['price_min'], 2) }}</li>
                                                @endif
                                                @if(isset($log->payload['price_max']) && $log->payload['price_max'])
                                                <li><strong>{{ __('Max Price') }}:</strong> {{ settings('currency_symbol') }}{{ number_format($log->payload['price_max'], 2) }}</li>
                                                @endif
                                                <li><strong>{{ __('Apply Value') }}:</strong> {{ $log->payload['apply_value'] ?? 0 }}{{ isset($log->payload['apply_percentage']) && $log->payload['apply_percentage'] ? '%' : ($log->action_type->value === 'price_increase' || $log->action_type->value === 'price_decrease' ? settings('currency_symbol') : __(' units')) }}</li>
                                            </ul>
                                        </div>
                                        @endif
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-3">
                {{ $logs->links() }}
            </div>
            @else
            <div class="text-center py-5">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <h5>{{ __('No bulk update logs found') }}</h5>
                <p class="text-muted">{{ __('Bulk update logs will appear here after you perform bulk operations.') }}</p>
                <a href="{{ route('admin.catalog.bulk-update.index') }}" class="btn btn-primary">
                    <i class="fas fa-bolt me-1"></i> {{ __('Perform First Bulk Update') }}
                </a>
            </div>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('Statistics') }}</h6>
                </div>
                <div class="card-body">
                    @php
                        $totalUpdates = $logs->total();
                        $priceUpdates = $logs->whereIn('action_type', ['price_increase', 'price_decrease'])->count();
                        $stockUpdates = $logs->whereIn('action_type', ['stock_in', 'stock_out'])->count();
                        $totalAffected = $logs->sum('affected_rows_count');
                    @endphp
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-circle bg-primary me-3">
                            <i class="fas fa-bolt text-white"></i>
                        </div>
                        <div>
                            <div class="text-xs text-muted">{{ __('Total Bulk Updates') }}</div>
                            <div class="h5 mb-0">{{ $totalUpdates }}</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-circle bg-success me-3">
                            <i class="fas fa-chart-line text-white"></i>
                        </div>
                        <div>
                            <div class="text-xs text-muted">{{ __('Price Updates') }}</div>
                            <div class="h5 mb-0">{{ $priceUpdates }}</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-circle bg-info me-3">
                            <i class="fas fa-boxes text-white"></i>
                        </div>
                        <div>
                            <div class="text-xs text-muted">{{ __('Stock Updates') }}</div>
                            <div class="h5 mb-0">{{ $stockUpdates }}</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-warning me-3">
                            <i class="fas fa-cube text-white"></i>
                        </div>
                        <div>
                            <div class="text-xs text-muted">{{ __('Total Products Affected') }}</div>
                            <div class="h5 mb-0">{{ $totalAffected }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('About Bulk Update Logs') }}</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>{{ __('Audit Trail') }}</strong><br>
                        {{ __('Every bulk update operation is logged for compliance and audit purposes.') }}
                    </div>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>{{ __('Non‑repudiation') }}</strong><br>
                            <small class="text-muted">{{ __('Each log records who performed the action and when.') }}</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-shield-alt text-primary me-2"></i>
                            <strong>{{ __('Compliance') }}</strong><br>
                            <small class="text-muted">{{ __('Required for financial and inventory audit trails.') }}</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-undo text-warning me-2"></i>
                            <strong>{{ __('Traceability') }}</strong><br>
                            <small class="text-muted">{{ __('If needed, you can trace back changes to specific bulk operations.') }}</small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.icon-circle {
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
@endpush