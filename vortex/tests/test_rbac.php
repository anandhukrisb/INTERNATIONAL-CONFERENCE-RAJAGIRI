<?php
/**
 * ------------------------------------------------------------
 * test_rbac.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : Automated tests for Role-Based Access Control (RBAC).
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../classes/RBAC.php';

function run_rbac_tests(): bool
{
    TestHelper::printSuiteHeader('RBAC (Role-Based Access Control) Tests');

    // 1. Role Normalization Tests
    TestHelper::assertTrue(
        RBAC::normalizeRole('Super Administrator') === RBAC::ROLE_SUPER_ADMIN,
        'RBAC 1.1: Normalize "Super Administrator" to super_admin'
    );
    TestHelper::assertTrue(
        RBAC::normalizeRole('super_admin') === RBAC::ROLE_SUPER_ADMIN,
        'RBAC 1.2: Normalize "super_admin" to super_admin'
    );
    TestHelper::assertTrue(
        RBAC::normalizeRole('Finance Manager') === RBAC::ROLE_FINANCE,
        'RBAC 1.3: Normalize "Finance Manager" to finance'
    );
    TestHelper::assertTrue(
        RBAC::normalizeRole('finance') === RBAC::ROLE_FINANCE,
        'RBAC 1.4: Normalize "finance" to finance'
    );
    TestHelper::assertTrue(
        RBAC::normalizeRole('Support Specialist') === RBAC::ROLE_SUPPORT,
        'RBAC 1.5: Normalize "Support Specialist" to support'
    );
    TestHelper::assertTrue(
        RBAC::normalizeRole('support') === RBAC::ROLE_SUPPORT,
        'RBAC 1.6: Normalize "support" to support'
    );
    TestHelper::assertTrue(
        RBAC::normalizeRole('Auditor / Read-Only') === RBAC::ROLE_READ_ONLY,
        'RBAC 1.7: Normalize "Auditor / Read-Only" to read_only'
    );
    TestHelper::assertTrue(
        RBAC::normalizeRole('read_only') === RBAC::ROLE_READ_ONLY,
        'RBAC 1.8: Normalize "read_only" to read_only'
    );
    TestHelper::assertTrue(
        RBAC::normalizeRole('unknown_role') === RBAC::ROLE_READ_ONLY,
        'RBAC 1.9: Normalize unknown fallback to read_only'
    );

    // 2. Super Admin Permissions (Unrestricted)
    TestHelper::assertTrue(RBAC::can('payments.view', 'super_admin'), 'RBAC 2.1: Super Admin can view payments');
    TestHelper::assertTrue(RBAC::can('payments.refund', 'super_admin'), 'RBAC 2.2: Super Admin can process refunds');
    TestHelper::assertTrue(RBAC::can('events.create', 'super_admin'), 'RBAC 2.3: Super Admin can create events');
    TestHelper::assertTrue(RBAC::can('clients.create', 'super_admin'), 'RBAC 2.4: Super Admin can create API clients');
    TestHelper::assertTrue(RBAC::can('clients.view_secret', 'super_admin'), 'RBAC 2.5: Super Admin can view API secrets');
    TestHelper::assertTrue(RBAC::can('admins.manage', 'super_admin'), 'RBAC 2.6: Super Admin can manage admin team');

    // 3. Finance Manager Permissions
    TestHelper::assertTrue(RBAC::can('payments.view', 'finance'), 'RBAC 3.1: Finance can view payments');
    TestHelper::assertTrue(RBAC::can('payments.refund', 'finance'), 'RBAC 3.2: Finance can process refunds');
    TestHelper::assertTrue(RBAC::can('payments.sync', 'finance'), 'RBAC 3.3: Finance can sync transactions');
    TestHelper::assertTrue(RBAC::can('payments.export', 'finance'), 'RBAC 3.4: Finance can export financial reports');
    TestHelper::assertTrue(!RBAC::can('events.create', 'finance'), 'RBAC 3.5: Finance CANNOT create events');
    TestHelper::assertTrue(!RBAC::can('clients.create', 'finance'), 'RBAC 3.6: Finance CANNOT create API clients');
    TestHelper::assertTrue(!RBAC::can('clients.view_secret', 'finance'), 'RBAC 3.7: Finance CANNOT view client secrets');
    TestHelper::assertTrue(!RBAC::can('admins.manage', 'finance'), 'RBAC 3.8: Finance CANNOT manage admins');

    // 4. Support Specialist Permissions
    TestHelper::assertTrue(RBAC::can('payments.view', 'support'), 'RBAC 4.1: Support can view payments');
    TestHelper::assertTrue(RBAC::can('events.view', 'support'), 'RBAC 4.2: Support can view events');
    TestHelper::assertTrue(!RBAC::can('payments.refund', 'support'), 'RBAC 4.3: Support CANNOT process refunds');
    TestHelper::assertTrue(!RBAC::can('events.create', 'support'), 'RBAC 4.4: Support CANNOT create events');
    TestHelper::assertTrue(!RBAC::can('clients.create', 'support'), 'RBAC 4.5: Support CANNOT create API clients');
    TestHelper::assertTrue(!RBAC::can('clients.view_secret', 'support'), 'RBAC 4.6: Support CANNOT view client secrets');
    TestHelper::assertTrue(!RBAC::can('admins.manage', 'support'), 'RBAC 4.7: Support CANNOT manage admins');

    // 5. Read-Only Auditor Permissions
    TestHelper::assertTrue(RBAC::can('dashboard.view', 'read_only'), 'RBAC 5.1: Read-Only can view dashboard');
    TestHelper::assertTrue(RBAC::can('payments.view', 'read_only'), 'RBAC 5.2: Read-Only can view payments');
    TestHelper::assertTrue(RBAC::can('events.view', 'read_only'), 'RBAC 5.3: Read-Only can view events');
    TestHelper::assertTrue(RBAC::can('clients.view', 'read_only'), 'RBAC 5.4: Read-Only can view clients');
    TestHelper::assertTrue(!RBAC::can('payments.refund', 'read_only'), 'RBAC 5.5: Read-Only CANNOT process refunds');
    TestHelper::assertTrue(!RBAC::can('events.create', 'read_only'), 'RBAC 5.6: Read-Only CANNOT create events');
    TestHelper::assertTrue(!RBAC::can('events.edit', 'read_only'), 'RBAC 5.7: Read-Only CANNOT edit events');
    TestHelper::assertTrue(!RBAC::can('clients.create', 'read_only'), 'RBAC 5.8: Read-Only CANNOT create API clients');
    TestHelper::assertTrue(!RBAC::can('clients.edit', 'read_only'), 'RBAC 5.9: Read-Only CANNOT edit API clients');
    TestHelper::assertTrue(!RBAC::can('clients.view_secret', 'read_only'), 'RBAC 5.10: Read-Only CANNOT view secrets');

    // 6. Role Badges and Metadata
    $superBadge = RBAC::getRoleBadge('super_admin');
    TestHelper::assertTrue(str_contains($superBadge, 'Super Admin'), 'RBAC 6.1: Super Admin badge contains text');
    $financeBadge = RBAC::getRoleBadge('finance');
    TestHelper::assertTrue(str_contains($financeBadge, 'Finance'), 'RBAC 6.2: Finance badge contains text');
    $supportBadge = RBAC::getRoleBadge('support');
    TestHelper::assertTrue(str_contains($supportBadge, 'Support'), 'RBAC 6.3: Support badge contains text');
    $readOnlyBadge = RBAC::getRoleBadge('read_only');
    TestHelper::assertTrue(str_contains($readOnlyBadge, 'Read Only'), 'RBAC 6.4: Read-Only badge contains text');

    // 7. Admin List & Database Fetch
    $admins = RBAC::getAllAdmins();
    TestHelper::assertTrue(is_array($admins) && count($admins) > 0, 'RBAC 7.1: Fetched admin accounts from database');
    TestHelper::assertTrue(isset($admins[0]['normalized_role']), 'RBAC 7.2: Admin record contains normalized_role');
    TestHelper::assertTrue(isset($admins[0]['badge_html']), 'RBAC 7.3: Admin record contains badge_html');

    return true;
}

// CLI runner
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_rbac_tests();
    $counts = TestHelper::getCounts();
    echo "\n----------------------------------------\n";
    echo "Passed: " . $counts['passed'] . ", Failed: " . $counts['failed'] . "\n";
    echo "----------------------------------------\n";
    exit($counts['failed'] === 0 ? 0 : 1);
}
