<?php
// index.php

require_once 'stk_push.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone  = $_POST['phone'];
    $amount = $_POST['amount'];

    try {
        $result = stkPush($phone, $amount, 'MyShop', 'Purchase');

        if ($result['ResponseCode'] === '0') {
            $message = "✅ Check your phone and enter your M-Pesa PIN to complete payment.";
        } else {
            $message = "❌ Error: " . $result['ResponseDescription'];
        }
    } catch (Exception $e) {
        $message = "❌ " . $e->getMessage();
    }
}
?>

<form method="POST">
    <input type="tel"    name="phone"  placeholder="07XX XXX XXX" required>
    <input type="number" name="amount" placeholder="Amount (KSh)"  required>
    <button type="submit">Pay with M-Pesa</button>
</form>

<?php if (isset($message)) echo "<p>$message</p>"; ?>
