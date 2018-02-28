<?php
namespace Craft;

use Twig_Extension;
use Twig_Function_Method;

class PatreonAuthTwigExtension extends \Twig_Extension
{
	public function getName()
	{
		return 'PatreonAuth';
	}

	public function getFunctions()
	{
		return array(
			'getSessionVariable' => new \Twig_Function_Method($this, 'getSessionVariable'),
			'setSessionVariable' => new \Twig_Function_Method($this, 'setSessionVariable'),
			'getCreatorID' => new \Twig_Function_Method($this, 'getCreatorID'),
		);
	}

	public function getSessionVariable($name)
	{
		if(!craft()->userSession->hasState($name))
		{
			return null;
		}
		return craft()->userSession->getState($name);
	}

	public function setSessionVariable($name, $value)
	{
		craft()->userSession->setState($name, $value);
	}

	public function getCreatorID(){
		return craft()->plugins->getPlugin('patreonauth')->getSettings()->patreonCreatorId;
	}
}
