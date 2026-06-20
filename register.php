<?php
require_once 'db_connect.php'; 

if ($mysqli->connect_errno) {
    exit("連接資料庫失敗: " . $mysqli->connect_error);
}

$id = isset($_POST['username']) ? trim($_POST['username']) : '';
$key = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($id) || empty($key)) {
    exit("錯誤：帳號或密碼不能為空值！");
}

// 加密密碼
$hashed_password = password_hash($key, PASSWORD_DEFAULT);

// 寫入資料庫
$stmt = $mysqli->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $id, $hashed_password);

if ($stmt->execute()) {
    // 【修改區】用 JavaScript 跳出訊息框，並跳轉到登入頁面
    echo "<script>
            alert('註冊成功！來去登入吧！');
            window.location.href = 'login.html';
          </script>";
} else {
    // 失敗也可以用彈窗提示，並回到註冊頁
    echo "<script>
            alert('註冊失敗，該帳號可能已被使用。');
            window.location.href = 'register.html';
          </script>";
}

$stmt->close();
$mysqli->close();
?>
