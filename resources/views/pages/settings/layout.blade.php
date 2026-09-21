<div class="flex items-start max-md:flex-col">
    <nav class="me-10 w-full pb-4 md:w-[220px]" aria-label="{{ __('Settings') }}">
        <ul class="space-y-0.5">
            @foreach ([
                ['route' => 'profile.edit', 'match' => 'profile.*', 'label' => __('Profile')],
                ['route' => 'billing.index', 'match' => 'billing.*', 'label' => __('Billing')],
                ['route' => 'notifications.edit', 'match' => 'notifications.*', 'label' => __('Notifications')],
                ['route' => 'security.edit', 'match' => 'security.*', 'label' => __('Security')],
                ['route' => 'webhooks.index', 'match' => 'webhooks.*', 'label' => __('Webhooks')],
                ['route' => 'appearance.edit', 'match' => 'appearance.*', 'label' => __('Appearance')],
            ] as $item)
                @php($current = request()->routeIs($item['match']))
                <li>
                    <a
                        href="{{ route($item['route']) }}"
                        wire:navigate
                        @if($current) aria-current="page" @endif
                        @class([
                            'font-classical flex h-9 items-center rounded-sm border-l-2 px-3 text-[16px] font-medium transition-colors focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400',
                            'rounded-l-none border-amber-400 bg-amber-400/10 text-amber-400' => $current,
                            'text-ink-muted hover:text-ink border-transparent' => ! $current,
                        ])
                    >{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </nav>

    <hr class="border-hairline w-full md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <x-page-header :title="$heading ?? ''" :subtitle="$subheading ?? ''" :level="2" size="md" :bleed="false" class="px-0! lg:px-0!" />

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
