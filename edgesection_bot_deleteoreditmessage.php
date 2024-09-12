<?php

	//На этот файл ссылаться через Callback API VK

	date_default_timezone_set('Europe/Moscow');
	
	/*$connectCheckSub = mysqli_connect("localhost", "login", "password", "edgesection") or die("err_connect_check");
	$tokenES = "";
	$check = mysqli_fetch_assoc(mysqli_query($connectCheckSub, "SELECT * FROM `token` WHERE `token` = '{$tokenES}'"));
	if(($check['time'] + $check['timeSub']) < time()){
		echo '<h1>Подписка закончилась</h1>';
		exit;
	}
	mysqli_close($connectCheckSub);*/

	$user_token = "";

	$connect = mysqli_connect("localhost", "login", "password", "db") or die("err_connect");
	if(mysqli_error($connect)){
		echo "<h3>err_connect</h3>: ".mysqli_error($connect);
		exit;
	}
	
	mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `messages` (`id` INT PRIMARY KEY AUTO_INCREMENT, `group_id` INT, `date` INT, `from_id` INT, `idMessage` INT, `admin_author_id` INT, `conversation_message_id` INT, `peer_id` INT, `text` TEXT, `act` TEXT, `notified` INT, `time` INT)");
	
	$data = json_decode(file_get_contents('php://input')); 
	
	//-----------------------------
	//Если с момента запроса прошло более 10 секунд (отменяет действие)

	$timeout = 10;
	$time = time();
	$dateMessage = $data->object->date;

	if((integer) $dateMessage < ((integer) $time - $timeout)){
		echo "ok";
		exit;
	}

	//-----------------------------
	
	if($data->type == "confirmation"){
		
		echo "1"; //Сюда код подтверждения
		exit;
		
	}
	
	if($data->type == "message_reply"){
		
		mysqli_query($connect, "
			INSERT INTO `messages` 
			(
				`group_id`, 
				`date`, 
				`from_id`, 
				`idMessage`, 
				`admin_author_id`, 
				`conversation_message_id`, 
				`peer_id`, 
				`text`, 
				`act`, 
				`time`
			) 
			VALUES 
			(
				".$data->group_id.", 
				".$data->object->date.", 
				".$data->object->from_id.", 
				".$data->object->id.", 
				".$data->object->admin_author_id.", 
				".$data->object->conversation_message_id.", 
				".$data->object->peer_id.", 
				'".$data->object->text."', 
				'delete', 
				".time()."
			)
		");
		
	}else if($data->type == "message_new"){
		
		/*mysqli_query($connect, "
			INSERT INTO `messages` 
			(
				`date`, 
				`from_id`, 
				`idMessage`, 
				`admin_author_id`, 
				`conversation_message_id`, 
				`peer_id`, 
				`act`, 
				`time`
			) 
			VALUES 
			(
				".$data->object->message->date.", 
				".$data->object->message->from_id.", 
				".$data->object->message->id.", 
				0, 
				".$data->object->message->conversation_message_id.", 
				".$data->object->message->peer_id.", 
				'delete', 
				".time()."
			)
		");*/
		
	}else if($data->type == "message_edit"){
		
		//Писать сообщение об редактировании
		if($data->object->date >= (time() - 259200)){
			
			$oldText = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM `messages` WHERE `group_id` = ".$data->group_id." AND `idMessage` = ".$data->object->id.""));
			if($oldText['id'] <= 0){
				mysqli_query($connect, "INSERT INTO `messages` (`group_id`, `date`, `from_id`, `idMessage`, `admin_author_id`, `conversation_message_id`, `peer_id`, `text`, `act`, `time`) VALUES (".$data->group_id.", ".$data->object->date.", ".$data->object->from_id.", ".$data->object->id.", ".$data->object->admin_author_id.", ".$data->object->conversation_message_id.", ".$data->object->peer_id.", '".$data->object->text."', 'delete', ".time().")");
				$oldText = $data->object->text;
			}else{
				$oldText = $oldText['text'];
			}
			$infoUser = json_decode(file_get_contents("https://api.vk.com/method/users.get?user_ids=".$data->object->admin_author_id."&access_token=".$user_token."&v=5.131"));
			$infoGroup = json_decode(file_get_contents("https://api.vk.com/method/groups.getById?group_id=".$data->group_id."&access_token=".$user_token."&v=5.131"));
			
			file_get_contents("https://api.vk.com/method/messages.send?".http_build_query(array(
				'message' => "
					В группе @".$infoGroup->response[0]->screen_name."(".$infoGroup->response[0]->name.")
					Сообщение: ".$oldText."
					Изменено на: ".$data->object->text."\n\n
					🔁 Изменил: @id".$data->object->admin_author_id."(".$infoUser->response[0]->first_name." ".$infoUser->response[0]->last_name.")
				",
				'peer_id' => 290111268, //Идентификатор пользователя, которому отправится сообщение
				'access_token' => "", //Токен группы, от лица которой отправится сообщение
				'v' => "5.131",
				'random_id' => rand(0,100)
			)));
			
			mysqli_query($connect, "UPDATE `messages` SET `text` = '".$data->object->text."' WHERE `group_id` = ".$data->group_id." AND `idMessage` = ".$data->object->id."");
			
		}
		
	}
	
	echo "ok";
	exit;

?>