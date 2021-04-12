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
    $curl = curl_init("https://api.helloasso.com/oauth2/token");
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("content-type: application/x-www-form-urlencoded"));
    curl_setopt($curl, CURLOPT_POST, 1);

    $payload = [
      "grant_type" => "client_credentials",
      "client_id" => HA_CLIENT_ID,
      "client_secret" => HA_CLIENT_SECRET
    ];

    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($payload));

    $raw_content = do_curl_query($curl)->response;

    if (!file_exists(dirname(HELLOASSOV5_TOKENS_PATH))) {
        mkdir(dirname(HELLOASSOV5_TOKENS_PATH), 0700, true);
    }
    file_put_contents(HELLOASSOV5_TOKENS_PATH, $raw_content);
  }

  public function parseAccessToken(){
    // TODO
  }

  public function parseRefreshToken(){
    // TODO
  }
}
