@extends('layouts.app')

@section('title', 'My Sites')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">My Sites</h1>
                <p class="mt-2 text-gray-600">Manage all your WordPress sites in one place.</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <a href="{{ route('sites.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <i class="fas fa-plus mr-2"></i>
                    Create New Site
                </a>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="p-6">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" 
                                   id="search" 
                                   placeholder="Search sites..." 
                                   class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2.5 border">
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <select class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2.5 border">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <select class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2.5 border">
                            <option value="">Sort by</option>
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="name">Name (A-Z)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        @if($sites->count() > 0)
        <!-- Sites Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($sites as $site)
            <div class="bg-white shadow rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $site->title }}</h3>
                            <p class="text-sm text-gray-500">{{ $site->subdomain }}.wpplatform.com</p>
                        </div>
                        @if($site->status === 'active')
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                Active
                            </span>
                        @else
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                Inactive
                            </span>
                        @endif
                    </div>

                    <div class="space-y-2 mb-4">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-calendar mr-2"></i>
                            Created {{ $site->created_at->format('M d, Y') }}
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-database mr-2"></i>
                            Storage: {{ $site->storage_used ?? '0 MB' }}
                        </div>
                        @if($site->last_backup_at)
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-shield-alt mr-2"></i>
                            Last backup: {{ $site->last_backup_at->diffForHumans() }}
                        </div>
                        @endif
                    </div>

                    <div class="flex gap-2">
                        <a href="/wp.php?site={{ $site->subdomain }}" 
                           target="_blank"
                           class="flex-1 inline-flex justify-center items-center px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition-colors">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            Visit Site
                        </a>
                        <a href="/wp.php?site={{ $site->subdomain }}&wp-admin" 
                           target="_blank"
                           class="flex-1 inline-flex justify-center items-center px-3 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700 transition-colors">
                            <i class="fas fa-wordpress mr-2"></i>
                            WP Admin
                        </a>
                    </div>

                    <div class="mt-3 pt-3 border-t border-gray-200 flex justify-between">
                        <a href="{{ route('sites.show', $site) }}" 
                           class="text-sm text-gray-600 hover:text-gray-900">
                            <i class="fas fa-info-circle mr-1"></i>
                            Details
                        </a>
                        <a href="{{ route('sites.edit', $site) }}" 
                           class="text-sm text-gray-600 hover:text-gray-900">
                            <i class="fas fa-cog mr-1"></i>
                            Settings
                        </a>
                        <form action="{{ route('sites.destroy', $site) }}" 
                              method="POST" 
                              class="inline"
                              onsubmit="return confirm('Are you sure you want to delete this site? This action cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="text-sm text-red-600 hover:text-red-900">
                                <i class="fas fa-trash mr-1"></i>
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-8">
            {{ $sites->links() }}
        </div>

        @else
        <!-- Empty State -->
        <div class="bg-white shadow rounded-lg">
            <div class="p-12 text-center">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-globe text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No sites yet</h3>
                <p class="text-gray-500 mb-6 max-w-md mx-auto">
                    You haven't created any WordPress sites yet. Get started by creating your first site!
                </p>
                <a href="{{ route('sites.create') }}" class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <i class="fas fa-plus mr-2"></i>
                    Create Your First Site
                </a>
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
// Search functionality
document.getElementById('search').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const siteCards = document.querySelectorAll('.grid > div');
    
    siteCards.forEach(card => {
        const title = card.querySelector('h3').textContent.toLowerCase();
        const subdomain = card.querySelector('p').textContent.toLowerCase();
        
        if (title.includes(searchTerm) || subdomain.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
});
</script>
@endpush
@endsection