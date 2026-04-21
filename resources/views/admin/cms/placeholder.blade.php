@extends('layouts.admin')

@section('title', $title ?? __('CMS'))

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-0">{{ $title ?? __('CMS') }}</h1>
            <p class="text-muted">{{ $description ?? __('This section is under construction.') }}</p>
        </div>
        <div class="col-auto">
            @if(isset($createRoute))
            <a href="{{ $createRoute }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> {{ __('Create New') }}
            </a>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <p>{{ __('This feature is part of Sprint 14 and will be implemented soon.') }}</p>
            <p class="mb-0">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> {{ __('Back to Dashboard') }}
                </a>
            </p>
        </div>
    </div>
</div>
@endsection