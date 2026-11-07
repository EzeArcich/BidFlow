<?php

namespace Database\Factories;

use App\Domain\Integrations\Models\InvitationAccessToken;
use App\Domain\Organizations\TenantContext;
use App\Domain\Tenders\Models\TenderInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvitationAccessToken> */
class InvitationAccessTokenFactory extends Factory
{
    protected $model = InvitationAccessToken::class;

    public function definition(): array
    {
        return [
            'organization_id' => fn () => app(TenantContext::class)->id(),
            'invitation_id' => TenderInvitation::factory(),
            'token_hash' => hash('sha256', random_bytes(32)),
            'expires_at' => now()->addDay(),
        ];
    }
}
