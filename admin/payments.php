<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$db = getDB();


/*
|--------------------------------------------------------------------------
| SEARCH / FILTER
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$statusFilter = $_GET['status'] ?? 'all';

$methodFilter = $_GET['method'] ?? 'all';


$allowedStatuses = [
    'all',
    'completed',
    'pending',
    'failed',
    'refunded'
];


if (!in_array(
    $statusFilter,
    $allowedStatuses,
    true
)) {

    $statusFilter = 'all';
}


/*
|--------------------------------------------------------------------------
| PAYMENT METHODS
|--------------------------------------------------------------------------
*/

try {

    $methodStmt = $db->query(
        "SELECT DISTINCT payment_method
         FROM payments
         WHERE payment_method IS NOT NULL
         AND payment_method != ''
         ORDER BY payment_method ASC"
    );

    $paymentMethods = $methodStmt->fetchAll(
        PDO::FETCH_COLUMN
    );

} catch (Throwable $e) {

    error_log(
        'Payment methods error: ' .
        $e->getMessage()
    );

    $paymentMethods = [];
}


/*
|--------------------------------------------------------------------------
| WHERE
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];


if ($search !== '') {

    $where[] =
        '(u.name LIKE :search_name
        OR u.email LIKE :search_email
        OR p.transaction_id LIKE :search_transaction)';

    $params[':search_name'] =
        '%' . $search . '%';

    $params[':search_email'] =
        '%' . $search . '%';

    $params[':search_transaction'] =
        '%' . $search . '%';
}


if ($statusFilter !== 'all') {

    $where[] = 'p.status = :status';

    $params[':status'] =
        $statusFilter;
}


if ($methodFilter !== 'all') {

    $where[] =
        'p.payment_method = :payment_method';

    $params[':payment_method'] =
        $methodFilter;
}


$whereSql = '';

if (!empty($where)) {

    $whereSql =
        'WHERE ' .
        implode(' AND ', $where);
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

try {

    $totalPaymentsStmt = $db->query(
        'SELECT COUNT(*) FROM payments'
    );

    $totalPayments =
        (int) $totalPaymentsStmt->fetchColumn();


    $completedStmt = $db->query(
        "SELECT COUNT(*)
         FROM payments
         WHERE status = 'completed'"
    );

    $completedPayments =
        (int) $completedStmt->fetchColumn();


    $pendingStmt = $db->query(
        "SELECT COUNT(*)
         FROM payments
         WHERE status = 'pending'"
    );

    $pendingPayments =
        (int) $pendingStmt->fetchColumn();


    $failedStmt = $db->query(
        "SELECT COUNT(*)
         FROM payments
         WHERE status = 'failed'"
    );

    $failedPayments =
        (int) $failedStmt->fetchColumn();


    $refundedStmt = $db->query(
        "SELECT COUNT(*)
         FROM payments
         WHERE status = 'refunded'"
    );

    $refundedPayments =
        (int) $refundedStmt->fetchColumn();


    $revenueStmt = $db->query(
        "SELECT COALESCE(SUM(amount), 0)
         FROM payments
         WHERE status = 'completed'"
    );

    $totalRevenue =
        (float) $revenueStmt->fetchColumn();

} catch (Throwable $e) {

    error_log(
        'Payment statistics error: ' .
        $e->getMessage()
    );

    $totalPayments = 0;
    $completedPayments = 0;
    $pendingPayments = 0;
    $failedPayments = 0;
    $refundedPayments = 0;
    $totalRevenue = 0;
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$perPage = 10;

$page = max(
    1,
    (int) ($_GET['page'] ?? 1)
);


/*
|--------------------------------------------------------------------------
| COUNT FILTERED
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)
    FROM payments p
    LEFT JOIN users u ON u.id = p.user_id
    {$whereSql}
";


$countStmt = $db->prepare($countSql);

$countStmt->execute($params);

$filteredPayments =
    (int) $countStmt->fetchColumn();


$totalPages = max(
    1,
    (int) ceil(
        $filteredPayments / $perPage
    )
);


if ($page > $totalPages) {
    $page = $totalPages;
}


$offset = ($page - 1) * $perPage;


/*
|--------------------------------------------------------------------------
| PAYMENT LIST
|--------------------------------------------------------------------------
*/

$limit = (int) $perPage;
$offsetValue = (int) $offset;


$sql = "
    SELECT
        p.id,
        p.user_id,
        p.amount,
        p.payment_method,
        p.transaction_id,
        p.status,
        p.paid_at,
        u.name,
        u.email
    FROM payments p
    LEFT JOIN users u
        ON u.id = p.user_id
    {$whereSql}
    ORDER BY p.id DESC
    LIMIT {$limit} OFFSET {$offsetValue}
";


$stmt = $db->prepare($sql);

$stmt->execute($params);

$payments = $stmt->fetchAll();


require_once __DIR__ . '/../includes/header.php';
?>

<style>

.admin-container {
    max-width: 1250px;
    margin: 40px auto;
    padding: 0 20px;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 20px;
}

.admin-header h1 {
    margin: 0;
}

.admin-nav {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 30px;
}

.admin-nav a {
    padding: 10px 16px;
    border-radius: 8px;
    text-decoration: none;
    background: #222;
    color: #fff;
}

.admin-nav a.active {
    background: #e50914;
}


/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: #151515;
    padding: 20px;
    border-radius: 12px;
}

.stat-icon {
    font-size: 25px;
}

.stat-title {
    color: #aaa;
    font-size: 13px;
    margin-top: 8px;
}

.stat-value {
    font-size: 27px;
    font-weight: bold;
    margin-top: 5px;
}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

.filter-box {
    background: #151515;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.filter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-form input,
.filter-form select {
    padding: 10px;
    border-radius: 7px;
    border: 1px solid #444;
    background: #222;
    color: #fff;
}

.btn {
    display: inline-block;
    padding: 9px 14px;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    text-decoration: none;
}

.btn-primary {
    background: #e50914;
    color: #fff;
}

.btn-secondary {
    background: #555;
    color: #fff;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.payment-table-wrapper {
    overflow-x: auto;
}

.payment-table {
    width: 100%;
    border-collapse: collapse;
    background: #151515;
}

.payment-table th,
.payment-table td {
    padding: 13px;
    border-bottom: 1px solid #333;
    text-align: left;
}

.payment-table th {
    background: #222;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
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

.badge-default {
    background: #374151;
    color: #fff;
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

.pagination {
    display: flex;
    justify-content: center;
    gap: 7px;
    margin-top: 25px;
    flex-wrap: wrap;
}

.pagination a,
.pagination span {
    padding: 9px 13px;
    background: #222;
    color: #fff;
    border-radius: 7px;
    text-decoration: none;
}

.pagination .active {
    background: #e50914;
}


@media (max-width: 800px) {

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .admin-header {
        flex-direction: column;
        align-items: flex-start;
    }

}


@media (max-width: 550px) {

    .stats-grid {
        grid-template-columns: 1fr;
    }

}

</style>


<div class="admin-container">


    <div class="admin-header">

        <div>

            <h1>💳 Payment Management</h1>

            <p>
                Monitor payments and platform revenue.
            </p>

        </div>

        <a
            href="<?= BASE_URL ?>/admin/index.php"
            class="btn btn-secondary"
        >
            ← Dashboard
        </a>

    </div>


    <!-- NAV -->

    <div class="admin-nav">

        <a href="<?= BASE_URL ?>/admin/index.php">
            📊 Dashboard
        </a>

        <a href="<?= BASE_URL ?>/admin/movies.php">
            🎬 Movies
        </a>

        <a href="<?= BASE_URL ?>/admin/users.php">
            👥 Users
        </a>

        <a
            href="<?= BASE_URL ?>/admin/payments.php"
            class="active"
        >
            💳 Payments
        </a>

    </div>


    <!-- STATISTICS -->

    <div class="stats-grid">


        <div class="stat-card">

            <div class="stat-icon">
                💳
            </div>

            <div class="stat-title">
                Total Payments
            </div>

            <div class="stat-value">
                <?= number_format($totalPayments) ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🟢
            </div>

            <div class="stat-title">
                Completed
            </div>

            <div class="stat-value">
                <?= number_format($completedPayments) ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                💰
            </div>

            <div class="stat-title">
                Revenue
            </div>

            <div class="stat-value">
                $<?= number_format($totalRevenue, 2) ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🟡
            </div>

            <div class="stat-title">
                Pending
            </div>

            <div class="stat-value">
                <?= number_format($pendingPayments) ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🔴
            </div>

            <div class="stat-title">
                Failed
            </div>

            <div class="stat-value">
                <?= number_format($failedPayments) ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                ↩️
            </div>

            <div class="stat-title">
                Refunded
            </div>

            <div class="stat-value">
                <?= number_format($refundedPayments) ?>
            </div>

        </div>

    </div>


    <!-- FILTER -->

    <div class="filter-box">

        <h2>🔎 Search & Filter</h2>

        <form method="GET" class="filter-form">


            <input
                type="text"
                name="search"
                placeholder="User, email or transaction ID..."
                value="<?= e($search) ?>"
            >


            <select name="status">

                <option
                    value="all"
                    <?= $statusFilter === 'all'
                        ? 'selected'
                        : '' ?>
                >
                    All Status
                </option>

                <option
                    value="completed"
                    <?= $statusFilter === 'completed'
                        ? 'selected'
                        : '' ?>
                >
                    Completed
                </option>

                <option
                    value="pending"
                    <?= $statusFilter === 'pending'
                        ? 'selected'
                        : '' ?>
                >
                    Pending
                </option>

                <option
                    value="failed"
                    <?= $statusFilter === 'failed'
                        ? 'selected'
                        : '' ?>
                >
                    Failed
                </option>

                <option
                    value="refunded"
                    <?= $statusFilter === 'refunded'
                        ? 'selected'
                        : '' ?>
                >
                    Refunded
                </option>

            </select>


            <select name="method">

                <option
                    value="all"
                    <?= $methodFilter === 'all'
                        ? 'selected'
                        : '' ?>
                >
                    All Methods
                </option>


                <?php foreach ($paymentMethods as $method): ?>

                    <option
                        value="<?= e($method) ?>"
                        <?= $methodFilter === $method
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e($method) ?>
                    </option>

                <?php endforeach; ?>

            </select>


            <button
                type="submit"
                class="btn btn-primary"
            >
                🔎 Search
            </button>


            <a
                href="<?= BASE_URL ?>/admin/payments.php"
                class="btn btn-secondary"
            >
                Reset
            </a>

        </form>

    </div>


    <h2>
        Payments
        <small>
            (<?= number_format($filteredPayments) ?> found)
        </small>
    </h2>


    <!-- TABLE -->

    <div class="payment-table-wrapper">

        <table class="payment-table">

            <thead>

                <tr>

                    <th>ID</th>

                    <th>User</th>

                    <th>Amount</th>

                    <th>Method</th>

                    <th>Transaction</th>

                    <th>Status</th>

                    <th>Date</th>

                </tr>

            </thead>


            <tbody>

            <?php if (empty($payments)): ?>

                <tr>

                    <td colspan="7">
                        No payments found.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($payments as $payment): ?>

                    <tr>

                        <td>
                            #<?= (int) $payment['id'] ?>
                        </td>


                        <td>

                            <strong>
                                <?= e(
                                    $payment['name']
                                    ?? 'Deleted User'
                                ) ?>
                            </strong>

                            <?php if (!empty($payment['email'])): ?>

                                <br>

                                <small>
                                    <?= e($payment['email']) ?>
                                </small>

                            <?php endif; ?>

                        </td>


                        <td>

                            <strong>
                                $<?= number_format(
                                    (float) $payment['amount'],
                                    2
                                ) ?>
                            </strong>

                        </td>


                        <td>
                            <?= e(
                                $payment['payment_method']
                            ) ?>
                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $payment['transaction_id']
                                )
                            ): ?>

                                <code>
                                    <?= e(
                                        $payment[
                                            'transaction_id'
                                        ]
                                    ) ?>
                                </code>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php

                            $status =
                                $payment['status'];

                            $badgeClass = match (
                                $status
                            ) {

                                'completed' =>
                                    'badge-completed',

                                'pending' =>
                                    'badge-pending',

                                'failed' =>
                                    'badge-failed',

                                'refunded' =>
                                    'badge-refunded',

                                default =>
                                    'badge-default'
                            };

                            ?>


                            <span
                                class="badge <?= $badgeClass ?>"
                            >
                                <?= e(
                                    ucfirst($status)
                                ) ?>
                            </span>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $payment['paid_at']
                                )
                            ): ?>

                                <?= e(
                                    date(
                                        'Y-m-d H:i',
                                        strtotime(
                                            $payment['paid_at']
                                        )
                                    )
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- PAGINATION -->

    <?php if ($totalPages > 1): ?>

        <div class="pagination">

            <?php if ($page > 1): ?>

                <a
                    href="?search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&method=<?= urlencode($methodFilter) ?>&page=<?= $page - 1 ?>"
                >
                    ← Previous
                </a>

            <?php endif; ?>


            <?php

            $startPage =
                max(1, $page - 2);

            $endPage =
                min(
                    $totalPages,
                    $page + 2
                );

            ?>


            <?php for (
                $i = $startPage;
                $i <= $endPage;
                $i++
            ): ?>

                <?php if ($i === $page): ?>

                    <span class="active">
                        <?= $i ?>
                    </span>

                <?php else: ?>

                    <a
                        href="?search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&method=<?= urlencode($methodFilter) ?>&page=<?= $i ?>"
                    >
                        <?= $i ?>
                    </a>

                <?php endif; ?>

            <?php endfor; ?>


            <?php if ($page < $totalPages): ?>

                <a
                    href="?search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&method=<?= urlencode($methodFilter) ?>&page=<?= $page + 1 ?>"
                >
                    Next →
                </a>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>