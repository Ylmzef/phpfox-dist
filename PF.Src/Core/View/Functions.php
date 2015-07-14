<?php

namespace Core\View;

class Functions {
	private $_method;
	private $_extra;

	public function __construct($method, $extra = null) {
		$this->_method = $method;
		$this->_extra = $extra;
	}

	public function __toString() {

		try {
			$Template = \Phpfox_Template::instance();

			switch($this->_method) {
				case 'footer':
					\Phpfox::getBlock('core.template-menufooter');
					break;
				case 'nav':
					\Phpfox::getBlock('feed.form2', ['menu' => true]);
					\Phpfox::getBlock('core.template-notification');
					\Phpfox::getBlock('core.template-menu');
					break;
				case 'content':
					if (!PHPFOX_IS_AJAX_PAGE) {
						echo '<div class="_block_' . $this->_method . '">';
					}
					$this->_loadBlocks(2);
					if ($this->_extra) {
						echo $this->_extra;
					}
					else {
						try {
							\Phpfox_Module::instance()->getControllerTemplate();
						} catch (\Exception $e) {
							exit($e->getMessage());
						}
					}
					$this->_loadBlocks(4);
					if (!PHPFOX_IS_AJAX_PAGE) {
						echo '</div>';
					}

					if (PHPFOX_IS_AJAX_PAGE) {
						$content = ob_get_contents(); ob_clean();
						return $content;
					}

					break;
				case 'top':
					$this->_loadBlocks(11);
					if (!PHPFOX_IS_AJAX_PAGE) {
						echo '<div class="_block_' . $this->_method . '">';
					}
					$Template->getLayout('search');
					if (!PHPFOX_IS_AJAX_PAGE) {
						echo '</div>';
					}

					$this->_loadBlocks(7);
					break;
				case 'errors':
					$Template->getLayout('error');
					break;
				case 'left':
					$this->_loadBlocks(1);
					break;
				case 'right':
					$this->_loadBlocks(3);
					break;
				case 'logo':
					\Phpfox::getBlock('core.template-logo');
					break;
				case 'breadcrumb':
					if (!PHPFOX_IS_AJAX_PAGE) {
						echo '<div class="_block_' . $this->_method . '">';
					}
					$Template->getLayout('breadcrumb');
					if (!PHPFOX_IS_AJAX_PAGE) {
						echo '</div>';
					}
					break;
				case 'title':
					echo $Template->getTitle();
					break;
				case 'h1':
					if (!PHPFOX_IS_AJAX_PAGE) {
						echo '<div class="_block_' . $this->_method . '">';
					}
					list($breadcrumbs, $title) = $Template->getBreadCrumb();
					if (count($title)) {
						echo '<h1><a href="' . $title[1] . '">' . \Phpfox_Parse_Output::instance()->clean($title[0]) . '</a></h1>';
					}
					if (!PHPFOX_IS_AJAX_PAGE) {
						echo '</div>';
					}
					break;
			}

		} catch (\Exception $e) {
			// throw new \Exception($e->getMessage(), $e->getCode(), $e);
			/*
			ob_clean();
			echo $e->getMessage();
			exit;
			*/
			register_shutdown_function(function() use($e) {
				ob_clean();
				throw new \Exception($e->getMessage(), $e->getCode(), $e);
			});
		}

		return '';
	}

	private function _loadBlocks($location) {
		if (\Phpfox_Template::instance()->bIsSample) {
			echo '<div class="block_sample" onclick="window.parent.$(\'#location\').val(' . $location . '); window.parent.js_box_remove(window.parent.$(\'.js_box\').find(\'.js_box_content\')[0]);">[Block: ' . $location . ']</div>';
			return;
		}

		echo '<div class="_block" data-location="' . $location . '">';
		if ($location == 3) {
			echo \Phpfox_Template::instance()->getSubMenu();
		}
		$blocks = \Phpfox_Module::instance()->getModuleBlocks($location);

		foreach ($blocks as $block) {
			\Phpfox::getBlock($block);
		}
		echo '</div>';
	}
}