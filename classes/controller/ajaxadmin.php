<?php
/**
 *
 *
 * @author Tim Redmond <kweejee@tummycaching.com>
 * @copyright Copyright 2009 Tim Redmond
 * @license GNU General Public License version 3 <http://www.gnu.org/licenses/>
 * @package kwalbum
 * @since Aug 24, 2009
 */

class Controller_AjaxAdmin extends Controller_Kwalbum
{
	function action_EditLocationName()
	{
		$this->_testPermission();
		$loc = Model :: factory('kwalbum_location')->load((int)$_POST['id']);
		if ( ! empty($_POST['value']))
		{
			$loc->name = Security :: xss_clean($_POST['value']);
			$loc->save();
		}
		echo $loc->name;
		exit;
	}

	function action_DeleteLocation()
	{
		$this->_testPermission();
		Model :: factory('kwalbum_location')
			->load((int)$_POST['id'])
			->delete();
		exit;
	}

	function action_EditPersonName()
	{
		$this->_testPermission();
		$person = Model :: factory('kwalbum_person')->load((int)$_POST['id']);
		if ( ! empty($_POST['value']))
		{
			$person->name = Security :: xss_clean($_POST['value']);
			$person->save();
		}
		echo $person->name;
		exit;
	}

	function action_DeletePerson()
	{
		$this->_testPermission();
		Model :: factory('kwalbum_person')
			->load((int)$_POST['id'])
			->delete();
		exit;
	}
	
	function action_EditTagName()
	{
		$this->_testPermission();
		$tag = Model :: factory('kwalbum_tag')->load((int)$_POST['id']);
		if ( ! empty($_POST['value']))
		{
			$tag->name = Security :: xss_clean($_POST['value']);
			$tag->save();
		}
		echo $tag->name;
		exit;
	}

	function action_DeleteTag()
	{
		$this->_testPermission();
		Model :: factory('kwalbum_tag')
			->load((int)$_POST['id'])
			->delete();
		exit;
	}

	function action_GetUserPermission()
	{
		$this->_testPermission();
		$perms = Model_Kwalbum_User::$permission_names;
		$user = Model :: factory('kwalbum_user')->load((int)$_GET['userid']);
		$perms['selected'] = $user->permission_level;
		echo json_encode($perms);
		exit;
	}


	function action_EditUserPermission()
	{
		$this->_testPermission();
		$user = Model :: factory('kwalbum_user')->load((int)$_POST['userid']);
		if ( ! empty($_POST['value']))
		{
			if ($user->id > 1 and $user->id != $this->user->id)
			{
				$user->permission_level = (int)$_POST['value'];
				$user->save();
			}
		}
		echo $user->permission_description;
		exit;
	}

	function action_DeleteUser()
	{
		$this->_testPermission();
		Model :: factory('kwalbum_user')
			->load((int)$_POST['userid'])
			->delete();
		exit;
	}

	private function _testPermission()
	{
		if ($this->user->is_admin)
			return;
		exit;
	}
}