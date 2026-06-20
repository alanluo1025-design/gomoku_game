<?php
// challenge_request.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$friend_id = isset($_POST['friend_id']) ? intval($_POST['friend_id']) : 0;

if ($friend_id <= 0 || $current_user_id == $friend_id) {
    echo json_encode(['success' => false, 'message' => '無效的請求！']);
    exit();
}

// 檢查是否已有進行中或等待確認的挑戰（不論誰發起的）
$check_query = "SELECT id FROM games WHERE ((black_player_id = ? AND white_player_id = ?) OR (black_player_id = ? AND white_player_id = ?)) AND status IN ('playing', 'waiting')";
$stmt = $mysqli->prepare($check_query);
$stmt->bind_param("iiii", $current_user_id, $friend_id, $friend_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => '您們之間已有進行中或等待確認的挑戰！']);
    exit();
}

// 建立一筆等待確認的挑戰房間
// 發起人為黑棋(black_player_id)，被挑戰者為白棋(white_player_id)
// current_turn 填入發起人 ID（黑棋），因為接受後黑棋先下
$insert_query = "INSERT INTO games (black_player_id, white_player_id, current_turn, status) VALUES (?, ?, ?, 'waiting')";
$insert_stmt = $mysqli->prepare($insert_query);
$insert_stmt->bind_param("iii", $current_user_id, $friend_id, $current_user_id);

if ($insert_stmt->execute()) {
    $game_id = $mysqli->insert_id;
    echo json_encode(['success' => true, 'game_id' => $game_id, 'message' => '挑戰請求已發送，等待對方回應。']);
} else {
    echo json_encode(['success' => false, 'message' => '發送挑戰請求失敗：' . $mysqli->error]);
}
