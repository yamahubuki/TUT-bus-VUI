<?php


/**********************************************************************************
*
*	東京工科大学バス時刻表API　専用VUIモジュール
*
*	(c) 2019 Tago laboratory.
*
*   licensed by GPLv3
*
**********************************************************************************/

	/****************************************************
	*	設定
	****************************************************/

	//Alexaの応答遅延を加味して現在時刻に加算する値(秒)
		define("TIME_DELAY",5);
	//APIのベースURL。ただし、公式のものより広い範囲をベースとみなしているので注意
		define("BASE_URL","https://bus.t-lab.cs.teu.ac.jp/api/v1/");

	/****************************************************
	*	公開(外部から利用)する関数
	****************************************************/

	//時刻・出発地名称・到着地名称・件数を指定して時刻表を得る
	//結果はパースして連想配列で返す
	function getTimeTable($time,$fromName,$toName,$num){
		$URL=BASE_URL."timetables?".
			"datetime=".urlencode(timeToSTR($time+TIME_DELAY)).
			"&limit=".($num+1);
		if(busstopNameToInt($fromName)){
			$URL.="&from=".busstopNameToInt($fromName);
		}
		if(busstopNameToInt($toName)){
			$URL.="&to=".busstopNameToInt($toName);
		}
		$tmp=file_get_contents($URL,false);
		return parse($tmp,$num);
	}

	//時刻・出発地名称・件数を指定して時刻表を得る
	//結果はパースして連想配列で返す
	function getTimeTableFrom($time,$name,$num){
		$tmp=file_get_contents(
			BASE_URL."timetables?".
			"from=".busstopNameToInt($name).
			"&datetime=".urlencode(timeToSTR($time+TIME_DELAY)).
			"&limit=".($num+1)
		,false);
		return parse($tmp,$num);
	}

	//時刻・行先名称・件数を指定して時刻表を得る
	//結果はパースして連想配列で返す
	function getTimeTableTo($time,$name,$num){
		$tmp=file_get_contents(
			BASE_URL."timetables?".
			"to=".busstopNameToInt($name).
			"&datetime=".urlencode(timeToSTR($time+TIME_DELAY)).
			"&limit=".($num+1)
		,false);
		return parse($tmp,$num);
	}

	//取得した時刻表配列からVUI用の発言文字列を生成
	function toVoice($data){
		if ($data===null){
			return "申し訳ありません。APIサーバとの通信に失敗しました。しばらくたってから、再度お試しください。";
		}
		if ($data===array()){
			return "本日、お調べしたコースではこれ以降のバスの運行がありません。";
		}
		if (count($data)==0){
			return "このコースのバスの運行はありません。";
		} else {
			$ret="";
			$i=1;
			$flg=false;

			//１本目の出力
			if ($data[0]["is_shuttle"]){
				$ret.="現在、".$data[0]["from"]."からシャトル運航中です。";

				//シャトルが続く分をを読み飛ばす
				for(;$i<count($data);$i++){
					if(!$data[$i]["is_shuttle"]){
						break;
					}
				}
				//読み飛ばしをしたならその旨を読む
				if($i!==1){
					$ret.="シャトル運航が続きます。";
				}
			} else {
				$ret="次のバスは、".$data[0]["departure_time"]."に".$data[0]["from"]."から出発します。";
			}
			$lastPlace=$data[0]["from"];

			//２便目以降の出力
			if($i<count($data)){
				$ret.="その後は、";
			}
			for(;$i<count($data);$i++){
				if ($data[$i]["is_shuttle"]){
					if($flg==true){
						$ret.="です。";
					}
					$ret.=$data[$i]["departure_time"]."頃からは".$data[$i]["from"]."からのシャトル運行となります。";
					$flg=false;
					break;
				} else {
					if($flg==true){
						$ret.="、";
					}
					if ($lastPlace!=$data[$i]["from"]){
							$ret.=$data[$i]["from"]."から";
							$lastPlace=$data[$i]["from"];
					}
					$ret.=$data[$i]["departure_time"]."発";
					$flg=true;
				}
			}
			if($flg==true){
				$ret.="です。";
			}
			if($data[count($data)-1]["is_final"]){
				$ret.="このバスが、このコースの最終便です。";
			}
			return  $ret;
		}
	}

	/****************************************************
	*	内部用関数
	****************************************************/

	//取得したjsonデータをパースして連想配列にする
	function parse($tmp,$num){
		if ($tmp===false){
			return null;
		}
		$tmp=json_decode($tmp,true);
		if ($tmp["success"]!=true){
			return null;
		}
		$ret=[];
		for($i=0;$i<$num;$i++){
			if (count($tmp["timetables"])<=$i){
				if ($i>0){
					$ret[$i-1]["is_final"]=true;
				}
				return $ret;
			}
			$ret[$i]["is_final"]=false;
			$ret[$i]["is_shuttle"]=$tmp["timetables"][$i]["is_shuttle"];
			$ret[$i]["from"]=getCourseData($tmp,$tmp["timetables"][$i]["course_id"],"departure");
			$ret[$i]["to"]=getCourseData($tmp,$tmp["timetables"][$i]["course_id"],"arrival");
			$ret[$i]["departure_time"]=toTimeOnly($tmp["timetables"][$i]["departure_time"]);
			$ret[$i]["arrival_time"]=toTimeOnly($tmp["timetables"][$i]["arrival_time"]);
		}
		if (count($tmp["timetables"])==$num){
			$ret[$num-1]["is_final"]==true;
		}
		return $ret;
	}


	//日付・時刻・タイムゾーンが含まれるレスポンステキストから時刻情報のみを抜き出す
	function toTimeOnly($input){
		return preg_replace("/\d{4}-\d{2}-\d{2}T(\d{2}:\d{2}).*/","$1",$input);
	}

	//APIからのレスポンスの連想配列を基にコースの情報を取得する
	function getCourseData($response,$id,$dataName){
/*
		foreach($response["course"] as $data){
			if ($data["id"]==$id){
				if (isset($data[$dataName])){
					return $data[$dataName];
				}
			}
		}
		return "";
*/
		//バグ対策のため適当に返す
		if (isset($response["course"][$dataName]["name"])){
			return $response["course"][$dataName]["name"];
		} else {
			return "";
		}
	}

	//Unix形式時刻(int)からAPIに渡す時刻文字列を生成
	function timeToSTR($time=0){
		return date("Y-m-d H:i",$time);
	}

	//バス停の名前からAPIで使うバス停IDを得る
	//名前を間違っていたら0が返る
	function busstopNameToInt($name){
		switch($name){
			case "八王子みなみ野":
				return 1;
			case "図書館棟前":
				return 2;
			case "八王子駅南口":
				return 3;
			case "厚生棟前":
				return 4;
			case "学生会館":
				return 5;
			case "正門前ロータリー":
				return 6;
			default:
				if ($name!=""){
					trigger_error("Unknown busstopname '".urlencode($name)."' request",E_USER_WARNING);
				}
				return false;
		}
	}
