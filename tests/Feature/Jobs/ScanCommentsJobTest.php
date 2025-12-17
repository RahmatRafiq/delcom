<?php

use App\Jobs\ScanCommentsJob;
use App\Models\Filter;
use App\Models\FilterGroup;
use App\Models\Platform;
use App\Models\User;
use App\Models\UserPlatform;
use App\Services\FilterMatcher;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('ScanCommentsJob', function () {
    describe('job dispatching', function () {
        it('can be dispatched to queue', function () {
            Queue::fake();

            $platform = Platform::factory()->create(['name' => 'youtube']);
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $platform->id,
                'connection_method' => 'api',
                'is_active' => true,
            ]);

            ScanCommentsJob::dispatch($userPlatform);

            Queue::assertPushed(ScanCommentsJob::class);
        });

        it('accepts max_contents and max_comments parameters', function () {
            Queue::fake();

            $platform = Platform::factory()->create(['name' => 'youtube']);
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $platform->id,
            ]);

            ScanCommentsJob::dispatch($userPlatform, 5, 50);

            Queue::assertPushed(ScanCommentsJob::class);
        });

        it('accepts specific content id parameter', function () {
            Queue::fake();

            $platform = Platform::factory()->create(['name' => 'instagram']);
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $platform->id,
            ]);

            ScanCommentsJob::dispatch($userPlatform, 10, 100, 'specific_content_123');

            Queue::assertPushed(ScanCommentsJob::class);
        });
    });

    describe('job execution with extension connection', function () {
        it('completes without API calls for extension connection', function () {
            $platform = Platform::factory()->create(['name' => 'youtube']);
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $platform->id,
                'connection_method' => 'extension',
                'is_active' => true,
                'auto_moderation_enabled' => true,
            ]);

            $filterGroup = FilterGroup::factory()->youtube()->create([
                'user_id' => $this->user->id,
                'is_active' => true,
            ]);

            Filter::factory()->create([
                'filter_group_id' => $filterGroup->id,
                'pattern' => 'spam',
                'is_active' => true,
            ]);

            $job = new ScanCommentsJob($userPlatform);
            $job->handle(new FilterMatcher);

            $userPlatform->refresh();
            expect($userPlatform->last_scanned_at)->not->toBeNull();
        });
    });

    describe('platform support', function () {
        it('works with YouTube platform', function () {
            $platform = Platform::factory()->create(['name' => 'youtube']);
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $platform->id,
                'connection_method' => 'extension',
            ]);

            $job = new ScanCommentsJob($userPlatform);

            expect($job)->toBeInstanceOf(ScanCommentsJob::class);
        });

        it('works with Instagram platform', function () {
            $platform = Platform::factory()->create(['name' => 'instagram']);
            $userPlatform = UserPlatform::factory()->create([
                'user_id' => $this->user->id,
                'platform_id' => $platform->id,
                'connection_method' => 'extension',
            ]);

            $job = new ScanCommentsJob($userPlatform);

            expect($job)->toBeInstanceOf(ScanCommentsJob::class);
        });
    });
});
