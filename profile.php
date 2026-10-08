<?php 
declare(strict_types=1); 
 
require_once __DIR__ . '/includes/auth.php'; 
 
requireLogin(); 
 
$currentUser = getCurrentUser(true); 
 
if (!$currentUser) { 
    redirect('/login.php'); 
} 
 
$pageTitle = 'My Profile'; 
 
$db = getDB(); 
 
/* 
|--------------------------------------------------------------------------
| User Statistics 
|--------------------------------------------------------------------------
*/ 
 
$watchedCount = 0; 
$watchlistCount = 0; 
$ratingCount = 0; 
$watchlistMovies = []; 
$paymentHistory = [];
 
 
/* 
|--------------------------------------------------------------------------
| Watched Count 
|--------------------------------------------------------------------------
*/ 
 
try { 
 
    $stmt = $db->prepare( 
        'SELECT COUNT(*) 
         FROM watch_history 
         WHERE user_id = :user_id' 
    ); 
 
    $stmt->execute([ 
        ':user_id' => (int) $currentUser['id'] 
    ]); 
 
    $watchedCount = (int) $stmt->fetchColumn(); 
 
} catch (PDOException $e) { 
 
    error_log('Watch history count error: ' . $e->getMessage()); 
 
} 
/*
|--------------------------------------------------------------------------
| Payment History
|--------------------------------------------------------------------------
*/

try {

    $stmt = $db->prepare(
        'SELECT
            id,
            amount,
            payment_method,
            transaction_id,
            status,
            paid_at
         FROM payments
         WHERE user_id = :user_id
         ORDER BY paid_at DESC'
    );

    $stmt->execute([
        ':user_id' => (int) $currentUser['id']
    ]);

    $paymentHistory = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log('Payment history error: ' . $e->getMessage());

}
 
 
/* 
|--------------------------------------------------------------------------
| Watchlist Count 
|--------------------------------------------------------------------------
*/ 
 
try { 
 
    $stmt = $db->prepare( 
        'SELECT COUNT(*) 
         FROM watchlist 
         WHERE user_id = :user_id' 
    ); 
 
    $stmt->execute([ 
        ':user_id' => (int) $currentUser['id'] 
    ]); 
 
    $watchlistCount = (int) $stmt->fetchColumn(); 
 
} catch (PDOException $e) { 
 
    error_log('Watchlist count error: ' . $e->getMessage()); 
 
} 
 
 
/* 
|--------------------------------------------------------------------------
| Ratings Count 
|--------------------------------------------------------------------------
*/ 
 
try { 
 
    $stmt = $db->prepare( 
        'SELECT COUNT(*) 
         FROM ratings 
         WHERE user_id = :user_id' 
    ); 
 
    $stmt->execute([ 
        ':user_id' => (int) $currentUser['id'] 
    ]); 
 
    $ratingCount = (int) $stmt->fetchColumn(); 
 
} catch (PDOException $e) { 
 
    error_log('Ratings count error: ' . $e->getMessage()); 
 
} 
 
 
/* 
|--------------------------------------------------------------------------
| My Watchlist Movies 
|--------------------------------------------------------------------------
*/ 
 
try { 
 
    $stmt = $db->prepare( 
        'SELECT 
            m.id, 
            m.title, 
            m.release_year, 
            m.duration, 
            m.poster, 
            m.rating, 
            m.is_premium 
         FROM watchlist w 
         INNER JOIN movies m 
            ON m.id = w.movie_id 
         WHERE w.user_id = :user_id 
         ORDER BY w.created_at DESC' 
    ); 
 
    $stmt->execute([ 
        ':user_id' => (int) $currentUser['id'] 
    ]); 
 
    $watchlistMovies = $stmt->fetchAll(); 
 
} catch (PDOException $e) { 
 
    error_log('Watchlist movies error: ' . $e->getMessage()); 
 
} 
 
 
require_once __DIR__ . '/includes/header.php'; 
?> 
 
 
<section class="section"> 
 
    <div class="container"> 
 
 
        <!-- ===================================================== 
             PROFILE HEADER 
        ====================================================== --> 
 
        <div 
            style=" 
                background:linear-gradient( 
                    135deg, 
                    #171717, 
                    #101010 
                ); 
                border:1px solid #292929; 
                border-radius:20px; 
                padding:35px; 
                margin-bottom:30px; 
            " 
        > 
 
            <div 
                style=" 
                    display:flex; 
                    align-items:center; 
                    gap:25px; 
                    flex-wrap:wrap; 
                " 
            > 
 
                <!-- Avatar --> 
 
                <div 
                    style=" 
                        width:90px; 
                        height:90px; 
                        border-radius:50%; 
                        background:linear-gradient( 
                            135deg, 
                            #e50914, 
                            #ff4d4d 
                        ); 
                        display:flex; 
                        align-items:center; 
                        justify-content:center; 
                        font-size:36px; 
                        font-weight:800; 
                        color:white; 
                    " 
                > 
                    <?= e(strtoupper(substr($currentUser['name'], 0, 1))) ?> 
                </div> 
 
 
                <!-- User --> 
 
                <div> 
 
                    <h1 
                        style=" 
                            margin:0 0 8px; 
                            font-size:32px; 
                        " 
                    > 
                        <?= e($currentUser['name']) ?> 
                    </h1> 
 
                    <p 
                        style=" 
                            margin:0; 
                            color:#999; 
                        " 
                    > 
                        <?= e($currentUser['email']) ?> 
                    </p> 
 
                </div> 
 
            </div> 
 
        </div> 
 
 
        <!-- ===================================================== 
             ACCOUNT / SUBSCRIPTION 
        ====================================================== --> 
 
        <div 
            style=" 
                display:grid; 
                grid-template-columns: 
                    repeat(auto-fit, minmax(260px, 1fr)); 
                gap:20px; 
                margin-bottom:30px; 
            " 
        > 
 
            <!-- Account --> 
 
            <div 
                style=" 
                    background:#151515; 
                    border:1px solid #292929; 
                    border-radius:16px; 
                    padding:25px; 
                " 
            > 
 
                <div 
                    style=" 
                        color:#888; 
                        font-size:13px; 
                        margin-bottom:8px; 
                    " 
                > 
                    ACCOUNT TYPE 
                </div> 
 
                <div 
                    style=" 
                        font-size:24px; 
                        font-weight:700; 
                    " 
                > 
                    <?= e(ucfirst($currentUser['role'])) ?> 
                </div> 
 
            </div> 
 
 
            <!-- Subscription --> 
 
            <div 
                style=" 
                    background:#151515; 
                    border:1px solid #292929; 
                    border-radius:16px; 
                    padding:25px; 
                " 
            > 
 
                <div 
                    style=" 
                        color:#888; 
                        font-size:13px; 
                        margin-bottom:8px; 
                    " 
                > 
                    SUBSCRIPTION 
                </div> 
 
                <div 
    style=" 
        font-size:24px; 
        font-weight:700; 
    " 
> 
    <?php if (
        $currentUser['subscription_status'] === 'active'
        && !empty($currentUser['subscription_expires_at'])
        && strtotime($currentUser['subscription_expires_at']) > time()
    ): ?>

        <span style="color:#f5c518;">
            👑 Premium Active
        </span>

    <?php else: ?>

        <span style="color:#aaa;">
            Free
        </span>

    <?php endif; ?>
</div>


<?php if (
    $currentUser['subscription_status'] === 'active'
    && !empty($currentUser['subscription_expires_at'])
    && strtotime($currentUser['subscription_expires_at']) > time()
): ?>

    <div
        style="
            margin-top:10px;
            color:#aaa;
            font-size:14px;
        "
    >
        Expires:
        <strong style="color:#fff;">
            <?= date(
                'F j, Y',
                strtotime($currentUser['subscription_expires_at'])
            ) ?>
        </strong>
    </div>

<?php else: ?>

    <a 
        href="<?= BASE_URL ?>/subscription.php" 
        style="
            display:inline-block;
            margin-top:12px;
            color:#e50914;
            text-decoration:none;
            font-weight:600;
        "
    >
        👑 Upgrade to Premium →
    </a>

<?php endif; ?>
 
            </div> 
 
        </div> 
 
 
        <!-- ===================================================== 
             STATISTICS 
        ====================================================== --> 
 
        <h2 class="section-title"> 
            Your Activity 
        </h2> 
 
 
        <div 
            style=" 
                display:grid; 
                grid-template-columns: 
                    repeat(auto-fit, minmax(180px, 1fr)); 
                gap:16px; 
                margin-top:20px; 
                margin-bottom:40px; 
            " 
        > 
 
            <!-- Watched --> 
 
            <a
                href="<?= BASE_URL ?>/history.php"
                style="
                    text-decoration:none;
                    color:inherit;
                    background:#151515;
                    border:1px solid #292929;
                    border-radius:16px;
                    padding:25px;
                    text-align:center;
                    display:block;
                "
            > 
 
                <div style="font-size:32px;"> 
                    🎬 
                </div> 
 
                <div 
                    style=" 
                        font-size:30px; 
                        font-weight:800; 
                        margin-top:8px; 
                    " 
                > 
                    <?= $watchedCount ?> 
                </div> 
 
                <div style="color:#888;"> 
                    Movies Watched 
                </div> 
 
            </a> 
 
 
            <!-- Watchlist --> 
 
            <div 
                style=" 
                    background:#151515; 
                    border:1px solid #292929; 
                    border-radius:16px; 
                    padding:25px; 
                    text-align:center; 
                " 
            > 
 
                <div style="font-size:32px;"> 
                    ❤️ 
                </div> 
 
                <div 
                    style=" 
                        font-size:30px; 
                        font-weight:800; 
                        margin-top:8px; 
                    " 
                > 
                    <?= $watchlistCount ?> 
                </div> 
 
                <div style="color:#888;"> 
                    Watchlist 
                </div> 
 
            </div> 
 
 
            <!-- Ratings --> 
 
            <div 
                style=" 
                    background:#151515; 
                    border:1px solid #292929; 
                    border-radius:16px; 
                    padding:25px; 
                    text-align:center; 
                " 
            > 
 
                <div style="font-size:32px;"> 
                    ⭐ 
                </div> 
 
                <div 
                    style=" 
                        font-size:30px; 
                        font-weight:800; 
                        margin-top:8px; 
                    " 
                > 
                    <?= $ratingCount ?> 
                </div> 
 
                <div style="color:#888;"> 
                    Ratings Given 
                </div> 
 
            </div> 
 
        </div> 
 
 
        <!-- ===================================================== 
             MY WATCHLIST 
        ====================================================== --> 
 
        <div style="margin-bottom:50px;"> 
 
            <div 
                style=" 
                    display:flex; 
                    align-items:center; 
                    justify-content:space-between; 
                    gap:15px; 
                    margin-bottom:20px; 
                    flex-wrap:wrap; 
                " 
            > 
 
                <h2 class="section-title" style="margin:0;"> 
                    My Watchlist 
                </h2> 
 
                <span style="color:#777;"> 
                    <?= $watchlistCount ?> saved 
                </span> 
 
            </div> 
 
 
            <?php if (!empty($watchlistMovies)): ?> 
 
                <div class="movie-grid"> 
 
                    <?php foreach ($watchlistMovies as $movie): ?> 
 
                        <article class="movie-card"> 
 
                            <a 
                                href="<?= BASE_URL ?>/movie.php?id=<?= (int) $movie['id'] ?>" 
                            > 
 
                                <div class="movie-poster"> 
 
                                    <?php if (!empty($movie['poster'])): ?> 
 
                                        <img 
                                            src="<?= e($movie['poster']) ?>" 
                                            alt="<?= e($movie['title']) ?>" 
                                            loading="lazy" 
                                        > 
 
                                    <?php else: ?> 
 
                                        <div 
                                            style=" 
                                                width:100%; 
                                                height:100%; 
                                                display:flex; 
                                                align-items:center; 
                                                justify-content:center; 
                                                color:#777; 
                                            " 
                                        > 
                                            No Poster 
                                        </div> 
 
                                    <?php endif; ?> 
 
 
                                    <?php if ((int) $movie['is_premium'] === 1): ?> 
 
                                        <div class="movie-badge"> 
                                            Premium 
                                        </div> 
 
                                    <?php endif; ?> 
 
 
                                    <div class="movie-overlay"> 
 
                                        <div class="play-button"> 
                                            ▶ 
                                        </div> 
 
                                    </div> 
 
                                </div> 
 
 
                                <div class="movie-info"> 
 
                                    <div class="movie-title"> 
                                        <?= e($movie['title']) ?> 
                                    </div> 
 
 
                                    <div class="movie-meta"> 
 
                                        <span class="movie-rating"> 
                                            ★ <?= number_format((float) $movie['rating'], 1) ?> 
                                        </span> 
 
                                        <span> 
                                            <?= (int) $movie['release_year'] ?> 
                                        </span> 
 
                                        <span> 
                                            <?= (int) $movie['duration'] ?> min 
                                        </span> 
 
                                    </div> 
 
                                </div> 
 
                            </a> 
 
                        </article> 
 
                    <?php endforeach; ?> 
 
                </div> 
 
            <?php else: ?> 
 
                <div 
                    style=" 
                        text-align:center; 
                        padding:55px 20px; 
                        background:#151515; 
                        border:1px solid #292929; 
                        border-radius:16px; 
                    " 
                > 
 
                    <div 
                        style=" 
                            font-size:52px; 
                            margin-bottom:15px; 
                        " 
                    > 
                        ❤️ 
                    </div> 
 
                    <h3> 
                        Your watchlist is empty 
                    </h3> 
 
                    <p 
                        style=" 
                            color:#888; 
                            margin:10px 0 25px; 
                        " 
                    > 
                        Save movies you want to watch later. 
                    </p> 
 
                    <a 
                        href="<?= BASE_URL ?>/movies.php" 
                        class="btn btn-primary" 
                    > 
                        Browse Movies 
                    </a> 
 
                </div> 
 
            <?php endif; ?> 
 
        </div> 
        <!-- =====================================================
     PAYMENT HISTORY
====================================================== -->

<div style="margin-bottom:50px;">

    <h2 class="section-title" style="margin-bottom:20px;">
        Payment History
    </h2>

    <?php if (!empty($paymentHistory)): ?>

        <div
            style="
                background:#151515;
                border:1px solid #292929;
                border-radius:16px;
                overflow:hidden;
            "
        >

            <div
                style="
                    overflow-x:auto;
                "
            >

                <table
                    style="
                        width:100%;
                        border-collapse:collapse;
                        min-width:700px;
                    "
                >

                    <thead>

                        <tr
                            style="
                                border-bottom:1px solid #292929;
                                color:#888;
                                font-size:13px;
                                text-align:left;
                            "
                        >

                            <th style="padding:18px 20px;">
                                DATE
                            </th>

                            <th style="padding:18px 20px;">
                                AMOUNT
                            </th>

                            <th style="padding:18px 20px;">
                                METHOD
                            </th>

                            <th style="padding:18px 20px;">
                                TRANSACTION ID
                            </th>

                            <th style="padding:18px 20px;">
                                STATUS
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($paymentHistory as $payment): ?>

                            <tr
                                style="
                                    border-bottom:1px solid #242424;
                                "
                            >

                                <!-- DATE -->

                                <td style="padding:18px 20px;">

                                    <?= date(
                                        'M j, Y H:i',
                                        strtotime($payment['paid_at'])
                                    ) ?>

                                </td>


                                <!-- AMOUNT -->

                                <td
                                    style="
                                        padding:18px 20px;
                                        font-weight:700;
                                    "
                                >

                                    $<?= number_format(
                                        (float) $payment['amount'],
                                        2
                                    ) ?>

                                </td>


                                <!-- METHOD -->

                                <td style="padding:18px 20px;">

                                    <?php if ($payment['payment_method'] === 'test_card'): ?>

                                        💳 Test Card

                                    <?php elseif ($payment['payment_method'] === 'test_qr'): ?>

                                        📱 Test QR

                                    <?php else: ?>

                                        <?= e($payment['payment_method']) ?>

                                    <?php endif; ?>

                                </td>


                                <!-- TRANSACTION ID -->

                                <td
                                    style="
                                        padding:18px 20px;
                                        color:#aaa;
                                        font-family:monospace;
                                    "
                                >

                                    <?= e($payment['transaction_id']) ?>

                                </td>


                                <!-- STATUS -->

                                <td style="padding:18px 20px;">

                                    <?php if ($payment['status'] === 'completed'): ?>

                                        <span
                                            style="
                                                display:inline-block;
                                                padding:6px 10px;
                                                border-radius:20px;
                                                background:rgba(46,204,113,.12);
                                                color:#2ecc71;
                                                font-size:12px;
                                                font-weight:700;
                                            "
                                        >
                                            ✓ Completed
                                        </span>

                                    <?php elseif ($payment['status'] === 'pending'): ?>

                                        <span
                                            style="
                                                display:inline-block;
                                                padding:6px 10px;
                                                border-radius:20px;
                                                background:rgba(241,196,15,.12);
                                                color:#f1c40f;
                                                font-size:12px;
                                                font-weight:700;
                                            "
                                        >
                                            Pending
                                        </span>

                                    <?php elseif ($payment['status'] === 'refunded'): ?>

                                        <span
                                            style="
                                                display:inline-block;
                                                padding:6px 10px;
                                                border-radius:20px;
                                                background:rgba(52,152,219,.12);
                                                color:#3498db;
                                                font-size:12px;
                                                font-weight:700;
                                            "
                                        >
                                            Refunded
                                        </span>

                                    <?php else: ?>

                                        <span
                                            style="
                                                display:inline-block;
                                                padding:6px 10px;
                                                border-radius:20px;
                                                background:rgba(231,76,60,.12);
                                                color:#e74c3c;
                                                font-size:12px;
                                                font-weight:700;
                                            "
                                        >
                                            Failed
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php else: ?>

        <div
            style="
                text-align:center;
                padding:45px 20px;
                background:#151515;
                border:1px solid #292929;
                border-radius:16px;
            "
        >

            <div style="font-size:45px; margin-bottom:12px;">
                💳
            </div>

            <h3>
                No payment history
            </h3>

            <p style="color:#888; margin:10px 0 25px;">
                Your Premium purchases will appear here.
            </p>

            <a
                href="<?= BASE_URL ?>/subscription.php"
                class="btn btn-primary"
            >
                👑 Get Premium
            </a>

        </div>

    <?php endif; ?>

</div>
 
 
        <!-- ===================================================== 
             QUICK ACTIONS 
        ====================================================== --> 
 
        <h2 class="section-title"> 
            Quick Access 
        </h2> 
 
 
        <div 
            style=" 
                display:grid; 
                grid-template-columns: 
                    repeat(auto-fit, minmax(220px, 1fr)); 
                gap:16px; 
                margin-top:20px; 
            " 
        > 
 
            <!-- Browse --> 
 
            <a 
                href="<?= BASE_URL ?>/movies.php" 
                style=" 
                    text-decoration:none; 
                    color:inherit; 
                    background:#151515; 
                    border:1px solid #292929; 
                    border-radius:16px; 
                    padding:25px; 
                " 
            > 
 
                <div style="font-size:30px;"> 
                    🎬 
                </div> 
 
                <h3> 
                    Browse Movies 
                </h3> 
 
                <p style="color:#888;"> 
                    Discover something new to watch. 
                </p> 
 
            </a> 
 
 
            <!-- Watchlist --> 
 
            <a 
                href="#watchlist" 
                onclick="
                    document.querySelector('.section-title').scrollIntoView({
                        behavior: 'smooth'
                    });
                    return false;
                " 
                style=" 
                    text-decoration:none; 
                    color:inherit; 
                    background:#151515; 
                    border:1px solid #292929; 
                    border-radius:16px; 
                    padding:25px; 
                " 
            > 
 
                <div style="font-size:30px;"> 
                    ❤️ 
                </div> 
 
                <h3> 
                    My Watchlist 
                </h3> 
 
                <p style="color:#888;"> 
                    View movies you saved. 
                </p> 
 
            </a> 


            <!-- WATCH HISTORY -->

            <a 
                href="<?= BASE_URL ?>/history.php"
                style="
                    text-decoration:none;
                    color:inherit;
                    background:#151515;
                    border:1px solid #292929;
                    border-radius:16px;
                    padding:25px;
                "
            >

                <div style="font-size:30px;">
                    🕐
                </div>

                <h3>
                    Watch History
                </h3>

                <p style="color:#888;">
                    Continue watching movies you started.
                </p>

            </a>
 
 
            <!-- Home --> 
 
            <a 
                href="<?= BASE_URL ?>/index.php" 
                style=" 
                    text-decoration:none; 
                    color:inherit; 
                    background:#151515; 
                    border:1px solid #292929; 
                    border-radius:16px; 
                    padding:25px; 
                " 
            > 
 
                <div style="font-size:30px;"> 
                    🏠 
                </div> 
 
                <h3> 
                    Home 
                </h3> 
 
                <p style="color:#888;"> 
                    Return to CineStream home. 
                </p> 
 
            </a> 
 
        </div> 
 
 
        <!-- ===================================================== 
             MEMBER SINCE 
        ====================================================== --> 
 
        <div 
            style=" 
                margin-top:40px; 
                padding-top:25px; 
                border-top:1px solid #292929; 
                color:#777; 
                font-size:14px; 
            " 
        > 
 
            Member since: 
            <?= date( 
                'F j, Y', 
                strtotime($currentUser['created_at']) 
            ) ?> 
 
        </div> 
 
 
    </div> 
 
</section> 
 
 
<?php 
require_once __DIR__ . '/includes/footer.php'; 
?>