<?php
/**
 *
 *
 * @author Tim Redmond <kweejee@tummycaching.com>
 * @copyright Copyright 2009-2012 Tim Redmond
 * @license GNU General Public License version 3 <http://www.gnu.org/licenses/>
 * @package kwalbum
 * @since 3.0 Jul 6, 2009
 */

class Model_Kwalbum_Item extends Kwalbum_Model
{
	public $id, $type, $user_id, $location,
		$visible_date, $sort_date, $update_date, $create_date,
		$description, $latitude, $longitude, $path, $filename,
		$has_comments, $hide_level, $count, $is_external, $loaded;
	private $_user_name, $_original_location, $_original_user_id,
		 $_location_id, $_tags, $_persons, $_comments, $_comment_count,
		 $_external_site, $_external_id, $_external_site_is_changed;

	static public $types = array(
			0 => 'unknown',
			1 => 'gif', 2 => 'jpeg', 3 => 'png',
			/*40 => 'wmv',
			41 => 'txt',
			42 => 'mp3',
			43 => 'zip',
			44 => 'html',
			45 => 'divx',
			46 => 'ogg',
			47 => 'wav',
			48 => 'xml', 49 => 'gpx',
			50 => 'ods', 51 => 'odt',
			52 => 'flv',
			53 => 'doc',
			54 => 'mpeg',
			55 => 'mp4',*/
			255 => 'description only'
		);
	static private $_where = array();
	static private $_sort_field = 'sort_dt';
	static private $_sort_direction = 'ASC';
	static private $_gtlt = '<';

	/**
	 * Load an item based on $field matching $id
	 * 
	 * @param mixed $id
	 * @param string $field
	 * @return Model_Kwalbum_Item
	 */
	public function load($id = null, $field = 'id')
	{
		$this->clear();

		if ($id === null)
		{
			return $this;
		}

		$result = DB::query(Database::SELECT,
			"SELECT *
			FROM kwalbum_items
			WHERE $field = :id
			LIMIT 1")
			->param(':id', $id)
			->execute();
		if ($result->count() == 0)
		{
			return $this;
		}

		$row = $result[0];

		$this->id = (int)$row['id'];
		$this->type = Model_Kwalbum_Item::$types[$row['type_id']];
		$this->user_id = $this->_original_user_id = (int)$row['user_id'];
		$this->_location_id = (int)$row['location_id'];
		$this->visible_date = $row['visible_dt'];
		$this->sort_date = $row['sort_dt'];
		$this->update_date = $row['update_dt'];
		$this->create_date = $row['create_dt'];
		$this->description = $row['description'];
		$this->latitude = (float)$row['latitude'];
		$this->longitude = (float)$row['longitude'];
		$this->path = self::get_config('item_path').$row['path'];
		$this->filename = $row['filename'];
		$this->has_comments = (bool)$row['has_comments'];
		$this->hide_level = (int)$row['hide_level'];
		$this->count = (int)$row['count'];
		$this->is_external = (bool)$row['is_external'];

		$result = DB::query(Database::SELECT,
			"SELECT loc.name AS name, p.id, p.name AS parent
			FROM kwalbum_locations loc
			LEFT JOIN kwalbum_locations p ON (p.id = loc.parent_location_id)
			WHERE loc.id = :id
			LIMIT 1")
			->param(':id', $this->_location_id)
			->execute();
		$this->location = $this->_original_location = ($result[0]['parent'] ? $result[0]['parent'].self::get_config('location_separator_1') : '').$result[0]['name'];
		$this->loaded = true;
		return $this;
	}

	/**
	 * Save object changes to the database through inserts or updates
	 *
	 * @param boolean $update_update_date_with_update_date
	 * @return Model_Kwalbum_Item
	 */
	public function save($update_update_date_with_update_date = true)
	{
		// Set type
		$types = array_flip(Model_Kwalbum_Item::$types);
		$type_id = $types[$this->type];
		// Set location

		// Item has an original location so check for name changes
		if ($this->_location_id)
		{
			// Update original location's item count if the name is different
			if (trim($this->location) != $this->_original_location)
			{
				DB::query(Database::UPDATE, "
					UPDATE kwalbum_locations loc
					LEFT JOIN kwalbum_locations p ON (p.id = loc.parent_location_id)
					SET loc.count = loc.count-1, p.child_count = p.child_count-1
					WHERE loc.id = :id")
					->param(':id', $this->_location_id)
					->execute();
			}

			// Use the original id if there are no changes
			else
			{
				$location_id = $this->_location_id;
			}
		}

		// If there is no location id set then there is a change so get id for new location name
		if ( ! isset($location_id))
		{
			$names = explode(trim(self::get_config('location_separator_1')), $this->location);
			foreach ($names as $i => $name)
			{
				$name = trim($name);
				if (!$name)
					unset($names[$i]);
			}
			$this->location = implode(trim(self::get_config('location_separator_1')), $names);

			// The location name is unknown so use default unknown id and name
			if (empty($this->location))
			{
				$location_id = $this->_location_id = 1;
				$result = DB::query(Database::SELECT, "
					SELECT name
					FROM kwalbum_locations
					WHERE id = 1
					LIMIT 1")
					->execute();
				$this->location = $this->_original_location = $result[0]['name'];
			}

			// Get new location id for known name
			else
			{
				$loc_name = $this->location;
				$names = explode(trim(self::get_config('location_separator_1')), $loc_name);
				if (count($names) > 1)
				{
					$parent_loc_name = trim($names[0]);
					array_shift($names);
					foreach ($names as $i => $name)
					{
						$names[$i] = trim($name);
						if (!$name)
							unset($names[$i]);
					}
					$loc_name = implode(self::get_config('location_separator_2'), $names);
					if (!$loc_name)
					{
						$loc_name = $parent_loc_name;
						unset($parent_loc_name);
					}
				}
				// Get id if new location already exists
				if (isset($parent_loc_name))
				{
					$result = DB::query(Database::SELECT, "
						SELECT loc.id
						FROM kwalbum_locations loc
						LEFT JOIN kwalbum_locations p ON (p.id = loc.parent_location_id)
						WHERE loc.name = :name AND p.name = :parent_name
						LIMIT 1")
						->param(':name', $loc_name)
						->param(':parent_name', $parent_loc_name)
						->execute();
				}
				else
				{
					$result = DB::query(Database::SELECT, "
						SELECT id
						FROM kwalbum_locations
						WHERE name = :name
						LIMIT 1")
						->param(':name', $loc_name)
						->execute();
				}

				// If new location does not exist then create it
				if ($result->count() == 0)
				{
					if (isset($parent_loc_name))
					{
						// Get parent location id
						$result = DB::query(Database::SELECT, "
							SELECT id
							FROM kwalbum_locations
							WHERE name = :name
							LIMIT 1")
							->param(':name', $parent_loc_name)
							->execute();

							// If new location's parent does not exist then create it
							if ($result->count() == 0)
							{
								$result = DB::query(Database::INSERT, "
									INSERT INTO kwalbum_locations
									(name)
									VALUES (:name)")
									->param(':name', $parent_loc_name)
									->execute();

							}
							$parent_loc_id = $result[0];
						// Create new location with parent
						$result = DB::query(Database::INSERT, "
							INSERT INTO kwalbum_locations
							(name, parent_location_id)
							VALUES (:name, :parent_id)")
							->param(':name', $loc_name)
							->param(':parent_id', $parent_loc_id)
							->execute();
					}
					else
					{
						// Create new location without parent
						$result = DB::query(Database::INSERT, "
							INSERT INTO kwalbum_locations
							(name)
							VALUES (:name)")
							->param(':name', $loc_name)
							->execute();
					}

					$location_id = $result[0];
				}
				else
				{
					$location_id = $result[0]['id'];
				}
			}

			// Update count on new location
			DB::query(Database::UPDATE,
				"UPDATE kwalbum_locations loc LEFT JOIN kwalbum_locations p ON (p.id = loc.parent_location_id)
				SET loc.count = loc.count+1, p.child_count = p.child_count+1
				WHERE loc.id = :id")
				->param(':id', $location_id)
				->execute();
		}

		// Save any changes to location id and original name
		$this->_location_id = $location_id;
		$this->_original_location_name = $this->location;

		// Set update_date

		if ($update_update_date_with_update_date) {
			$this->update_date = date('Y-m-d H:i:s');
		}

		// Save actual item

		if ($this->loaded == false)
		{
			// create_date is never updated, only set at insert
			$this->create_date = $this->update_date;
			$query = DB::query(Database::INSERT,
				"INSERT INTO kwalbum_items
				(type_id, location_id, user_id, description, path, filename,
					create_dt, update_dt, visible_dt, sort_dt,
					count, latitude, longitude, hide_level, is_external)
				VALUES (:type_id, :location_id, :user_id, :description, :path, :filename,
					:create_date, :update_date, :visible_date, :sort_date,
					:count, :latitude, :longitude, :hide_level, :is_external)")
				->param(':create_date', $this->create_date);
			if ( ! $this->visible_date)
			{
				$this->visible_date = $this->update_date;
			}
			if ( ! $this->sort_date)
			{
				$this->sort_date = $this->update_date;
			}
		}
		else
		{
			$query = DB::query(Database::UPDATE,
				"UPDATE kwalbum_items
				SET type_id = :type_id, location_id = :location_id, user_id = :user_id,
					description = :description, path = :path, filename = :filename,
					update_dt = :update_date, sort_dt = :sort_date, visible_dt = :visible_date,
					count = :count, latitude = :latitude, longitude = :longitude,
					hide_level = :hide_level, is_external = :is_external
				WHERE id = :id")
				->param(':id', $this->id);
		}
		$query
			->param(':type_id', $type_id)
			->param(':location_id', $location_id)
			->param(':user_id', $this->user_id)
			->param(':description', trim($this->description))
			->param(':path', str_replace(self::get_config('item_path'), '', $this->path))
			->param(':filename', trim($this->filename))
			->param(':update_date', $this->update_date)
			->param(':visible_date', $this->visible_date)
			->param(':sort_date', $this->sort_date)
			->param(':count', (int) $this->count)
			->param(':latitude', $this->latitude ? $this->latitude : 0)
			->param(':longitude', $this->longitude ? $this->longitude : 0)
			->param(':hide_level', $this->hide_level)
			->param(':is_external', (int) $this->is_external);

		$result = $query->execute();

		if ($this->loaded == false)
		{
			$this->id = $result[0];
			$this->loaded = true;
		}

		// Set tags, persons, and external site once we know we have
		// an item_id for the relationship.

		// Remove duplicates of new person and tags
		// Use __get to make sure the array exists
		$this->_persons = array_filter(array_unique($this->persons));
		$this->_tags = array_filter(array_unique($this->tags));

		// Remove old item-person and item-tag relations
		$this->_delete_person_relations();
		$this->_delete_tag_relations();

		// Create new item-person and item-tag relations
		$person = Model::factory('kwalbum_person');
		foreach($this->_persons as $name)
		{
			$name = trim($name);
			if ($name != '')
			{
				$person->clear();
				$person->name = $name;
				$person->save();
				DB::query(Database::INSERT,
					"INSERT INTO kwalbum_items_persons
					(item_id, person_id)
					VALUES (:item_id, :person_id)")
					->param(':item_id', $this->id)
					->param(':person_id', $person->id)
					->execute();
				$person->count = $person->count+1;
				$person->save();
			}
		}
		$tag = Model::factory('kwalbum_tag');
		foreach($this->_tags as $name)
		{
			$name = trim($name);
			if ($name != '')
			{
				$tag->clear();
				$tag->name = $name;
				$tag->save();
				DB::query(Database::INSERT,
					"INSERT INTO kwalbum_items_tags
					(item_id, tag_id)
					VALUES (:item_id, :tag_id)")
					->param(':item_id', $this->id)
					->param(':tag_id', $tag->id)
					->execute();
				$tag->count = $tag->count+1;
				$tag->save();
			}
		}

		if ($this->_external_site_is_changed)
		{
			// remove old relation if it exists
			DB::query(Database::DELETE,
				"DELETE FROM kwalbum_items_sites
				WHERE item_id = :id")
				->param(':id', $this->id)
				->execute();

			// create relation and update item if is_external is not already correct
			$query_item_again = false;
			if ($this->_external_site != null and $this->_external_id != 0)
			{
				DB::query(Database::INSERT,
					"INSERT INTO kwalbum_items_sites
					(item_id, site_id, external_item_id)
					VALUES (:id, :site_id, :external_id)")
					->param(':id', $this->id)
					->param(':site_id', $this->_external_site->id)
					->param(':external_id', $this->_external_id)
					->execute();

				if ($this->is_external !== true)
				{
					$query_item_again = true;
					$this->is_external = true;
				}

			}
			else
			{
				if ($this->is_external !== false)
				{
					$query_item_again = true;
					$this->is_external = false;
					$this->_external_site = null;
					$this->_external_id = 0;
				}
			}
			if ($query_item_again === true)
			{
				DB::query(Database::UPDATE,
					"UPDATE kwalbum_items
					SET is_external = :is_external
					WHERE id = :id")
					->param(':id', $this->id)
					->param(':is_external', (int)$this->is_external)
					->execute();
			}
		}

		return $this;
	}

	/**
	 * Delete an item from the database along with any relationships to other
	 * tables in the database.  If it is the current object, clear it also.
	 * 
	 * @param int $id optional id for an item that is not the current object
	 */
	public function delete($id = NULL)
	{
		if ($id === NULL)
		{
			$id = $this->id;
		}

		// Remove item from location count
		if ($id == $this->id)
		{
			$location_id = $this->_location_id;
			$persons = $this->persons;
			$tags = $this->tags;
		}
		else
		{
			$result = DB::query(Database::SELECT, 'SELECT location_id
				FROM kwalbum_items
				WHERE item_id = :id')
				->param(':id', $id)
				->execute();
			$location_id = $result[0]['location_id'];
		}
		DB::query(Database::UPDATE, 'UPDATE kwalbum_locations
			SET count = count-1
			WHERE id = :location_id AND count > 0')
			->param(':location_id', $location_id)
			->execute();

		// Delete relation between item and external site if it exists
		DB::query(Database::DELETE, "DELETE FROM kwalbum_items_sites
			WHERE item_id = :id")
			->param(':id', $id)
			->execute();

		// Remove item-person relations and reduce persons' item counts
		$this->_delete_person_relations($id);

		// Remove item-tag relations and reduce tags' item counts
		$this->_delete_tag_relations($id);

		// Delete comments
		$comments = $this->_load_comments($id);
		foreach ($comments as $comment)
		{
			$comment->delete();
		}

		// Delete external site relation
		DB::query(Database::DELETE,
			"DELETE FROM kwalbum_items_sites
			WHERE item_id = :id")
			->param(':id', $id)
			->execute();

		// Delete the item
		DB::query(Database::DELETE, "DELETE FROM kwalbum_items
			WHERE id = :id")
			->param(':id', $id)
			->execute();

		if ($id == $this->id)
		{
			$this->clear();
		}
	}

	/**
	 * Add 1 to an item's count if it was not recently viewed by the user.
	 */
	public function increase_count()
	{
		$id = $this->id;

		$session = Session::instance();
		$viewed_ids = $session->get('viewed_item_ids', array());
		$count_it = true;
		if (in_array($id, $viewed_ids))
		{
			$count_it = false;
		}

		if ($count_it)
		{
			$this->count++;
			DB::query(Database::UPDATE, "UPDATE kwalbum_items
					SET count = count+1
					WHERE id = :id")
					->param(':id', $id)
					->execute();
			$viewed_ids[] = $id;
		}
		$session->set('viewed_item_ids', $viewed_ids);
	}

	public function __get($id)
	{
		switch ($id)
		{
			case 'tags':
				if ($this->_tags === null)
				{
					$this->_tags = array();
					$result = DB::query(Database::SELECT,
						"SELECT name
						FROM kwalbum_items_tags
							LEFT JOIN kwalbum_tags ON tag_id = id
						WHERE item_id = :id
						ORDER BY name")
						->param(':id', $this->id)
						->execute();
					foreach($result as $row)
					{
						$this->_tags[] = $row['name'];
					}
				}

				return $this->_tags;
			case 'persons':
				if ($this->_persons === null)
				{
					$this->_persons = array();
					$result = DB::query(Database::SELECT,
						"SELECT name
						FROM kwalbum_items_persons
							LEFT JOIN kwalbum_persons ON person_id = id
						WHERE item_id = :id
						ORDER BY name")
						->param(':id', $this->id)
						->execute();
					foreach($result as $row)
					{
						$this->_persons[] = $row['name'];
					}
				}

				return $this->_persons;
			case 'comments':
				if ($this->_comments === null)
				{
					$this->_comments = $this->_load_comments($this->id);
				}
				return $this->_comments;
			case 'pretty_date':
				$date = explode(' ', $this->visible_date);

				if (count($date) > 1) {
					$time = explode(trim(self::get_config('location_separator_1')), $date[1]);
					$hour = $time[0];
					$minute = $time[1];
				} else {
					$hour = '';
					$minute = '';
				}
				$date = explode('-', $date[0]);
				$year = $date[0];
				$month = $date[1];
					$day = $date[2];

				if (0 == $month)
				{
					if ((int)$year)
						$pretty_date = $year;
					else
						$pretty_date = '';
				}
				else if (0 == $day)
				{
					$pretty_date = date('F Y', strtotime("$year-$month-1"));
				}
				else
				{
					$pretty_date = date('F j, Y', strtotime($this->visible_date));
				}

				if ($hour != '00' or $minute != '00')
				{
					$pretty_date .= " $hour:$minute";
				}

				return $pretty_date;
			case 'date':
				$date = explode(' ', $this->visible_date);
				return $date[0];
			case 'time':
				$date = explode(' ', $this->visible_date);
				return $date[1];
			case 'user_name':
				if ($this->_user_name === null)
				{
					$result = DB::query(Database::SELECT,
						"SELECT name
						FROM kwalbum_users
						WHERE id = :id
						LIMIT 1")
						->param(':id', $this->user_id)
						->execute();
					$this->_user_name = $result[0]['name'];
				}
				return $this->_user_name;
			case 'external_site':
			case 'external_id':
				if ($this->_external_site === null)
				{
					if ($this->is_external === false)
					{
						if ($id == 'external_site')
							return null;
						else
							return 0;
					}
					$result = DB::query(Database::SELECT,
						"SELECT site_id, external_item_id
						FROM kwalbum_items_sites
						WHERE item_id = :id
						LIMIT 1")
						->param(':id', $this->id)
						->execute();
					$site_id = $result[0]['site_id'];
					$this->_external_id = (int)$result[0]['external_item_id'];
					$this->_external_site = Model::factory('kwalbum_site')->load($site_id);
				}
				if ($id == 'external_site')
					return $this->_external_site;
				else
					return $this->_external_id;
			case 'comment_count':
				if ($this->_comment_count)
					return $this->_comment_count;
				if ( ! $this->has_comments)
					return 0;

				$query = "SELECT count(*)
					FROM kwalbum_comments
					WHERE item_id = :id";
				$result = DB::query(Database::SELECT, $query)
					->param(':id', $this->id)
					->execute();
				$this->_comment_count = (int)$result[0]['count(*)'];
				return $this->_comment_count;
		}
	}

	public function __set($key, $value)
	{
		// Overwrite tags if an array is given or add to the existing array
		if ($key == 'tags')
		{
			if (is_array($value))
				$this->_tags = $value;
			else
			{
				if ($this->_tags === null)
				{
					$this->_tags = $this->tags;
				}
				$this->_tags[] = (string)$value;
			}
		}

		// Overwrite persons if an array is given or add to the existing array
		else if ($key == 'persons')
		{
			if (is_array($value))
				$this->_persons = $value;
			else
			{
				if ($this->_persons === null)
				{
					$this->_persons = $this->persons;
				}
				$this->_persons[] = (string)$value;
			}
		}

		// Overwrite comments if an array is given or add to the existing array
		else if ($key == 'comments')
		{
			if (is_array($value))
				$this->_comments = $value;
			else
			{
				if ($this->_comments === null)
				{
					$this->_comments = $this->comments;
				}
				if ($value instanceof Model_Kwalbum_Comment)
					$this->_comments[] = $value;
			}
		}
		else if ($key == 'external_site' or $key == 'external_id')
		{
			$this->_external_site_is_changed = true;
			if ($key == 'external_site')
				$this->_external_site = $value;
			else
				$this->_external_id = (int)$value;
		}
	}

	public function clear()
	{
		$this->id = $this->user_id = $this->location_id = $this->latitude
			= $this->longitude = $this->hide_level = $this->count
			= $this->_external_id = 0;
		$this->description = $this->visible_date = $this->sort_date = $this->update_date
			= $this->create_date = $this->path = $this->filename = '';
		$this->_tags = $this->_persons = $this->_comments
			= $this->_location = $this->_user_name
			= $this->_original_location = $this->_original_user_id
			= $this->_external_site = null;
		$this->type = Model_Kwalbum_Item::$types[0];
		$this->has_comments = $this->is_external
			= $this->loaded = $this->_external_site_is_changed = false;
	}

	function hide_if_needed($user)
	{
		if ( ! $user->can_view_item($this))
		{
			$id = $this->id;
			$sort_date = $this->sort_date;
			$this->clear();
			$this->id = $id;
			$this->sort_date = $sort_date;
			$this->type = Model_Kwalbum_Item :: $types[3];
			$this->path = MODPATH.'kwalbum/media/';
			$this->filename = 'no.png';
			$this->hide_level = 100;
			$this->location = 'hidden';
			$this->visible_date = '0000-00-00 00:00:00';
			// Setting tags and persons to empty arrays will cause them to never be loaded
			$this->tags = array();
			$this->persons = array();
		}
	}

	private function _delete_person_relations($id = null)
	{
		if ($id === NULL)
		{
			$id = $this->id;
		}

		// Remove item from person counts
		$result = DB::query(Database::UPDATE,
			"UPDATE kwalbum_persons
			SET count = count-1
			WHERE id IN
				(SELECT person_id
					FROM kwalbum_items_persons
					WHERE item_id = :id
				)")
			->param(':id', $id)
			->execute();

		if ($result > 0)
		{
			// Remove relations between item and persons
			$result = DB::query(Database::DELETE,
				"DELETE FROM kwalbum_items_persons
				WHERE item_id = :id")
				->param(':id', $id)
				->execute();
		}
	}

	private function _delete_tag_relations($id = null)
	{
		if ($id === NULL)
		{
			$id = $this->id;
		}

		// Remove item from tag counts
		$result = DB::query(Database::UPDATE,
			"UPDATE kwalbum_tags
			SET count = count-1
			WHERE id IN
				(SELECT tag_id
					FROM kwalbum_items_tags
					WHERE item_id = :id
				)")
			->param(':id', $id)
			->execute();

		if ($result > 0)
		{
			// Remove relations between item and tags
			$result = DB::query(Database::DELETE,
				"DELETE FROM kwalbum_items_tags
				WHERE item_id = :id")
				->param(':id', $id)
				->execute();
		}
	}

	private function _load_comments($id = null)
	{
		if ($id === NULL)
		{
			$id = $this->id;
		}

		$comments = array();

		$result = DB::query(Database::SELECT,
			"SELECT id
			FROM kwalbum_comments
			WHERE item_id = :id")
			->param(':id', $this->id)
			->execute();

		foreach ($result as $row)
		{
			$comments[] = Model::factory('kwalbum_comment')->load($row['id']);
		}

		return $comments;
	}

	static public function check_unique_filename($path = null, $filename = null)
	{
		if ($path == null or $filename == null)
	        throw new Kohana_Exception('$path or $filename was not given when calling Model_Kwalbum_Item::check_unique_filename($path, $filename)');

		$result = DB::query(Database::SELECT,
			"SELECT id
			FROM kwalbum_items
			WHERE path = :path AND filename = :filename
			LIMIT 1")
			->param(':path', str_replace(self::get_config('item_path'), '', $path))
			->param(':filename', $filename)
			->execute();
		if ($result->count() == 0)
		{
			return true;
		}

		return false;
	}

	static public function get_where_query()
	{
		$query = '';
		foreach (Model_Kwalbum_Item::$_where as $where)
		{
			if ($query)
				$query .= ' AND '.$where;
			else
				$query = ' WHERE '.$where;
		}

		return $query;
	}

	static public function append_where($name, $value)
	{
		$query = '';

		switch ($name)
		{
			case 'location':
				$parent_name = '';
				$loc_name = '';
				$names = explode(trim(self::get_config('location_separator_1')), $value);
				if (count($names) > 1)
				{
					$parent_name = trim($names[0]);
					array_shift($names);
					foreach ($names as &$n)
						$n = trim($n);
					$loc_name = implode(self::get_config('location_separator_2'), $names);
				}
				if ($loc_name) {
					$query = (string)DB::query(null, " location_id =
						(SELECT loc.id
						FROM kwalbum_locations loc
						LEFT JOIN kwalbum_locations p ON (p.id = loc.parent_location_id)
						WHERE loc.name = :location AND p.name = :parent_location)")
						->param(':location', $loc_name)
						->param(':parent_location', $parent_name);
				}
				else
				{
					if ($parent_name)
						$value = $parent_name;
					$query = (string)DB::query(null, " location_id IN (SELECT loc.id
						    FROM kwalbum_locations loc
						    LEFT JOIN kwalbum_locations p ON (p.id = loc.parent_location_id)
						    WHERE loc.name = :location OR p.name = :location)")
						->param(':location', $value);
				}
				break;
			case 'date':
				$newDate = explode('-', $value);
				$year = (int) $newDate[0];
				$month = (int) @$newDate[1];
				$day = (int) @$newDate[2];

				if (1800 <= $year and 12 >= $month and 32 >= $day)
				{
					if (0 == $month)
					{
						$date1 = "$value 00:00:00";
						$date2 = "$year-12-31 23:59:59";
					}
					else if (0 == $day)
					{
						$date1 = "$value 00:00:00";
						$date2 = "$year-$month-32 23:59:59";
					} else
					{
						$date1 = "$value 00:00:00";
						$date2 = "$value 23:59:59";
					}
					$query = (string)DB::query(null, 'sort_dt >= :date1 AND sort_dt <= :date2')
						->param(':date1', $date1)
						->param(':date2', $date2);
				}
				break;
			case 'tags':
				foreach($value as $tag)
				{
					$query .= ($query ? ' AND ' : null).
						(string)DB::query(null, " 0 < (SELECT count(*) FROM kwalbum_items_tags
						LEFT JOIN kwalbum_tags ON kwalbum_items_tags.tag_id = kwalbum_tags.id
						WHERE kwalbum_tags.name=:tag AND kwalbum_items_tags.item_id=kwalbum_items.id)")
						->param(':tag', $tag);
				}
				break;
			case 'people':
				foreach($value as $tag)
				{
					$query .= ($query ? ' AND ' : null).
						(string)DB::query(null, " 0 < (SELECT count(*) FROM kwalbum_items_persons
						LEFT JOIN kwalbum_persons ON kwalbum_items_persons.person_id = kwalbum_persons.id
						WHERE kwalbum_persons.name LIKE :tag AND kwalbum_items_persons.item_id=kwalbum_items.id)")
						->param(':tag', $tag.'%');
				}
				break;
			case 'type':
				$types = array_flip(Model_Kwalbum_Item::$types);
				$type_id = $types[$value];
				$query = (string)DB::query(null, " type_id = :type_id")
					->param(':type_id', $type_id);
			case 'hide_level':
				$query = (string)DB::query(null, " hide_level = :hide_level")
					->param(':hide_level', (int)$value);
				break;
			case 'user_id':
				$query = (string)DB::query(null, " user_id = :user_id")
					->param(':user_id', (int)$value);
				break;
			case 'create_dt':
				$query = (string)DB::query(null, " create_dt = :create_dt")
					->param(':create_dt', $value);
				break;
			case 'create_date':
				$query = (string)DB::query(null, " DATE(create_dt) = :create_date")
					->param(':create_date', $value);
				break;
			default:
				$query = '';
		}

		Model_Kwalbum_Item::$_where[] = $query;
	}

	/**
	 * get a collection of thumbnails based on the query
	 * @param int $page_number
	 * @return ArrayOfItem
	 */
	static public function get_thumbnails($page_number = 1)
	{
		$sort_field = Model_Kwalbum_Item::$_sort_field;
		$sort_direction = Model_Kwalbum_Item::$_sort_direction;
		$query = 'SELECT kwalbum_items.id AS id
			FROM kwalbum_items'.Model_Kwalbum_Item::get_where_query()
			." ORDER BY $sort_field $sort_direction
			LIMIT :offset,:limit";

		$limit = self::get_config('items_per_page');
		$offset = ($page_number-1)*$limit;
		$result = DB::query(Database::SELECT, $query)
			->param(':offset', $offset)
			->param(':limit', $limit)
			->execute();

		$items = array();
		foreach ($result as $row)
		{
			$items[] = Model::factory('kwalbum_item')->load($row['id']);
		}

		return $items;
	}

	static public function get_total_items()
	{
		$query = 'SELECT count(*)
			FROM kwalbum_items '.Model_Kwalbum_Item::get_where_query();
		$result = DB::query(Database::SELECT, $query)
			->execute();
		return (int)$result[0]['count(*)'];
	}

	static public function set_sort_field($sort_field)
	{
		switch ($sort_field)
		{
			case 'update': 
				$sort_field = 'update_dt';
				break;
			case 'create': 
				$sort_field = 'create_dt';
				break;
			case 'count':
			case 'id':
				break;
			default: $sort_field = 'sort_dt';
		}
		Model_Kwalbum_Item::$_sort_field = $sort_field;
	}

	static public function set_sort_direction($sort_direction)
	{
		if ($sort_direction == 'ASC')
		{
			Model_Kwalbum_Item::$_sort_direction = 'ASC';
			Model_Kwalbum_Item::$_gtlt = '<';
		}
		else
		{
			Model_Kwalbum_Item::$_sort_direction = 'DESC';
			Model_Kwalbum_Item::$_gtlt = '>';
		}
	}

	static public function get_index($id, $sort_value)
	{
		$where_query = Model_Kwalbum_Item::get_where_query();
		if ( ! $where_query)
			$where_query = ' WHERE ';
		else
			$where_query .= ' AND ';

		$sort_field = Model_Kwalbum_Item::$_sort_field;
		$sort_direction = Model_Kwalbum_Item::$_sort_direction;
		$gtlt = Model_Kwalbum_Item::$_gtlt;
		$query = "SELECT count(*)
			FROM kwalbum_items $where_query
			($sort_field $gtlt :sort_value
				OR ($sort_field = :sort_value AND id $gtlt :id))
			ORDER BY $sort_field $sort_direction, id $sort_direction";
		$result = DB::query(Database::SELECT, $query)
			->param(':sort_value', $sort_value)
			->param(':id', $id)
			->execute();
		return (int)$result[0]['count(*)']+1;
	}

	static public function get_previous_item($id, $sort_value)
	{
		$where_query = Model_Kwalbum_Item::get_where_query();
		if ( ! $where_query)
			$where_query = ' WHERE ';
		else
			$where_query .= ' AND ';

		$sort_field = Model_Kwalbum_Item::$_sort_field;
		$sort_direction = Model_Kwalbum_Item::$_sort_direction;
		$sort_direction = ($sort_direction == 'ASC' ? 'DESC' : 'ASC');
		$gtlt = Model_Kwalbum_Item::$_gtlt;

		$query = "SELECT id
			FROM kwalbum_items $where_query
			($sort_field $gtlt :sort_value
				OR ($sort_field = :sort_value AND id $gtlt :id))
			ORDER BY $sort_field $sort_direction, id $sort_direction
			LIMIT 1";
		$result = DB::query(Database::SELECT, $query)
			->param(':sort_value', $sort_value)
			->param(':id', $id)
			->execute();
		return Model::factory('kwalbum_item')->load((int)$result[0]['id']);
	}

	static public function get_next_item($id, $sort_value)
	{
		$where_query = Model_Kwalbum_Item::get_where_query();
		if ( ! $where_query)
			$where_query = ' WHERE ';
		else
			$where_query .= ' AND ';

		$sort_field = Model_Kwalbum_Item::$_sort_field;
		$sort_direction = Model_Kwalbum_Item::$_sort_direction;
		$gtlt = (Model_Kwalbum_Item::$_gtlt == '<' ? '>' : '<');

		$query = "SELECT id
			FROM kwalbum_items $where_query
			($sort_field $gtlt :sort_value
				OR ($sort_field = :sort_value AND id $gtlt :id))
			ORDER BY $sort_field $sort_direction, id $sort_direction
			LIMIT 1";
		$result = DB::query(Database::SELECT, $query)
			->param(':sort_value', $sort_value)
			->param(':id', $id)
			->execute();
		return Model::factory('kwalbum_item')->load((int)$result[0]['id']);
	}

	static public function get_page_number($index)
	{
		return ceil($index/self::get_config('items_per_page'));
	}

	/**
	 * recursive function to find items to be used as markers on a map
	 * 
	 * @param float $left
	 * @param float $right
	 * @param float $top
	 * @param float $bottom
	 * @param string $where
	 * @param ArrayOfArray $data
	 * @param int $limit
	 * @param int $depth
	 * @return void
	 */
	static public function getMarkers($left, $right, $top, $bottom, &$where, &$data, $limit, $depth = 0) {
		$where_query = $where
			." AND latitude IS NOT NULL AND latitude != 0"
			." AND latitude >= '$bottom' AND latitude <= '$top'"
			.($left>$right
				? " AND (longitude >= '$left' OR longitude <= '$right')"
				: " AND longitude >= '$left' AND longitude <= '$right'");
			$query = "SELECT count(*) FROM kwalbum_items $where_query";
		$result = DB::query(Database::SELECT, $query)
			->execute();
		$count = (int)$result[0]['count(*)'];

		if (0 == $count)
			return;

		if (0 < $depth and $limit < $count)
		{
			$depth--;
			$centerLat = ($left+$right)/2;
			$centerLon = ($top+$bottom)/2;
			Model_Kwalbum_Item::getMarkers($left, $centerLat, $top, $centerLon, $where, $data, $limit, $depth);
			Model_Kwalbum_Item::getMarkers($centerLat, $right, $top, $centerLon, $where, $data, $limit, $depth);
			Model_Kwalbum_Item::getMarkers($left, $centerLat, $centerLon, $bottom, $where, $data, $limit, $depth);
			Model_Kwalbum_Item::getMarkers($centerLat, $right, $centerLon, $bottom, $where, $data, $limit, $depth);
		}
		else
		{
			if ($limit >= $count)
			{
				$query = 'SELECT id, latitude as lat, longitude as lon, count(*) as count, visible_dt as date, description'
					.' FROM kwalbum_items'
					.$where_query
					.' GROUP BY latitude, longitude'
					.' ORDER BY latitude'
					.' LIMIT '.$limit;
				$result = DB::query(Database::SELECT, $query)
					->execute();
				foreach($result as $row) {
					$row['group'] = false;
					$data[] = $row;
				}
			}
			else
			{
				$query = 'SELECT AVG(latitude) as lat, AVG(longitude) as lon'
					.' FROM kwalbum_items'
					.$where_query
					.' LIMIT 1';
				$result = DB::query(Database::SELECT, $query)
					->execute();
				$row = $result[0];
				$row['group'] = true;
				$row['count'] = $count;
				$data[] = $row;
			}
		}
		return;
	}
}
