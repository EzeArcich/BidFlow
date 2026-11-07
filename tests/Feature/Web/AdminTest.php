<?php

namespace Tests\Feature\Web;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMembership;
use App\Domain\Organizations\TenantContext;
use App\Domain\Shared\ActivityContext;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create(['name' => 'Acme Compras', 'type' => 'company']);
        $this->user = User::factory()->create(['email' => 'web@example.test', 'password' => 'web-test-password']);
        $this->tenant(fn () => OrganizationMembership::factory()->create(['user_id' => $this->user->id, 'role' => 'operator']));
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->run($this->organization->id, $callback(...));
    }

    private function draft(array $attributes = []): Tender
    {
        return $this->tenant(fn () => Tender::factory()->create(['type' => 'services', 'created_by' => $this->user->id, ...$attributes]));
    }

    private function form(array $attributes = []): array
    {
        return ['title' => 'Mantenimiento de oficinas', 'type' => 'services', 'deadline_local' => now()->addWeek()->setTimezone(config('bidding.display_timezone'))->format('Y-m-d\TH:i'), 'description' => 'Servicio trimestral', 'conditions_text' => 'Trabajos fuera del horario comercial', ...$attributes];
    }

    public function test_guest_pages_and_authentication_redirects(): void
    {
        $this->get('/')->assertOk()->assertSee('Bienvenido a Bidflow');
        $this->get('/login')->assertOk()->assertSee('name="_token"', false);
        foreach (['/dashboard', '/tenders', '/tenders/create', '/suppliers', '/organizations'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        $this->post('/tenders', $this->form())->assertRedirect('/login');
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_login_logout_and_sanctum_remain_separate(): void
    {
        $this->post('/login', ['email' => $this->user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->post('/login', ['email' => $this->user->email, 'password' => 'web-test-password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user, 'web');
        $this->get('/dashboard')->assertOk()->assertSee('Acme Compras');
        $this->getJson('/api/v1/tenders')->assertUnauthorized();
        $token = $this->user->createToken('separate-api', ['rfq:read'])->plainTextToken;
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest('web');
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->withToken($token)->getJson('/api/v1/tenders')->assertOk();
    }

    public function test_csrf_is_enforced_outside_test_bypass(): void
    {
        $this->app->instance('env', 'local');
        $this->post('/login', ['email' => $this->user->email, 'password' => 'web-test-password'])->assertStatus(419);
        $this->assertGuest('web');
    }

    public function test_multiple_memberships_require_selection_and_forged_organization_is_rejected(): void
    {
        $other = Organization::factory()->create();
        $this->actingAs($this->user, 'web');
        $this->post('/organizations', ['organization_id' => $other->id])->assertForbidden();
        app(TenantContext::class)->run($other->id, fn () => OrganizationMembership::factory()->create(['user_id' => $this->user->id]));
        $this->get('/dashboard')->assertRedirect('/organizations');
        $this->get('/organizations')->assertOk()->assertSee('Acme Compras')->assertSee($other->name);
        $this->post('/organizations', ['organization_id' => $other->id])->assertRedirect('/dashboard')->assertSessionHas('active_organization_id', $other->id);
        $this->get('/dashboard')->assertOk();
        $this->post('/organizations', ['organization_id' => 'not-an-id'])->assertSessionHasErrors('organization_id');
    }

    public function test_missing_and_revoked_memberships_never_expose_tenant_data(): void
    {
        $outsider = User::factory()->create();
        $this->actingAs($outsider, 'web')->get('/dashboard')->assertRedirect('/organizations');
        $this->get('/organizations')->assertOk()->assertSee('No tenés una organización activa')->assertDontSee('Acme Compras');
        $this->actingAs($this->user, 'web')->withSession(['active_organization_id' => $this->organization->id]);
        $this->tenant(fn () => OrganizationMembership::where('user_id', $this->user->id)->firstOrFail()->delete());
        $this->get('/tenders')->assertRedirect('/organizations')->assertSessionMissing('active_organization_id');
    }

    public function test_dashboard_counts_and_lists_use_only_current_organization(): void
    {
        $this->draft(['title' => 'Solicitud visible']);
        $this->draft(['status' => 'published', 'title' => 'Mantenimiento publicado']);
        $other = Organization::factory()->create();
        app(TenantContext::class)->run($other->id, fn () => Tender::factory()->create(['title' => 'Compra confidencial']));
        $this->actingAs($this->user, 'web')->get('/dashboard')->assertOk()->assertViewHas('counts', fn ($counts) => $counts->sum() === 2 && (int) $counts['draft'] === 1)->assertSee('Solicitud visible')->assertDontSee('Compra confidencial');
        $this->get('/tenders?status=published&q=Mantenimiento&per_page=1')->assertOk()->assertSee('Mantenimiento publicado')->assertDontSee('Solicitud visible')->assertDontSee('Compra confidencial')->assertViewHas('tenders', fn ($tenders) => $tenders->total() === 1);
        $this->get('/tenders?status=invalid')->assertSessionHasErrors('status');
        $this->assertSame('api', app(ActivityContext::class)->origin);
        $this->expectException(LogicException::class);
        app(TenantContext::class)->id();
    }

    public function test_browser_flow_create_edit_items_invite_confirm_publish(): void
    {
        Mail::fake();
        Queue::fake();
        $this->actingAs($this->user, 'web');
        $this->get('/tenders/create')->assertOk()->assertSee('Empecemos por lo esencial');
        $this->post('/tenders', $this->form())->assertRedirect();
        $tender = $this->tenant(fn () => Tender::firstOrFail());
        $url = '/tenders/'.$tender->id;
        $this->get($url.'/edit')->assertOk()->assertSee('Mantenimiento de oficinas');
        $this->patch($url, $this->form(['title' => 'Mantenimiento anual']))->assertRedirect($url);
        $this->get($url.'/items/create')->assertOk();
        $this->post($url.'/items', ['description' => 'Servicio de climatización', 'unit' => 'hora', 'quantity' => '12.500', 'specifications_text' => 'Equipos de hasta 5000 frigorías'])->assertRedirect($url);
        $item = $this->tenant(fn () => TenderItem::firstOrFail());
        $this->get($url.'/items/'.$item->id.'/edit')->assertOk()->assertSee('12.500');
        $this->patch($url.'/items/'.$item->id, ['quantity' => '15.000', 'specifications_text' => 'Incluye limpieza'])->assertRedirect($url);
        $supplier = Supplier::factory()->create(['type' => 'contractor']);
        $this->tenant(fn () => OrganizationSupplier::factory()->create(['supplier_id' => $supplier->id]));
        $this->get('/suppliers')->assertOk()->assertSee($supplier->trade_name);
        $this->get($url.'/suppliers')->assertOk()->assertSee('Elegí a quién invitar');
        $payload = ['invitations' => [['supplier_id' => $supplier->id]]];
        $this->post($url.'/invitations', $payload)->assertRedirect($url);
        $this->post($url.'/invitations', $payload)->assertRedirect($url);
        $this->assertDatabaseCount('tender_invitations', 1);
        $this->get($url.'/publish')->assertOk()->assertSee('Confirmar publicación');
        $this->post($url.'/publish', ['confirmation' => '1'])->assertRedirect($url)->assertSessionHas('success');
        $this->get($url)->assertOk()->assertSee('Publicada')->assertSee('15.000')->assertSee('Incluye limpieza')->assertSee($supplier->trade_name)->assertDontSee('Revisar y publicar');
        $this->assertDatabaseHas('tenders', ['id' => $tender->id, 'status' => 'published', 'organization_id' => $this->organization->id]);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $tender->id, 'action' => 'tender.published', 'origin' => 'web']);
        $this->assertDatabaseCount('outbox_events', 1);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    public function test_business_errors_keep_form_input_and_published_records_immutable(): void
    {
        $tender = $this->draft();
        $url = '/tenders/'.$tender->id;
        $this->actingAs($this->user, 'web')->post($url.'/publish')->assertSessionHasErrors('confirmation');
        $this->from($url.'/publish')->post($url.'/publish', ['confirmation' => '1'])->assertRedirect($url.'/publish');
        $response = $this->get($url.'/publish');
        $response->assertOk()->assertSee('Agregá al menos un ítem antes de publicar.');
        $this->from('/tenders/create')->post('/tenders', $this->form(['title' => '', 'deadline_local' => 'invalid']))->assertSessionHasErrors(['title', 'deadline_at'])->assertSessionHasInput('description', 'Servicio trimestral');
        $this->post('/tenders', $this->form(['organization_id' => $this->organization->id]))->assertSessionHasErrors('organization_id');
        $published = $this->draft(['status' => 'published']);
        $publishedUrl = '/tenders/'.$published->id;
        $this->get($publishedUrl.'/edit')->assertForbidden();
        $this->from($publishedUrl)->patch($publishedUrl, $this->form(['title' => 'No cambiar']))->assertRedirect($publishedUrl)->assertSessionHasErrors('operation');
        $this->from($publishedUrl)->post($publishedUrl.'/publish', ['confirmation' => '1'])->assertSessionHasErrors('operation');
        $this->assertDatabaseMissing('tenders', ['id' => $published->id, 'title' => 'No cambiar']);
    }

    public function test_tenant_and_nested_item_boundaries(): void
    {
        $foreignOrg = Organization::factory()->create();
        $foreign = app(TenantContext::class)->run($foreignOrg->id, fn () => Tender::factory()->create());
        $this->actingAs($this->user, 'web');
        foreach (['', '/edit', '/items/create', '/suppliers', '/publish'] as $suffix) {
            $this->get('/tenders/'.$foreign->id.$suffix)->assertNotFound();
        }
        $this->patch('/tenders/'.$foreign->id, $this->form())->assertNotFound();
        $this->post('/tenders/'.$foreign->id.'/publish')->assertNotFound();
        $own = $this->draft();
        $second = $this->draft();
        $item = $this->tenant(fn () => TenderItem::factory()->create(['tender_id' => $second->id]));
        $this->get('/tenders/'.$own->id.'/items/'.$item->id.'/edit')->assertNotFound();
        $this->delete('/tenders/'.$own->id.'/items/'.$item->id)->assertNotFound();
        $this->get('/dashboard')->assertOk();
    }

    public static function writeRoles(): array
    {
        return [['owner'], ['admin'], ['operator']];
    }

    #[DataProvider('writeRoles')]
    public function test_writer_roles_can_create_and_remove_draft_items(string $role): void
    {
        $this->tenant(fn () => OrganizationMembership::where('user_id', $this->user->id)->firstOrFail()->update(['role' => $role]));
        $tender = $this->draft();
        $item = $this->tenant(fn () => TenderItem::factory()->create(['tender_id' => $tender->id]));
        $this->actingAs($this->user, 'web')->get('/tenders/create')->assertOk();
        $this->delete('/tenders/'.$tender->id.'/items/'.$item->id)->assertRedirect('/tenders/'.$tender->id);
        $this->assertDatabaseMissing('tender_items', ['id' => $item->id]);
    }

    public function test_viewer_can_read_but_cannot_mutate_anything(): void
    {
        $this->tenant(fn () => OrganizationMembership::where('user_id', $this->user->id)->firstOrFail()->update(['role' => 'viewer']));
        $tender = $this->draft();
        $url = '/tenders/'.$tender->id;
        $item = $this->tenant(fn () => TenderItem::factory()->create(['tender_id' => $tender->id]));
        $this->actingAs($this->user, 'web')->get('/dashboard')->assertOk();
        $this->get('/tenders')->assertOk()->assertDontSee('Nueva licitación');
        $this->get($url)->assertOk()->assertDontSee('Revisar y publicar');
        $this->get('/suppliers')->assertOk();
        foreach (['/tenders/create', $url.'/edit', $url.'/items/create', $url.'/items/'.$item->id.'/edit', $url.'/suppliers', $url.'/publish'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/tenders', $this->form())->assertForbidden();
        $this->patch($url, $this->form())->assertForbidden();
        $this->post($url.'/items', [])->assertForbidden();
        $this->patch($url.'/items/'.$item->id, [])->assertForbidden();
        $this->delete($url.'/items/'.$item->id)->assertForbidden();
        $this->post($url.'/invitations', [])->assertForbidden();
        $this->post($url.'/publish', ['confirmation' => '1'])->assertForbidden();
    }
}
