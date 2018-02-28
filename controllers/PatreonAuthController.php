<?php
namespace Craft;

require_once(craft()->path->getPluginsPath() . 'patreonauth/vendor/autoload.php');

class PatreonAuthController extends BaseController
{
	protected $allowAnonymous = true;

	public function actionStartAuth()
	{
		$startUrl = craft()->request->getQuery('startUrl', null);
		if(!empty($startUrl))
		{
			craft()->userSession->setState('patreonAuth_startUrl', $startUrl);
		}
		$settings = craft()->plugins->getPlugin('patreonauth')->getSettings();
		$this->redirect('https://www.patreon.com/oauth2/authorize?response_type=code&client_id='.
			$settings->patreonClientId . '&redirect_uri=' .
			urlencode($settings->patreonUrlAuthEnd),
			true, 303);
		craft()->end();
	}

	public function actionFinishAuth()
	{
		$settings = craft()->plugins->getPlugin('patreonauth')->getSettings();
		// First get tokens
		$oauthClient = new \Patreon\OAuth($settings->patreonClientId, $settings->patreonClientSecret);
		$tokens = $oauthClient->get_tokens(craft()->request->getParam('code'), $settings->patreonUrlAuthEnd);
		if(isset($tokens['error']) && !empty($tokens['error']))
		{
			Craft::getLogger()->log('Auth failed for user from ' . $_SERVER['REMOTE_ADDR'] . ': ' . $tokens['error'], 'trace', false, 'application', 'PatreonAuth');
			$this->redirectToUrl();
			return;
		}
		// Save tokens to session
		craft()->userSession->setState('patreonAuth_accessToken',$tokens['access_token']);
		craft()->userSession->setState('patreonAuth_refreshToken',$tokens['refresh_token']);

		// We have tokens. Now request data for this user
		$apiClient = new \Patreon\API($tokens['access_token']);
		$patreonResponse = $apiClient->fetch_user();
		$userName=$patreonResponse['data']['attributes']['full_name'];
		$userId=$patreonResponse['data']['id'];
		$userImageUrl=$patreonResponse['data']['attributes']['image_url'];
		$userThumbUrl=$patreonResponse['data']['attributes']['thumb_url'];
		if(!isset($patreonResponse['included']))
		{
			// When "included" is not in response, user hasnt pledged us
			Craft::getLogger()->log('User ' . $userName . ' from ' . $_SERVER['REMOTE_ADDR'] . ' authed but cant fetch pledge-data.', 'trace', false, 'application', 'PatreonAuth');
			$this->setSessionVariables($userName,$userId,$userImageUrl,$userThumbUrl);
			$this->redirectToUrl($settings->patreonUrlWhenNoPledge);
			return;
		}
		$included=$patreonResponse['included'];

		$pledge=null;
		foreach($included AS $obj)
		{
			if($obj['type'] == 'pledge' && intval($obj['relationships']['creator']['data']['id']) == intval($settings->patreonCreatorId))
			{
				$pledge = $obj;
			}
		}
		if(null == $pledge)
		{
			Craft::getLogger()->log('Cant find pledge for user ' . $userName . ' from ' . $_SERVER['REMOTE_ADDR'] . '. Pledge: ' . print_r($pledge, true), 'trace', false, 'application', 'PatreonAuth');
			$this->setSessionVariables($userName,$userId,$userImageUrl,$userThumbUrl);
			$this->redirectToUrl($settings->patreonUrlWhenNoPledge);
			return;
		}

		// When user has to wait till next month to get access
		if($settings->patreonWaitOneMonth)
		{
			$UTC_TIMEZONE = new \DateTimeZone('UTC');
			$currentDate=new \DateTime(null, $UTC_TIMEZONE);
			// Construct time from data
			$pledgeValidAfter=\DateTime::createFromFormat(\DateTime::ATOM, $pledge['attributes']['created_at']);
			$pledgeValidAfter->setTimezone($UTC_TIMEZONE);
			// Set time to zero (we dont care about time)
			$pledgeValidAfter->setTime(0, 0, 0);
			$tmp=getdate($pledgeValidAfter->getTimestamp());
			// Set date to the beginning of the next month
			$pledgeValidAfter->setDate($tmp['year'], $tmp['mon']+1, 1);
			// DEBUG ONLY!!!
			//$pledgeValidAfter->sub(new \DateInterval('P0000-01-00T00:00:00'));
			if($pledgeValidAfter->getTimestamp() >= $currentDate->getTimestamp())
			{
				Craft::getLogger()->log('User ' . $userName . ' from ' . $_SERVER['REMOTE_ADDR'] . ' has a valid pledge ' . print_r($pledge, true) . ', but the next month is not started yet. currentDate=' . $currentDate->format('Y-m-d') . ' validAfter=' . $pledgeValidAfter->format('Y-m-d'), 'trace', false, 'application', 'PatreonAuth');
				$this->setSessionVariables($userName,$userId,$userImageUrl,$userThumbUrl);
				$this->redirectToUrl($settings->patreonUrlWhenUserHasToWait);
				return;
			}
		}

		// Work done. Set the session variable and finish!
		$this->setSessionVariables($userName,$userId,$userImageUrl,$userThumbUrl,$pledge['attributes']['amount_cents']);
		Craft::getLogger()->log('User ' . $userName . ' from ' . $_SERVER['REMOTE_ADDR'] . ' has a valid pledge. Amount: ' . $pledge['attributes']['amount_cents'] . ' Session-State set.', 'trace', false, 'application', 'PatreonAuth');
		$this->redirectToUrl();
	}

	private function setSessionVariables($userName,$userId,$imageUrl,$thumbUrl,$pledgeAmount=0)
	{
		craft()->userSession->setState('patreonAuth_username', $userName);
		craft()->userSession->setState('patreonAuth_userId', $userId);
		craft()->userSession->setState('patreonAuth_imageUrl', $imageUrl);
		craft()->userSession->setState('patreonAuth_thumbUrl', $thumbUrl);
		craft()->userSession->setState('patreonAuth_pledgeAmount', $pledgeAmount);
		craft()->userSession->setState('patreonAuth_userHasValidPledge', 1);
	}

	private function redirectToUrl($forceTarget=null, $default='/')
	{
		$redirectTo=$default;
		if(!empty($forceTarget))
		{
			$redirectTo=$forceTarget;
		}
		else if(craft()->userSession->hasState('patreonAuth_startUrl'))
		{
			$redirectTo=craft()->userSession->getState('patreonAuth_startUrl');
		}
		$this->redirect($redirectTo, true, 303);
		craft()->end();
	}

	public function getCreatorID(){
		return craft()->patreonAuth_PatreonAuthService->getCreatorID();
	}
}
