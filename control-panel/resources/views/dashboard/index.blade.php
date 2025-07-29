@extends('layouts.app')

@section('title', 'Dashboard')
@section('header', 'Dashboard')

@section('content')
<div class="space-y-6">
    <!-- Welcome & Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Welcome back, {{ auth()->user()->name }}!</h2>
                <p class="mt-1 text-gray-600">
                    @if(auth()->user()->plan_id == 1)
                        You're on the <span class="font-semibold">Free Plan</span>. 
                        <a href="{{ route('billing.plans') }}" class="text-blue-600 hover:underline">Upgrade for more features</a>
                    @else
                        You're on the <span class="font-semibold">{{ auth()->user()->plan->name }}</span> plan
                    @endif
                </p>
            </div>
            <a href="{{ route('sites.create') }}" class="mt-4 sm:mt-0 btn-primary">
                <i class="fas fa-plus mr-2"></i>Create New Site
            </a>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="stat-card">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-globe text-2xl text-blue-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Active Sites</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['active_sites'] }}</p>
                </div>
                <div class="ml-auto">
                    <span class="text-sm text-gray-500">/ {{ auth()->user()->plan->site_limit ?? '∞' }}</span>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-hdd text-2xl text-green-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Storage Used</p>
                    <p class="text-2xl font-bold text-gray-900">{{ formatBytes($stats['storage_used']) }}</p>
                </div>
                <div class="ml-auto">
                    <span class="text-sm {{ $stats['storage_percentage'] > 80 ? 'text-red-500' : 'text-gray-500' }}">
                        {{ round($stats['storage_percentage']) }}%
                    </span>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-chart-line text-2xl text-purple-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Bandwidth (30d)</p>
                    <p class="text-2xl font-bold text-gray-900">{{ formatBytes($stats['bandwidth_used']) }}</p>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-100 rounded-lg">
                    <i class="fas fa-archive text-2xl text-yellow-600"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Backups</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['total_backups'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Sites -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Recent Sites</h3>
                <a href="{{ route('sites.index') }}" class="text-blue-600 hover:underline text-sm">View all</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Site</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($recentSites as $site)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div>
                                    <a href="https://{{ $site->subdomain }}.{{ config('app.domain') }}" target="_blank" 
                                       class="font-medium text-gray-900 hover:text-blue-600">
                                        {{ $site->subdomain }}.{{ config('app.domain') }}
                                    </a>
                                    <p class="text-sm text-gray-500">{{ ucfirst($site->template) }} template</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="status-badge status-{{ $site->status }}">
                                    {{ ucfirst($site->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $site->created_at->diffForHumans() }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <a href="{{ route('sites.manage', $site) }}" class="text-blue-600 hover:underline">
                                    Manage
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                No sites yet. <a href="{{ route('sites.create') }}" class="text-blue-600 hover:underline">Create your first site</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Plan Usage Alert -->
    @if($stats['storage_percentage'] > 80 || $stats['sites_percentage'] > 80)
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
            <div class="flex">
                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">Approaching Plan Limits</h3>
                    <p class="mt-1 text-sm text-yellow-700">
                        @if($stats['storage_percentage'] > 80)
                            Storage usage at {{ round($stats['storage_percentage']) }}%. 
                        @endif
                        @if($stats['sites_percentage'] > 80)
                            Using {{ $stats['active_sites'] }} of {{ auth()->user()->plan->site_limit }} sites.
                        @endif
                        <a href="{{ route('billing.plans') }}" class="font-medium underline">Upgrade your plan</a>
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>

<style>
    .btn-primary {
        @apply inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors;
    }
    
    .stat-card {
        @apply bg-white rounded-lg shadow p-6;
    }
    
    .status-badge {
        @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium;
    }
    
    .status-active {
        @apply bg-green-100 text-green-800;
    }
    
    .status-suspended {
        @apply bg-red-100 text-red-800;
    }
    
    .status-pending {
        @apply bg-yellow-100 text-yellow-800;
    }
</style>

@php
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
@endphp
@endsection