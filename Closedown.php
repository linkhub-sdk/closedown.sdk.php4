<?php
/**
* =====================================================================================
* Class for base module for Closedown API SDK. It include base functionality for
* RESTful web service request and parse json result. It uses Linkhub module
* to accomplish authentication APIs.
*
* This module uses curl and openssl for HTTPS Request. So related modules must
* be installed and enabled.
*
* http://www.linkhub.co.kr
* Author : Jeong yo han (yhjeong@linkhub.co.kr)
* Written : 2015-06-23
*
* Thanks for your interest.
* We welcome any suggestions, feedbacks, blames or anythings.
* ======================================================================================
*/

require_once 'Linkhub/linkhub.auth.php';
require_once 'Linkhub/JSON.php';

class Closedown
{
	var $token;

	//생성자
    function Closedown($LinkID,$SecretKey) {
    	$this->Linkhub = new Linkhub($LinkID,$SecretKey);
    	$this->VERS = '1.0';
    	$this->ServiceID = 'CLOSEDOWN';
    	$this->ServiceURL = 'https://closedown.linkhub.co.kr';
		$this->scopes[] = '170';
    }
            
    function getsession_Token() {
    	$Refresh = true;
		
		if(!is_null($this->token)){
    		$Expiration = gmdate($this->token->expiration);
    		$now = gmdate("Y-m-d H:i:s",time());
    		$Refresh = $Expiration < $now;
		}
  	
    	if($Refresh){
			
    		$this->token = $this->Linkhub->getToken($this->ServiceID, null, $this->scopes);
    		//TODO return Exception으로 처리 변경...

			if(is_a($this->token,'LinkhubException')) {
    			return new ClosedownException($this->token);
    		}
    	}
		
    	return $this->token->session_token;
    }

	//회원 잔여포인트 확인
    function GetBalance() {
    	$_Token = $this->getsession_Token(null);

    	if(is_a($_Token,'ClosedownException')) return $_Token;
    	
    	return $this->Linkhub->getPartnerBalance($_Token,$this->ServiceID);
    }

	// 검색단가 확인
	function GetUnitCost(){
    	$result = $this->executeCURL('/UnitCost');
		
		return $result->unitCost;
	}

	// 휴폐업조회 - 단건
	function checkCorpNum($CorpNum){

		if(is_null($CorpNum) || $CorpNum=== ""){
			return new ClosedownException('{"code": -99999999, "message": "사업자번호가 입력되지 않았습니다."}');
		}
		
		$url = '/Check?CN='.$CorpNum ;

		$result = $this->executeCURL($url);

		$CorpStateInfo= new CorpState();
		$CorpStateInfo->fromJsonInfo($result);
		
		return $CorpStateInfo;
	}

	//휴폐업조회 - 대량
	function checkCorpNums($corpNumList = array()){
		
		if(is_null($corpNumList) || empty($corpNumList)){
			return new ClosedownException('{"code": -99999999, "message": "사업자번호 배열이 입력되지 않았습니다."}');
		}
		
		$postdata = $this->Linkhub->json_encode($corpNumList);
		$result = $this->executeCURL('/Check', true, $postdata) ;
		
		$CorpStateList = array();
		
		for($i=0; $i<Count($result); $i++){
			$CorpState = new CorpState();
			$CorpState->fromJsonInfo($result[$i]);
			$CorpStateList[$i] = $CorpState;
		}
		return $CorpStateList;
	}
	
    function executeCURL($uri, $isPost = false, $postdata = null) {
		
		$http = curl_init(($this->ServiceURL).$uri);
		$header = array();

		$header[] = 'Authorization: Bearer '.$this->getsession_Token(null);
		$header[] = 'x-api-version: '.$this->VERS;
		$header[] = 'Content-Type: Application/json';

		if($isPost) {
			curl_setopt($http, CURLOPT_POST,1);
			curl_setopt($http, CURLOPT_POSTFIELDS, $postdata);   
		}
				
		curl_setopt($http, CURLOPT_HTTPHEADER,$header);
		curl_setopt($http, CURLOPT_RETURNTRANSFER, TRUE);
		
		$responseJson = curl_exec($http);
		$http_status = curl_getinfo($http, CURLINFO_HTTP_CODE);
		
		curl_close($http);
			
		if($http_status != 200) {
			return new ClosedownException($responseJson);
		}
		
		return $this->Linkhub->json_decode($responseJson);
	}
}


class CorpState
{
	var $corpNum;
	var $type;
	var $state;
	var $stateDate;
	var $checkDate;

	function fromJsonInfo($jsonInfo){
		isset($jsonInfo->corpNum) ? ($this->corpNum = $jsonInfo->corpNum): null;
		isset($jsonInfo->type) ? ($this->type = $jsonInfo->type) : $this->type = null;
		isset($jsonInfo->state) ? ($this->state = $jsonInfo->state) : null;
		isset($jsonInfo->stateDate) ? ($this->stateDate = $jsonInfo->stateDate) : null;
		isset($jsonInfo->checkDate) ? ($this->checkDate = $jsonInfo->checkDate) : null;
	}
}


// 예외클래스
class ClosedownException {
	var $code;
	var $message;

	function ClosedownException($responseJson) {
		if(is_a($responseJson,'LinkhubException')) {
			$this->code = $responseJson->code;
			$this->message = $responseJson->message;
			return $this;
		}
		$json = new Services_JSON();
		$result = $json->decode($responseJson);
		$this->code = $result->code;
		$this->message = $result->message;
		$this->isException = true;
		return $this;
	}
	function __toString() {
		return "[code : {$this->code}] : {$this->message}\n";
	}
}
?>
