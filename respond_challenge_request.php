<?php
// respond_challenge_request.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登入'], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_POST['game_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => '缺少遊戲ID或動作'], JSON_UNESCAPED_UNICODE);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$game_id = intval($_POST['game_id']);
$action  = $_POST['action'];

// 先判斷動作類型，再分別驗證身份
// accept / reject：驗證接收者（白棋）身份
// cancel：驗證發起者（黑棋）身份
if ($action === 'accept' || $action === 'reject') {
    $check_query = "SELECT id FROM games WHERE id = ? AND white_player_id = ? AND status = 'waiting'";
    $stmt = $mysqli->prepare($check_query);
    $stmt->bind_param("ii", $game_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => '查無此待處理挑戰或您無權回應。'], JSON_UNESCAPED_UNICODE);
        exit();
    }

} else if ($action === 'cancel') {
    $check_query = "SELECT id FROM games WHERE id = ? AND black_player_id = ? AND status = 'waiting'";
    $stmt = $mysqli->prepare($check_query);
    $stmt->bind_param("ii", $game_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => '查無此待處理挑戰或您無權取消。'], JSON_UNESCAPED_UNICODE);
        exit();
    }

} else {
    echo json_encode(['success' => false, 'message' => '無效的動作'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($action === 'accept') {
    // 接受挑戰：更新狀態為 playing，並設定當前回合為黑棋（發起者）
    $update_stmt = $mysqli->prepare("UPDATE games SET status = 'playing', current_turn = black_player_id WHERE id = ?");
    $update_stmt->bind_param("i", $game_id);

    if ($update_stmt->execute()) {
        $response['success']  = true;
        $response['message']  = '挑戰已接受！即將進入遊戲。';
        $response['redirect'] = 'game.php?game_id=' . $game_id;
    } else {
        $response['message'] = '接受失敗，請稍後再試。';
    }

} else if ($action === 'reject' || $action === 'cancel') {
    // 拒絕或取消：直接刪除該筆 waiting 房間（資料表沒有 rejected/cancelled 狀態）
    $delete_stmt = $mysqli->prepare("DELETE FROM games WHERE id = ?");
    $delete_stmt->bind_param("i", $game_id);

    if ($delete_stmt->execute()) {
        $response['success'] = true;
        $response['message'] = ($action === 'reject') ? '已拒絕挑戰。' : '挑戰已取消。';
    } else {
        $response['message'] = '操作失敗，請稍後再試。';
    }
}

$mysqli->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
