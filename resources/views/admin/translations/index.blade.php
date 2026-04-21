@extends('layouts.admin')

@section('title', 'Translation Management')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Translation Management</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.translations.scan') }}" class="btn btn-outline-primary">
                <i class="fas fa-search"></i> Scan for New Strings
            </a>
            <a href="{{ route('admin.translations.import') }}" class="btn btn-outline-secondary">
                <i class="fas fa-file-import"></i> Import
            </a>
            <a href="{{ route('admin.translations.export') }}" class="btn btn-outline-secondary">
                <i class="fas fa-file-export"></i> Export
            </a>
            <a href="{{ route('admin.translations.languages') }}" class="btn btn-outline-info">
                <i class="fas fa-language"></i> Manage Languages
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Strings</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalStrings }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-list-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Languages</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $languagesCount }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-language fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Translation Groups</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $groupsCount }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-folder fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Untranslated</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $untranslatedCount }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Language Groups Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Translation Groups</h6>
            <a href="{{ route('admin.translations.create') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Add New String
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Group</th>
                            <th>Keys</th>
                            <th>Languages</th>
                            <th>Completion</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($groups as $group)
                        <tr>
                            <td>
                                <strong>{{ $group->group }}</strong>
                                @if($group->description)
                                <br><small class="text-muted">{{ $group->description }}</small>
                                @endif
                            </td>
                            <td>{{ $group->keys_count }}</td>
                            <td>{{ $group->languages_count }}</td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    @php
                                        $percentage = $group->keys_count > 0 ? 
                                            round(($group->translated_count / ($group->keys_count * $group->languages_count)) * 100) : 0;
                                        $color = $percentage >= 90 ? 'bg-success' : ($percentage >= 50 ? 'bg-warning' : 'bg-danger');
                                    @endphp
                                    <div class="progress-bar {{ $color }}" role="progressbar" 
                                         style="width: {{ $percentage }}%;" 
                                         aria-valuenow="{{ $percentage }}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        {{ $percentage }}%
                                    </div>
                                </div>
                            </td>
                            <td>{{ $group->updated_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.translations.group', $group->group) }}" 
                                   class="btn btn-sm btn-info">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="{{ route('admin.translations.export.group', $group->group) }}" 
                                   class="btn btn-sm btn-secondary">
                                    <i class="fas fa-download"></i> Export
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Translations</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        @foreach($recentTranslations as $translation)
                        <div class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">{{ $translation->key }}</h6>
                                <small>{{ $translation->updated_at->diffForHumans() }}</small>
                            </div>
                            <p class="mb-1">
                                <span class="badge bg-secondary">{{ $translation->group }}</span>
                                <span class="badge bg-info">{{ $translation->lang_code }}</span>
                            </p>
                            <small class="text-muted">{{ Str::limit($translation->value, 100) }}</small>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Language Statistics</h6>
                </div>
                <div class="card-body">
                    <canvas id="languageChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        $('#dataTable').DataTable({
            pageLength: 25,
            order: [[0, 'asc']]
        });

        // Language Chart
        const ctx = document.getElementById('languageChart').getContext('2d');
        const languageChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: @json($languageStats->pluck('name')),
                datasets: [{
                    data: @json($languageStats->pluck('translated_count')),
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', 
                        '#e74a3b', '#858796', '#5a5c69', '#6f42c1'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9', '#17a673', '#2c9faf', '#f4b619',
                        '#e02d1b', '#6b7280', '#4a4c5a', '#5932a6'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} strings (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    });
</script>
@endpush