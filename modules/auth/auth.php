<?php
// auth.php — SECURE ROLE/PERMISSION HANDLER

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';

function current_user()
{
    if (!isset($_SESSION['user_id'])) {
        return null; // user not logged in
    }

    return [
        'id'    => $_SESSION['user_id'],
        'role'  => $_SESSION['role'] ?? 'guest',   // admin | subadmin | DAA | inst_admin | end_user
        'name'  => $_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'Unknown')
    ];
}

function has_permission($perm): bool
{
    $user = current_user();
    if (!$user) return false;

    $role = $user['role'];

    // Standardized role keys to match case-sensitivity safely
    $permissions = [

        // ADMIN – Supply Officer / SPMU In-Charge (Full Inventory & Controls)
        'admin' => [
            'users.manage',
            'roles.manage',
            'permissions.manage',
            'inventory.full',
            'inventory.view',
            'inventory.add',
            'inventory.edit',
            'inventory.delete',
            'suppliers.manage',
            'reports.view',
            'reports.export',
            'requests.manage',
            'disposed.view',
            'procurement.view',
            'procurement.send',
            'procurement.update',
            'procurement.history',
            'system.settings'
        ],

        // DAA – Director of Academic Affairs (Approver for Academic Depts)
        'DAA' => [
            'inventory.view',     // View all stocks
            'reports.view',       // View expiring & expired items reports
            'disposed.view',      // View disposed items log
            'requests.manage'     // Confirm or reject academic request orders (RIS)
        ],

        // INST_ADMIN – Campus Administrative Director (Approver for Non-Academic Depts)
        'inst_admin' => [
            'inventory.view',     // View campus wide stocks
            'reports.view',       // View operational reports
            'disposed.view',      // View general logs
            'requests.manage'     // Confirm or reject non-academic request orders (RIS)
        ],

        // SUBADMIN – Assistant Supply Officer (No delete, no user management)
        'subadmin' => [
            'inventory.view',
            'inventory.add',
            'inventory.update',
            'reports.view',
            'suppliers.view',
            'archive_all.view'
        ],

        // END USER – only view own department inventory, request, maybe simple updates
        'end_user' => [
            'inventory.view',
            'inventory.request',
        ],

        // PROCUREMENT – only procurement module
        'procurement' => [
            'procurement.view',
            'procurement.update',
            'procurement.history'
        ],

        'guest' => []
    ];

    return in_array($perm, $permissions[$role] ?? [], true);
}

function require_permission($perm): void
{
    if (!has_permission($perm)) {
        http_response_code(403);
        exit("⛔ Forbidden: You do not have permission: " . htmlspecialchars($perm));
    }
}

function allow_roles(array $roles): void
{
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        header("Location: /SUPPLY_OFFICE_first/modules/auth/index.php?error=Unauthorized");
        exit();
    }
}

function is_role(string $role): bool
{
    $u = current_user();
    return $u && $u['role'] === $role;
}