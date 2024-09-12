<?php

	date_default_timezone_set('Europe/Moscow');
	
	/*$connectCheckSub = mysqli_connect("localhost", "login", "password", "edgesection") or die("err_connect_check");
	$tokenES = "";
	$check = mysqli_fetch_assoc(mysqli_query($connectCheckSub, "SELECT * FROM `token` WHERE `token` = '{$tokenES}'"));
	if(($check['time'] + $check['timeSub']) < time()){
		echo '<h1>Подписка закончилась</h1>';
		exit;
	}
	mysqli_close($connectCheckSub);*/

	$connect = mysqli_connect("localhost", "login", "password", "db") or die("err_connect");
	if(mysqli_error($connect)){
		echo "<h3>err_connect</h3>: ".mysqli_error($connect);
		exit;
	}
	
	$group_token = "";
	$user_token = "";
	
	$id_group_check = 102574847; //Идентификатор группы, где проверяются сообщение (Токен в переменной $group_token должен быть указанной в переменной группы)
	
	mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `messages` (`id` INT PRIMARY KEY AUTO_INCREMENT, `group_id` INT, `date` INT, `from_id` INT, `idMessage` INT, `admin_author_id` INT, `conversation_message_id` INT, `peer_id` INT, `text` TEXT, `act` TEXT, `notified` INT, `time` INT)");
	
	mysqli_query($connect, "DELETE FROM `messages` WHERE `date` < ".(time() - 259200)."");
	
	//--------------
	//102574847
	//--------------
	
	$messages = mysqli_query($connect, "SELECT * FROM `messages` WHERE `group_id` = ".$id_group_check." AND `notified` = 0 ORDER BY `id` DESC LIMIT 100");
	
	$ids_messages = array();
	
	while($message = mysqli_fetch_assoc($messages)){
		array_push($ids_messages, $message['idMessage']);
	}
	
	$ids_messages = implode(",", $ids_messages);
	
	echo "Непроверенные сообщения: ".$ids_messages."<br>";
	
	$checkOfVK = json_decode(file_get_contents("https://api.vk.com/method/messages.getById?message_ids=".$ids_messages."&access_token=".$group_token."&v=5.131"));
	
	$messagesIdDeleted = array();
	
	for($i = 0; $i <= $checkOfVK->response->count; $i++){
		
		if($checkOfVK->response->items[$i]->deleted == 1){
			array_push($messagesIdDeleted, $checkOfVK->response->items[$i]->id);
			mysqli_query($connect, "UPDATE `messages` SET `notified` = 1 WHERE `group_id` = ".$id_group_check." AND `idMessage` = ".$checkOfVK->response->items[$i]->id."");
			
			sleep(0);
		}
		
	}
	
	echo "Удалённые сообщения: ".implode(",", $messagesIdDeleted)."<br>";
	
	if(count($messagesIdDeleted) >= 1){
		
		$textForDM = "";
		$deletedMessage = json_decode(file_get_contents("https://api.vk.com/method/messages.getById?message_ids=".implode(",", $messagesIdDeleted)."&access_token=".$group_token."&v=5.131"));
		for($i = 0; $i < $deletedMessage->response->count; $i++){
			$infoUser = json_decode(file_get_contents("https://api.vk.com/method/users.get?user_ids=".$deletedMessage->response->items[$i]->admin_author_id."&access_token=".$user_token."&v=5.131"));
			
			$textForDM = $textForDM."В группе @club102574847(EDGESECTION)\n Сообщение с текстом: ".$deletedMessage->response->items[$i]->text."\n❌ Удалил: @id".$deletedMessage->response->items[$i]->admin_author_id."(".$infoUser->response[0]->first_name." ".$infoUser->response[0]->last_name.")\n\n";
		}
		
		file_get_contents("https://api.vk.com/method/messages.send?".http_build_query(array(
			'message' => $textForDM,
			'peer_id' => 1, //Идентификатор человека, которому отправлять сообщение
			'access_token' => "", //Токен группы, от лица которой будет отправляться сообщение
			'v' => "5.131",
			'random_id' => rand(0,100)
		)));
		
	}

?>