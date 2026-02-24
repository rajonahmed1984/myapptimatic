<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicProductsUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_products_index_renders_inertia_when_flag_is_off(): void
    {
        $this->seedCatalog();
        config()->set('features.react_public_products', false);

        $response = $this->get(route('products.public.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Public\\/Products\\/Index', false);
    }

    #[Test]
    public function public_products_index_renders_inertia_when_flag_is_on(): void
    {
        $this->seedCatalog();
        config()->set('features.react_public_products', true);

        $response = $this->get(route('products.public.index'));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Public\\/Products\\/Index', false);
    }

    #[Test]
    public function public_product_show_renders_inertia_when_flag_is_off(): void
    {
        ['product' => $product] = $this->seedCatalog();
        config()->set('features.react_public_products', false);

        $response = $this->get(route('products.public.show', ['product' => $product->slug]));

        $response->assertOk();
        $response->assertSee('data-page=');
        $response->assertSee('Public\\/Products\\/Show', false);
    }

    #[Test]
    public function public_product_show_and_plan_routes_render_inertia_when_flag_is_on(): void
    {
        ['product' => $product, 'plan' => $plan] = $this->seedCatalog();
        config()->set('features.react_public_products', true);

        $showResponse = $this->get(route('products.public.show', ['product' => $product->slug]));
        $showResponse->assertOk();
        $showResponse->assertSee('data-page=');
        $showResponse->assertSee('Public\\/Products\\/Show', false);

        $planResponse = $this->get(route('products.public.plan', [
            'product' => $product->slug,
            'plan' => $plan->slug,
        ]));
        $planResponse->assertOk();
        $planResponse->assertSee('data-page=');
        $planResponse->assertSee('Public\\/Products\\/Show', false);
    }

    #[Test]
    public function public_product_plan_route_keeps_404_contract_for_mismatched_product_and_plan(): void
    {
        ['product' => $product, 'otherProduct' => $otherProduct, 'plan' => $plan] = $this->seedCatalog();

        config()->set('features.react_public_products', false);
        $this->get(route('products.public.plan', [
            'product' => $otherProduct->slug,
            'plan' => $plan->slug,
        ]))->assertNotFound();

        config()->set('features.react_public_products', true);
        $this->get(route('products.public.plan', [
            'product' => $otherProduct->slug,
            'plan' => $plan->slug,
        ]))->assertNotFound();
    }

    /**
     * @return array{product: Product, otherProduct: Product, plan: Plan}
     */
    private function seedCatalog(): array
    {
        $product = Product::create([
            'name' => 'Starter Hosting',
            'slug' => 'starter-hosting',
            'description' => 'Entry level plan',
            'status' => 'active',
        ]);

        $otherProduct = Product::create([
            'name' => 'Business Hosting',
            'slug' => 'business-hosting',
            'description' => 'Business plan',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Starter Monthly',
            'slug' => 'starter-monthly',
            'interval' => 'monthly',
            'price' => 12.50,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        Plan::create([
            'product_id' => $otherProduct->id,
            'name' => 'Business Monthly',
            'slug' => 'business-monthly',
            'interval' => 'monthly',
            'price' => 22.50,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        return [
            'product' => $product,
            'otherProduct' => $otherProduct,
            'plan' => $plan,
        ];
    }
}
