<?php

namespace App\Traits;

trait BreadcrumbTrait
{
    /**
     * Get breadcrumb items for admin pages
     */
    protected function getAdminBreadcrumbs(string $currentPage, ?string $currentRoute = null): array
    {
        return [
            ['label' => 'Home', 'route' => route('home')],
            ['label' => 'Admin Dashboard', 'route' => route('admin.dashboard')],
            ['label' => $currentPage],
        ];
    }

    /**
     * Get breadcrumb items for dashboard pages
     */
    protected function getDashboardBreadcrumbs(string $role): array
    {
        $roleLabel = ucfirst($role);
        $routeName = match($role) {
            'admin' => 'admin.dashboard',
            'teacher' => 'teacher.dashboard',
            'student' => 'student.dashboard',
            default => 'home',
        };

        return [
            ['label' => 'Home', 'route' => route('home')],
            ['label' => "$roleLabel Dashboard", 'route' => route($routeName)],
        ];
    }

    /**
     * Get breadcrumb items with custom path
     */
    protected function getCustomBreadcrumbs(array $items): array
    {
        // Ensure Home is first if not present
        if (empty($items) || $items[0]['label'] !== 'Home') {
            array_unshift($items, ['label' => 'Home', 'route' => route('home')]);
        }
        return $items;
    }
}
