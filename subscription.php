<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

requireLogin();

$db = getDB();
$currentUser = getCurrentUser();

if (!$currentUser) {
    redirect('/login.php');
}

$userId = (int) $currentUser['id'];


/*
|--------------------------------------------------------------------------
| PLAN
|--------------------------------------------------------------------------
*/

$planName = 'Premium Monthly';
$planPrice = 9.90;
$planDays = 30;


/*
|--------------------------------------------------------------------------
| HANDLE TEST PAYMENT
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        http_response_code(403);

        exit('403 Forbidden - Invalid CSRF token.');
    }


    $paymentMethod = trim(
        (string) ($_POST['payment_method'] ?? '')
    );


    if (!in_array(
        $paymentMethod,
        ['test_card', 'test_qr'],
        true
    )) {

        $errorMessage = 'Please select a payment method.';

    } else {

        try {

            $db->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | CREATE UNIQUE TEST TRANSACTION ID
            |--------------------------------------------------------------------------
            */

            $transactionId =
                'TEST-' .
                strtoupper(bin2hex(random_bytes(6)));


            /*
            |--------------------------------------------------------------------------
            | PAYMENT RECORD
            |--------------------------------------------------------------------------
            */

            $paymentStmt = $db->prepare(
                'INSERT INTO payments
                    (
                        user_id,
                        subscription_id,
                        amount,
                        payment_method,
                        transaction_id,
                        status,
                        paid_at
                    )
                 VALUES
                    (
                        :user_id,
                        NULL,
                        :amount,
                        :payment_method,
                        :transaction_id,
                        :status,
                        CURRENT_TIMESTAMP
                    )'
            );


            $paymentStmt->execute([
                ':user_id' => $userId,
                ':amount' => $planPrice,
                ':payment_method' => $paymentMethod,
                ':transaction_id' => $transactionId,
                ':status' => 'completed'
            ]);


            /*
            |--------------------------------------------------------------------------
            | CALCULATE EXPIRATION
            |--------------------------------------------------------------------------
            |
            | If user already has active Premium that hasn't expired,
            | extend it by 30 days.
            |
            */

            $currentExpiry = $currentUser['subscription_expires_at'] ?? null;

            $now = new DateTimeImmutable();

            if (
                $currentUser['subscription_status'] === 'active'
                && !empty($currentExpiry)
            ) {

                $expiryDate = new DateTimeImmutable(
                    $currentExpiry
                );

                if ($expiryDate > $now) {

                    $newExpiry = $expiryDate->modify(
                        '+' . $planDays . ' days'
                    );

                } else {

                    $newExpiry = $now->modify(
                        '+' . $planDays . ' days'
                    );
                }

            } else {

                $newExpiry = $now->modify(
                    '+' . $planDays . ' days'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE USER
            |--------------------------------------------------------------------------
            */

            $updateStmt = $db->prepare(
                'UPDATE users
                 SET
                    subscription_status = :status,
                    subscription_expires_at = :expires_at
                 WHERE id = :user_id'
            );


            $updateStmt->execute([
                ':status' => 'active',
                ':expires_at' => $newExpiry->format(
                    'Y-m-d H:i:s'
                ),
                ':user_id' => $userId
            ]);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $db->commit();


            /*
            |--------------------------------------------------------------------------
            | REFRESH CURRENT USER
            |--------------------------------------------------------------------------
            */

            $currentUser = getCurrentUser(true);


            $successMessage =
                'Payment successful! Premium is active until ' .
                $newExpiry->format('Y-m-d H:i');

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log(
                'Subscription payment error: ' .
                $e->getMessage()
            );

            $errorMessage =
                'Payment failed. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| CHECK CURRENT SUBSCRIPTION
|--------------------------------------------------------------------------
*/

$isPremium = (
    $currentUser['subscription_status'] === 'active'
    && !empty($currentUser['subscription_expires_at'])
    && strtotime(
        $currentUser['subscription_expires_at']
    ) > time()
);


if (
    $currentUser['subscription_status'] === 'active'
    && !empty($currentUser['subscription_expires_at'])
    && strtotime(
        $currentUser['subscription_expires_at']
    ) <= time()
) {

    $expireStmt = $db->prepare(
        'UPDATE users
         SET subscription_status = :status
         WHERE id = :user_id'
    );

    $expireStmt->execute([
        ':status' => 'expired',
        ':user_id' => $userId
    ]);

    $currentUser = getCurrentUser(true);

    $isPremium = false;
}


$pageTitle = 'Premium Subscription';

require_once __DIR__ . '/includes/header.php';
?>


<section class="section">

    <div class="container">


        <!-- HEADER -->

        <div
            style="
                text-align:center;
                margin-bottom:45px;
            "
        >

            <h1 class="section-title">
                Premium Subscription
            </h1>

            <p
                style="
                    color:#888;
                    margin-top:10px;
                "
            >
                Unlock all premium movies and enjoy unlimited watching.
            </p>

        </div>


        <!-- SUCCESS -->

        <?php if ($successMessage !== ''): ?>

            <div
                style="
                    max-width:600px;
                    margin:0 auto 25px;
                    padding:16px 20px;
                    background:#12351f;
                    border:1px solid #21663a;
                    border-radius:10px;
                    color:#7df0a1;
                "
            >

                ✓ <?= e($successMessage) ?>

            </div>

        <?php endif; ?>


        <!-- ERROR -->

        <?php if ($errorMessage !== ''): ?>

            <div
                style="
                    max-width:600px;
                    margin:0 auto 25px;
                    padding:16px 20px;
                    background:#351212;
                    border:1px solid #662121;
                    border-radius:10px;
                    color:#ff8585;
                "
            >

                ✕ <?= e($errorMessage) ?>

            </div>

        <?php endif; ?>


        <?php if ($isPremium): ?>

            <!-- ACTIVE PREMIUM -->

            <div
                style="
                    max-width:600px;
                    margin:0 auto;
                    padding:35px;
                    background:#151515;
                    border:1px solid #333;
                    border-radius:18px;
                    text-align:center;
                "
            >

                <div
                    style="
                        font-size:55px;
                        margin-bottom:15px;
                    "
                >
                    👑
                </div>


                <h2 style="margin-bottom:10px;">
                    You are Premium
                </h2>


                <p style="color:#888;">
                    Your Premium subscription is active.
                </p>


                <div
                    style="
                        margin-top:25px;
                        padding:15px;
                        background:#0d0d0d;
                        border-radius:10px;
                    "
                >

                    <div
                        style="
                            color:#777;
                            font-size:13px;
                            margin-bottom:5px;
                        "
                    >
                        Subscription expires
                    </div>

                    <strong>
                        <?= e(
                            date(
                                'F d, Y H:i',
                                strtotime(
                                    $currentUser[
                                        'subscription_expires_at'
                                    ]
                                )
                            )
                        ) ?>
                    </strong>

                </div>


                <!-- EXTEND -->

                <form
                    method="POST"
                    style="margin-top:25px;"
                >

                    <?= csrfField() ?>

                    <input
                        type="hidden"
                        name="payment_method"
                        value="test_card"
                    >

                    <button
                        type="submit"
                        class="btn btn-primary"
                        style="width:100%;"
                    >
                        Extend Premium — $9.90
                    </button>

                </form>

            </div>


        <?php else: ?>

            <!-- PREMIUM PLAN -->

            <div
                style="
                    max-width:600px;
                    margin:0 auto;
                    background:#151515;
                    border:1px solid #333;
                    border-radius:18px;
                    overflow:hidden;
                "
            >

                <div
                    style="
                        padding:35px;
                        text-align:center;
                        background:linear-gradient(
                            135deg,
                            #1b1b1b,
                            #111
                        );
                    "
                >

                    <div
                        style="
                            font-size:55px;
                            margin-bottom:15px;
                        "
                    >
                        👑
                    </div>


                    <h2>
                        <?= e($planName) ?>
                    </h2>


                    <div
                        style="
                            font-size:38px;
                            font-weight:bold;
                            margin-top:15px;
                        "
                    >
                        $9.90
                    </div>


                    <div
                        style="
                            color:#888;
                            margin-top:5px;
                        "
                    >
                        30 days
                    </div>

                </div>


                <!-- FEATURES -->

                <div style="padding:30px;">

                    <h3 style="margin-bottom:20px;">
                        Premium benefits
                    </h3>


                    <div
                        style="
                            display:flex;
                            flex-direction:column;
                            gap:14px;
                            color:#ccc;
                        "
                    >

                        <div>
                            ✓ Access all Premium movies
                        </div>

                        <div>
                            ✓ Unlimited movie watching
                        </div>

                        <div>
                            ✓ Resume movies from exact position
                        </div>

                        <div>
                            ✓ Watch history
                        </div>

                        <div>
                            ✓ No Premium restrictions
                        </div>

                    </div>


                    <!-- PAYMENT -->

                    <form
                        method="POST"
                        style="margin-top:30px;"
                    >

                        <?= csrfField() ?>


                        <label
                            style="
                                display:block;
                                color:#aaa;
                                margin-bottom:8px;
                            "
                        >
                            Payment Method
                        </label>


                        <select
                            name="payment_method"
                            required
                            style="
                                width:100%;
                                padding:13px;
                                background:#0d0d0d;
                                border:1px solid #333;
                                border-radius:8px;
                                color:#fff;
                                margin-bottom:20px;
                            "
                        >

                            <option value="">
                                Select payment method
                            </option>

                            <option value="test_card">
                                💳 Test Card
                            </option>

                            <option value="test_qr">
                                📱 Test QR Payment
                            </option>

                        </select>


                        <button
                            type="submit"
                            class="btn btn-primary"
                            style="
                                width:100%;
                                padding:15px;
                                font-size:16px;
                            "
                        >
                            🔒 Pay $9.90
                        </button>


                        <p
                            style="
                                color:#666;
                                font-size:12px;
                                text-align:center;
                                margin-top:15px;
                            "
                        >
                            TEST MODE — No real money will be charged.
                        </p>

                    </form>

                </div>

            </div>

        <?php endif; ?>

    </div>

</section>


<?php
require_once __DIR__ . '/includes/footer.php';
?>