<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$db = getDB();

function adminCount(PDO $db, string $sql): int
{
    try {
        return (int) $db->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        error_log('Admin count error: ' . $e->getMessage());
        return 0;
    }
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalMovies = adminCount(
    $db,
    'SELECT COUNT(*) FROM movies'
);

$premiumMovies = adminCount(
    $db,
    'SELECT COUNT(*) FROM movies WHERE is_premium = 1'
);

$totalUsers = adminCount(
    $db,
    'SELECT COUNT(*) FROM users'
);

$activePremiumUsers = adminCount(
    $db,
    "SELECT COUNT(*)
     FROM users
     WHERE subscription_status = 'active'
     AND subscription_expires_at IS NOT NULL
     AND subscription_expires_at > NOW()"
);

$completedPayments = adminCount(
    $db,
    "SELECT COUNT(*)
     FROM payments
     WHERE status = 'completed'"
);

$pendingPayments = adminCount(
    $db,
    "SELECT COUNT(*)
     FROM payments
     WHERE status = 'pending'"
);


/*
|--------------------------------------------------------------------------
| TOTAL REVENUE
|--------------------------------------------------------------------------
*/

try {

    $stmt = $db->query(
        "SELECT COALESCE(SUM(amount), 0)
         FROM payments
         WHERE status = 'completed'"
    );

    $totalRevenue = (float) $stmt->fetchColumn();

} catch (Throwable $e) {

    error_log('Revenue error: ' . $e->getMessage());

    $totalRevenue = 0;
}


/*
|--------------------------------------------------------------------------
| RECENT PAYMENTS
|--------------------------------------------------------------------------
*/

$recentPayments = [];

try {

    $stmt = $db->query(
        "SELECT
            p.id,
            p.amount,
            p.payment_method,
            p.status,
            p.paid_at,
            u.name,
            u.email
         FROM payments p
         LEFT JOIN users u ON u.id = p.user_id
         ORDER BY p.id DESC
         LIMIT 5"
    );

    $recentPayments = $stmt->fetchAll();

} catch (Throwable $e) {

    error_log('Recent payments error: ' . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| RECENT USERS
|--------------------------------------------------------------------------
*/

$recentUsers = [];

try {

    $stmt = $db->query(
        "SELECT
            id,
            name,
            email,
            role,
            subscription_status,
            created_at
         FROM users
         ORDER BY id DESC
         LIMIT 5"
    );

    $recentUsers = $stmt->fetchAll();

} catch (Throwable $e) {

    error_log('Recent users error: ' . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| RECENT MOVIES
|--------------------------------------------------------------------------
*/

$recentMovies = [];

try {

    $stmt = $db->query(
        "SELECT
            id,
            title,
            release_year,
            rating,
            is_premium,
            created_at
         FROM movies
         ORDER BY id DESC
         LIMIT 5"
    );

    $recentMovies = $stmt->fetchAll();

} catch (Throwable $e) {

    error_log('Recent movies error: ' . $e->getMessage());
}


require_once __DIR__ . '/../includes/header.php';
?>

<style>

.admin-dashboard {
    max-width: 1250px;
    margin: 40px auto;
    padding: 0 20px;
}

.admin-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 20px;
}

.admin-top h1 {
    margin: 0;
}

.admin-top p {
    margin: 6px 0 0;
    opacity: .7;
}

.admin-nav {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 30px;
}

.admin-nav a {
    padding: 11px 17px;
    border-radius: 8px;
    background: #222;
    color: #fff;
    text-decoration: none;
}

.admin-nav a:hover {
    background: #333;
}

.admin-nav a.active {
    background: #e50914;
}


/*
|--------------------------------------------------------------------------
| STAT CARDS
|--------------------------------------------------------------------------
*/

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 35px;
}

.stat-card {
    background: #151515;
    border-radius: 14px;
    padding: 22px;
    border: 1px solid #292929;
}

.stat-icon {
    font-size: 28px;
    margin-bottom: 12px;
}

.stat-title {
    opacity: .7;
    font-size: 14px;
}

.stat-value {
    font-size: 30px;
    font-weight: 700;
    margin-top: 6px;
}

.stat-link {
    display: inline-block;
    margin-top: 12px;
    color: #aaa;
    text-decoration: none;
    font-size: 13px;
}

.stat-link:hover {
    color: #fff;
}


/*
|--------------------------------------------------------------------------
| QUICK ACTIONS
|--------------------------------------------------------------------------
*/

.quick-actions {
    background: #151515;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 30px;
}

.quick-actions h2 {
    margin-top: 0;
}

.quick-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.quick-btn {
    display: block;
    padding: 15px;
    border-radius: 9px;
    background: #222;
    color: #fff;
    text-decoration: none;
    text-align: center;
}

.quick-btn:hover {
    background: #333;
}


/*
|--------------------------------------------------------------------------
| CONTENT GRID
|--------------------------------------------------------------------------
*/

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}

.dashboard-card {
    background: #151515;
    border-radius: 14px;
    overflow: hidden;
}

.dashboard-card-header {
    padding: 18px 20px;
    border-bottom: 1px solid #292929;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dashboard-card-header h2 {
    margin: 0;
    font-size: 19px;
}

.dashboard-card-header a {
    color: #aaa;
    text-decoration: none;
    font-size: 13px;
}

.dashboard-card-header a:hover {
    color: #fff;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-wrapper {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th,
.admin-table td {
    padding: 13px 16px;
    text-align: left;
    border-bottom: 1px solid #292929;
}

.admin-table th {
    font-size: 13px;
    color: #aaa;
}

.admin-table td {
    font-size: 14px;
}

.admin-table tr:last-child td {
    border-bottom: none;
}


/*
|--------------------------------------------------------------------------
| BADGES
|--------------------------------------------------------------------------
*/

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.badge-premium {
    background: #f59e0b;
    color: #111;
}

.badge-free {
    background: #374151;
    color: #fff;
}

.badge-admin {
    background: #7c3aed;
    color: #fff;
}

.badge-user {
    background: #374151;
    color: #fff;
}

.badge-completed {
    background: #16a34a;
    color: #fff;
}

.badge-pending {
    background: #f59e0b;
    color: #111;
}

.badge-failed {
    background: #dc2626;
    color: #fff;
}

.badge-refunded {
    background: #6b7280;
    color: #fff;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 900px) {

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .quick-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .quick-grid {
        grid-template-columns: 1fr;
    }

    .admin-top {
        flex-direction: column;
        align-items: flex-start;
    }

}

</style>


<div class="admin-dashboard">


    <!-- HEADER -->

    <div class="admin-top">

        <div>

            <h1>🛠️ Admin Dashboard</h1>

            <p>
                Manage your movie streaming platform.
            </p>

        </div>

        <a
            href="<?= BASE_URL ?>/index.php"
            class="quick-btn"
        >
            🏠 Website
        </a>

    </div>


    <!-- NAVIGATION -->

    <div class="admin-nav">

        <a
            href="<?= BASE_URL ?>/admin/index.php"
            class="active"
        >
            📊 Dashboard
        </a>

        <a
            href="<?= BASE_URL ?>/admin/movies.php"
        >
            🎬 Movies
        </a>

        <a
            href="<?= BASE_URL ?>/admin/users.php"
        >
            👥 Users
        </a>

        <a
            href="<?= BASE_URL ?>/admin/payments.php"
        >
            💳 Payments
        </a>

    </div>


    <!-- STATISTICS -->

    <div class="stats-grid">


        <div class="stat-card">

            <div class="stat-icon">
                🎬
            </div>

            <div class="stat-title">
                Total Movies
            </div>

            <div class="stat-value">
                <?= number_format($totalMovies) ?>
            </div>

            <a
                href="<?= BASE_URL ?>/admin/movies.php"
                class="stat-link"
            >
                Manage movies →
            </a>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                👑
            </div>

            <div class="stat-title">
                Premium Movies
            </div>

            <div class="stat-value">
                <?= number_format($premiumMovies) ?>
            </div>

            <a
                href="<?= BASE_URL ?>/admin/movies.php?premium=premium"
                class="stat-link"
            >
                View Premium movies →
            </a>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                👥
            </div>

            <div class="stat-title">
                Total Users
            </div>

            <div class="stat-value">
                <?= number_format($totalUsers) ?>
            </div>

            <a
                href="<?= BASE_URL ?>/admin/users.php"
                class="stat-link"
            >
                Manage users →
            </a>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🟢
            </div>

            <div class="stat-title">
                Active Premium Users
            </div>

            <div class="stat-value">
                <?= number_format($activePremiumUsers) ?>
            </div>

            <a
                href="<?= BASE_URL ?>/admin/users.php?subscription=active"
                class="stat-link"
            >
                View Premium users →
            </a>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                💰
            </div>

            <div class="stat-title">
                Completed Revenue
            </div>

            <div class="stat-value">
                $<?= number_format($totalRevenue, 2) ?>
            </div>

            <a
                href="<?= BASE_URL ?>/admin/payments.php"
                class="stat-link"
            >
                View payments →
            </a>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                💳
            </div>

            <div class="stat-title">
                Completed Payments
            </div>

            <div class="stat-value">
                <?= number_format($completedPayments) ?>
            </div>

            <a
                href="<?= BASE_URL ?>/admin/payments.php?status=completed"
                class="stat-link"
            >
                View completed →
            </a>

        </div>

    </div>


    <!-- QUICK ACTIONS -->

    <div class="quick-actions">

        <h2>⚡ Quick Actions</h2>

        <div class="quick-grid">

            <a
                href="<?= BASE_URL ?>/admin/movies.php"
                class="quick-btn"
            >
                ➕ Add / Manage Movies
            </a>

            <a
                href="<?= BASE_URL ?>/admin/users.php"
                class="quick-btn"
            >
                👥 Manage Users
            </a>

            <a
                href="<?= BASE_URL ?>/admin/payments.php"
                class="quick-btn"
            >
                💳 View Payments
            </a>

            <a
                href="<?= BASE_URL ?>/index.php"
                class="quick-btn"
            >
                🌐 Open Website
            </a>

        </div>

    </div>


    <!-- RECENT DATA -->

    <div class="dashboard-grid">


        <!-- RECENT USERS -->

        <div class="dashboard-card">

            <div class="dashboard-card-header">

                <h2>👥 Recent Users</h2>

                <a href="<?= BASE_URL ?>/admin/users.php">
                    View all →
                </a>

            </div>


            <div class="table-wrapper">

                <table class="admin-table">

                    <thead>

                        <tr>

                            <th>User</th>

                            <th>Role</th>

                            <th>Plan</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($recentUsers)): ?>

                        <tr>

                            <td colspan="3">
                                No users found.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($recentUsers as $user): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= e($user['name']) ?>
                                    </strong>

                                    <br>

                                    <small>
                                        <?= e($user['email']) ?>
                                    </small>

                                </td>


                                <td>

                                    <?php if ($user['role'] === 'admin'): ?>

                                        <span class="badge badge-admin">
                                            Admin
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-user">
                                            User
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if ($user['subscription_status'] === 'active'): ?>

                                        <span class="badge badge-premium">
                                            👑 Premium
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-free">
                                            Free
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- RECENT MOVIES -->

        <div class="dashboard-card">

            <div class="dashboard-card-header">

                <h2>🎬 Recent Movies</h2>

                <a href="<?= BASE_URL ?>/admin/movies.php">
                    View all →
                </a>

            </div>


            <div class="table-wrapper">

                <table class="admin-table">

                    <thead>

                        <tr>

                            <th>Movie</th>

                            <th>Year</th>

                            <th>Type</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($recentMovies)): ?>

                        <tr>

                            <td colspan="3">
                                No movies found.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($recentMovies as $movie): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= e($movie['title']) ?>
                                    </strong>

                                    <br>

                                    <small>
                                        ⭐ <?= number_format((float) $movie['rating'], 1) ?>
                                    </small>

                                </td>


                                <td>
                                    <?= (int) $movie['release_year'] ?>
                                </td>


                                <td>

                                    <?php if ((int) $movie['is_premium'] === 1): ?>

                                        <span class="badge badge-premium">
                                            👑 Premium
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-free">
                                            Free
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- RECENT PAYMENTS -->

        <div class="dashboard-card">

            <div class="dashboard-card-header">

                <h2>💳 Recent Payments</h2>

                <a href="<?= BASE_URL ?>/admin/payments.php">
                    View all →
                </a>

            </div>


            <div class="table-wrapper">

                <table class="admin-table">

                    <thead>

                        <tr>

                            <th>User</th>

                            <th>Amount</th>

                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($recentPayments)): ?>

                        <tr>

                            <td colspan="3">
                                No payments found.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($recentPayments as $payment): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= e($payment['name'] ?? 'Deleted User') ?>
                                    </strong>

                                    <br>

                                    <small>
                                        <?= e($payment['payment_method']) ?>
                                    </small>

                                </td>


                                <td>

                                    $<?= number_format(
                                        (float) $payment['amount'],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <?php
                                    $status = $payment['status'];

                                    $badgeClass = match ($status) {
                                        'completed' => 'badge-completed',
                                        'pending' => 'badge-pending',
                                        'failed' => 'badge-failed',
                                        'refunded' => 'badge-refunded',
                                        default => 'badge-free'
                                    };
                                    ?>

                                    <span
                                        class="badge <?= $badgeClass ?>"
                                    >
                                        <?= e(ucfirst($status)) ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- PAYMENT SUMMARY -->

        <div class="dashboard-card">

            <div class="dashboard-card-header">

                <h2>📈 Payment Summary</h2>

                <a href="<?= BASE_URL ?>/admin/payments.php">
                    Manage →
                </a>

            </div>


            <div class="table-wrapper">

                <table class="admin-table">

                    <tbody>

                        <tr>

                            <td>
                                🟢 Completed
                            </td>

                            <td>
                                <strong>
                                    <?= number_format($completedPayments) ?>
                                </strong>
                            </td>

                        </tr>


                        <tr>

                            <td>
                                🟡 Pending
                            </td>

                            <td>
                                <strong>
                                    <?= number_format($pendingPayments) ?>
                                </strong>
                            </td>

                        </tr>


                        <tr>

                            <td>
                                💰 Revenue
                            </td>

                            <td>

                                <strong>
                                    $<?= number_format(
                                        $totalRevenue,
                                        2
                                    ) ?>
                                </strong>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>