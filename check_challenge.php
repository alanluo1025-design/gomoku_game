<?php
// check_challenge.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$response = [
    'incoming' => null
    // sent_status 只有在帶了 pending_game_id 參數時才會加入回應
];

if (!isset($_SESSION['user_id'])) {
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// 1. 查詢最新一筆「別人向我發起的等待確認挑戰」
$sql_incoming = "
    SELECT g.id AS game_id, u.username AS challenger_name
    FROM games g
    JOIN users u ON g.black_player_id = u.id
    WHERE g.white_player_id = ? AND g.status = 'waiting'
    ORDER BY g.id DESC
    LIMIT 1
";
$stmt = $mysqli->prepare($sql_incoming);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $response['incoming'] = [
        'game_id'        => (int)$row['game_id'],
        'challenger_name' => $row['challenger_name']
    ];
}
$stmt->close();

// 2. 查詢我送出的某筆挑戰目前狀態（前端透過 GET 傳入 pending_game_id）
// 若找不到（已被刪除=拒絕/取消），sent_status 維持 null，前端可藉此判斷
$pending_game_id = isset($_GET['pending_game_id']) ? intval($_GET['pending_game_id']) : 0;
if ($pending_game_id > 0) {
    $sql_sent = "SELECT status FROM games WHERE id = ? AND black_player_id = ?";
    $stmt2 = $mysqli->prepare($sql_sent);
    $stmt2->bind_param("ii", $pending_game_id, $current_user_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    if ($row2 = $result2->fetch_assoc()) {
        $response['sent_status'] = $row2['status'];   // 'waiting' 或 'playing'
    } else {
        $response['sent_status'] = null;               // 找不到 = 房間已被刪除（拒絕或取消）
    }
    $stmt2->close();
}

$mysqli->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
