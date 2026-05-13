<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Copyright (DMCA) Policy')]
#[Layout('layouts.public')]
class extends Component {
    //
};
?>

@php
    $entity = config('legal.entity');
    $appName = config('app.name');
    $dmcaEmail = config('legal.dmca_email');
    $effectiveDate = config('legal.effective_date');
@endphp

<div>
    <article class="mx-auto max-w-3xl py-8">
        <header class="mb-10 border-b border-zinc-800 pb-8">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Copyright (DMCA) Policy') }}</h1>
            <p class="mt-3 text-sm text-zinc-500">{{ __('Effective :date', ['date' => $effectiveDate]) }}</p>
            <p class="mt-4 text-zinc-400">
                {{ __(':entity respects the intellectual property rights of others and expects users of :app to do the same. This page explains how to send us a notice if you believe content on :app infringes your copyright, and how to dispute a notice as a user whose content was removed.', ['entity' => $entity, 'app' => $appName]) }}
            </p>
        </header>

        <div class="space-y-10 text-zinc-300">
            <section>
                <h2 class="text-xl font-semibold text-zinc-100">{{ __('Filing a takedown notice') }}</h2>
                <p class="mt-3">
                    {{ __('To file a notice under 17 U.S.C. § 512(c) of the Digital Millennium Copyright Act, send the following to :email:', ['email' => $dmcaEmail]) }}
                </p>
                <ol class="mt-3 list-decimal space-y-2 pl-6 text-zinc-400">
                    <li>{{ __('Your contact information: full legal name, mailing address, phone number, and email address.') }}</li>
                    <li>{{ __('Identification of the copyrighted work you claim has been infringed (a description and, if registered, the registration number).') }}</li>
                    <li>{{ __('Identification of the infringing material — the exact URL on :app where it appears, with enough detail for us to locate it.', ['app' => $appName]) }}</li>
                    <li>{{ __('A statement that you have a good-faith belief that the use is not authorized by the copyright owner, its agent, or the law.') }}</li>
                    <li>{{ __('A statement, under penalty of perjury, that the information in the notice is accurate and that you are the copyright owner or authorized to act on the owner\'s behalf.') }}</li>
                    <li>{{ __('Your physical or electronic signature.') }}</li>
                </ol>
                <p class="mt-3 text-sm text-zinc-500">
                    {{ __('Notices missing any of these elements may be invalid under the DMCA.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">{{ __('Have an account?') }}</h2>
                <p class="mt-3">
                    {{ __('If you have a :app account, the fastest way to send a notice is through our support form — submissions are routed straight to our moderation queue.', ['app' => $appName]) }}
                </p>
                @auth
                    <p class="mt-4">
                        <a
                            href="{{ route('support.create', ['category' => 'copyright']) }}"
                            class="inline-flex items-center justify-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-amber-400 transition"
                        >
                            {{ __('Submit a DMCA notice') }}
                        </a>
                    </p>
                @else
                    <p class="mt-4">
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center justify-center rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-zinc-100 hover:border-zinc-500 hover:bg-zinc-800 transition"
                        >
                            {{ __('Log in to use the support form') }}
                        </a>
                    </p>
                @endauth
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">{{ __('What happens next') }}</h2>
                <ol class="mt-3 list-decimal space-y-2 pl-6 text-zinc-400">
                    <li>{{ __('We review the notice for completeness and, if valid, remove or disable access to the material expeditiously.') }}</li>
                    <li>{{ __('We notify the user who posted the material and forward your contact information so they can respond.') }}</li>
                    <li>{{ __('We may terminate the accounts of users who repeatedly infringe.') }}</li>
                </ol>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">{{ __('Filing a counter-notice') }}</h2>
                <p class="mt-3">
                    {{ __('If your material was removed and you believe the takedown was a mistake or that you had the right to post the material, you can send a counter-notice to :email containing:', ['email' => $dmcaEmail]) }}
                </p>
                <ol class="mt-3 list-decimal space-y-2 pl-6 text-zinc-400">
                    <li>{{ __('Your contact information.') }}</li>
                    <li>{{ __('Identification of the material that was removed and where it had appeared on :app.', ['app' => $appName]) }}</li>
                    <li>{{ __('A statement, under penalty of perjury, that you have a good-faith belief the material was removed in error.') }}</li>
                    <li>{{ __('Your consent to the jurisdiction of the federal court in the district where you live (or, for users outside the United States, of any judicial district in which :app may be found).', ['app' => $appName]) }}</li>
                    <li>{{ __('A statement that you will accept service of process from the original complainant.') }}</li>
                    <li>{{ __('Your physical or electronic signature.') }}</li>
                </ol>
                <p class="mt-3 text-sm text-zinc-500">
                    {{ __('Filing a knowingly false counter-notice — or a knowingly false takedown notice — can expose you to liability for damages and attorneys\' fees.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">{{ __('Designated agent') }}</h2>
                <p class="mt-3">
                    {{ __('Notices are received by the :entity legal team at :email. We are registered as a service provider under § 512(c)(2); we will publish our DMCA agent registration number on this page once registration is complete.', ['entity' => $entity, 'email' => $dmcaEmail]) }}
                </p>
            </section>
        </div>
    </article>
</div>
