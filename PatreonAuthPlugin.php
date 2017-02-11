<?php
namespace Craft;

class PatreonAuthPlugin extends BasePlugin
{
	/**
	 * @return mixed
	 */
	public function getName()
	{
		return Craft::t('Patreon Auth');
	}

	/**
	 * @return string
	 */
	public function getVersion()
	{
		return '1.0.0';
	}

	public function getSchemaVersion()
	{
		return '1.0.0';
	}

	/**
	 * @return string
	 */
	public function getDeveloper()
	{
		return 'Neoran';
	}

	/**
	 * @return string
	 */
	public function getDeveloperUrl()
	{
		return 'https://github.com/FH3095';
	}

	/**
	 * @return string
	 */
	public function getPluginUrl()
	{
		return '';
	}

	/**
	 * @return mixed
	 */
	public function getSettingsHtml()
	{
		return craft()->templates->render('patreonauth/_settings', array(
			'settings' => $this->getSettings()
		));
	}

	public function addTwigExtension()
	{
		Craft::import('plugins.patreonauth.twigextensions.PatreonAuthTwigExtension');
		return new PatreonAuthTwigExtension();
	}

	/**
	 * @return array
	 */
	protected function defineSettings()
	{
		return array(
			'patreonClientId'				=> array(AttributeType::String, 'required' => true),
			'patreonClientSecret'			=> array(AttributeType::String, 'required' => true),
			'patreonCreatorId'				=> array(AttributeType::Number, 'required' => true),
			'patreonUrlAuthEnd'				=> array(AttributeType::String, 'required' => true),
			'patreonRewardTitle'			=> array(AttributeType::String, 'required' => true),
			'patreonWaitOneMonth'			=> array(AttributeType::Bool),
			'patreonUrlWhenUserHasToWait'	=> array(AttributeType::String),
			'patreonUrlWhenNoPledge'		=> array(AttributeType::String),
		);
	}
}
