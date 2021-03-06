<?php
/*
 * Copyright 2008 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Authentication class that deals with 2-Legged OAuth
 * See http://sites.google.com/site/oauthgoog/2leggedoauth/2opensocialrestapi
 * for more information on the difference between 2 or 3 legged oauth
 *
 * @author Chris Chabot
 */
class osapiOAuth2Legged extends osapiAuth {
  protected $consumerToken;
  protected $accessToken;
  protected $userId;

  public function __construct($consumerKey, $consumerSecret, $userId = null) {
    $this->userId = $userId;
    $this->signatureMethod = new OAuthSignatureMethod_HMAC_SHA1();
    $this->consumerToken = new OAuthConsumer($consumerKey, $consumerSecret, null);
    $this->accessToken = null;
  }

  /**
   * Sign the request using OAuth. This uses the consumer token and key
   * but 2 legged oauth doesn't require an access token and key. In situations where you want to
   * do a 'reverse phone home' (aka: gadget does a makeRequest to your server
   * and your server wants to retrieve more social information) this is the prefered
   * method.
   *
   * @param string $method the method (get/put/delete/post)
   * @param string $url the url to sign (http://site/social/rest/people/1/@me)
   * @param array $params the params that should be appended to the url (count=20 fields=foo, etc)
   * @param string $postBody for POST/PUT requests, the postBody is included in the signature
   * @return string the signed url
   */
  public function sign($method, $url, $params = array(), $postBody = false) {
    $oauthRequest = OAuthRequest::from_request($method, $url, $params);

    $params = $this->mergeParameters($params);

    foreach ($params as $key => $val) {
      if (is_array($val)) {
        $val = implode(',', $val);
      }
      $oauthRequest->set_parameter($key, $val);
    }
    if ($postBody && strlen($postBody)) {
      $oauthRequest->set_parameter($postBody, '');
    }
    $oauthRequest->sign_request($this->signatureMethod, $this->consumerToken, $this->accessToken);
    if ($postBody) {
      unset($oauthRequest->parameters[$postBody]);
    }
    $signedUrl = $oauthRequest->to_url();
    return $signedUrl;
  }
  
  /**
   * Merges the supplied parameters with reasonable defaults for 2 legged oauth. User-supplied parameters
   * will have precedent over the defaults.
   *
   * @param array $params the user-supplied params that will be appended to the url
   * @return array the combined parameters
   */
  protected function mergeParameters($params) {
    $defaults = array('oauth_nonce' => md5(microtime() . mt_rand()),
        'oauth_version' => OAuthRequest::$version, 'oauth_timestamp' => time(),
        'oauth_consumer_key' => $this->consumerToken->key);
    
    if ($this->userId != null) {
      $defaults['xoauth_requestor_id'] = $this->userId;
    }
    if ($this->accessToken != null) {
      $params['oauth_token'] = $this->accessToken->key;
    }
    
    return array_merge($defaults, $params);
  }
}
