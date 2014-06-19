<?php

header('Content-Type: text/html; charset=utf-8'); # charset fix


function connect_db()
{
	global $link;

	$host = "localhost";
	$user = "user";
	$password = "pass";
	$db = "db";

	$link = mysql_connect($host,$user,$password) 
		or die (mysql_error());

	mysql_select_db($db) 
		or die(mysql_error());

	mysql_query("SET CHARSET 'utf8'");
}

connect_db();


function is_loged_in()
{
	if(isset($_SESSION[id]) && $_SESSION[id] != null)
		return true;
	else
		return false;
}


function get_elements()
{
	$act = $_POST['act'];

	switch ($act)
	{
		case 'login':
			$elements = login();
			break;
		case 'useredit':
			$elements = user_edit();
			break;
		case 'restore':
			$elements = restore_password();
			break;
		case 'set_new_passwd':
			$elements = set_new_passwd();
			break;
		case 'user_register':
			$elements = user_register();
			break;
		default:
			break;
	}
	if (is_array($elements))
		return $elements;

	$mod = $_GET['mod'];	

	switch ($mod) 
	{
		case 'login':
			$elements = login_form();
			break;
		case 'logout':
			$elements = logout();	
			break;		
		case 'restore':
			if(isset($_GET[token]))
				$elements = set_new_passwd_form();
			else
				$elements = restore_password_form();
			break;
		case 'register':
			$elements = user_register_form();
			break;
		case 'useredit':
			$elements = user_edit_form();
			break;
		case 'confirm':
			$elements = user_reg_confirm();
			break;		
		case 'account':
			$elements = user_account();
			break;
		default:
			if(is_loged_in())
				$elements = user_account();
			else
				$elements = login_form();
			break;
	}
	return $elements;
}


function apply_template()
{
	$elements = get_elements();
	include_once('tpl/account.tpl');
}


function login_form()
{
	if(is_loged_in())
		header("Location: /account.php?mod=account");

	$out[title] = 'Форма входа';
	$out[content] = 
	'
		<form method="post" action="/account.php" id="login_form" name="login_form">
			<input name="act" value="login" type="hidden">
			<p>E-mail:<br/><input name="email" class="input" maxlength="100" type="text"></p>  
			<p>Пароль:<br/><input name="password" class="input" maxlength="100" type="password"></p> 
			<p><input value="Отправить" name="button" class="button" type="submit"> <a href="account.php?mod=restore">Восстановить пароль</a></p>
			<p><a href="account.php?mod=register">Регистрация</a> 
		</form>	
	';

	return $out;
}

function login()
{
	
	$errors = array();

	if(strlen($_POST[email]) == 0 || strlen($_POST[password]) == 0)
		header("Location: /account.php?mod=login");

	if (preg_match ("/^[a-zа-я0-9_\-\.]+\@[a-zа-я0-9\-\.]+\.[a-za-я]+/i", $_POST[email]))
		$email =  $_POST[email];
	else
		$errors[email] = "Введён неверный электронный адрес";

	$password_length_limit = 100;

	if (preg_match ("/.+/i", $_POST[password]) || count ($_POST[password]) < $password_length_limit )
		$password = md5($_POST[password]);
	else 
		$errors[password] = "Введён неверный пароль";
	
	if(count($errors))
	{
		$out[content] = 
		"
			<p>Ошибка:</p> 
			<ul>
		";

		if (isset($errors[email])) 		
			$out[content] .= "<li>" . $errors[email] . "</li>";

		if (isset($errors[password]))	
			$out[content] .= "<li>" . $errors[password] . "</li>";

		$out[content] .= 
		"
			</ul>
		";

		$out[content] .= '<p>Перейти к <a href="/account.php?mod=login">форме входа</p>';

		return $out;
	}

	$query = 
	"SELECT 
		`users`.`id_user`	, 
		`is_confirmed`		,
		`email`				, 
		`password` 
	FROM 
		`users` 
	INNER JOIN 
		`users_registration` 
			ON 
				`users`.`id_user` = `users_registration`.`id_user` 
	WHERE 
		`email` = '$email' 
		AND
		`password` = '$password' 
	";
	
	$result = mysql_query ($query) or die ("mysql error");
	$query_result = mysql_fetch_assoc ($result);

	if($email === $query_result[email] && $password === $query_result[password])
	{
		if($query_result[is_confirmed] == 1)
		{
			$_SESSION[id] = $query_result['id_user'];
			header("Location: /account.php");
		}
		elseif ($query_result[is_confirmed] == 0)
		{
			$out[title] = 'Авторизация';
			$out[content] .= "<p>Аккаунт не активирован. Провертье почту.</p>";
			$out[content] .= '<p>Перейти к <a href="/account.php?mod=login">форме входа</p>';
		}
	}
	else
	{
		$out[title] = 'Авторизация';
		$out[content] .= "<p>Неверная пара логин/пароль.</p>";
		$out[content] .= '<p>Перейти к <a href="/account.php?mod=login">форме входа</p>';
	}

	return $out;
}

function logout()
{
	if(!is_loged_in())
		header("Location: /account.php?mod=login");

	if(isset($_SESSION[id]))
	{
		unset($_SESSION[id]);
		header("Location: /account.php");
	}

}

function user_account() 
{
	if(!is_loged_in())
		header("Location: /account.php?mod=login");

	if (isset($_GET[uid]) && preg_match ("/^[0-9]+/i", $_GET[uid]))
	{
		$id_user =  (int) $_GET[uid];
		$is_own = false;
	}
	else
	{
		$id_user = $_SESSION[id];
		$is_own = true;
	}

	$query = 	"SELECT 
					`id_user`	,
					`name`		,
					`email` 
				FROM 
					`users`
				WHERE
					`id_user` = '$id_user' 
				";

	$result = mysql_query ($query) or die ("mysql error");
	$query_result = mysql_fetch_assoc ($result);	

	if($is_own == true)
	{
		$out[title] = "Личный кабинет";

		$out[content] .= "<p>Привет, " . $query_result[name] . '! [<a href="/account.php?mod=logout">Выход</a>]</p>';
		$out[content] .= 
		'
			<ul>
				<li><a href="/account.php?mod=useredit">Редактирование данных профиля</a></li>
			</ul>			
		';
		$out[content] .= "";

	}
	else
	{
		# TODO
	}

	return $out;
}

function user_register_form()
{
	if(is_loged_in())
		header("Location: /account.php");

	$out[title] = "Регистрация";

	$out[content] .= 
	'
		<div><a href="/account.php">&larr; К форме входа</a></div>
		<form method="post" action="/account.php" id="user_register_form" name="user_register_form">
			<input name="act" value="user_register" type="hidden">
			<p>Имя:<br/><input name="name" class="input" maxlength="100" value="" type="text"></p>  
			<p>E-mail:<br/><input name="email" class="input" maxlength="100" value="" type="text"></p>  
			<p>Пароль:<br/><input name="passwd" class="input" maxlength="100" value="" type="password"></p>
			<p><input value="Отправить" name="button" class="button" type="submit"></p>
		</form>	
	';

	return $out;
}

function user_register()
{
	if (preg_match ("/^[a-zа-я0-9_\-\.]+\@[a-zа-я0-9\-\.]+\.[a-za-я]+/i", $_POST[email]))
		$email =  $_POST[email];
	else
		$errors[email] = "Введён неверный электронный адрес";

	if(preg_match ("/^[a-zа-яёЁ]+(\s{1}[a-zа-яёЁ]+)?$/ui", $_POST[name] = trim ($_POST[name])))
		$name = $_POST[name];
	else 
		$errors[name] = "Введено некорректное имя";

	if (preg_match ("/^[a-zа-я0-9_\-\.]+/i", $_POST[passwd]))	
		$password = md5($_POST[passwd]);
	else
		$errors[passwd] = "Неверно введён пароль";

	if( mysql_num_rows (mysql_query ("SELECT email FROM `users` WHERE `email` = '$email' ")) > 0 )
		$errors[acc_email_exist] = "Этот email уже используется";


	$out[title] = "Регистрация";

	if(!count($errors))
	{
		// -- Добавляем нового пользователя -- 		

		$query = 
		"INSERT INTO `users`
		(
			`email`		, 
			`name`		, 
			`password`
		) 
		VALUES 
		(
			'$email'	,
			'$name'		,
			'$password'
		) ";

		$result = mysql_query ($query) or die ("mysql error");
		$query_result = mysql_fetch_assoc ($result);

		unset($query, $result, $query_result);


		// -- Записываем данные о регистрации в базу --

		$token = md5(mt_rand()) . md5(mt_rand());
		$time = date("Y-m-d H:i:s");
		$ip = ip2long($_SERVER['REMOTE_ADDR']);
		$is_confirmed = 0;	// не подтверждён

		$query = 
		"INSERT INTO `users_registration`
		(
			`id_user`		,
			`token`			,
			`time`			,
			`ip`			,
			`is_confirmed`
		) 
		VALUES 
		(
			( SELECT `id_user` FROM `users` WHERE `email` = '$email' ),
			'$token'		,
			'$time'			,
			'$ip'			,
			'$is_confirmed'
		) ";

		mysql_query($query) or die ("mysql error");


		//	-- Отправляем пользователю письмо --

		$to			= $email;
		$from 		= "email@here";
		$domain		= "domain.com";

		$subject =	'Восстановить пароль aккаунта';
		$headers =	'From: ' . $from . "\r\n" .
					'Reply-To: ' . $from . "\r\n" .
					'X-Mailer: PHP/' . phpversion() . "\r\n" .
					"Content-type: text/$type; charset=utf-8" . "\r\n" .
					"Mime-Version: 1.0" . "\r\n" ;

		$confirm_link = 
					'http://' . $domain . '/account.php?mod=confirm&' . 
					'token=' . $token .
					'&code=' . strrev ($ip);

		$message =	'Подтвердить регистрацию aккаунта: ' . "\r\n" .
					$confirm_link;
					
		mail($to, $subject, $message, $headers) 
			or die("mail error");


		$out[content] = 
		'
			<p>Пользователь зарегистрирован.</p>
			<p>Ссылка для подтверждения отправлена на почту: <b>' . $email . '</b></p>
			<div><a href="/account.php?mod=login">&larr; К форме входа</a></div>
		';
	}
	else
	{
		$out[content] = 
		'
			<p>Ошибка:<p>
			<ul>
		';

		foreach ($errors as $value) 
			$out[content] .= "<li>" . $value . "</li>";

		$out[content] .=
		'
			</ul>
			<div><a href="/account.php?mod=register">&larr; К форме регистрации</a></div>
		';
	}

	return $out;

}


function user_reg_confirm ()
{

	$out[title] = "Подтверждение";

	$errors = array();

	if (preg_match ("/^[a-f0-9]{64}$/i", $_GET[token]))
		$token =  $_GET[token];
	else
		$errors[token] = "Неверный token";

	if (preg_match ("/^[0-9]+$/i", $_GET[code]))
		$code = strrev ($_GET[code]);
	else
		$errors[code] = "Неверный code";

	$query = 
	"SELECT 
		`id_user`		,
		`token`			,
		`ip`			,
		`is_confirmed`
	FROM 
		`users_registration` 
	WHERE 
		`token` = '$token' 
		AND
		`ip` = '$code' ";

	$result = mysql_query ($query) or die ("mysql error");
	$query_result = mysql_fetch_assoc ($result);


	$result_mysql_num_rows = mysql_num_rows($result);
	$result_is_confirmed = $query_result[is_confirmed];
	$result_id_user = $query_result[id_user];
	$result_token = $query_result[token];
	$result_ip = $query_result[ip];

	unset($query, $result, $query_result);

	if($result_mysql_num_rows == 1 && !strcmp($result_token, $token) && !strcmp($result_ip,$code) && $result_is_confirmed == 0 ) 
	{

		$is_confirmed = 1;
		$confirmed_time = date("Y-m-d H:i:s");
		$confirmed_ip = ip2long($_SERVER['REMOTE_ADDR']);

		$query = 
		"UPDATE
			`users_registration`
		SET
			`is_confirmed` = '$is_confirmed',
			`confirmed_time` = '$confirmed_time',
			`confirmed_ip` = '$confirmed_ip'
		WHERE
			`id_user` = '$result_id_user' 
		";

		$result = mysql_query ($query) or die ("mysql error");
		$query_result = mysql_fetch_assoc ($result);

		$out[content] .= '<p>Вы подтвердили свою учётная запись. Теперь вы можете <a href="/account.php?mod=login">войти на сайт</a>.</p>';
	}	
	elseif ($result_is_confirmed == 1) 
	{
		$out[content] .= 
		'
			<p>Учётная запись уже подтверждена.</p>
			<div><a href="/account.php">&larr; К форме входа</a></div>
		';
	}
	else
		# токен и/или код не найден 
		header("Location: /account.php");	

	return $out;

}


function restore_password ()
{
	$out[title] = "Воcстановлние пароля";

	$errors = array();

	if (preg_match ("/^[a-zа-я0-9_\-\.]+\@[a-zа-я0-9\-\.]+\.[a-za-я]+/i", $_POST[email]))
		$email =  $_POST[email];
	else
		$errors[email] = "Введён неверный электронный адрес";

	$query = "SELECT `id_user`, `email` FROM `users` WHERE `email` = '$email' ";

	$result = mysql_query ($query) or die ("mysql error");
	$query_result = mysql_fetch_assoc ($result);

	$result_email = $query_result[email];
	$result_id_user = $query_result[id_user];

	if ($result_email !== $email || isset($errors[email]))
	{
		$out[content] = 
		'
			<p>Указанный адрес не найден!</p>
			<p>Попробуйте <a href="/account.php?mod=restore">ещё раз</a>.<p>
		';
		return $out;	
	}

	unset($query, $result, $query_result);

	// --

	$token = md5 (mt_rand()) . md5(mt_rand());
	$time = date ("Y-m-d H:i:s");
	$ip = ip2long ($_SERVER['REMOTE_ADDR']);

	$query =	"INSERT INTO `passwd_restore_request` " 
				.
				"(
					`id_user`,
					`token`,
					`time`,
					`ip`
				) " 
				.
				"VALUES 
				(
					'$result_id_user',
					'$token',
					'$time',
					'$ip'
				) ";

	$result = mysql_query ($query) or die ("mysql error");

	// --

	$to     	= $email;
	$from 		= "email@here";		# need to be set 
	$domain		= "domain.com";

	$subject =	'Восстановить пароль aккаунта';
	$headers =	'From: ' . $from . "\r\n" .
				'Reply-To: ' . $from . "\r\n" .
				'X-Mailer: PHP/' . phpversion() . "\r\n" .
				"Content-type: text/$type; charset=utf-8" . "\r\n" .
				"Mime-Version: 1.0" . "\r\n" ;


	$restore_link = 'http://' . $domain . '/account.php?mod=restore&token=' . $token . '&code=' . strrev ($ip);

	$message =	'Ссылка на воcстановление пароля: ' . "\r\n" . 
				$restore_link;
				
	mail($to, $subject, $message, $headers) or die("mail error");
	
	$out[content] = 
	'
		<p>Ссылка на восстановление пароля отправлена на адрес: <b>' . $to . '</b>.</p>
		<p>Перейти к <a href="/account.php?mod=login">форме входа</a><p>
	';
	return $out;

}

function restore_password_form ()
{
	if(is_loged_in())
		header("Location: /account.php");

	if (preg_match ("/^[a-f0-9]{64}$/i", $_POST[email]))
		$token =  $_POST[token];
	else
		$errors[token] = "Неверный token";

	$out[title] = "Восстановить пароль";

	$out[content] .= 
	'
		<div><a href="/account.php">&larr; К форме входа</a></div>
		<form method="post" action="/account.php" id="user_edit_form" name="user_edit_form">
			<input name="act" value="restore" type="hidden">
			<p>E-mail:<br/><input name="email" class="input" maxlength="100" value="" type="text"></p>  
			<p><input value="Восстановить" name="button" class="button" type="submit"></p>
		</form>	
	';

	return $out;

}

function set_new_passwd_form()
{
	$out[title] = "Восстановление пароля";

	$errors = array();

	if (preg_match ("/^[a-f0-9]{64}$/i", $_GET[token]))
		$token =  $_GET[token];
	else
		$errors[token] = "Неверный token";

	if (preg_match ("/^[0-9]+$/i", $_GET[code]))
		$code = strrev ($_GET[code]);
	else
		$errors[code] = "Неверный code";

	$query = 	"SELECT 
					`token`,
					`ip`,
					`users`.`name`
				FROM 
					`passwd_restore_request` 
				INNER JOIN 
					`users` 
						ON 
							`passwd_restore_request`.`id_user` = `users`.`id_user` 
				WHERE 
					`passwd_restore_request`.`token` = '$token' 
					AND
					`passwd_restore_request`.`ip` = '$code' 
					AND
					`passwd_restore_request`.`is_used` = '0'
				";

	$result = mysql_query ($query) or die ("mysql error");
	$query_result = mysql_fetch_assoc ($result);

	if(mysql_num_rows($result) == 1 && $query_result[token] === $token && $query_result[ip] === $code)
	{
		$out[content] .= "<p>Задайте новый пароль для пользователя <b>$query_result[name]</b>:</p>";
		$out[content] .= 
		'
			<form method="post" action="/account.php" id="login_form" name="login_form">
				<input name="act" value="set_new_passwd" type="hidden">
				<input name="token" value="'.$token.'" type="hidden">
				<input name="code" value="'.$code.'" type="hidden">
				<p>Новый пароль:<br/><input name="password" class="input" maxlength="100" type="password"></p>  
				<p>Подтверждение :<br/><input name="password_confirm" class="input" maxlength="100" type="password"></p> 
				<p><input value="Отправить" name="button" class="button" type="submit"></p>
			</form>	
		';
	}
	else
		header("Location: /account.php?mod=login");

	return $out;
}

function set_new_passwd()
{
	$out[title] = "Изменение пароля";
	$errors = array();

	if (preg_match ("/^[a-f0-9]{64}$/i", $_POST[token]))
		$token =  $_POST[token];
	else
		$errors[token] = "Неверный token";

	if (preg_match ("/^[0-9]+$/i", $_POST[code]))
		$code = $_POST[code];
	else
		$errors[code] = "Неверный code";

	$password_length_max = 100;
	$password_length_min = 3;

	if 	(
		preg_match ("/[a-zа-я0-9]+/i", $_POST[password]) &&
		strlen ($_POST[password]) < $password_length_max &&
		preg_match ("/[a-zа-я0-9]+/i", $_POST[password_confirm]) &&
		strlen ($_POST[password_confirm]) < $password_length_max &&
		strcmp($_POST[password], $_POST[password_confirm]) == 0
	)
	{
		$password = md5 ($_POST[password]);
	}
	elseif (!count($_POST[password]) != 0 && $_POST[password_confirm] != 0)
	{
		$errors[password] = "Ошибка при вводе пароля";
	}

	if(count($errors))
	{
		# TODO
		$out[content] .= "error";
		return $out;
	}

	$query = 	"UPDATE 
					`users` 
				SET 
					`password` = '$password'
				WHERE  
					`id_user` = 
					(
						SELECT 
							`id_user` 
						FROM 
							`passwd_restore_request` 
						WHERE 
							`token`= '$token' 
							AND
							`ip` = '$code'
					) 
				";

	$result = mysql_query ($query) or die ("mysql error");

	unset($query, $result);

	$time = date("Y-m-d H:i:s");
	$ip = ip2long($_SERVER[REMOTE_ADDR]);

	$query = 	"UPDATE
					`passwd_restore_request` 
				SET
					`is_used` = 1 			,
					`used_time` = '$time'	,
					`used_ip` = '$ip'
				WHERE
					`token`= '$token' 
					AND
					`ip` = '$code'
				";

	$result = mysql_query ($query) or die ("mysql error");

	$out[content] .= "<p>Настройки успешно изменены.</p>";
	$out[content] .= '<p>Перейти к <a href="/account.php?mod=login">форме входа</a><p>';

	return $out;
}


function user_edit_form()
{
	if(!is_loged_in())
		header("Location: /account.php?mod=login");

	$out[title] = "Редактирование данных профиля";

	$query = "SELECT `id_user`, `name`, `email` FROM `users` WHERE `id_user` = '$_SESSION[id]' ";

	$result = mysql_query ($query) or die ("mysql error");
	$query_result = mysql_fetch_assoc ($result);

	$out[content] = '<div><a href="/account.php">&larr; В личный кабинет</a></div>';

	$out[content] .= 
	'
		<form method="post" action="/account.php" id="user_edit_form" name="user_edit_form">
			<input name="act" value="useredit" type="hidden">
			<p>Имя:<br/><input name="name" class="input" maxlength="25" value="'.$query_result[name].'" type="text"></p>
			<p>E-mail:<br/><input name="email" class="input" maxlength="25" value="'.$query_result[email].'" type="text"></p>  
			<p>&nbsp;</p>
			<p>Изменить пароль:</p>
			<p>Пароль:<br/><input name="password" class="input" maxlength="25" type="password"></p> 
			<p>Ещё раз:<br/><input name="password_confirm" class="input" maxlength="25" type="password"></p>
			<p><input value="Отправить" name="button" class="button" type="submit"></p>
		</form>	
	';

	return $out;
}

function user_edit()
{
	if(!is_loged_in())
		header("Location: /account.php?mod=login");	

	$errors = array();

	if(preg_match ("/^[a-zа-яёЁ]+(\s{1}[a-zа-яёЁ]+)?$/ui", $_POST[name] = trim ($_POST[name])))
		$name = $_POST[name];
	else 
		$errors[name] = "Введено некорректное имя";

	if(preg_match ("/^[\w0-9\.-]+\@[\w0-9\.-]+\.[\w]+/i", $_POST[email]))
		$email = $_POST[email];
	else 
		$errors[email] = "Введён некорректный email";

	$password_length_max = 100;
	$password_length_min = 3;

	if (
			preg_match ("/[a-zа-я0-9]+/i", $_POST[password]) &&
			count ($_POST[password]) < $password_length_max &&
			preg_match ("/[a-zа-я0-9]+/i", $_POST[password_confirm]) &&
			count ($_POST[password_confirm]) < $password_length_max &&
			$_POST[password] === $_POST[password_confirm]
	)
	{
		$password = md5 ($_POST[password]);
		$password_query_string = ", `password` = '$password'";
	}
	elseif (!count($_POST[password]) != 0 && $_POST[password_confirm] != 0)
		$errors[password] = "Ошибка при вводе пароля";

	$out[title]	= "Изменение данных";

	if(!count($errors))
	{
		$query = 	"UPDATE 
						`users` 
					SET 
						`name` = '$name'	,
						`email` = '$email' 
						$password_query_string 
					WHERE 
						`id_user` = '$_SESSION[id]' ";
		$result = mysql_query ($query) or die ("mysql error");

		$out[content] .= "<p>Настройки успешно изменены</p>";
		$out[content] .= '<p>Перейти в <a href="/account.php">личный кабинет</a></p>';
	}
	else
	{
		$out[content] .= 
		"
			<p>$errors[name]</p>
			<p>$errors[email]</p>
			<p>$errors[password]</p>
		";
		$out[content] .=  '<p>Перейти к <a href="/account.php?mod=useredit">редактированию данных</a></p>';
	}

	return $out;

}

apply_template()


?>
