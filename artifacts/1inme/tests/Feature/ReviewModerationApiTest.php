<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\Review;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Owner-side review moderation REST API (mobile parity for the web
 * /user/.../reviews/* actions):
 *
 *   GET    /api/v1/me/reviews
 *   POST   /api/v1/me/reviews/{review}/approve
 *   POST   /api/v1/me/reviews/{review}/hide
 *   POST   /api/v1/me/reviews/{review}/pin
 *   POST   /api/v1/me/reviews/{review}/reply
 *   DELETE /api/v1/me/reviews/{review}
 *
 * All actions are owner-scoped: a creator can only touch their own
 * native reviews. We use a real Bearer token (not Sanctum::actingAs)
 * because the API path runs the TouchSessionToken middleware.
 */
class ReviewModerationApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'own-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function makeReviewsLink(User $user): Link
    {
        return Link::create([
            'user_id' => $user->id,
            'type'    => Link::TYPE_REVIEWS,
            'alias'   => Link::generateAlias(),
            'title'   => 'My reviews',
        ]);
    }

    private function makeReview(User $user, Link $link, array $overrides = []): Review
    {
        return Review::create(array_merge([
            'user_id'     => $user->id,
            'link_id'     => $link->id,
            'author_name' => 'Jane',
            'rating'      => 5,
            'body'        => 'Loved it!',
            'status'      => Review::STATUS_PENDING,
        ], $overrides));
    }

    public function test_mine_lists_own_reviews_with_counts(): void
    {
        $user = $this->makeUser();
        $link = $this->makeReviewsLink($user);
        $this->makeReview($user, $link, ['status' => Review::STATUS_PENDING]);
        $this->makeReview($user, $link, ['status' => Review::STATUS_APPROVED]);
        $this->makeReview($user, $link, ['status' => Review::STATUS_HIDDEN]);

        $this->withToken($user->createToken('test')->plainTextToken);

        $res = $this->getJson('/api/v1/me/reviews');
        $res->assertOk()
            ->assertJsonPath('data.counts.pending', 1)
            ->assertJsonPath('data.counts.approved', 1)
            ->assertJsonPath('data.counts.hidden', 1)
            ->assertJsonCount(3, 'data.reviews');

        // Status filter narrows the list.
        $this->getJson('/api/v1/me/reviews?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data.reviews')
            ->assertJsonPath('data.reviews.0.status', Review::STATUS_PENDING);
    }

    public function test_mine_is_owner_scoped(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $link  = $this->makeReviewsLink($other);
        $this->makeReview($other, $link);

        $this->withToken($owner->createToken('test')->plainTextToken);

        $this->getJson('/api/v1/me/reviews')
            ->assertOk()
            ->assertJsonCount(0, 'data.reviews');
    }

    public function test_approve_hide_pin_reply_and_delete(): void
    {
        $user = $this->makeUser();
        $link = $this->makeReviewsLink($user);
        $review = $this->makeReview($user, $link, [
            'status'  => Review::STATUS_PENDING,
            'is_spam' => true,
        ]);

        $this->withToken($user->createToken('test')->plainTextToken);

        // Approve clears the spam flag and publishes.
        $this->postJson("/api/v1/me/reviews/{$review->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', Review::STATUS_APPROVED)
            ->assertJsonPath('data.is_spam', false);

        // Pin toggles on.
        $this->postJson("/api/v1/me/reviews/{$review->id}/pin")
            ->assertOk()
            ->assertJsonPath('data.pinned', true);

        // Reply sets the public reply.
        $this->postJson("/api/v1/me/reviews/{$review->id}/reply", ['reply' => 'Thank you!'])
            ->assertOk()
            ->assertJsonPath('data.reply', 'Thank you!');
        $this->assertNotNull($review->fresh()->replied_at);

        // Empty reply clears it.
        $this->postJson("/api/v1/me/reviews/{$review->id}/reply", ['reply' => ''])
            ->assertOk()
            ->assertJsonPath('data.reply', null);

        // Hide.
        $this->postJson("/api/v1/me/reviews/{$review->id}/hide")
            ->assertOk()
            ->assertJsonPath('data.status', Review::STATUS_HIDDEN);

        // Delete.
        $this->deleteJson("/api/v1/me/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
        $this->assertNull(Review::find($review->id));
    }

    public function test_cannot_moderate_another_users_review(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $link  = $this->makeReviewsLink($other);
        $review = $this->makeReview($other, $link);

        $this->withToken($owner->createToken('test')->plainTextToken);

        $this->postJson("/api/v1/me/reviews/{$review->id}/approve")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');

        // Untouched.
        $this->assertEquals(Review::STATUS_PENDING, $review->fresh()->status);
    }

    public function test_missing_review_returns_404(): void
    {
        $user = $this->makeUser();
        $this->withToken($user->createToken('test')->plainTextToken);

        $this->postJson('/api/v1/me/reviews/99999999/approve')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/reviews')->assertUnauthorized();
    }
}
