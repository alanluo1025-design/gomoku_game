<?php
// get_lobby_data.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$response = [
    'success' => true,
    'user' => [],
    'friends' => [],
    'requests' => [],
    'pending_challenges_to_me' => [],
    'pending_challenges_from_me' => [],
    'notifications' => []
];

// 初始化 notifications 表 (如果不存在)
$mysqli->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    user_id INT, 
    message VARCHAR(255), 
    is_read BOOLEAN DEFAULT FALSE, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 抓取通知
$notif_query = "SELECT id, message FROM notifications WHERE user_id = ? AND is_read = FALSE";
$notif_stmt = $mysqli->prepare($notif_query);
$notif_stmt->bind_param("i", $current_user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();

$notif_ids = [];
while ($row = $notif_result->fetch_assoc()) {
    $response['notifications'][] = $row['message'];
    $notif_ids[] = $row['id'];
}
$notif_stmt->close();

if (!empty($notif_ids)) {
    $ids_str = implode(',', $notif_ids);
    $mysqli->query("UPDATE notifications SET is_read = TRUE WHERE id IN ($ids_str)");
}

$user_query = "
    SELECT id, username, wins, losses FROM users WHERE id = ?
";
$stmt0 = $mysqli->prepare($user_query);
$stmt0->bind_param("i", $current_user_id);
$stmt0->execute();
$user_result = $stmt0->get_result();

while ($row = $user_result->fetch_assoc()) {
    $response['user'] = [
        'id' => $row['id'],
        'username' => $row['username'],
        'wins' => $row['wins'],
        'losses' => $row['losses']
    ];
}

// 1. 撈取「已綁定的好友名單」 (status = 'accepted')
$friends_query = "
    SELECT u.id, u.username, u.wins, u.losses 
    FROM friends f
    JOIN users u ON (f.user_id = u.id AND f.friend_id = ?) OR (f.friend_id = u.id AND f.user_id = ?)
    WHERE f.status = 'accepted'
";
$stmt1 = $mysqli->prepare($friends_query);
$stmt1->bind_param("ii", $current_user_id, $current_user_id);
$stmt1->execute();
$friends_result = $stmt1->get_result();

while ($row = $friends_result->fetch_assoc()) {
    $response['friends'][] = [
        'id' => $row['id'],
        'username' => $row['username'],
        'wins' => $row['wins'],
        'losses' => $row['losses']
    ];
}

// 2. 撈取「收到的好友請求名單」 (friend_id 是自己 且 status = 'pending')
$requests_query = "
    SELECT f.id AS request_id, u.username 
    FROM friends f
    JOIN users u ON f.user_id = u.id
    WHERE f.friend_id = ? AND f.status = 'pending'
";
$stmt2 = $mysqli->prepare($requests_query);
$stmt2->bind_param("i", $current_user_id);
$stmt2->execute();
$requests_result = $stmt2->get_result();

while ($row = $requests_result->fetch_assoc()) {
    $response['requests'][] = [
        'request_id' => $row['request_id'],
        'username' => $row['username']
    ];
}
// 3. 撈取「收到的挑戰請求」(白棋是自己 且 status = 'waiting')
$challenges_to_me_query = "
    SELECT g.id AS game_id, u.username 
    FROM games g
    JOIN users u ON g.black_player_id = u.id
    WHERE g.white_player_id = ? AND g.status = 'waiting'
";
$stmt3 = $mysqli->prepare($challenges_to_me_query);
$stmt3->bind_param("i", $current_user_id);
$stmt3->execute();
$challenges_to_me_result = $stmt3->get_result();

while ($row = $challenges_to_me_result->fetch_assoc()) {
    $response['pending_challenges_to_me'][] = [
        'game_id'  => $row['game_id'],
        'username' => $row['username']
    ];
}
$stmt3->close();

// 4. 撈取「送出的挑戰請求」(黑棋是自己 且 status = 'waiting')
$challenges_from_me_query = "
    SELECT g.id AS game_id, u.username 
    FROM games g
    JOIN users u ON g.white_player_id = u.id
    WHERE g.black_player_id = ? AND g.status = 'waiting'
";
$stmt4 = $mysqli->prepare($challenges_from_me_query);
$stmt4->bind_param("i", $current_user_id);
$stmt4->execute();
$challenges_from_me_result = $stmt4->get_result();

while ($row = $challenges_from_me_result->fetch_assoc()) {
    $response['pending_challenges_from_me'][] = [
        'game_id'  => $row['game_id'],
        'username' => $row['username']
    ];
}
$stmt4->close();

echo json_encode($response, JSON_UNESCAPED_UNICODE);
