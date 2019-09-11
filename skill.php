<?php

ini_set('display_errors','Off');

require_once dirname(__FILE__).'/alexa-sdk-master/alexa-sdk.php';
use Alexa\Skill_Template;
use Alexa\Exception;
require_once dirname(__FILE__)."/mod_TUT-busVUI/module.php";
require(__FILE__)."/settings.php");


class Simple_Skill extends Skill_Template {
	public function intent_request() {

		$timestamp=strtotime($this->input()->request()->get_timestamp());
		if ($timestamp+150<=time()){
			return400("request is timed out.",901);
		}

		$intent_name=$this->input()->request()->intent()->get_name();
		$target=$this->input()->request()->intent()->get_slot_value("busstops");
		$from=$this->input()->request()->intent()->get_slot_value("from");
		$to=$this->input()->request()->intent()->get_slot_value("to");

		if ($intent_name=="checkNow"){
			$text=toVoice(getTimeTable(time(),$from,$to,4));
		} else {
			$text='You started the skill!'.
			$intent_name."がリクエストされました。";
			$text.="targetには".$target."、";
			$text.="rfromには".$from."、";
			$text.="toには".$to."がセットされています。";
		}
		$this->output()->response()->output_speech()->set_text($text);
		$this->output()->response()->end_session();
	}
}




$headers=getallheaders();
request_check();
$chain=file_get_contents($headers["SignatureCertChainUrl"]);
certChainValidation($chain);



$simple_skill = new Simple_Skill(ALEXA-SKILL-ID);
try{
	$simple_skill->run();
} catch( Exception $exception) {
	$simple_skill->log( $exception->getMessage());
	echo $exception->getMessage();
}


//Alexaからのリクエストの正当性の確認
//	公式ドキュメント：
//	https://developer.amazon.com/ja/docs/custom-skills/host-a-custom-skill-as-a-web-service.html#check-request-signature
function request_check(){
	//STEP1 リクエストヘッダを取得
	$headers=getallheaders();

	//STEP2 必要なヘッダが存在することを確認
	if (empty($headers["SignatureCertChainUrl"])){
		return400("required header ('SignatureCertChainUrl') not found.",201);
	} else if (empty($headers["Signature"])){
		return400("required header ('Signature') not found.",202);
	}

	//STEP3 SignatureCertChainUrl の検証
	$url=URLRegularize($headers["SignatureCertChainUrl"]);

	if (!preg_match("!^https://s3.amazonaws.com/echo.api/.*!",$url)
		&& !preg_match("!^https://s3.amazonaws.com:443/echo.api/.*!",$url)
	){
		return400("'SignatureCertChainUrl' is bad.",301);
	}
}


//パス中の"../"や"./"や"//"を削除して、きれいなURLにする
//プロトコル名・ドメインは小文字に統一する
//ユーザ名・パスワード・フラグメントは、含まれていれば削除する
function URLRegularize($url){
	$parts=parse_url($url);
	if($parts===false){
		return false;
	}

	//結果の保存用変数
	$ret="";

	//プロトコル名・サーバ名を小文字に統一してつなげる
	$ret.=mb_strtolower($parts["scheme"])."://";
	$ret.=mb_strtolower($parts["host"]);
	if(isset($parts["port"])){
		$ret.=":".mb_strtolower($parts["port"]);
	}
	$ret.="/";

	//パスのドット部分と重複した/の削除
	$paths=explode("/",$parts["path"]);
	for($i=0;$i<count($paths);$i++){
		if($paths[$i]=="."){
			array_splice($paths,$i,1);
			$i--;
		} else if($paths[$i]==""){
			array_splice($paths,$i,1);
			$i--;
		} else if($paths[$i]==".."){
			array_splice($paths,$i,1);
			$i--;
			if ($i>=0){
				array_splice($paths,$i,1);
				$i--;
			}
		}
	}
	$ret.=implode("/",$paths);
	if (isset($parts["query"])){
		$ret.="?".$parts["query"];
	}
	return $ret;
}


//セキュリティ的な意味はないがそれっぽく検証している証明書チェーン検証
function certChainValidation($chain){
	//つながった証明書を\nで区切って配列にする
	$chain=explode("\n",$chain);

	//配列中のbeginやendの列を削除しながら１つずつの証明書に分ける
	$no=0;
	$certs=[];
	foreach($chain as $row){
		if ($row=="-----BEGIN CERTIFICATE-----"){
			$certs[$no][]=$row;
			continue;
		} else if ($row=="-----END CERTIFICATE-----"){
			$certs[$no][]=$row;
			$no++;
			continue;
		} else {
			$certs[$no][]=$row;
		}
	}

	//各証明書をln区切りの１つの文字列に直す
	foreach($certs as &$cert){
		$cert=implode("\n",$cert);
	}


	for($i=count($certs)-3;$i>=0;$i--){
		if(
			md5(serialize(openssl_x509_parse($certs[$i])['issuer']))
			!=
			md5(serialize(openssl_x509_parse($certs[$i+1])['subject']))
		){
			return400("SignatureCertChain is not valid",401);
		}
	}

	$ca[0]=CA_FILE;

	if (!openssl_x509_checkpurpose(
		openssl_x509_read(
			$certs[count($certs)-2]
		),
		X509_PURPOSE_SSL_SERVER,
		$ca
	)){
		return400("chain's root certificate can not trust",402);
	}

	//リクエスト署名の検証
	$publicKey=(openssl_pkey_get_public($certs[0]));
	$signature=base64_decode(getallheaders()["Signature"]);
	$file=file_get_contents("php://input");
	if (!openssl_verify($file,$signature,$publicKey,"sha1")){
		trigger_error("Signature is not Valid.",E_USER_WARNING);
	}
}

//不正なリクエストに対して400を返して終了する
function return400($message,$code){
	http_response_code(400);
	header("content-type: application/json");
	$ret["result"]="NG";
	$ret["message"]=$message;
	$ret["code"]=$code;
	echo makeJson($ret);
	die();
}

//jsonを生成して返す
function makeJson($input){
	$json=json_encode($input,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	if ($json===false){
		http_response_code(500);
		die();
	}
	return $json;
}
