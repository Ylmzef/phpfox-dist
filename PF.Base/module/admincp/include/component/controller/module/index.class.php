<?php
/**
 * [PHPFOX_HEADER]
 */

defined('PHPFOX') or exit('NO DICE!');

/**
 * 
 * 
 * @copyright		[PHPFOX_COPYRIGHT]
 * @author  		Raymond Benc
 * @package  		Module_Admincp
 * @version 		$Id: index.class.php 1931 2010-10-25 11:58:06Z Raymond_Benc $
 */
class Admincp_Component_Controller_Module_Index extends Phpfox_Component
{
	/**
	 * Controller
	 */
	public function process()
	{
		Phpfox::getUserParam('admincp.can_manage_modules', true);
		
		if ($aVals = $this->request()->getArray('val'))
		{
			if (Phpfox::getService('admincp.module.process')->updateActive($aVals))
			{
				$this->url()->send('admincp.module', null, Phpfox::getPhrase('admincp.module_s_updated'));
			}			
		}		
		
		if ($sDeleteId = $this->request()->get('delete'))
		{
			$sCachePhrase = Phpfox::getPhrase('admincp.module_successfully_deleted');
			
			if (Phpfox::getService('admincp.module.process')->delete($sDeleteId))
			{
				$this->url()->send('admincp.module', null, $sCachePhrase);
			}
		}
		
		if (($sModuleInstall = $this->request()->get('install')))
		{
			if (Phpfox::getService('admincp.module.process')->install($sModuleInstall, array(
						'table' => true,
						'post_install' => true,
						'insert' => true
					)
				)
			)
			{
				$sCachePhrase = Phpfox::getPhrase('admincp.module_successfully_installed');
				
				Phpfox::getLib('cache')->remove();
				
				$this->url()->send('admincp.module', null, $sCachePhrase);
			}
		}

		$modules = [];
		$aModules = Phpfox::getService('admincp.module')->get(true);
		if (!isset($aModules['3rdparty'])) {
			$aModules['3rdparty'] = [];
		}

		foreach ($aModules['3rdparty'] as $key => $value) {
			if ($value['product_id'] == 'phpfox' && $this->request()->get('view') != 'all') {
				continue;
			}

			$modules[$key] = $value;
		}
		
		$this->template()->setTitle(Phpfox::getPhrase('admincp.manage_modules'))
			->setBreadCrumb(Phpfox::getPhrase('admincp.manage_modules'))
			->assign(array(
				'aModules' => $modules
			)
		);		
	}
	
	/**
	 * Garbage collector. Is executed after this class has completed
	 * its job and the template has also been displayed.
	 */
	public function clean()
	{
		(($sPlugin = Phpfox_Plugin::get('admincp.component_controller_module_index_clean')) ? eval($sPlugin) : false);
	}
}

?>