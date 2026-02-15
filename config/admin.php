<?php

use App\Enums\Role;

$configuredPanelRoles = env('ADMIN_PANEL_ROLES');
$panelRoles = Role::adminRoles();

if (is_string($configuredPanelRoles) && trim($configuredPanelRoles) !== '') {
    $parsed = array_values(array_filter(array_map('trim', explode(',', $configuredPanelRoles))));
    if ($parsed !== []) {
        $panelRoles = $parsed;
    }
}

return [
    'panel_roles' => $panelRoles,
];
