<?php

namespace Tests\Feature;

use App\Http\Middleware\ConvertAdminViewToInertia;
use App\Http\Middleware\HandleInertiaRequests;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhaseBAdminRouteMiddlewareTest extends TestCase
{
    #[Test]
    public function phase_b_admin_page_routes_do_not_use_convert_wrapper(): void
    {
        $routeNames = [
            'admin.dashboard',
            'admin.customers.show',
            'admin.hr.employees.show',
            'admin.invoices.client-view',
        ];

        foreach ($routeNames as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route [{$routeName}] is not registered.");

            $middleware = $route->gatherMiddleware();

            $this->assertContains(HandleInertiaRequests::class, $middleware, "Route [{$routeName}] should keep Inertia middleware.");
            $this->assertNotContains(ConvertAdminViewToInertia::class, $middleware, "Route [{$routeName}] should not use ConvertAdminViewToInertia.");
        }
    }
}
