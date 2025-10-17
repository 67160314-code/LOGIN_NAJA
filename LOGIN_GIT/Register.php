<?php
require __DIR__ . '/config_mysqli.php'; // ไฟล์เชื่อมต่อฐานข้อมูลของคุณ

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าจากฟอร์ม
    $email = trim($_POST['email'] ?? '');
    $name  = trim($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';

    // ตรวจสอบว่าข้อมูลครบไหม
    if (empty($email) || empty($name) || empty($password)) {
        $message = '⚠️ Please fill in all fields.';
    } else {
        // ตรวจสอบว่า email ซ้ำไหม
        $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = '⚠️ This email is already registered.';
        } else {
            // เข้ารหัสรหัสผ่าน
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // เพิ่มข้อมูลลงฐานข้อมูล
            $stmt = $mysqli->prepare('INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $email, $name, $hash);

            if ($stmt->execute()) {
                $message = '✅ Registration successful!';
            } else {
                $message = '❌ Database error: ' . $stmt->error;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f6f8;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }
    form {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        width: 300px;
    }
    input {
        width: 100%;
        padding: 8px;
        margin: 6px 0 12px;
        border: 1px solid #ccc;
        border-radius: 6px;
    }
    button {
        width: 100%;
        padding: 10px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    button:hover {
        background-color: #0056b3;
    }
    .message {
        margin-bottom: 12px;
        color: #333;
        font-weight: bold;
        text-align: center;
    }
</style>
</head>
<body>

<form method="POST" action="">
    <h2>Register</h2>
    <?php if (!empty($message)) echo "<div class='message'>$message</div>"; ?>
    
    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Display Name:</label>
    <input type="text" name="display_name" required>

    <label>Password:</label>
    <input type="password" name="password" required>

    <button type="submit">Register</button>
</form>

</body>
</html>
