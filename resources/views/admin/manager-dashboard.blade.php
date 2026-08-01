@extends('layouts.admin.master')

@section('title')
    Manager Dashboard
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Manager Dashboard</h1>
                <p class="mb-0 text-muted">Access reports, approvals, scheduling, and team operations from one place.</p>
            </div>
            <span class="badge badge-{{ $isOdooManager ? 'success' : 'secondary' }} px-3 py-2 text-uppercase">
                {{ $isOdooManager ? 'Manager Access' : 'Administrator Access' }}
            </span>
        </div>

        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="card shadow h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between mb-4">
                            <div>
                                <h5 class="mb-1">Welcome, {{ auth()->user()->name }}</h5>
                                <p class="mb-0 text-muted">
                                    Use this dashboard to review team activity, manage approvals, and open key operational reports.
                                </p>
                            </div>
                            <i class="fas fa-users-cog fa-2x text-primary"></i>
                        </div>

                        <h6 class="text-primary font-weight-bold text-uppercase mb-3">Manager Overview</h6>
                        <ul class="text-muted mb-4 pl-3">
                            @foreach ($managerWorklist as $item)
                                <li class="mb-2">{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Access</h6>
                    </div>
                    <div class="card-body">
                        @foreach ($managerQuickLinks as $link)
                            <div class="border rounded p-3 mb-3">
                                <div class="font-weight-bold">{{ $link['title'] }}</div>
                                <div class="small text-muted mb-3">{{ $link['description'] }}</div>
                                <a href="{{ $link['route'] }}" class="btn btn-outline-{{ $link['class'] }} btn-sm">
                                    {{ $link['button'] }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
