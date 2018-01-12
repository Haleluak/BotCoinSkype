<?php
require_once("config.php");
require_once("httprequest.php");
function do_request($url)
{

	$reqobj = new httpRequestLib("");
	$reqobj->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.98 Safari/537.36');
	$data = $reqobj->doRequest($url);
	return $data;
}
function request_token()
{
	$reqobj = new httpRequestLib("https://login.microsoftonline.com/botframework.com/oauth2/v2.0/token");
	$reqobj->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.98 Safari/537.36');
	$postdata = array(
		"grant_type" => "client_credentials",
		"client_id" => $GLOBALS["APP_ID"],
		"client_secret" => $GLOBALS["APP_SECRET"],
		"scope" => "https://api.botframework.com/.default"
	);
	$reqobj->setPost($postdata, true);
	$data = $reqobj->doRequest();
	$json = json_decode($data);
	$token_file = $GLOBALS['TOKEN_FILE'];
	if($json && $json->access_token)
	{
		$t = time();
		$info = array("access_token" => $json->access_token, "expire_time" => ($t + $json->expires_in));
		$f = fopen($token_file, "w");
		fwrite($f, json_encode($info));
		fclose($f);
		return $json->access_token;
	}
	else
	{
		return "";
	}
}

function is_token_valid()
{
	$t = time();
	$token_file = $GLOBALS['TOKEN_FILE'];
	if(file_exists($token_file))
	{
		$data = file_get_contents($token_file);
		$expired = json_decode($data)->expire_time;
		if($expired <= $t)
			return false;
		return true;
	}
	return false;
}

function get_token()
{
	if(!is_token_valid())
	{
		return request_token();
	}
	$data = file_get_contents($GLOBALS['TOKEN_FILE']);
	return json_decode($data)->access_token;
}
function ask_eve()
{

	$default = array("Xin lỗi bạn qua ngu", "Bạn ngu do bẩm sinh, may mắn hay tài năng", "Mang não theo nhé -_-");
	return $default[rand(0, count($default) - 1)];
}
function reply($req, $res)
{
	$url = $req["serviceUrl"].'/v3/conversations/'.$req["conversation"]["id"].'/activities/'.$req["id"];
	$reqobj = new httpRequestLib($url);
	$reqobj->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.98 Safari/537.36');
	$reqobj->addHeader("Authorization: Bearer ".get_token());
	$reqobj->addHeader("Content-Type: application/json");
	$reqobj->setPost(json_encode($res), false);
	$reqobj->doRequest();
}
function build_response($info)
{
	$response = '{
		"type": "message",
		"from": {
			"id": "botId",
			"name": "botName"
		},
		"conversation": {
			"id": "conversationId",
			"name": "conversationName"
		},
		"recipient": {
			"id": "userId",
			"name": "userName"
		},
		"text": "Reply",
		"attachments": [
		],
		"replyToId": "activityId"
	}';
	$res = json_decode($response, true);
	$res["from"] = $info["bot"];
	$res["recipient"] = $info["user"];
	$res["conversation"] = $info["conversation"];
	$res["text"] = $info["text"];
	$res["replyToId"] = $info["id"];
	return $res;

}
function ask_author($text)
{
	if(stripos($text, "Viet") !== False)
		return true;
	if(stripos($text, "tac gia") !== False)
		return true;
	return false;
}
function coinmarketcap($coin)
{
	$url = 'https://api.coinmarketcap.com/v1/ticker/';
	$data = file_get_contents($url);
	$characters = json_decode($data, true);
	$key = array_search(strtoupper($coin), array_column($characters, 'symbol'));
	if($key !== False) {
		$coins =  $characters[$key];
		$result = 'USD price : ' . round($coins['price_usd'] , 2) .' USD'. 
		' <br /> BTC price: ' . sprintf("%.8f", $coins['price_btc']) . 
		' <br /> Rate24h : ' . $coins['percent_change_24h'] . '%';
	} else {
		return ask_eve();
	}
	return $result;
}
function bittrexcoin($coin)
{
		if(stripos($coin, "btc") !== False)
		{
			$url = 'https://bittrex.com/api/v1.1/public/getmarketsummary?market=usdt-btc';
		}
		else
		{
			$url = 'https://bittrex.com/api/v1.1/public/getmarketsummary?market=btc-'. $coin ;
		}
		$data = file_get_contents($url); // put the contents of the file into a variable
		$characters = json_decode($data); // decode the JSON feed
		if($characters->success)
		{
			$rate24h =  ($characters->result[0]->Last/$characters->result[0]->PrevDay - 1) * 100 ;
			$rate24h = $rate24h > 0  ? '+' . round(abs($rate24h), 1) . '%' : round($rate24h, 1) . '%';
			$result = 'Last price : ' . sprintf("%.8f", $characters->result[0]->Last) . 
		' <br /> High price: ' . sprintf("%.8f", $characters->result[0]->High) . 
		' <br /> Low price: ' . sprintf("%.8f", $characters->result[0]->Low) .
		' <br /> Rate24h: ' . $rate24h .		
		' <br /> BaseVolume: ' . $characters->result[0]->BaseVolume . ' BTC ';
		}
		else
		{
			return 0;
		}
		return $result;
}
function response()
{
	$req = json_decode(file_get_contents('php://input'), true);
	if($req)
	{
		$res = build_response($req);
		$coin = $res["text"];
		if (strpos($coin, 'HDjokerCoin') !== false) {
			$name = explode(" ", $coin);
			$coin = $name[1];
		}
		if(bittrexcoin($coin))
		{
			$res["text"] = bittrexcoin($coin);
		}
		else
		{
			$res["text"] = coinmarketcap($coin);
		}
		if (strpos($res["text"], 'lol') !== false || strpos($res["text"], 'okay') !== false || strpos($res["text"], 'troll') !== false || strpos($res["text"], 'moon') !== false)
		{
			$default = array("lol.jpg", "okay.jpg", "troll.jpg", "moon.jpg");
			$icon =  $default[rand(0, count($default) - 1)];
			$res["attachments"][] = array(
				 "contentType" => "image/jpg",
				"contentUrl" => "http://viewcoin.herokuapp.com/images/"  . $icon,
				"name" => $icon
			);			
		}
		reply($req, $res);
	}
}

?>