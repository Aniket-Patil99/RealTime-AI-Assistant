<?php
require_once 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $message = $_POST["message"];
    $session_id = session_id();

    // Save User Message
    $stmt = $pdo->prepare("INSERT INTO messages (session_id, sender, content) VALUES (?, 'user', ?)");
    $stmt->execute([$session_id, $message]);

    $api_key = "your key";
    $api_url = "your url";

    $data = [
        "model" => "meta/llama3-8b-instruct",
        "messages" => [
            [
                "role" => "user",
                "content" => $message
            ]
        ],
        "max_tokens" => 200
    ];

    $ch = curl_init($api_url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $api_key
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    curl_close($ch);

    $result = json_decode($response, true);
    $bot_message = $result["choices"][0]["message"]["content"];

    // Save Bot Message
    $stmt = $pdo->prepare("INSERT INTO messages (session_id, sender, content) VALUES (?, 'bot', ?)");
    $stmt->execute([$session_id, $bot_message]);

    // Track analytics (total responses)
    $stmt = $pdo->prepare("INSERT INTO analytics (event_name, event_value) VALUES ('bot_response', '1')");
    $stmt->execute();

    // Fetch updated stats for real-time dashboard update
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages");
    $total_chats = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(DISTINCT session_id) FROM messages");
    $active_users = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM analytics WHERE event_name = 'bot_response'");
    $ai_responses = $stmt->fetchColumn();

    // Re-calculate success rate
    $success_rate = $total_chats > 0 ? floor(($ai_responses / ($total_chats / 2)) * 100) : 0;
    if ($success_rate > 100) $success_rate = 99;

    echo json_encode([
        "message" => $bot_message,
        "stats" => [
            "total_chats" => $total_chats,
            "active_users" => $active_users,
            "ai_responses" => $ai_responses,
            "success_rate" => $success_rate
        ]
    ]);

}
?>

