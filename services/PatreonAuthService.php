<?php
namespace Craft;

require_once(craft()->path->getPluginsPath() . 'patreonauth/vendor/patreon-php/src/patreon.php');

use \Patreon\API;
use \Patreon\OAuth;

class PatreonAuthService extends BaseApplicationComponent
{
	public function getCreatorID(){
		return craft()->plugins->getPlugin('patreonauth')->getSettings()->patreonCreatorId;
	}
}
