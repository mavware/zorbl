<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Terms of Service')]
#[Layout('layouts.public')]
class extends Component {
    //
};
?>

@php
    $entity = config('legal.entity');
    $appName = config('app.name');
    $contactEmail = config('legal.contact_email');
    $dmcaEmail = config('legal.dmca_email');
    $governingLaw = config('legal.governing_law');
    $effectiveDate = config('legal.effective_date');
    $minimumAge = config('legal.minimum_age');
@endphp

<x-seo-meta title="Terms of Service" :description="__('The terms of service for :app.', ['app' => $appName])" />

<div>
    <article class="mx-auto max-w-3xl py-8">
        <header class="mb-10 border-b border-zinc-800 pb-8">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Terms of Service') }}</h1>
            <p class="mt-3 text-sm text-zinc-500">{{ __('Effective :date', ['date' => $effectiveDate]) }}</p>
            <p class="mt-4 text-zinc-400">
                {{ __('Welcome to :app. These Terms of Service ("Terms") govern your access to and use of :app, operated by :entity ("we", "us", "our"). By using :app you agree to these Terms.', ['app' => $appName, 'entity' => $entity]) }}
            </p>
        </header>

        <div class="space-y-10 text-zinc-300">
            <section>
                <h2 class="text-xl font-semibold text-zinc-100">1. {{ __('Eligibility') }}</h2>
                <p class="mt-3">
                    {{ __('You must be at least :age years old to use :app. If you are between :age and the age of majority in your jurisdiction, you confirm that a parent or legal guardian has reviewed and agreed to these Terms on your behalf. You may not use the service if we have previously terminated your account.', ['age' => $minimumAge, 'app' => $appName]) }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">2. {{ __('Accounts') }}</h2>
                <p class="mt-3">
                    {{ __('You are responsible for keeping your password (or third-party login) secure and for all activity on your account. Notify us immediately if you suspect unauthorized access. We may suspend or terminate accounts that violate these Terms.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">3. {{ __('Your Content') }}</h2>
                <p class="mt-3">
                    {{ __('"Your Content" means crosswords, grids, clues, comments, ratings, profile information, and anything else you submit to :app. You keep ownership of Your Content.', ['app' => $appName]) }}
                </p>
                <p class="mt-3">
                    {{ __('You grant us a worldwide, non-exclusive, royalty-free license to host, store, reproduce, adapt (for example, to reformat for the solver, generate thumbnails, or export to .ipuz/.puz/.pdf), publish, perform, and display Your Content for the purpose of operating, promoting, and improving the service. This license ends when you delete Your Content or your account, except where we are required to retain it (for example, backups for a reasonable retention period or content already shared with others).') }}
                </p>
                <p class="mt-3">
                    {{ __('You represent that Your Content does not infringe anyone\'s rights, including copyright in clues, answers, or imagery. Crossword grids built from common words are generally not protectable, but original clues and themed material may be — do not copy them from other publications without permission.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">4. {{ __('Community Content Submitted to Shared Libraries') }}</h2>
                <p class="mt-3">
                    {{ __('When you publish a puzzle or submit a clue, that material may be surfaced to other users (for example, in the clue library or community browsing). For those features, the license you grant us above includes the right to display Your Content to other users and to let them discover, reference, and reuse public clues for their own puzzles. We do not sell Your Content.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">5. {{ __('Acceptable Use') }}</h2>
                <p class="mt-3">{{ __('You agree not to:') }}</p>
                <ul class="mt-3 list-disc space-y-1 pl-6 text-zinc-400">
                    <li>{{ __('upload content that is illegal, hateful, harassing, sexually explicit involving minors, or that violates someone else\'s rights;') }}</li>
                    <li>{{ __('attempt to break, probe, or interfere with the security, integrity, or rate limits of the service;') }}</li>
                    <li>{{ __('scrape, mass-download, or train machine-learning models on content that is not yours without our written permission;') }}</li>
                    <li>{{ __('use the service to send spam, phishing, or unsolicited messages;') }}</li>
                    <li>{{ __('misrepresent your identity, impersonate others, or run multiple accounts to evade restrictions; or') }}</li>
                    <li>{{ __('use the service to compete with us by replicating its core functionality.') }}</li>
                </ul>
                <p class="mt-3">{{ __('We may remove content or suspend accounts that violate these rules, with or without notice.') }}</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">6. {{ __('AI Features') }}</h2>
                <p class="mt-3">
                    {{ __(':app offers optional AI-assisted features such as grid autofill and clue generation. AI outputs are generated programmatically, may be inaccurate or unoriginal, and you are responsible for reviewing them before publishing. We send the minimum input needed (grid pattern, target word) to our AI providers and do not authorize them to train on your inputs. AI features may be limited to paid plans and may change or be discontinued.', ['app' => $appName]) }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">7. {{ __('Paid Plans, Billing, and Refunds') }}</h2>
                <p class="mt-3">
                    {{ __('Paid plans renew automatically at the interval you selected (monthly or yearly) until you cancel. Payments are processed by Stripe; we do not store your full card number. You can cancel at any time from your billing settings, and your plan stays active through the end of the paid period. Fees are non-refundable except where required by law or as stated at the time of purchase. We may change pricing with at least 30 days\' notice before your next renewal.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">8. {{ __('Our Intellectual Property') }}</h2>
                <p class="mt-3">
                    {{ __('The :app name, logo, software, design, and trademarks are owned by :entity. These Terms grant you no rights to use them except as needed to use the service. The word list bundled with the editor is licensed for use within :app; you may not extract it for redistribution.', ['app' => $appName, 'entity' => $entity]) }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">9. {{ __('Copyright and DMCA') }}</h2>
                <p class="mt-3">
                    {{ __('If you believe content on :app infringes your copyright, send a notice to :email that includes: (a) your contact information, (b) identification of the copyrighted work, (c) the URL of the allegedly infringing material, (d) a statement that you have a good-faith belief the use is not authorized, (e) a statement under penalty of perjury that the notice is accurate and that you are the rights holder or authorized to act on their behalf, and (f) your physical or electronic signature. We respond to valid notices and may terminate the accounts of repeat infringers.', ['app' => $appName, 'email' => $dmcaEmail]) }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">10. {{ __('Termination') }}</h2>
                <p class="mt-3">
                    {{ __('You may stop using :app at any time and delete your account from settings. We may suspend or terminate your access if you violate these Terms, if required by law, or if continued operation would create unreasonable legal or operational risk. Sections that by their nature should survive termination (such as ownership, disclaimers, limitation of liability, and governing law) will survive.', ['app' => $appName]) }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">11. {{ __('Disclaimer of Warranties') }}</h2>
                <p class="mt-3">
                    {{ __('THE SERVICE IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND, WHETHER EXPRESS, IMPLIED, OR STATUTORY, INCLUDING IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT. WE DO NOT WARRANT THAT THE SERVICE WILL BE UNINTERRUPTED, ERROR-FREE, OR SECURE.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">12. {{ __('Limitation of Liability') }}</h2>
                <p class="mt-3">
                    {{ __('TO THE FULLEST EXTENT PERMITTED BY LAW, :entity AND ITS OFFICERS, DIRECTORS, AND EMPLOYEES WILL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, OR FOR LOST PROFITS OR DATA. OUR TOTAL LIABILITY FOR ALL CLAIMS RELATED TO THE SERVICE WILL NOT EXCEED THE GREATER OF (A) THE AMOUNTS YOU PAID US IN THE TWELVE MONTHS BEFORE THE CLAIM AROSE, OR (B) USD $100. SOME JURISDICTIONS DO NOT ALLOW THESE LIMITS, IN WHICH CASE THEY APPLY TO THE MAXIMUM EXTENT PERMITTED.', ['entity' => strtoupper($entity)]) }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">13. {{ __('Indemnification') }}</h2>
                <p class="mt-3">
                    {{ __('You agree to defend, indemnify, and hold harmless :entity from any claim, loss, or expense (including reasonable attorneys\' fees) arising from Your Content, your use of the service, or your violation of these Terms or any law.', ['entity' => $entity]) }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">14. {{ __('Governing Law and Disputes') }}</h2>
                <p class="mt-3">
                    {{ __('These Terms are governed by the laws of :law, without regard to conflict-of-laws rules. The exclusive venue for any dispute that is not subject to arbitration is the courts located in that jurisdiction, and you consent to personal jurisdiction there. If you live in the European Economic Area or the United Kingdom, nothing in these Terms removes the consumer rights you have under mandatory local law.', ['law' => $governingLaw]) }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">15. {{ __('Changes to These Terms') }}</h2>
                <p class="mt-3">
                    {{ __('We may update these Terms from time to time. When we do, we will update the "Effective" date above and, if changes are material, give reasonable notice (for example, by email or an in-app banner) before they take effect. Continued use of the service after that date means you accept the updated Terms.') }}
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">16. {{ __('Contact') }}</h2>
                <p class="mt-3">
                    {{ __('Questions about these Terms? Email :email.', ['email' => $contactEmail]) }}
                </p>
            </section>
        </div>
    </article>
</div>
