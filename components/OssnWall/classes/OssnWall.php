<?php
/**
 * OpenSource-SocialNetwork
 *
 * @package   (Informatikon.com).ossn
 * @author    OSSN Core Team <info@opensource-socialnetwork.com>
 * @copyright 2014 iNFORMATIKON TECHNOLOGIES
 * @license   General Public Licence http://opensource-socialnetwork.com/licence
 * @link      http://www.opensource-socialnetwork.com/licence
 */
class OssnWall extends OssnObject {
		/**
		 * Post on wall
		 *
		 * @params $post: Post text
		 *         $friends: Friend guids
		 *         $location: Post location
		 *         $access: (OSSN_PUBLIC, OSSN_PRIVATE, OSSN_FRIENDS)
		 * @param string $post
		 *
		 * @return bool;
		 */
		public function Post($post, $friends = '', $location = '', $access = '') {
				self::initAttributes();
				if(empty($access)) {
						$access = OSSN_PUBLIC;
				}
				if($this->owner_guid < 1 || $this->poster_guid < 1 || empty($post)) {
						return false;
				}
				if(isset($this->item_type) && !empty($this->item_type)) {
						$this->data->item_type = $this->item_type;
				}
				if(isset($this->item_guid) && !empty($this->item_guid)) {
						$this->data->item_guid = $this->item_guid;
				}
				$this->data->poster_guid = $this->poster_guid;
				$this->data->access      = $access;
				$this->subtype           = 'wall';
				$this->title             = '';
				
				$wallpost['post'] = htmlspecialchars($post, ENT_QUOTES, 'UTF-8');
				if(!empty($friends)) {
						$wallpost['friend'] = $friends;
				}
				if(!empty($location)) {
						$wallpost['location'] = $location;
				}
				//Encode multibyte Unicode characters literally (default is to escape as \uXXXX)
				$this->description = json_encode($wallpost, JSON_UNESCAPED_UNICODE);
				if($this->addObject()) {
						$this->wallguid = $this->getObjectId();
						if(isset($_FILES['ossn_photo'])) {
								$this->OssnFile->owner_guid = $this->wallguid;
								$this->OssnFile->type       = 'object';
								$this->OssnFile->subtype    = 'wallphoto';
								$this->OssnFile->setFile('ossn_photo');
								$this->OssnFile->setPath('ossnwall/images/');
								$this->OssnFile->addFile();
						}
						$params['subject_guid'] = $this->wallguid;
						$params['poster_guid']  = $this->poster_guid;
						if(isset($wallpost['friend'])) {
								$params['friends'] = explode(',', $wallpost['friend']);
						}
						ossn_trigger_callback('wall', 'post:created', $params);
						return true;
				}
				return true;
		}
		
		/**
		 * Initialize the objects.
		 *
		 * @return void;
		 */
		public function initAttributes() {
				if(empty($this->type)) {
						$this->type = 'user';
				}
				$this->OssnFile = new OssnFile;
				if(!isset($this->data)) {
						$this->data = new stdClass;
				}
				$this->OssnDatabase = new OssnDatabase;
		}
		
		/**
		 * Get posts by owner
		 *
		 * @params $owner: Owner guid
		 *         $type Owner type
		 *
		 * @return object;
		 */
		public function GetPostByOwner($owner, $type = 'user') {
				self::initAttributes();
				$this->type       = $type;
				$this->subtype    = 'wall';
				$this->owner_guid = $owner;
				$this->order_by   = 'guid DESC';
				return $this->getObjectByOwner();
		}
		
		/**
		 * Get user posts
		 *
		 * @params $user: User guid
		 *
		 * @return object;
		 */
		public function GetUserPosts($user) {
				$this->type       = "user";
				$this->subtype    = 'wall';
				$this->owner_guid = $user;
				$this->order_by   = 'guid DESC';
				return $this->getObjectByOwner();
		}
		
		/**
		 * Get post by guid
		 *
		 * @params $guid: Post guid
		 *
		 * @return object;
		 */
		public function GetPost($guid) {
				$this->object_guid = $guid;
				return $this->getObjectById();
		}
		
		/**
		 * Delete post
		 *
		 * @params $post: Post guid
		 *
		 * @return bool;
		 */
		public function deletePost($post) {
				if($this->deleteObject($post)) {
						ossn_trigger_callback('post', 'delete', $post);
						return true;
				}
				return false;
		}
		
		/**
		 * Delete All Posts
		 *
		 * @return void;
		 */
		public function deleteAllPosts() {
				$posts = $this->GetPosts();
				if(!$posts) {
						return false;
				}
				foreach($posts as $post) {
						$this->deleteObject($post->guid);
						ossn_trigger_callback('post', 'delete', $post->guid);
				}
		}
		
		/**
		 * Get all site wall posts
		 *
		 * @return object;
		 */
		public function GetPosts() {
				self::initAttributes();
				$this->subtype  = 'wall';
				$this->order_by = 'guid DESC';
				return $this->getObjectsByTypes();
		}
		/**
		 * Get user group posts guids
		 *
		 * @param integer $userguid Guid of user
		 *
		 * @return array;
		 */
		public static function getUserGroupPostsGuids($userguid) {
				if(empty($userguid)) {
						return false;
				}
				$statement = "SELECT * FROM ossn_entities, ossn_entities_metadata WHERE(
				  ossn_entities_metadata.guid = ossn_entities.guid 
				  AND  ossn_entities.subtype='poster_guid'
				  AND type = 'object'
				  AND value = '{$userguid}'
				  );";
				$database  = new OssnDatabase;
				$database->statement($statement);
				$database->execute();
				$objects = $database->fetch(true);
				if($objects) {
						foreach($objects as $object) {
								$guids[] = $object->owner_guid;
						}
						asort($guids);
						return $guids;
				}
				return false;
		}
		/**
		 * Get user group posts guids
		 *
		 * @param integer $userguid Guid of user
		 *
		 * @return array;
		 */
		public function getFriendsPosts() {
				self::initAttributes();
				$user = ossn_loggedin_user();
				if(isset($user->guid) && !empty($user->guid)) {
						$friends      = $user->getFriends();
						$friend_guids = '';
						if($friends) {
								foreach($friends as $friend) {
										$friend_guids[] = $friend->guid;
								}
						}
						// add all users posts;
						// (if user has 0 friends, show at least his own postings if wall access type = friends only)
						$friend_guids[] = $user->guid;
						$friend_guids   = implode(',', $friend_guids);
						
						$wheres = "md.guid = e.guid 
		   				AND  e.subtype='poster_guid'
		   				AND e.type = 'object'
		   				AND md.value IN ({$friend_guids})
		   				AND o.guid = e.owner_guid";
					
						$this->OssnDatabase->statement("SELECT o.* FROM ossn_entities as e, 
					  					ossn_entities_metadata as md, 
					  					ossn_object as o WHERE({$wheres}) ORDER BY guid DESC;");
						$this->OssnDatabase->execute();
						$data = $this->OssnDatabase->fetch(true);
						if($data){
							return $data;
						}
						
				}
				return false;
		}
} //class
