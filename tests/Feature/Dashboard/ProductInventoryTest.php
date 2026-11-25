<?php

namespace Tests\Feature\Dashboard;

use App\Enums\GymRole;
use App\Models\Gym;
use App\Models\Product;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductInventoryTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->memberOf($this->gym, GymRole::Owner)->create();
        app(CurrentGym::class)->set($this->gym);
        $this->actingAs($this->owner);
    }

    public function test_products_have_a_view_page_and_editable_status(): void
    {
        $branch = $this->gym->mainBranch();
        $data = [
            'name' => 'Water',
            'sku' => 'WATER-01',
            'category' => 'Drinks',
            'cost_php' => '10.00',
            'price_php' => '20.00',
            'low_stock_threshold' => 2,
            'status' => 'active',
            'initial_stock' => 10,
            'initial_branch_id' => $branch->id,
        ];

        $this->post(route('product-inventory.store'), $data)->assertRedirect();
        $product = Product::sole();

        $this->get(route('product-inventory.index'))->assertInertia(fn (Assert $page) => $page->where('products.data.0.status', 'active'));
        $this->get(route('product-inventory.show', $product))->assertInertia(fn (Assert $page) => $page->where('readOnly', true)->where('product.status', 'active')->where('product.stockQuantity', 10)->has('history.data', 1)->where('history.data.0.action', 'Created')->where('history.data.0.by', $this->owner->name));
        $this->get(route('product-inventory.edit', $product))->assertInertia(fn (Assert $page) => $page->where('readOnly', false)->where('canManage', true));

        $this->patch(route('product-inventory.update', $product), [...$data, 'status' => 'out_of_stock'])->assertRedirect();
        $this->assertSame('out_of_stock', $product->refresh()->status);
        $this->assertTrue($product->is_active);

        $this->patch(route('product-inventory.update', $product), [...$data, 'status' => 'inactive'])->assertRedirect();
        $this->assertSame('inactive', $product->refresh()->status);
        $this->assertFalse($product->is_active);
        $this->get(route('product-inventory.show', $product))->assertInertia(fn (Assert $page) => $page->has('history.data', 3)->where('history.data.0.action', 'Updated')->where('history.data.0.details.0', 'Status: out_of_stock → inactive'));
    }

    public function test_product_details_remain_tenant_scoped(): void
    {
        $product = new Product(['name' => 'Private product', 'cost_centavos' => 1000, 'price_centavos' => 2000, 'status' => 'active']);
        $product->save();
        $otherGym = Gym::factory()->create();
        $otherOwner = User::factory()->memberOf($otherGym, GymRole::Owner)->create();

        $this->actingAs($otherOwner)->get(route('product-inventory.show', $product))->assertNotFound();
        $this->actingAs($otherOwner)->get(route('product-inventory.edit', $product))->assertNotFound();
    }
}
