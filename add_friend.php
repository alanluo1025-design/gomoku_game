<?php
// add_friend.php (純 PHP 後端)
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php'; // 引入你的資料庫連線

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$target_friend_id = isset($_POST['friend_id']) ? intval($_POST['friend_id']) : 0;

if ($target_friend_id <= 0 || $current_user_id == $target_friend_id) {
    echo json_encode(['success' => false, 'message' => '無效的請求！']);
    exit();
}

// 檢查是否已經存在好友關係或請求 (不論誰發起的)
$check_query = "SELECT * FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)";
$stmt = $mysqli->prepare($check_query);
$stmt->bind_param("iiii", $current_user_id, $target_friend_id, $target_friend_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => '雙方已經是好友，或是有尚未處理的好友請求！']);
} else {
    // 寫入一筆新的請求，status 預設為 'pending'
    $insert_query = "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')";
    $insert_stmt = $mysqli->prepare($insert_query);
    $insert_stmt->bind_param("ii", $current_user_id, $target_friend_id);
    
    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '好友請求已送出，等待對方確認！']);
    } else {
        echo json_encode(['success' => false, 'message' => '發送好友請求失敗！']);
    }
}
