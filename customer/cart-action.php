<?php
require_once dirname(__DIR__) . "/includes/config.php";

$db = getDB();

// Block unauthenticated users
if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? 'cart.php';

// Sanitize redirect to prevent open redirect attacks
$redirect = filter_var($redirect, FILTER_SANITIZE_URL);
if (!str_starts_with($redirect, '/') && !str_starts_with($redirect, 'cart.php')) {
    $redirect = 'cart.php';
}

switch ($action) {

    case 'add':
        // Accept part_id and quantity from both POST and GET
        $id       = filter_input(INPUT_POST, 'part_id',  FILTER_VALIDATE_INT)
                 ?: filter_input(INPUT_GET,  'part_id',  FILTER_VALIDATE_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT)
                 ?: filter_input(INPUT_GET,  'quantity', FILTER_VALIDATE_INT)
                 ?: 1; // default to 1 if not provided

        if (!$id || $quantity < 1) {
            $_SESSION['cart_error'] = "Invalid product data.";
            break;
        }

        // Check part exists and is active (prepared statement)
        $stmt = $db->prepare("SELECT stock FROM spare_parts WHERE part_id = ? AND is_active = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stock_result = $stmt->get_result();

        if (!$stock_result || $stock_result->num_rows === 0) {
            $_SESSION['cart_error'] = "Part not found.";
            break;
        }
        $stock = (int)$stock_result->fetch_assoc()['stock'];

        // Get current qty already in cart (prepared statement)
        $stmt2 = $db->prepare("SELECT quantity FROM cart WHERE user_id = ? AND part_id = ?");
        $stmt2->bind_param("ii", $user_id, $id);
        $stmt2->execute();
        $check = $stmt2->get_result();
        $current_qty = ($check && $check->num_rows > 0) ? (int)$check->fetch_assoc()['quantity'] : 0;

        if (($current_qty + $quantity) > $stock) {
            $_SESSION['cart_error'] = "Not enough stock. Only $stock unit(s) available.";
            break;
        }

        if ($current_qty > 0) {
            // Update existing cart row (prepared statement)
            $stmt3 = $db->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND part_id = ?");
            $stmt3->bind_param("iii", $quantity, $user_id, $id);
            $stmt3->execute();
        } else {
            // Insert new cart row
            $stmt3 = $db->prepare("INSERT INTO cart (user_id, part_id, quantity) VALUES (?, ?, ?)");
            $stmt3->bind_param("iii", $user_id, $id, $quantity);
            $stmt3->execute();
        }

        $_SESSION['cart_success'] = "Item added to cart.";
        break;


    case 'remove':
        // Accept id from both POST and GET
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT)
           ?: filter_input(INPUT_GET,  'id', FILTER_VALIDATE_INT);

        if (!$id) {
            $_SESSION['cart_error'] = "Item not found.";
            break;
        }

        $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ? AND part_id = ?");
        $stmt->bind_param("ii", $user_id, $id);
        $stmt->execute();
        $_SESSION['cart_success'] = "Item removed.";
        break;


    case 'clear':
        $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $_SESSION['cart_success'] = "Cart cleared.";
        break;


    default:
        $_SESSION['cart_error'] = "Invalid action.";
        break;
}

header("Location: $redirect");
exit();
?>
