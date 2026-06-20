<?php
// search_user.php (純後端 API)
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php'; // 確保引入你的資料庫連線檔 [cite: 37]

// 1. 檢查是否登入
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
// 取得前端 GET 傳過來的搜尋字串
$search_username = isset($_GET['username']) ? trim($_GET['username']) : '';

if ($search_username === '') {
    echo json_encode(['success' => false, 'message' => '請輸入要搜尋的帳號']);
    exit();
}

// 2. 去 users 表尋找該玩家是否存在 [cite: 3, 4]
$user_query = "SELECT id, username, wins, losses FROM users WHERE username = ?";
$stmt = $mysqli->prepare($user_query);
$stmt->bind_param("s", $search_username);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows === 0) {
    // 找不到該玩家
    echo json_encode(['success' => false, 'message' => '找不到該玩家']);
    exit();
}

$target_user = $user_result->fetch_assoc();
$target_id = $target_user['id'];

// 準備要回傳給前端的基礎資料
$response = [
    'success' => true,
    'user' => [
        'id' => $target_user['id'],
        'username' => $target_user['username'],
        'wins' => $target_user['wins'],
        'losses' => $target_user['losses']
    ],
    'is_me' => false,
    'is_friend' => false,
    'is_pending' => false
];

// 3. 判斷搜尋到的是不是玩家自己
if ($current_user_id == $target_id) {
    $response['is_me'] = true;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// 4. 去 friends 表檢查雙方的好友狀態 
// 雙向檢查：可能是目前玩家發給對方，也可能是對方發給目前玩家
$friend_query = "SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)";
$stmt2 = $mysqli->prepare($friend_query);
$stmt2->bind_param("iiii", $current_user_id, $target_id, $target_id, $current_user_id);
$stmt2->execute();
$friend_result = $stmt2->get_result();

if ($friend_result->num_rows > 0) {
    $row = $friend_result->fetch_assoc();
    if ($row['status'] === 'accepted') {
        $response['is_friend'] = true; // 已經是好友
    } else if ($row['status'] === 'pending') {
        $response['is_pending'] = true; // 請求還在等待同意
    }
}

// 將結果打包成 JSON 傳回給大廳前端
echo json_encode($response, JSON_UNESCAPED_UNICODE);
