<?php
require_once("todobot_config.php"); 

function say($url, $chat_id, $answer) {
	file_get_contents($url . "/sendmessage?&chat_id=" . $chat_id . "&text=" . $answer . "&parse_mode=Markdown");
}

function keyboard($url, $chat_id, $answer, $markup) {
	file_get_contents($url . "/sendmessage?&chat_id=" . $chat_id . "&text=" . $answer . "&parse_mode=Markdown&reply_markup=" . $markup);
}

function tasks($mysqli, $url, $chat_id, $user_id, $date, $answer) {
	$keyboard = [];
	$temp = [];

	$query = "SELECT id FROM todobot_tasks WHERE user_id= " . $user_id . " AND complete=0";
	$result = $mysqli->query($query);

	$num_rows = $result->num_rows;

	if ($num_rows < 1) {
		$err_answer = "Вы пока не добавили ни одной задачи.";
		$keyboard[] = ["Завершённые задачи"];
		
		$reply_markup = json_encode([
		    'keyboard' => $keyboard, 
		    'resize_keyboard' => true,
		    'one_time_keyboard' => true
		]);

		keyboard($url, $chat_id, $answer, $reply_markup);	
		exit();
	}

	$counter = 1;
	$i = 1;
	$div = ($num_rows / 5) * 5; // Делим нацело и умножаем. Пример: 18 / 5 = 3. 3 * 5 = 15.
	$diff = $num_rows - $div; // 18 - 15 = 3
	while ($row = $result->fetch_assoc()) {
		// $temp[] = $row["id"];
		$temp[] = (string)$i++;

		if ($counter % 5 == 0) {
			$keyboard[] = $temp;
			$temp = [];
		}
		elseif ($num_rows - $counter == 0) $keyboard[] = $temp;

		++$counter;
	}
	$keyboard[] = ["Показать список дел"];

	$reply_markup = json_encode([
	    'keyboard' => $keyboard, 
	    'resize_keyboard' => true,
	    'one_time_keyboard' => true
	]);

	keyboard($url, $chat_id, $answer, $reply_markup);	
}

$bot_token = TOKEN;
$url = "https://api.telegram.org/bot" . $bot_token;

$content = file_get_contents("php://input");
$update = json_decode($content, TRUE);

$message = $update["message"];

$user_id = $message["from"]["id"];
$chat_id = $message["chat"]["id"];
$text = $message["text"];

$mysqli = new mysqli(DB_ADDRESS, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_errno) {
	$answer = "Не удалось подключиться: %s\n" . $mysqli->connect_error;
	file_get_contents($url . "/sendmessage?chat_id=" . $chat_id . "&text=" . $answer);
	exit();
}

$mysqli->set_charset("utf8");

$keyboard = [];

if ($text === "/start") {

}
elseif ($text === "Завершённые задачи") {
	$date = date("Y-m-d", time());
	$answer = "*Завершённые задачи:*\n";

	$query = "SELECT id, text FROM todobot_tasks WHERE user_id= " . $user_id . " AND complete=1";
	$result = $mysqli->query($query);

	$i = 1;
	while ($row = $result->fetch_assoc()) {
		$answer .= $i++ . ". " . $row["text"] . "\n";
	}
	$answer = urlencode($answer);

	$keyboard = [
		["Показать список дел"]
	];

	$reply_markup = json_encode([
	    'keyboard' => $keyboard, 
	    'resize_keyboard' => true,
	    'one_time_keyboard' => true
	]);

	keyboard($url, $chat_id, $answer, $reply_markup);
}
elseif ($text === "Показать список дел") {
	$date = date("Y-m-d", time());
	$answer = "*Список дел:*\n";

	$query = "SELECT id, text FROM todobot_tasks WHERE user_id= " . $user_id . " AND complete=0";
	$result = $mysqli->query($query);

	$i = 1;
	while ($row = $result->fetch_assoc()) {
		$answer .= $i++ . ". " . $row["text"] . "\n";
	}
	$answer = urlencode($answer);

	$keyboard = [
		["Завершить задачу", "Завершённые задачи"]
	];

	$reply_markup = json_encode([
	    'keyboard' => $keyboard, 
	    'resize_keyboard' => true,
	    'one_time_keyboard' => true
	]);

	keyboard($url, $chat_id, $answer, $reply_markup);
}
elseif ($text === "Завершить задачу") {
	$date = date("Y-m-d", time());
	$answer = "Выберите задачу для завершения.";
	tasks($mysqli, $url, $chat_id, $user_id, $date, $answer);
}
elseif(preg_match('~^[\d]+$~', $text)) {
	$keyboard = [];
	$temp = [];
	$date = date("Y-m-d", time());
	// $query = "UPDATE todobot_tasks SET complete=1 WHERE id=" . $text . " AND user_id=" . $user_id;

	$query_id = "SELECT id FROM todobot_tasks WHERE user_id= " . $user_id . " AND complete=0 ORDER BY id ASC LIMIT 1 OFFSET " . ((int)$text-1);
	$result = $mysqli->query($query_id);
	$row = $result->fetch_assoc();
	$id = $row["id"];

	$query = "UPDATE todobot_tasks SET complete=1 WHERE id=" . $id . " AND user_id=" . $user_id;

	if ($mysqli->query($query) === TRUE) {
	    $answer = "Задача <" . $text . "> выполнена.";
	} else {
	    $answer = "Ошибка: " . $query . "\n" . $mysqli->error;
	}

	tasks($mysqli, $url, $chat_id, $user_id, $date, $answer);
}
else {
	$date = date("Y-m-d", time());

	$query = "INSERT INTO todobot_tasks (user_id, date, date_complete, text, complete) VALUES ('$user_id', '$date', '$date', '$text', 0)";

	if ($mysqli->query($query) === TRUE) {
	    $answer = "Задача добавлена.";
	} else {
	    $answer = "Ошибка: " . $query . "<br>" . $mysqli->error;
	}

	$keyboard = [
		["Показать список дел"]
	];

	$reply_markup = json_encode([
	    'keyboard' => $keyboard, 
	    'resize_keyboard' => true,
	    'one_time_keyboard' => true
	]);

	keyboard($url, $chat_id, $answer, $reply_markup);
}

?>