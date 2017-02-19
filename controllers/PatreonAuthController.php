<?php
namespace Craft;

require_once(craft()->path->getPluginsPath() . '/patreonauth/vendor/patreon-php/src/patreon.php');

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
		if(!isset($patreonResponse['included']))
		{
			// When "included" is not in response, user hasnt pledged us -> deny
			Craft::getLogger()->log('User ' . $userName . ' from ' . $_SERVER['REMOTE_ADDR'] . ' authed but cant fetch pledge-data.', 'trace', false, 'application', 'PatreonAuth');
			$this->redirectToUrl($settings->patreonUrlWhenNoPledge);
			return;
		}
		$included=$patreonResponse['included'];

		$pledge=null;
		$reward=null;
		foreach($included AS $obj)
		{
			if($obj['type'] == 'pledge' && intval($obj['relationships']['creator']['data']['id']) == intval($settings->patreonCreatorId))
			{
				$pledge = $obj;
			}
			else if($obj['type'] == 'reward' && intval($obj['relationships']['creator']['data']['id']) == intval($settings->patreonCreatorId) &&
					isset($obj['attributes']['title']) && strcasecmp($obj['attributes']['title'],$settings->patreonRewardTitle)==0)
			{
				$reward = $obj;
			}
		}
		if(null == $pledge || null == $reward)
		{
			Craft::getLogger()->log('Cant find pledge or reward for user ' . $userName . ' from ' . $_SERVER['REMOTE_ADDR'] . '. Pledge: ' . print_r($pledge, true) . "\n Reward: " . print_r($reward,true), 'trace', false, 'application', 'PatreonAuth');
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
				Craft::getLogger()->log('User ' . $userName . ' from ' . $_SERVER['REMOTE_ADDR'] . ' has a valid pledge, but the next month is not started yet. currentDate=' . $currentDate->format('Y-m-d') . ' validAfter=' . $pledgeValidAfter->format('Y-m-d'), 'trace', false, 'application', 'PatreonAuth');
				$this->redirectToUrl($settings->patreonUrlWhenUserHasToWait);
				return;
			}
		}

		// Work done. Set the session variable and finish!
		craft()->userSession->setState('patreonAuth_username', $userName);
		craft()->userSession->setState('patreonAuth_userHasValidPledge', 1);
		Craft::getLogger()->log('User ' . $userName . ' from ' . $_SERVER['REMOTE_ADDR'] . ' has a valid pledge. Session-State set.', 'trace', false, 'application', 'PatreonAuth');
		$this->redirectToUrl();
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
}
