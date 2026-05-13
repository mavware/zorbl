<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Privacy Policy')]
#[Layout('layouts.public')]
class extends Component {
    //
};
?>

@php
    $entity = config('legal.entity');
    $appName = config('app.name');
    $contactEmail = config('legal.contact_email');
    $effectiveDate = config('legal.effective_date');
    $minimumAge = config('legal.minimum_age');
@endphp

<div>
    <article class="mx-auto max-w-3xl py-8">
        <header class="mb-10 border-b border-zinc-800 pb-8">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Privacy Policy') }}</h1>
            <p class="mt-3 text-sm text-zinc-500">{{ __('Effective :date', ['date' => $effectiveDate]) }}</p>
            <p class="mt-4 text-zinc-400">
                {{ __('This Privacy Policy explains how :entity ("we") collects, uses, and protects personal data when you use :app. It applies to visitors, registered users, and paid subscribers.', ['entity' => $entity, 'app' => $appName]) }}
            </p>
        </header>

        <div class="space-y-10 text-zinc-300">
            <section>
                <h2 class="text-xl font-semibold text-zinc-100">1. {{ __('Who We Are') }}</h2>
                <p class="mt-3">
                    {{ __(':entity is the data controller for personal data processed through :app. You can reach us at :email.', ['entity' => $entity, 'app' => $appName, 'email' => $contactEmail]) }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">2. {{ __('What We Collect') }}</h2>
                <div class="mt-3 space-y-3">
                    <p><strong class="text-zinc-100">{{ __('Account data.') }}</strong> {{ __('Your name, email, password hash, profile information, and any login provider identifiers (for example, your Google account ID if you sign in with Google).') }}</p>
                    <p><strong class="text-zinc-100">{{ __('Content you create.') }}</strong> {{ __('Crosswords, clues, comments, ratings, favorites, contest entries, and support tickets.') }}</p>
                    <p><strong class="text-zinc-100">{{ __('Solve data.') }}</strong> {{ __('Puzzle attempts, solve times, progress snapshots (so we can sync your place across devices), and completion records.') }}</p>
                    <p><strong class="text-zinc-100">{{ __('Billing data.') }}</strong> {{ __('If you subscribe to a paid plan, Stripe processes your payment. We store your Stripe customer ID, plan, status, and a redacted card brand and last-four. We do not see or store your full card number.') }}</p>
                    <p><strong class="text-zinc-100">{{ __('Technical data.') }}</strong> {{ __('IP address, browser user agent, device type, language, timestamps, and pages requested. We use this to run the service securely and diagnose issues.') }}</p>
                    <p><strong class="text-zinc-100">{{ __('Communications.') }}</strong> {{ __('If you contact us — through support, email, or in-app forms — we keep that correspondence to respond and to improve the service.') }}</p>
                </div>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">3. {{ __('How and Why We Use It') }}</h2>
                <p class="mt-3">{{ __('We use personal data for the following purposes, with the corresponding legal basis under the UK GDPR and EU GDPR shown in brackets:') }}</p>
                <ul class="mt-3 list-disc space-y-2 pl-6 text-zinc-400">
                    <li>{{ __('To create and operate your account, deliver the service, and provide customer support [contract performance].') }}</li>
                    <li>{{ __('To process payments and renewals through Stripe [contract performance].') }}</li>
                    <li>{{ __('To secure the service against fraud, abuse, and unauthorized access, including rate limiting and audit logs [legitimate interest].') }}</li>
                    <li>{{ __('To send service emails — receipts, security alerts, password resets, and material changes to these policies [contract performance / legal obligation].') }}</li>
                    <li>{{ __('To send product update emails. You can opt out at any time from notification preferences [legitimate interest, or consent where required].') }}</li>
                    <li>{{ __('To improve the service by analyzing aggregate, non-identifying usage patterns [legitimate interest].') }}</li>
                    <li>{{ __('To comply with legal obligations, including tax, accounting, and lawful requests from authorities [legal obligation].') }}</li>
                </ul>
                <p class="mt-3">{{ __('Where we rely on consent (for example, optional analytics cookies in regions that require it) you can withdraw that consent at any time through the cookie preferences link in the footer.') }}</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">4. {{ __('Who We Share It With') }}</h2>
                <p class="mt-3">{{ __('We share personal data only with vendors that help us run the service, under contracts that require them to protect your data and use it only on our instructions:') }}</p>
                <ul class="mt-3 list-disc space-y-1 pl-6 text-zinc-400">
                    <li>{{ __('Stripe — payment processing and subscription billing.') }}</li>
                    <li>{{ __('Google — for users who sign in with Google (we receive your name, email, and Google account ID).') }}</li>
                    <li>{{ __('Anthropic — when you use AI clue or autofill features, the necessary grid pattern or target word is sent to generate output. Anthropic does not train on this data per our agreement.') }}</li>
                    <li>{{ __('Our hosting and email providers (for example, Laravel Cloud and our transactional email service) — to run the platform and deliver emails.') }}</li>
                </ul>
                <p class="mt-3">{{ __('We may also share data if required by law, court order, or to protect the rights, safety, or property of users or the public. We never sell your personal data.') }}</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">5. {{ __('Cookies and Similar Technologies') }}</h2>
                <p class="mt-3">
                    {{ __('We use a small number of cookies to keep you logged in, remember your preferences, and protect against cross-site request forgery. See our') }}
                    <a href="{{ route('legal.cookies') }}" class="text-amber-500 hover:underline">{{ __('Cookie Policy') }}</a>
                    {{ __('for the full list and your choices.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">6. {{ __('International Transfers') }}</h2>
                <p class="mt-3">
                    {{ __('Our service and several of our vendors are based in the United States. If you access :app from outside the US, your personal data will be transferred to and processed in the US and in countries where our vendors operate. Where required, we rely on Standard Contractual Clauses or equivalent safeguards approved by the European Commission and UK ICO.', ['app' => $appName]) }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">7. {{ __('How Long We Keep It') }}</h2>
                <p class="mt-3">{{ __('We keep personal data only as long as we need it for the purposes described in this policy:') }}</p>
                <ul class="mt-3 list-disc space-y-1 pl-6 text-zinc-400">
                    <li>{{ __('Account data: until you delete your account. When you click "Delete account" we cancel any active subscription, revoke API tokens, and remove your profile, puzzles, attempts, clues, comments, favorites, support tickets, and other personal records from active systems. Routine backups age out within 90 days.') }}</li>
                    <li>{{ __('Billing records: kept for seven years to meet tax and accounting law.') }}</li>
                    <li>{{ __('Support tickets: kept for two years after the last interaction.') }}</li>
                    <li>{{ __('Security logs: kept for up to 12 months.') }}</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">8. {{ __('Your Rights') }}</h2>
                <p class="mt-3">{{ __('If you are in the European Economic Area, the United Kingdom, Switzerland, or another jurisdiction with similar laws, you have the right to:') }}</p>
                <ul class="mt-3 list-disc space-y-1 pl-6 text-zinc-400">
                    <li>{{ __('access the personal data we hold about you;') }}</li>
                    <li>{{ __('have it corrected if it is wrong or out of date;') }}</li>
                    <li>{{ __('have it deleted (also called the "right to be forgotten");') }}</li>
                    <li>{{ __('export it in a machine-readable format (data portability);') }}</li>
                    <li>{{ __('restrict or object to processing based on legitimate interest, including direct marketing;') }}</li>
                    <li>{{ __('withdraw any consent you have given, without affecting prior processing; and') }}</li>
                    <li>{{ __('lodge a complaint with your local data protection authority — for example, the UK ICO or your national supervisory authority in the EEA.') }}</li>
                </ul>
                <p class="mt-3">{{ __('To exercise these rights, email :email or use your account settings, where many actions (download your data, delete your account, change notification preferences) are available self-serve. We will respond within one month.', ['email' => $contactEmail]) }}</p>
                <p class="mt-3">{{ __('California residents have similar rights under the CCPA / CPRA — including the right to know, delete, correct, and opt out of any "sale" or "sharing" of personal information. We do not sell personal information.') }}</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">9. {{ __('Children') }}</h2>
                <p class="mt-3">
                    {{ __(':app is not directed to children under :age, and we do not knowingly collect personal data from them. If you believe a child has provided us personal data, contact us at :email and we will delete it.', ['app' => $appName, 'age' => $minimumAge, 'email' => $contactEmail]) }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">10. {{ __('Security') }}</h2>
                <p class="mt-3">
                    {{ __('We use industry-standard safeguards including TLS for data in transit, encrypted backups, hashed passwords, optional two-factor authentication, and least-privilege access for our team. No system is perfectly secure, but we work hard to keep your data safe and to investigate and disclose incidents quickly when they happen.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">11. {{ __('Changes to This Policy') }}</h2>
                <p class="mt-3">
                    {{ __('We may update this Privacy Policy as the service evolves. When we do, we update the "Effective" date above and, for material changes, notify you by email or an in-app banner before they take effect.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">12. {{ __('Contact') }}</h2>
                <p class="mt-3">
                    {{ __('Questions, requests, or complaints can be sent to :email.', ['email' => $contactEmail]) }}
                </p>
            </section>
        </div>
    </article>
</div>
