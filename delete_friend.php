<?php
// delete_friend.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$target_friend_id = isset($_POST['friend_id']) ? intval($_POST['friend_id']) : 0;

if ($target_friend_id <= 0) {
    echo json_encode(['success' => false, 'message' => '無效的操作參數']);
    exit();
}

// 取得目前的 username 以便發送通知
$user_stmt = $mysqli->prepare("SELECT username FROM users WHERE id = ?");
$user_stmt->bind_param("i", $current_user_id);
$user_stmt->execute();
$user_res = $user_stmt->get_result();
$my_username = $user_res->fetch_assoc()['username'];
$user_stmt->close();

// 刪除好友記錄
$delete_query = "DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)";
$delete_stmt = $mysqli->prepare($delete_query);
$delete_stmt->bind_param("iiii", $current_user_id, $target_friend_id, $target_friend_id, $current_user_id);

if ($delete_stmt->execute()) {
    if ($mysqli->affected_rows > 0) {
        // 發送通知給被刪除的人
        $msg = "{$my_username} 已解除與您的好友關係。";
        $mysqli->query("INSERT INTO notifications (user_id, message) VALUES ($target_friend_id, '$msg')");
        
        echo json_encode(['success' => true, 'message' => '已解除好友關係。']);
    } else {
        echo json_encode(['success' => false, 'message' => '找不到該好友紀錄。']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '資料庫刪除失敗']);
}
$mysqli->close();
