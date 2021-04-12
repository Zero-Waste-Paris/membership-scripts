<?php

if(!defined('ZWP_TOOLS')){  die(); }
require_once(ZWP_TOOLS . 'lib/util.php');
require_once(ZWP_TOOLS . 'config.php');

const HELLOASSOV5_TOKENS_PATH  = __DIR__ . "/helloassoV5_tokens.json";

class HelloAssoV5Connector {

  public function getTokensFromScratch() {
    /**
     * For debugging purposes, to do a curl query from CLI:
     * curl -X POST 'https://api.helloasso.com/oauth2/token' -H 'content-type: application/x-www-form-urlencoded' --data-urlencode 'grant_type=client_credentials' --data-urlencode 'client_id=$CLIENT_ID' --data-urlencode 'client_secret=$CLIENT_SECRET'
     */
    $raw_content = $this->doHACurlQuery([
      "grant_type" => "client_credentials",
      "client_id" => HA_CLIENT_ID,
      "client_secret" => HA_CLIENT_SECRET
    ]);
    $this->writeTokensFile($raw_content);
  }

  public function parseAccessToken(){
    $tokens = $this->parseTokensAsArray();
    return $tokens["access_token"];

  }

  public function parseRefreshToken(){
    $tokens = $this->parseTokensAsArray();
    return $tokens["refresh_token"];
  }

  private function parseTokensAsArray(){
    return json_decode(file_get_contents(HELLOASSOV5_TOKENS_PATH), true);
  }

  public function refreshTokens(){
    /**
     * For debugging purposes, to do a curl query from CLI:
     * curl -X POST 'https://api.helloasso.com/oauth2/token' -H 'content-type: application/x-www-form-urlencoded' --data-urlencode 'grant_type=refresh_token' --data-urlencode 'client_id=$CLIENT_ID' --data-urlencode 'refresh_token=$REFRESH_TOKEN'
     */
    $raw_content = $this->doHACurlQuery([
      "grant_type" => "refresh_token",
      "client_id" => HA_CLIENT_ID,
      "refresh_token" => $this->parseRefreshToken()
    ]);
    $this->writeTokensFile($raw_content);
  }

  private function doHACurlQuery($payload){
    $curl = curl_init("https://api.helloasso.com/oauth2/token");
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("content-type: application/x-www-form-urlencoded"));
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($payload));

    return do_curl_query($curl)->response;
  }

  private function writeTokensFile($content){
    if (!file_exists(dirname(HELLOASSOV5_TOKENS_PATH))) {
        mkdir(dirname(HELLOASSOV5_TOKENS_PATH), 0700, true);
    }
    file_put_contents(HELLOASSOV5_TOKENS_PATH, $content);
  }

  // TODO:
  // - handle pagination
  // - handle the case where the tokens expired
  //   expired access token  => 401, {"message":"Authorization has been denied for this request."}
  //   expired refresh token => ???
}
