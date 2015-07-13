<?php
/**
 * [PHPFOX_HEADER]
 */

defined('PHPFOX') or exit('NO DICE!');

/**
 * Class we used to upload images within a hiddne iframe which gives the effect
 * that we are using AJAX to upload an image in the background.
 * 
 * @copyright		[PHPFOX_COPYRIGHT]
 * @author  		Raymond Benc
 * @package  		Module_Photo
 * @version 		$Id: frame.class.php 7200 2014-03-17 20:28:57Z Fern $
 */
class Photo_Component_Controller_Frame extends Phpfox_Component
{
	/**
	 * Controller
	 */
	public function process()
	{
		// We only allow users the ability to upload images.
		if (!Phpfox::isUser())
		{		
			exit;
		}

		if (isset($_REQUEST['picup']))
		{
			$_FILES['Filedata'] = $_FILES['image'];
			unset($_FILES['image']);
		}
		if (isset($_FILES['Filedata']) && !isset($_FILES['image'])) // photo.enable_mass_uploader == true
		{
			$_FILES['image'] = array();//$_FILES['Filedata'];
			$_FILES['image']['error']['image'] = UPLOAD_ERR_OK;
			$_FILES['image']['name']['image'] = $_FILES['Filedata']['name'];
			$_FILES['image']['type']['image'] = $_FILES['Filedata']['type'];
			$_FILES['image']['tmp_name']['image'] = $_FILES['Filedata']['tmp_name'];
			$_FILES['image']['size']['image'] = $_FILES['Filedata']['size'];
		}

		$fn = (isset($_SERVER['HTTP_X_FILENAME']) ? $_SERVER['HTTP_X_FILENAME'] : false);
		if ($fn) 
		{
			define('PHPFOX_HTML5_PHOTO_UPLOAD', true);

			$sHTML5TempFile = PHPFOX_DIR_CACHE . 'image_' . md5(PHPFOX_DIR_CACHE . $fn . uniqid());

			file_put_contents(
				$sHTML5TempFile,
				file_get_contents('php://input')
			);
			$_FILES['image'] = array(
				'name' => array($fn),
				'type' => array('image/jpeg'),
				'tmp_name' => array($sHTML5TempFile),
				'error' => array(0),
				'size' => array(filesize($sHTML5TempFile))
			);
		}

		// If no images were uploaded lets get out of here.
		if (!isset($_FILES['image']))
		{			
			exit;
		}
		
		// Make sure the user group is actually allowed to upload an image
		if (!Phpfox::getUserParam('photo.can_upload_photos'))
		{			
			exit;
		}
		if (($iFlood = Phpfox::getUserParam('photo.flood_control_photos')) !== 0)
		{
			$aFlood = array(
				'action' => 'last_post', // The SPAM action
				'params' => array(
					'field' => 'time_stamp', // The time stamp field
					'table' => Phpfox::getT('photo'), // Database table we plan to check
					'condition' => 'user_id = ' . Phpfox::getUserId(), // Database WHERE query
					'time_stamp' => $iFlood * 60 // Seconds);	
				)
			);
				 			
				// actually check if flooding
			if (Phpfox::getLib('spam')->check($aFlood))
			{				
				Phpfox_Error::set(Phpfox::getPhrase('photo.uploading_photos_a_little_too_soon') . ' ' . Phpfox::getLib('spam')->getWaitTime());	
			}
			
			if (!Phpfox_Error::isPassed())
			{				
				// Output JavaScript	
				echo '<script type="text/javascript">';
				if (!isset($bIsInline))
				{		
					echo 'window.parent.document.getElementById(\'js_progress_cache_holder\').style.display = \'none\';';
					echo 'window.parent.document.getElementById(\'js_photo_form_holder\').style.display = \'block\';';
					echo 'window.parent.document.getElementById(\'js_upload_error_message\').innerHTML = \'<div class="error_message">' . implode('', Phpfox_Error::get()) . '</div>\';';				
				}
				else
				{
					if (isset($aVals['is_cover_photo']))
					{
						echo 'window.parent.$(\'#js_cover_photo_iframe_loader_error\').html(\'<div class="error_message">' . implode('', Phpfox_Error::get()) . '</div>\').show();';
					}
					else
					{					
						echo 'window.parent.$Core.resetActivityFeedError(\'' . implode('', Phpfox_Error::get()) . '\');';
					}
				}
				echo '</script>';				
				exit;			
			}
		}		
		
		$oFile = Phpfox_File::instance();
		$oImage = Phpfox_Image::instance();
		$aVals = $this->request()->get('val');
		if (defined('PHPFOX_HTML5_PHOTO_UPLOAD'))
		{
			$aParts = explode('&', $_SERVER['HTTP_X_POST_FORM']);
			foreach ($aParts as $sPart)
			{
				$aReq = explode('=', $sPart);
				if (substr($aReq[0], 0, 3) == 'val')
				{
					$aVals[preg_replace('/val\[(.*?)\]/i', '\\1', $aReq[0])] = (isset($aReq[1]) ? $aReq[1] : '');
				}
			}
		}
		if (!is_array($aVals))
		{
			$aVals = array();
		}
		
		$bIsInline = false;
		if (isset($aVals['action']) && $aVals['action'] == 'upload_photo_via_share')
		{
			$bIsInline = true;
		}		
		
		$oServicePhotoProcess = Phpfox::getService('photo.process');
		$aImages = array();	
		$aFeed = array();
		$iFileSizes = 0;
		$iCnt = 0;
		
		(($sPlugin = Phpfox_Plugin::get('photo.component_controller_frame_start')) ? eval($sPlugin) : false);
		
		if (!empty($aVals['album_id']))
		{
			$aAlbum = Phpfox::getService('photo.album')->getAlbum(Phpfox::getUserId(), $aVals['album_id'], true);		
		}
		
		if (isset($_REQUEST['status_info']) && !empty($_REQUEST['status_info']))
		{
			$aVals['description'] = $_REQUEST['status_info'];
		}
		
		foreach ($_FILES['image']['error'] as $iKey => $sError)
		{	
			if ($sError == UPLOAD_ERR_OK) 
			{				
				if ($aImage = $oFile->load('image[' . $iKey . ']', array(
							'jpg',
							'gif',
							'png'
						), (Phpfox::getUserParam('photo.photo_max_upload_size') === 0 ? null : (Phpfox::getUserParam('photo.photo_max_upload_size') / 1024))
					)
				)
				{					
					if (isset($aVals['action']) && $aVals['action'] == 'upload_photo_via_share')
					{
						$aVals['description'] = (isset($aVals['is_cover_photo']) ? null : $aVals['status_info']);
						$aVals['type_id'] = (isset($aVals['is_cover_photo']) ? '2' : '1');
					}	
					
					if ($iId = $oServicePhotoProcess->add(Phpfox::getUserId(), array_merge($aVals, $aImage)))
					{
						$iCnt++;
						$aPhoto = Phpfox::getService('photo')->getForProcess($iId);
						
						// Move the uploaded image and return the full path to that image.
						$sFileName = $oFile->upload('image[' . $iKey . ']', 
							Phpfox::getParam('photo.dir_photo'), 
							(Phpfox::getParam('photo.rename_uploaded_photo_names') ? Phpfox::getUserBy('user_name') . '-' . preg_replace('/&#/i', 'u', $aPhoto['title']) : $iId),
							(Phpfox::getParam('photo.rename_uploaded_photo_names') ? array() : true)							
						);

						if (!$sFileName)
						{
							exit('failed: ' . implode('', Phpfox_Error::get()));
						}
							
						// Get the original image file size.
						$iFileSizes += filesize(Phpfox::getParam('photo.dir_photo') . sprintf($sFileName, ''));
						
						// Get the current image width/height
						$aSize = getimagesize(Phpfox::getParam('photo.dir_photo') . sprintf($sFileName, ''));
						
						
						// Update the image with the full path to where it is located.
						$aUpdate = array(
								'destination' => $sFileName,
								'width' => $aSize[0],
								'height' => $aSize[1],
								'server_id' => Phpfox_Request::instance()->getServer('PHPFOX_SERVER_ID'),
								'allow_rate' => (empty($aVals['album_id']) ? '1' : '0'),
								'description' => (empty($aVals['description']) ? null : $aVals['description'])
							);
						
						// Solves bug, when categories are left empty and setting "photo.allow_photo_category_selection" is enabled:
						if (isset($aVals['category_id']))
						{
							$aUpdate['category_id'] = $aVals['category_id'];
						}
						elseif(isset($aVals['category_id[]']))
						{
							$aUpdate['category_id'] = $aVals['category_id[]'];
						}
						
						$oServicePhotoProcess->update(Phpfox::getUserId(), $iId, $aUpdate);				
						
						// Assign vars for the template.
						$aImages[] = array(
							'photo_id' => $iId,
							// 'album' => (isset($aAlbum) ? $aAlbum : null),
							'server_id' => Phpfox_Request::instance()->getServer('PHPFOX_SERVER_ID'),
							'destination' => $sFileName,
							'name' => $aImage['name'],
							'ext' => $aImage['ext'],
							'size' => $aImage['size'],
							'width' => $aSize[0],
							'height' => $aSize[1],
							'completed' => 'false'
						);
						
						(($sPlugin = Phpfox_Plugin::get('photo.component_controller_frame_process_photo')) ? eval($sPlugin) : false);
					}
				}
				else 
				{
					
				}
			}
		}		
		
		
		$iFeedId = 0;
		
		// Make sure we were able to upload some images
		if (count($aImages))
		{
			if (defined('PHPFOX_IS_HOSTED_SCRIPT'))
			{
				unlink(Phpfox::getParam('photo.dir_photo') . sprintf($sFileName, ''));
			}
			
			$aCallback = (!empty($aVals['callback_module']) ? Phpfox::callback($aVals['callback_module'] . '.addPhoto', $aVals['callback_item_id']) : null);
			
			$sAction = (isset($aVals['action']) ? $aVals['action'] : 'view_photo');
			
			// Have we posted an album for these set of photos?
			if (isset($aVals['album_id']) && !empty($aVals['album_id']))
			{
				$aAlbum = Phpfox::getService('photo.album')->getAlbum(Phpfox::getUserId(), $aVals['album_id'], true);
				
				// Set the album privacy
				Phpfox::getService('photo.album.process')->setPrivacy($aVals['album_id']);				
				
				// Check if we already have an album cover
				if (!Phpfox::getService('photo.album.process')->hasCover($aVals['album_id']))
				{
					// Set the album cover
					Phpfox::getService('photo.album.process')->setCover($aVals['album_id'], $iId);
				}
				
				// Update the album photo count
				if (!Phpfox::getUserParam('photo.photo_must_be_approved'))
				{
					Phpfox::getService('photo.album.process')->updateCounter($aVals['album_id'], 'total_photo', false, count($aImages));
				}
				
				if (!defined('PHPFOX_SKIP_FEED_ENTRY') && !Phpfox::getUserParam('photo.photo_must_be_approved'))
				{
					// (Phpfox::isModule('feed') ? $iFeedId = Phpfox::getService('feed.process')->callback($aCallback)->delete('photo_album', $aVals['album_id'], Phpfox::getUserId()) : null);					
					// (Phpfox::isModule('feed') ? $iFeedId = Phpfox::getService('feed.process')->callback($aCallback)->add('photo_album', $aVals['album_id'], $aAlbum['privacy'], $aAlbum['privacy_comment'], (isset($aVals['parent_user_id']) ? (int) $aVals['parent_user_id'] : 0)) : null);
				}
				
				$sAction = 'view_album';
			}
			else 
			{	
				if (!defined('PHPFOX_SKIP_FEED_ENTRY') && !Phpfox::getUserParam('photo.photo_must_be_approved'))
				{			
					// (Phpfox::isModule('feed') ? $iFeedId = Phpfox::getService('feed.process')->callback($aCallback)->add('photo', $iId, (isset($aVals['privacy']) ? (int) $aVals['privacy'] : 0), (isset($aVals['privacy_comment']) ? (int) $aVals['privacy_comment'] : 0), (isset($aVals['parent_user_id']) ? (int) $aVals['parent_user_id'] : 0)) : null);
				}				
			}
			//die('temp:' . __LINE__ . ' count: ' . count($aImages) . 'isset: ' . (print_r(isset($aVals['album_id']), true)));
			// Update the user space usage
			Phpfox::getService('user.space')->update(Phpfox::getUserId(), 'photo', $iFileSizes);			

			(($sPlugin = Phpfox_Plugin::get('photo.component_controller_frame_process_photos_done')) ? eval($sPlugin) : false);
						
			if (isset($aVals['page_id']) && $aVals['page_id'] > 0)
			{				
				if (Phpfox::getService('pages.process')->setCoverPhoto($aVals['page_id'], $iId, true))
				 {
					//echo '<script type="text/javascript">parent.window.location.href = "' . Phpfox::permalink('pages', $aVals['page_id'], '') . '";</script>';
					$aVals['is_cover_photo'] = 1;
				 }
				 else
				 {
					echo '<script type="text/javascript">alert("Something went wrong: ' . implode(Phpfox_Error::get()) . '");</script>';
					
				 }				 
			}
			
			if (isset($_REQUEST['picup']))
			{
				//exit();
			}
			else if (isset($aVals['method']) && $aVals['method'] == 'massuploader')
			{
				//echo 'window.aImagesUrl.push("' . urlencode(base64_encode(serialize($aImages))) . '");';
				echo 'window.aImagesUrl.push(' . (json_encode($aImages)) . ');';
			}
			else 
			{
				$sExtra = '';
				if (!empty($aVals['start_year']) && !empty($aVals['start_month']) && !empty($aVals['start_day']))
				{
					$sExtra .= '&start_year= ' . $aVals['start_year'] . '&start_month= ' . $aVals['start_month'] . '&start_day= ' . $aVals['start_day'] . '';	
				}

				if (!defined('PHPFOX_HTML5_PHOTO_UPLOAD'))
				{
					echo '<script type="text/javascript">';
				}
				if ($bIsInline && Phpfox::isModule('video') && Phpfox::getParam('video.convert_servers_enable'))
				{
					echo 'document.domain = "' . Phpfox::getParam('video.convert_js_parent') . '";';
				}

				if (!defined('PHPFOX_HTML5_PHOTO_UPLOAD'))
				{
					echo 'window.parent.';
				}

				echo '$.ajaxCall(\'photo.process\', \''. ((isset($aVals['page_id']) && !empty($aVals['page_id'])) ? 'is_page=1&' : '') .'js_disable_ajax_restart=true' . $sExtra . '&twitter_connection=' . ((isset($aVals['connection']) && isset($aVals['connection']['twitter'])) ? $aVals['connection']['twitter'] : '0') . '&facebook_connection=' . (isset($aVals['connection']['facebook']) ? $aVals['connection']['facebook'] : '0') . '&custom_pages_post_as_page=' . $this->request()->get('custom_pages_post_as_page') . '&photos=' . urlencode(json_encode($aImages)) . '&action=' . $sAction . '' . (isset($iFeedId) ? '&feed_id=' . $iFeedId : '') . '' . ($aCallback !== null ? '&callback_module=' . $aCallback['module'] . '&callback_item_id=' . $aCallback['item_id'] : '') . '&parent_user_id=' . (isset($aVals['parent_user_id']) ? (int) $aVals['parent_user_id'] : 0) . '&is_cover_photo=' . (isset($aVals['is_cover_photo']) ? '1' : '0') . ((isset($aVals['page_id']) && $aVals['page_id'] > 0) ? '&page_id='.$aVals['page_id'] : '') . '\');';
				if (!defined('PHPFOX_HTML5_PHOTO_UPLOAD'))
				{
					echo '</script>';
				}
			}
			
			(($sPlugin = Phpfox_Plugin::get('photo.component_controller_frame_process_photos_done_javascript')) ? eval($sPlugin) : false);
		}
		else 
		{
			// Output JavaScript
			if (!defined('PHPFOX_HTML5_PHOTO_UPLOAD'))
			{
				echo '<script type="text/javascript">';
			}
			else
			{
				unlink($sHTML5TempFile);
				header('HTTP/1.1 500 Internal Server Error');
			}

			if (!$bIsInline)
			{
				// echo 'window.parent.$(\'#js_progress_cache_holder\').hide();';
				// echo 'window.parent.document.getElementById(\'js_photo_form_holder\').style.display = \'block\';';
				// echo 'window.parent.document.getElementById(\'js_upload_error_message\').innerHTML = \'<div class="error_message">' . implode('', Phpfox_Error::get()) . '</div>\';';
			}
			else
			{
				if (Phpfox::isModule('video') && Phpfox::getParam('video.convert_servers_enable'))
				{
					echo 'document.domain = "' . Phpfox::getParam('video.convert_js_parent') . '";';
				}
				if (isset($aVals['is_cover_photo']))
				{
					echo 'window.parent.$(\'#js_cover_photo_iframe_loader_upload\').hide();';
					echo 'window.parent.$(\'#js_activity_feed_form\').show();';
					echo 'window.parent.$(\'#js_cover_photo_iframe_loader_error\').html(\'<div class="error_message">' . implode('', Phpfox_Error::get()) . '</div>\').show();';
				}
				else
				{
					echo 'window.parent.$Core.resetActivityFeedError(\'' . implode('', Phpfox_Error::get()) . '\');';
				}
			}
			if (!defined('PHPFOX_HTML5_PHOTO_UPLOAD'))
			{
				echo '</script>';
			}
		}		
		
		exit;
	}
	
	/**
	 * Garbage collector. Is executed after this class has completed
	 * its job and the template has also been displayed.
	 */
	public function clean()
	{
		(($sPlugin = Phpfox_Plugin::get('photo.component_controller_frame_clean')) ? eval($sPlugin) : false);
	}
}

?>
