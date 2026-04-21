<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportInputValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Owner Test User',
            'email' => 'owner-test-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($this->owner);
    }

    public function test_sales_report_rejects_invalid_period_value(): void
    {
        $response = $this->from('/reports/sales')->get('/reports/sales?period=yearly');

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['period']);
    }

    public function test_delivery_report_rejects_invalid_driver_filter(): void
    {
        $response = $this->from('/reports/delivery')->get('/reports/delivery?driver=invalid-value');

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['driver']);
    }

    public function test_inventory_report_rejects_invalid_item_type(): void
    {
        $response = $this->from('/reports/inventory')->get('/reports/inventory?item_type=javascript');

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['item_type']);
    }

    public function test_sales_report_accepts_valid_filters(): void
    {
        $response = $this->get('/reports/sales?period=daily&granularity=daily&filter=all&per_page=20');

        $response->assertOk();
    }
}
