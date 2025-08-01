@extends('layouts.app')

@section('title', 'Create New Site')
@section('header', 'Create New WordPress Site')

@section('content')
<div class="max-w-4xl mx-auto">
    <form method="POST" action="{{ route('sites.store') }}" x-data="siteForm()">
        @csrf
        
        <div class="space-y-6">
            <!-- Subdomain Selection -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Choose Your Subdomain</h3>
                
                <div class="flex items-center space-x-2">
                    <div class="flex-1">
                        <input type="text" 
                               name="subdomain" 
                               x-model="subdomain"
                               @input="checkAvailability()"
                               placeholder="yoursite"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               pattern="[a-z0-9-]+"
                               required>
                    </div>
                    <span class="text-gray-600">.{{ config('app.domain') }}</span>
                </div>
                
                @error('subdomain')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
                
                <div x-show="checking" class="mt-2 text-sm text-gray-500">
                    <i class="fas fa-spinner fa-spin mr-1"></i>Checking availability...
                </div>
                
                <div x-show="!checking && available === true" class="mt-2 text-sm text-green-600">
                    <i class="fas fa-check-circle mr-1"></i>Subdomain is available!
                </div>
                
                <div x-show="!checking && available === false" class="mt-2 text-sm text-red-600">
                    <i class="fas fa-times-circle mr-1"></i>Subdomain is already taken
                </div>
                
                <p class="mt-3 text-sm text-gray-500">
                    Use only lowercase letters, numbers, and hyphens. No spaces or special characters.
                </p>
            </div>

            <!-- Template Selection -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Select a Template</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach(['blog', 'business', 'portfolio', 'ecommerce'] as $template)
                        <label class="template-card" :class="{ 'selected': selectedTemplate === '{{ $template }}' }">
                            <input type="radio" 
                                   name="template" 
                                   value="{{ $template }}"
                                   x-model="selectedTemplate"
                                   class="sr-only"
                                   required>
                            
                            <div class="p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <i class="fas fa-{{ $template === 'blog' ? 'pen' : ($template === 'business' ? 'briefcase' : ($template === 'portfolio' ? 'images' : 'shopping-cart')) }} text-2xl text-gray-400"></i>
                                    <span class="template-check">
                                        <i class="fas fa-check"></i>
                                    </span>
                                </div>
                                <h4 class="font-semibold text-gray-900">{{ ucfirst($template) }}</h4>
                                <p class="text-sm text-gray-600 mt-1">
                                    @if($template === 'blog')
                                        Perfect for bloggers and content creators
                                    @elseif($template === 'business')
                                        Professional template for companies
                                    @elseif($template === 'portfolio')
                                        Showcase your work and projects
                                    @else
                                        Start selling online with WooCommerce
                                    @endif
                                </p>
                            </div>
                        </label>
                    @endforeach
                </div>
                
                @error('template')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Site Details -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Site Details</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Site Title</label>
                        <input type="text" 
                               name="site_title" 
                               value="{{ old('site_title') }}"
                               placeholder="My Awesome Website"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               required>
                        @error('site_title')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Admin Email</label>
                        <input type="email" 
                               name="admin_email" 
                               value="{{ old('admin_email', auth()->user()->email) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               required>
                        @error('admin_email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Admin Username</label>
                        <input type="text" 
                               name="admin_username" 
                               value="{{ old('admin_username', 'admin') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               required>
                        @error('admin_username')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Plan Limits Info -->
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                <div class="flex">
                    <i class="fas fa-info-circle text-blue-400"></i>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Your Plan Includes</h3>
                        <ul class="mt-1 text-sm text-blue-700 space-y-1">
                            <li>• {{ auth()->user()->plan->site_limit ?? 'Unlimited' }} WordPress sites</li>
                            <li>• {{ formatBytes(auth()->user()->plan->storage_limit * 1024 * 1024) }} storage per site</li>
                            <li>• {{ formatBytes(auth()->user()->plan->bandwidth_limit * 1024 * 1024) }} monthly bandwidth</li>
                            <li>• {{ auth()->user()->plan->backup_retention }} day backup retention</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-between">
                <a href="{{ route('sites.index') }}" class="text-gray-600 hover:text-gray-900">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Sites
                </a>
                
                <button type="submit" 
                        class="btn-primary"
                        :disabled="!available || checking"
                        x-text="creating ? 'Creating Site...' : 'Create Site'">
                    Create Site
                </button>
            </div>
        </div>
    </form>
</div>

<style>
    .template-card {
        @apply relative border-2 border-gray-200 rounded-lg cursor-pointer transition-all hover:border-gray-300;
    }
    
    .template-card.selected {
        @apply border-blue-500 bg-blue-50;
    }
    
    .template-check {
        @apply w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-xs text-gray-400;
    }
    
    .template-card.selected .template-check {
        @apply bg-blue-500 text-white;
    }
    
    .btn-primary {
        @apply inline-flex items-center px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed;
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

@push('scripts')
<script>
function siteForm() {
    return {
        subdomain: '',
        selectedTemplate: 'blog',
        available: null,
        checking: false,
        creating: false,
        checkTimer: null,
        
        checkAvailability() {
            clearTimeout(this.checkTimer);
            this.available = null;
            
            if (this.subdomain.length < 3) {
                return;
            }
            
            this.checking = true;
            this.checkTimer = setTimeout(() => {
                fetch(`/api/subdomain/check?subdomain=${this.subdomain}`)
                    .then(r => r.json())
                    .then(data => {
                        this.available = data.available;
                        this.checking = false;
                    });
            }, 500);
        }
    }
}
</script>
@endpush
@endsection