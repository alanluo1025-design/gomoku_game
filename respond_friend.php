<?php
// respond_friend.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($request_id <= 0 || !in_array($action, ['accept', 'reject'])) {
    echo json_encode(['success' => false, 'message' => '無效的操作參數']);
    exit();
}

// 安全檢查：確認該筆 pending 的邀請，接收人（friend_id）真的是目前登入的自己
$check_query = "SELECT f.user_id, u.username AS my_username FROM friends f JOIN users u ON u.id = ? WHERE f.id = ? AND f.friend_id = ? AND f.status = 'pending'";
$stmt = $mysqli->prepare($check_query);
$stmt->bind_param("iii", $current_user_id, $request_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => '找不到該筆好友請求，或已被處理']);
    exit();
}
$row = $result->fetch_assoc();
$sender_id = $row['user_id'];
$my_username = $row['my_username'];

if ($action === 'accept') {
    // 接受：修改狀態
    $update_query = "UPDATE friends SET status = 'accepted' WHERE id = ?";
    $update_stmt = $mysqli->prepare($update_query);
    $update_stmt->bind_param("i", $request_id);
    
    if ($update_stmt->execute()) {
        $msg = "{$my_username} 已接受您的好友請求！";
        $mysqli->query("INSERT INTO notifications (user_id, message) VALUES ($sender_id, '$msg')");
        echo json_encode(['success' => true, 'message' => '已成功加入好友！']);
    } else {
        echo json_encode(['success' => false, 'message' => '資料庫更新失敗']);
    }
} else if ($action === 'reject') {
    // 拒絕：直接刪除請求
    $delete_query = "DELETE FROM friends WHERE id = ?";
    $delete_stmt = $mysqli->prepare($delete_query);
    $delete_stmt->bind_param("i", $request_id);
    
    if ($delete_stmt->execute()) {
        $msg = "{$my_username} 已拒絕您的好友請求。";
        $mysqli->query("INSERT INTO notifications (user_id, message) VALUES ($sender_id, '$msg')");
        echo json_encode(['success' => true, 'message' => '已拒絕該好友請求。']);
    } else {
        echo json_encode(['success' => false, 'message' => '資料庫刪除失敗']);
    }
}
