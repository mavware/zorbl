<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Cookie Policy')]
#[Layout('layouts.public')]
class extends Component {
    //
};
?>

@php
    $appName = config('app.name');
    $contactEmail = config('legal.contact_email');
    $effectiveDate = config('legal.effective_date');
    $sessionCookie = config('session.cookie');
@endphp

<div>
    <article class="mx-auto max-w-3xl py-8">
        <header class="mb-10 border-b border-zinc-800 pb-8">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Cookie Policy') }}</h1>
            <p class="mt-3 text-sm text-zinc-500">{{ __('Effective :date', ['date' => $effectiveDate]) }}</p>
            <p class="mt-4 text-zinc-400">
                {{ __('This Cookie Policy explains how :app uses cookies and similar technologies, and the choices available to you. For more on how we handle personal data, see our', ['app' => $appName]) }}
                <a href="{{ route('legal.privacy') }}" class="text-amber-500 hover:underline">{{ __('Privacy Policy') }}</a>.
            </p>
        </header>

        <div class="space-y-10 text-zinc-300">
            <section>
                <h2 class="text-xl font-semibold text-zinc-100">1. {{ __('What Are Cookies?') }}</h2>
                <p class="mt-3">
                    {{ __('A cookie is a small text file that a website stores on your device so it can recognise your browser between requests. Similar technologies — local storage, session storage — store information in your browser without using cookies. We use both, and refer to them collectively as "cookies" in this policy.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">2. {{ __('Categories') }}</h2>
                <p class="mt-3">{{ __('Cookies fall into four broad categories:') }}</p>
                <ul class="mt-3 list-disc space-y-1 pl-6 text-zinc-400">
                    <li><strong class="text-zinc-100">{{ __('Strictly necessary.') }}</strong> {{ __('Required to run the service — for example, to keep you logged in and protect against cross-site request forgery. These cannot be switched off.') }}</li>
                    <li><strong class="text-zinc-100">{{ __('Functional.') }}</strong> {{ __('Remember preferences (such as your theme) and improve the experience.') }}</li>
                    <li><strong class="text-zinc-100">{{ __('Analytics.') }}</strong> {{ __('Help us understand how the service is used so we can improve it. Only set with your consent in regions that require it.') }}</li>
                    <li><strong class="text-zinc-100">{{ __('Marketing.') }}</strong> {{ __('Used to measure advertising or build interest profiles. We currently do not use any marketing cookies.') }}</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">3. {{ __('Cookies We Use') }}</h2>
                <div class="mt-4 overflow-x-auto rounded-xl border border-zinc-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-900/60 text-xs uppercase tracking-wider text-zinc-500">
                            <tr>
                                <th class="px-4 py-3">{{ __('Name') }}</th>
                                <th class="px-4 py-3">{{ __('Category') }}</th>
                                <th class="px-4 py-3">{{ __('Purpose') }}</th>
                                <th class="px-4 py-3">{{ __('Expiry') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800 text-zinc-300">
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-amber-400">{{ $sessionCookie }}</td>
                                <td class="px-4 py-3">{{ __('Strictly necessary') }}</td>
                                <td class="px-4 py-3">{{ __('Keeps you signed in for the duration of your session.') }}</td>
                                <td class="px-4 py-3">{{ __(':minutes minutes', ['minutes' => config('session.lifetime')]) }}</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-amber-400">XSRF-TOKEN</td>
                                <td class="px-4 py-3">{{ __('Strictly necessary') }}</td>
                                <td class="px-4 py-3">{{ __('Protects forms and actions against cross-site request forgery.') }}</td>
                                <td class="px-4 py-3">{{ __('Session') }}</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-amber-400">cookie_consent</td>
                                <td class="px-4 py-3">{{ __('Strictly necessary') }}</td>
                                <td class="px-4 py-3">{{ __('Remembers your cookie choices so the banner is shown only once.') }}</td>
                                <td class="px-4 py-3">{{ __('12 months') }}</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-amber-400">{{ __('Local storage (solver progress)') }}</td>
                                <td class="px-4 py-3">{{ __('Functional') }}</td>
                                <td class="px-4 py-3">{{ __('Keeps your in-progress solve on this device when you solve without an account.') }}</td>
                                <td class="px-4 py-3">{{ __('Until cleared') }}</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-amber-400">{{ __('Stripe cookies') }}</td>
                                <td class="px-4 py-3">{{ __('Strictly necessary') }}</td>
                                <td class="px-4 py-3">{{ __('Set by Stripe on the checkout and billing portal to process your payment securely. See Stripe\'s cookie policy.') }}</td>
                                <td class="px-4 py-3">{{ __('Varies') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-sm text-zinc-500">
                    {{ __('We currently do not use analytics or advertising cookies. If that changes, we will update this table and ask for your consent first where required.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">4. {{ __('Your Choices') }}</h2>
                <p class="mt-3">
                    {{ __('When you visit :app from a region that requires it (the EEA, the United Kingdom, or Switzerland), a banner appears the first time you load the site. From the banner you can accept all non-essential cookies, reject them, or open a preferences view to make a granular choice. You can change your mind at any time by clicking "Cookie preferences" in the footer.', ['app' => $appName]) }}
                </p>
                <p class="mt-3">
                    {{ __('All modern browsers let you block or delete cookies through their settings. Blocking strictly necessary cookies will break the service (you will not be able to stay logged in), but blocking the others is safe.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">5. {{ __('Changes') }}</h2>
                <p class="mt-3">
                    {{ __('We may update this Cookie Policy as our use of cookies changes. The "Effective" date above shows the most recent revision.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">6. {{ __('Contact') }}</h2>
                <p class="mt-3">
                    {{ __('Questions? Email :email.', ['email' => $contactEmail]) }}
                </p>
            </section>
        </div>
    </article>
</div>
