<?php

namespace Tests\Feature;

use App\Http\Middleware\ConvertAdminViewToInertia;
use App\Http\Middleware\HandleInertiaRequests;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhaseCTaskDetailRouteMiddlewareTest extends TestCase
{
    #[Test]
    public function task_detail_routes_are_inertia_routes_without_convert_wrapper(): void
    {
        $routeNames = [
            'admin.projects.tasks.show',
            'client.projects.tasks.show',
            'employee.projects.tasks.show',
            'rep.projects.tasks.show',
        ];

        foreach ($routeNames as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route [{$routeName}] is not registered.");

            $middleware = $route->gatherMiddleware();

            $this->assertContains(HandleInertiaRequests::class, $middleware, "Route [{$routeName}] should use Inertia middleware.");
            $this->assertNotContains(ConvertAdminViewToInertia::class, $middleware, "Route [{$routeName}] must not use ConvertAdminViewToInertia.");
        }
    }
}
