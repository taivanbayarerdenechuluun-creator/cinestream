<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$db = getDB();

$message = '';
$error = '';

$currentUser = getCurrentUser();


/*
|--------------------------------------------------------------------------
| UPDATE USER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $error = 'Invalid security token.';

    } else {

        $userId = (int) ($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? 'user';
        $subscriptionStatus = $_POST['subscription_status'] ?? 'none';

        $allowedRoles = ['user', 'admin'];

        $allowedSubscriptions = [
            'none',
            'active',
            'expired',
            'cancelled'
        ];

        if ($userId <= 0) {

            $error = 'Invalid user ID.';

        } elseif (!in_array($role, $allowedRoles, true)) {

            $error = 'Invalid role.';

        } elseif (!in_array($subscriptionStatus, $allowedSubscriptions, true)) {

            $error = 'Invalid subscription status.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Protect own admin account
                |--------------------------------------------------------------------------
                */

                if (
                    $currentUser &&
                    $userId === (int) $currentUser['id'] &&
                    $role !== 'admin'
                ) {

                    $error = 'You cannot remove your own admin role.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Premium expiry
                    |--------------------------------------------------------------------------
                    */

                    if ($subscriptionStatus === 'active') {

                        $stmt = $db->prepare(
                            'SELECT subscription_expires_at
                             FROM users
                             WHERE id = :id
                             LIMIT 1'
                        );

                        $stmt->execute([
                            ':id' => $userId
                        ]);

                        $existingExpiry = $stmt->fetchColumn();

                        if (
                            $existingExpiry &&
                            strtotime((string) $existingExpiry) > time()
                        ) {

                            $expiry = $existingExpiry;

                        } else {

                            $expiry = date(
                                'Y-m-d H:i:s',
                                strtotime('+30 days')
                            );
                        }

                    } else {

                        $expiry = null;
                    }


                    $stmt = $db->prepare(
                        'UPDATE users
                         SET
                            role = :role,
                            subscription_status = :subscription_status,
                            subscription_expires_at = :expires_at
                         WHERE id = :id'
                    );

                    $stmt->execute([
                        ':role' => $role,
                        ':subscription_status' => $subscriptionStatus,
                        ':expires_at' => $expiry,
                        ':id' => $userId
                    ]);

                    $message = 'User updated successfully.';
                }

            } catch (Throwable $e) {

                error_log('Admin user update error: ' . $e->getMessage());

                $error = 'Failed to update user.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| DELETE USER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $error = 'Invalid security token.';

    } else {

        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($userId <= 0) {

            $error = 'Invalid user ID.';

        } elseif (
            $currentUser &&
            $userId === (int) $currentUser['id']
        ) {

            $error = 'You cannot delete your own account.';

        } else {

            try {

                $db->beginTransaction();

                $tables = [
                    'watchlist',
                    'ratings',
                    'comments',
                    'watch_history',
                    'payments'
                ];

                foreach ($tables as $table) {

                    $stmt = $db->prepare(
                        "DELETE FROM {$table}
                         WHERE user_id = :user_id"
                    );

                    $stmt->execute([
                        ':user_id' => $userId
                    ]);
                }


                $stmt = $db->prepare(
                    'DELETE FROM users
                     WHERE id = :id'
                );

                $stmt->execute([
                    ':id' => $userId
                ]);


                if ($stmt->rowCount() === 0) {

                    throw new RuntimeException(
                        'User not found.'
                    );
                }


                $db->commit();

                $message = 'User deleted successfully.';

            } catch (Throwable $e) {

                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                error_log(
                    'Admin user delete error: ' .
                    $e->getMessage()
                );

                $error = 'Failed to delete user.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| SEARCH / FILTER
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$roleFilter = $_GET['role'] ?? 'all';

$subscriptionFilter = $_GET['subscription'] ?? 'all';


if (!in_array(
    $roleFilter,
    ['all', 'user', 'admin'],
    true
)) {

    $roleFilter = 'all';
}


if (!in_array(
    $subscriptionFilter,
    [
        'all',
        'none',
        'active',
        'expired',
        'cancelled'
    ],
    true
)) {

    $subscriptionFilter = 'all';
}


$where = [];

$params = [];


if ($search !== '') {

    $where[] =
        '(name LIKE :search_name
        OR email LIKE :search_email)';

    $params[':search_name'] =
        '%' . $search . '%';

    $params[':search_email'] =
        '%' . $search . '%';
}


if ($roleFilter !== 'all') {

    $where[] = 'role = :role';

    $params[':role'] = $roleFilter;
}


if ($subscriptionFilter !== 'all') {

    $where[] =
        'subscription_status = :subscription_status';

    $params[':subscription_status'] =
        $subscriptionFilter;
}


$whereSql = '';

if (!empty($where)) {

    $whereSql =
        'WHERE ' .
        implode(' AND ', $where);
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
| COUNT
|--------------------------------------------------------------------------
*/

$countStmt = $db->prepare(
    "SELECT COUNT(*)
     FROM users
     {$whereSql}"
);

$countStmt->execute($params);

$totalUsers = (int) $countStmt->fetchColumn();

$totalPages = max(
    1,
    (int) ceil($totalUsers / $perPage)
);


if ($page > $totalPages) {
    $page = $totalPages;
}


$offset = ($page - 1) * $perPage;


/*
|--------------------------------------------------------------------------
| USERS
|--------------------------------------------------------------------------
*/

$limit = (int) $perPage;
$offsetValue = (int) $offset;


$sql = "SELECT
            id,
            name,
            email,
            role,
            subscription_status,
            subscription_expires_at,
            created_at
        FROM users
        {$whereSql}
        ORDER BY id DESC
        LIMIT {$limit} OFFSET {$offsetValue}";


$stmt = $db->prepare($sql);

$stmt->execute($params);

$users = $stmt->fetchAll();


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
    gap: 20px;
    margin-bottom: 25px;
}

.admin-header h1 {
    margin: 0;
}

.admin-nav {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 25px;
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

.alert {
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
}

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
    padding: 9px 13px;
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

.btn-danger {
    background: #dc2626;
    color: #fff;
}

.user-table-wrapper {
    overflow-x: auto;
}

.user-table {
    width: 100%;
    border-collapse: collapse;
    background: #151515;
}

.user-table th,
.user-table td {
    padding: 13px;
    border-bottom: 1px solid #333;
    text-align: left;
}

.user-table th {
    background: #222;
}

.user-table select {
    padding: 7px;
    background: #222;
    color: #fff;
    border: 1px solid #444;
    border-radius: 6px;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.badge-admin {
    background: #7c3aed;
    color: #fff;
}

.badge-user {
    background: #374151;
    color: #fff;
}

.badge-premium {
    background: #f59e0b;
    color: #111;
}

.badge-free {
    background: #374151;
    color: #fff;
}

.badge-expired {
    background: #dc2626;
    color: #fff;
}

.badge-cancelled {
    background: #6b7280;
    color: #fff;
}

.you {
    color: #22c55e;
    font-weight: bold;
}

.actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 7px;
    margin-top: 25px;
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

    .admin-header {
        flex-direction: column;
        align-items: flex-start;
    }

}

</style>


<div class="admin-container">

    <div class="admin-header">

        <div>

            <h1>👥 User Management</h1>

            <p>
                Manage accounts, roles and Premium subscriptions.
            </p>

        </div>

        <a
            href="<?= BASE_URL ?>/admin/index.php"
            class="btn btn-secondary"
        >
            ← Dashboard
        </a>

    </div>


    <div class="admin-nav">

        <a href="<?= BASE_URL ?>/admin/index.php">
            📊 Dashboard
        </a>

        <a href="<?= BASE_URL ?>/admin/movies.php">
            🎬 Movies
        </a>

        <a
            href="<?= BASE_URL ?>/admin/users.php"
            class="active"
        >
            👥 Users
        </a>

        <a href="<?= BASE_URL ?>/admin/payments.php">
            💳 Payments
        </a>

    </div>


    <?php if ($message): ?>

        <div class="alert alert-success">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <div class="filter-box">

        <h2>🔎 Search & Filter</h2>

        <form method="GET" class="filter-form">

            <input
                type="text"
                name="search"
                placeholder="Name or email..."
                value="<?= e($search) ?>"
            >


            <select name="role">

                <option
                    value="all"
                    <?= $roleFilter === 'all' ? 'selected' : '' ?>
                >
                    All Roles
                </option>

                <option
                    value="user"
                    <?= $roleFilter === 'user' ? 'selected' : '' ?>
                >
                    Users
                </option>

                <option
                    value="admin"
                    <?= $roleFilter === 'admin' ? 'selected' : '' ?>
                >
                    Admins
                </option>

            </select>


            <select name="subscription">

                <option
                    value="all"
                    <?= $subscriptionFilter === 'all'
                        ? 'selected'
                        : '' ?>
                >
                    All Subscriptions
                </option>

                <option
                    value="active"
                    <?= $subscriptionFilter === 'active'
                        ? 'selected'
                        : '' ?>
                >
                    Premium Active
                </option>

                <option
                    value="none"
                    <?= $subscriptionFilter === 'none'
                        ? 'selected'
                        : '' ?>
                >
                    Free
                </option>

                <option
                    value="expired"
                    <?= $subscriptionFilter === 'expired'
                        ? 'selected'
                        : '' ?>
                >
                    Expired
                </option>

                <option
                    value="cancelled"
                    <?= $subscriptionFilter === 'cancelled'
                        ? 'selected'
                        : '' ?>
                >
                    Cancelled
                </option>

            </select>


            <button
                type="submit"
                class="btn btn-primary"
            >
                🔎 Search
            </button>


            <a
                href="<?= BASE_URL ?>/admin/users.php"
                class="btn btn-secondary"
            >
                Reset
            </a>

        </form>

    </div>


    <h2>
        Users
        <small>
            (<?= number_format($totalUsers) ?> found)
        </small>
    </h2>


    <div class="user-table-wrapper">

        <table class="user-table">

            <thead>

                <tr>

                    <th>ID</th>

                    <th>User</th>

                    <th>Role</th>

                    <th>Subscription</th>

                    <th>Expires</th>

                    <th>Joined</th>

                    <th>Actions</th>

                </tr>

            </thead>


            <tbody>

            <?php if (empty($users)): ?>

                <tr>

                    <td colspan="7">
                        No users found.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($users as $user): ?>

                    <tr>

                        <td>
                            #<?= (int) $user['id'] ?>
                        </td>


                        <td>

                            <strong>
                                <?= e($user['name']) ?>
                            </strong>

                            <?php if (
                                $currentUser &&
                                (int) $user['id'] ===
                                (int) $currentUser['id']
                            ): ?>

                                <span class="you">
                                    YOU
                                </span>

                            <?php endif; ?>

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

                            <?php
                            $subStatus =
                                $user['subscription_status'];
                            ?>

                            <?php if ($subStatus === 'active'): ?>

                                <span class="badge badge-premium">
                                    👑 Active
                                </span>

                            <?php elseif ($subStatus === 'expired'): ?>

                                <span class="badge badge-expired">
                                    Expired
                                </span>

                            <?php elseif ($subStatus === 'cancelled'): ?>

                                <span class="badge badge-cancelled">
                                    Cancelled
                                </span>

                            <?php else: ?>

                                <span class="badge badge-free">
                                    Free
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                !empty(
                                    $user['subscription_expires_at']
                                )
                            ): ?>

                                <?= e(
                                    date(
                                        'Y-m-d H:i',
                                        strtotime(
                                            $user[
                                                'subscription_expires_at'
                                            ]
                                        )
                                    )
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= e(
                                date(
                                    'Y-m-d',
                                    strtotime(
                                        $user['created_at']
                                    )
                                )
                            ) ?>

                        </td>


                        <td>

                            <div class="actions">

                                <form method="POST">

                                    <?= csrfField() ?>

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int) $user['id'] ?>"
                                    >


                                    <select name="role">

                                        <option
                                            value="user"
                                            <?= $user['role'] === 'user'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            User
                                        </option>

                                        <option
                                            value="admin"
                                            <?= $user['role'] === 'admin'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Admin
                                        </option>

                                    </select>


                                    <select
                                        name="subscription_status"
                                    >

                                        <option
                                            value="none"
                                            <?= $subStatus === 'none'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Free
                                        </option>

                                        <option
                                            value="active"
                                            <?= $subStatus === 'active'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Premium
                                        </option>

                                        <option
                                            value="expired"
                                            <?= $subStatus === 'expired'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Expired
                                        </option>

                                        <option
                                            value="cancelled"
                                            <?= $subStatus === 'cancelled'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Cancelled
                                        </option>

                                    </select>


                                    <button
                                        type="submit"
                                        name="update_user"
                                        class="btn btn-primary"
                                    >
                                        Save
                                    </button>

                                </form>


                                <?php if (
                                    !$currentUser ||
                                    (int) $user['id'] !==
                                    (int) $currentUser['id']
                                ): ?>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Delete this user and all related data?');"
                                    >

                                        <?= csrfField() ?>

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int) $user['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_user"
                                            class="btn btn-danger"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>


    <?php if ($totalPages > 1): ?>

        <div class="pagination">

            <?php if ($page > 1): ?>

                <a
                    href="?search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&subscription=<?= urlencode($subscriptionFilter) ?>&page=<?= $page - 1 ?>"
                >
                    ← Previous
                </a>

            <?php endif; ?>


            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
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
                        href="?search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&subscription=<?= urlencode($subscriptionFilter) ?>&page=<?= $i ?>"
                    >
                        <?= $i ?>
                    </a>

                <?php endif; ?>

            <?php endfor; ?>


            <?php if ($page < $totalPages): ?>

                <a
                    href="?search=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&subscription=<?= urlencode($subscriptionFilter) ?>&page=<?= $page + 1 ?>"
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