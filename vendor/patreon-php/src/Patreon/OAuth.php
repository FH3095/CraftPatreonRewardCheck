<?php
namespace Patreon;

class OAuth {
  private $client_id;
  private $client_secret;

  public function __construct($client_id, $client_secret) {
    $this->client_id = $client_id;
    $this->client_secret = $client_secret;
  }

  public function get_tokens($code, $redirect_uri) {
    return $this->__update_token(array(
        "grant_type" => "authorization_code",
        "code" => $code,
        "client_id" => $this->client_id,
        "client_secret" => $this->client_secret,
        "redirect_uri" => $redirect_uri
    ));
  }

  public function refresh_token($refresh_token, $redirect_uri) {
    return $this->__update_token(array(
        "grant_type" => "refresh_token",
        "refresh_token" => $refresh_token,
        "client_id" => $this->client_id,
        "client_secret" => $this->client_secret
    ));
  }

  private function __update_token($params) {
    $api_endpoint = "http://api.patreon.com/oauth2/token";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
	curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
    $ret=json_decode(curl_exec($ch), true);
	print_r(curl_getinfo($ch));
	print_r(http_build_query($params));
	return $ret;
  }
}