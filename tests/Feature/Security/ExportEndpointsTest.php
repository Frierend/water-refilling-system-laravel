<?php

namespace Tests\Feature\Security;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $deliveryUser;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Owner Test User',
            'email' => 'owner-export-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $this->deliveryUser = User::create([
            'name' => 'Delivery Test User',
            'email' => 'delivery-export-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'delivery',
        ]);

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '09123456789',
            'address' => 'Test Address',
            'is_regular' => true,
        ]);

        Order::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->owner->id,
            'quantity' => 2,
            'water_price' => 25.00,
            'is_delivery' => true,
            'delivery_fee' => 5.00,
            'total_amount' => 60.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'payment_reference' => null,
            'order_status' => 'completed',
            'delivery_user_id' => $this->deliveryUser->id,
            'delivery_date' => now(),
            'notes' => 'Test order',
        ]);

        $item = InventoryItem::create([
            'name' => 'Purified Water',
            'description' => 'For testing exports',
            'quantity' => 100,
            'threshold' => 10,
            'type' => 'water',
        ]);

        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'user_id' => $this->owner->id,
            'quantity_change' => 5,
            'transaction_type' => 'adjustment',
            'notes' => 'Test transaction',
        ]);

        $this->actingAs($this->owner);
    }

    public function test_sales_export_csv_downloads_successfully(): void
    {
        $response = $this->get('/reports/sales/export?format=csv&period=monthly');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', strtolower($response->headers->get('content-type', '')));
        $this->assertStringContainsString('.csv', $response->headers->get('content-disposition', ''));
        $this->assertStringContainsString('Order ID', $response->streamedContent());
    }

    public function test_delivery_export_pdf_downloads_successfully(): void
    {
        $response = $this->get('/reports/delivery/export?format=pdf&period=monthly');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', strtolower($response->headers->get('content-type', '')));
    }

    public function test_customer_export_pdf_downloads_successfully(): void
    {
        $response = $this->get('/reports/customer/export?format=pdf&period=monthly');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', strtolower($response->headers->get('content-type', '')));
    }

    public function test_inventory_report_pdf_returns_fallback_message(): void
    {
        $response = $this->from('/reports/inventory')->get('/reports/inventory/export?format=pdf');

        $response->assertStatus(302);
        $response->assertRedirect('/reports/inventory');
        $response->assertSessionHas('info');
    }

    public function test_inventory_module_csv_export_downloads_successfully(): void
    {
        $response = $this->get('/inventory/export?format=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', strtolower($response->headers->get('content-type', '')));
        $this->assertStringContainsString('ID,Name,Type,Quantity,Threshold', $response->streamedContent());
    }
}
