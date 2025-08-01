@extends('layouts.app')

@section('content')
<!-- Hero Section -->
<div class="bg-gradient-to-r from-purple-600 to-blue-600 text-white">
    <div class="container mx-auto px-6 py-20">
        <div class="text-center">
            <h1 class="text-5xl font-bold mb-4">
                Launch Your WordPress Site in Seconds
            </h1>
            <p class="text-xl mb-8 opacity-90">
                Professional WordPress hosting made simple. No technical skills required.
            </p>
            <div class="space-x-4">
                <a href="{{ route('register') }}" class="bg-white text-purple-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    Get Started Free
                </a>
                <a href="{{ route('pricing') }}" class="bg-transparent border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-purple-600 transition duration-300">
                    View Pricing
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Features Section -->
<div class="py-20">
    <div class="container mx-auto px-6">
        <h2 class="text-3xl font-bold text-center mb-12">Why Choose Our Platform?</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="bg-purple-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Lightning Fast</h3>
                <p class="text-gray-600">Deploy WordPress sites in under 60 seconds with our optimized infrastructure.</p>
            </div>
            <div class="text-center">
                <div class="bg-purple-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Secure & Reliable</h3>
                <p class="text-gray-600">Enterprise-grade security with automated backups and SSL certificates.</p>
            </div>
            <div class="text-center">
                <div class="bg-purple-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Scalable Hosting</h3>
                <p class="text-gray-600">Grow from a blog to an enterprise site without changing platforms.</p>
            </div>
        </div>
    </div>
</div>

<!-- Pricing Preview -->
<div class="bg-gray-50 py-20">
    <div class="container mx-auto px-6">
        <h2 class="text-3xl font-bold text-center mb-12">Simple, Transparent Pricing</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            @foreach($plans as $plan)
            <div class="bg-white rounded-lg shadow-lg p-8 {{ $loop->index === 1 ? 'transform scale-105 border-2 border-purple-600' : '' }}">
                @if($loop->index === 1)
                <div class="bg-purple-600 text-white text-center py-2 px-4 rounded-t-lg -mt-8 -mx-8 mb-8">
                    Most Popular
                </div>
                @endif
                <h3 class="text-2xl font-bold mb-4">{{ $plan->name }}</h3>
                <div class="text-4xl font-bold mb-4">
                    ${{ number_format($plan->price, 0) }}
                    <span class="text-lg font-normal text-gray-600">/month</span>
                </div>
                <ul class="space-y-3 mb-8">
                    @foreach($plan->features as $feature => $value)
                    <li class="flex items-center">
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        {{ is_bool($value) ? ucfirst($feature) : $value . ' ' . ucfirst($feature) }}
                    </li>
                    @endforeach
                </ul>
                <a href="{{ route('register') }}" class="block w-full text-center bg-purple-600 text-white py-3 rounded-lg font-semibold hover:bg-purple-700 transition duration-300">
                    Get Started
                </a>
            </div>
            @endforeach
        </div>
    </div>
</div>

<!-- CTA Section -->
<div class="bg-purple-600 text-white py-20">
    <div class="container mx-auto px-6 text-center">
        <h2 class="text-3xl font-bold mb-4">Ready to Launch Your WordPress Site?</h2>
        <p class="text-xl mb-8 opacity-90">Join thousands of users who trust our platform for their WordPress hosting needs.</p>
        <a href="{{ route('register') }}" class="bg-white text-purple-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300 inline-block">
            Start Your Free Trial
        </a>
    </div>
</div>
@endsection