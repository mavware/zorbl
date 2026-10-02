<?php

use App\Enums\TemplateGeneratorStyle;
use App\Jobs\GenerateTemplateDrafts;
use App\Models\Template;
use App\Models\User;
use App\Support\TemplateSearchOptions;

function templateDraftsJob(User $user, int $size = 9, array $seedWords = []): GenerateTemplateDrafts
{
    return new GenerateTemplateDrafts(
        requestedById: $user->id,
        size: $size,
        style: TemplateGeneratorStyle::Standard,
        count: 1,
        seed: 5,
        themeLengths: [],
        targetOverrides: [],
        search: new TemplateSearchOptions(attemptsPerTemplate: 1),
        seedWords: $seedWords,
    );
}

test('the job saves drafts and notifies the admin who asked for them', function () {
    $user = User::factory()->create();

    app()->call([templateDraftsJob($user), 'handle']);

    $draft = Template::with('annotation')->sole();

    expect($draft->is_active)->toBeFalse()
        ->and($draft->width)->toBe(9)
        ->and($draft->annotation)->not->toBeNull();

    $notification = $user->notifications()->sole();

    expect($notification->data['title'])->toBe('9×9 templates ready')
        ->and($notification->data['body'])->toBe('Saved 1 template(s) as inactive drafts.')
        ->and($notification->data['status'])->toBe('success');
});

test('the job reports a failure to the admin', function () {
    $user = User::factory()->create();

    templateDraftsJob($user)->failed(new RuntimeException('Out of memory'));

    $notification = $user->notifications()->sole();

    expect($notification->data['title'])->toBe('9×9 template generation failed')
        ->and($notification->data['body'])->toBe('Out of memory')
        ->and($notification->data['status'])->toBe('danger');
});
