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

$stmt = $mysqli->prepare("SELECT id, username, password FROM users WHERE username = ?");
$stmt->bind_param("s", $id); 
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (password_verify($key, $row['password'])) {
        $_SESSION['user_id'] = $row['id'];       
        $_SESSION['username'] = $row['username']; 
        
        // 用 JavaScript 跳出歡迎彈窗，點確定後進入遊戲大廳
        $welcome_msg = "登入成功！歡迎 " . htmlspecialchars($row['username']) . " 進入五子棋大廳！";
        echo "<script>
                alert('" . $welcome_msg . "');
                window.location.href = 'lobby.php';
              </script>";
    } else {
        echo "<script>
                alert('帳號或密碼錯誤。');
                window.location.href = 'login.php';
              </script>";
    }
} else {
    echo "<script>
            alert('帳號不存在。');
            window.location.href = 'login.html';
          </script>";
}

$stmt->close();
$mysqli->close();
?>
