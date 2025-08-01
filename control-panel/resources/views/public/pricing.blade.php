@extends('layouts.app')

@section('content')
<div class="bg-gray-50 min-h-screen py-12">
    <div class="container mx-auto px-6">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold mb-4">Choose Your Perfect Plan</h1>
            <p class="text-xl text-gray-600">Simple pricing that scales with your needs</p>
        </div>

        <!-- Pricing Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
            @foreach($plans as $plan)
            <div class="bg-white rounded-lg shadow-lg overflow-hidden {{ $plan->slug === 'professional' ? 'transform scale-105 border-2 border-purple-600' : '' }}">
                @if($plan->slug === 'professional')
                <div class="bg-purple-600 text-white text-center py-2 px-4">
                    Recommended
                </div>
                @endif
                
                <div class="p-8">
                    <h2 class="text-2xl font-bold mb-4">{{ $plan->name }}</h2>
                    
                    @if($plan->description)
                    <p class="text-gray-600 mb-6">{{ $plan->description }}</p>
                    @endif
                    
                    <div class="mb-8">
                        <span class="text-4xl font-bold">${{ number_format($plan->price, 0) }}</span>
                        <span class="text-gray-600">/month</span>
                        @if($plan->trial_days > 0)
                        <p class="text-sm text-green-600 mt-2">{{ $plan->trial_days }}-day free trial</p>
                        @endif
                    </div>

                    <div class="space-y-4 mb-8">
                        <h3 class="font-semibold text-gray-900">Features Include:</h3>
                        <ul class="space-y-3">
                            @if(isset($plan->features['sites']))
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>{{ $plan->features['sites'] === -1 ? 'Unlimited' : $plan->features['sites'] }} {{ Str::plural('Website', $plan->features['sites']) }}</span>
                            </li>
                            @endif
                            
                            @if(isset($plan->features['storage']))
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>{{ $plan->features['storage'] >= 1000 ? ($plan->features['storage'] / 1000) . ' GB' : $plan->features['storage'] . ' MB' }} Storage</span>
                            </li>
                            @endif
                            
                            @if(isset($plan->features['bandwidth']))
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>{{ $plan->features['bandwidth'] === -1 ? 'Unlimited' : ($plan->features['bandwidth'] >= 1000 ? ($plan->features['bandwidth'] / 1000) . ' GB' : $plan->features['bandwidth'] . ' MB') }} Bandwidth</span>
                            </li>
                            @endif
                            
                            @if(isset($plan->features['ssl']) && $plan->features['ssl'])
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Free SSL Certificate</span>
                            </li>
                            @endif
                            
                            @if(isset($plan->features['backups']) && $plan->features['backups'])
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Automated Backups</span>
                            </li>
                            @endif
                            
                            @if(isset($plan->features['staging']) && $plan->features['staging'])
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Staging Environment</span>
                            </li>
                            @endif
                            
                            @if(isset($plan->features['support']))
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>{{ ucfirst($plan->features['support']) }} Support</span>
                            </li>
                            @endif
                        </ul>
                    </div>

                    <a href="{{ route('register', ['plan' => $plan->slug]) }}" 
                       class="block w-full text-center py-3 rounded-lg font-semibold transition duration-300 
                              {{ $plan->slug === 'professional' ? 'bg-purple-600 text-white hover:bg-purple-700' : 'bg-gray-200 text-gray-800 hover:bg-gray-300' }}">
                        {{ $plan->price == 0 ? 'Start Free' : 'Get Started' }}
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        <!-- FAQ Section -->
        <div class="mt-20 max-w-3xl mx-auto">
            <h2 class="text-3xl font-bold text-center mb-8">Frequently Asked Questions</h2>
            
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-lg mb-2">Can I upgrade or downgrade my plan anytime?</h3>
                    <p class="text-gray-600">Yes! You can change your plan at any time. When upgrading, you'll be charged the prorated difference. When downgrading, we'll credit your account.</p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-lg mb-2">Do you offer refunds?</h3>
                    <p class="text-gray-600">We offer a 30-day money-back guarantee on all paid plans. If you're not satisfied, contact us for a full refund.</p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-lg mb-2">What happens when I reach my limits?</h3>
                    <p class="text-gray-600">We'll notify you when you're approaching your limits. You can upgrade your plan at any time to increase your resources.</p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold text-lg mb-2">Is there a setup fee?</h3>
                    <p class="text-gray-600">No! There are no setup fees or hidden charges. You only pay the monthly subscription fee.</p>
                </div>
            </div>
        </div>

        <!-- Contact CTA -->
        <div class="text-center mt-16">
            <p class="text-gray-600 mb-4">Have questions about our plans?</p>
            <a href="mailto:support@yoursite.com" class="text-purple-600 font-semibold hover:text-purple-700">
                Contact our sales team →
            </a>
        </div>
    </div>
</div>
@endsection