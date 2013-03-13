<?php
/**
 * WeNotif-Likes's main plugin file
 * 
 * @package Dragooon:WeNotif-Likes
 * @author Shitiz "Dragooon" Garg <Email mail@dragooon.net> <Url http://smf-media.com>
 * @copyright 2012-2013, Shitiz "Dragooon" Garg <mail@dragooon.net>
 * @license
 *		Licensed under "New BSD License (3-clause version)"
 *		http://www.opensource.org/licenses/BSD-3-Clause
 * @version 1.0
 */

if (!defined('WEDGE'))
	die('File cannot be requested directly');

/**
 * Callback for the hook, "notification_callback", registers this as a verified notifier for notifications
 *
 * @param array &$notifiers
 * @return void
 */
function wenotif_likes_callback(array &$notifiers)
{
	$notifiers['post_likes'] = new PostLikesNotifier();
}

/**
 * Hook callback for "display_message_list", doesn't do much except mark the topic's notifications as read
 *
 * @param array &$messages
 * @param array &$times
 * @param array &$all_posters
 * @return void
 */
function wenotif_likes_display(&$messages, &$times, &$all_posters)
{
    Notification::markReadForNotifier(we::$id, WeNotif::getNotifiers('post_likes'), $messages);
}

/**
 * Callback for "liked content", actually issues the notification
 *
 * @param string $content_type
 * @param int $id_content
 * @param bool $now_liked
 * @param int $like_time
 * @return void
 */
function wenotif_likes_liked_content($content_type, $id_content, $now_liked, $like_time)
{
	// Leave this sucker alone :P
	if (!$now_liked)
		return;

	// Make sure we can actually handle this like
	$notifier = WeNotif::getNotifiers($content_type . '_likes');
	if ($notifier == null || !($notifier instanceof LikesNotifier))
		return;

	$data = array(
		'member' => we::$user['name'],
		'count' => 1,
	);
	$id_member = $notifier->handleLike($id_content, $data);

	Notification::issue($id_member, $notifier, $id_content, $data);
}

/**
 * Base class for any type of liked content
 */
abstract class LikesNotifier extends Notifier
{
	/**
	 * Constructor, loads the language file for some strings we use
	 *
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
		loadPluginLanguage('Dragooon:WeNotif-Likes', 'plugin');
	}

	/**
	 * Add a generic handler for handling multiple content, increments the basic count
	 *
	 * @access public
	 * @param Notification $notification
	 * @param array &$data
	 * @param array &$email_data
	 * @return bool (false)
	 */
	public function handleMultiple(Notification $notification, array &$data, array &$email_data)
	{
		$existing_data = $notification->getData();
		$existing_data['count']++;
		$notification->updateData($existing_data);

		return false;
	}

	/**
	 * Called to hook into while liking a post, extends any existing data
	 *
	 * @access public
	 * @param int $id_content
	 * @param array &$data
	 * @return int The ID of the member to issue this to
	 */
	abstract public function handleLike($id_content, array &$data);
}

/**
 * Notifier for likes specifically for posts
 */
class PostLikesNotifier extends LikesNotifier
{
	/**
	 * Hook callback for handling likes, returns the recepient member
	 *
	 * @access public
	 * @param int $id_content
	 * @param array &$data
	 * @return int
	 */
	public function handleLike($id_content, array &$data)
	{
		$request = wesql::query('
			SELECT id_topic, subject, id_member
			FROM {db_prefix}messages
			WHERE id_msg = {int:message}
			LIMIT 1',
			array(
				'message' => $id_content,
			)
		);
		list ($topic, $subject, $member) = wesql::fetch_row($request);
		wesql::free_result($request);

		$data += array(
			'topic' => $topic,
			'subject' => $subject,
		);

		return $member;
	}

	/**
	 * Returns the notification's URL
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string
	 */
	public function getURL(Notification $notification)
	{
		$data = $notification->getData();
		return 'topic=' . $data['topic'] . '.msg' . $notification->getObject() . '#msg' . $notification->getObject();
	}

	/**
	 * Returns the text for this notification
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string
	 */
	public function getText(Notification $notification)
	{
		global $txt;

		$data = $notification->getData();

		if ($data['count'] > 1)
			return sprintf($txt['notification_likes_post_multi'], $data['member'], $data['count'], $data['subject']);
		else
			return sprintf($txt['notification_likes_post'], $data['member'], $data['subject']);
	}

	/**
	 * Returns the profile info for the notifier
	 *
	 * @access public
	 * @param int $id_member
	 * @return array
	 */
	public function getProfile($id_member)
	{
		global $txt;

		return array($txt['notification_likes_post_profile'], $txt['notification_likes_post_profile_desc'], array());
	}

	/**
	 * Returns this notifier's identifier
	 *
	 * @access public
	 * @return string
	 */
	public function getName()
	{
		return 'post_likes';
	}

	/**
	 * Returns instant e-mail subject and body
	 *
	 * @access public
	 * @param Notification $notification
	 * @param array $email_data
	 * @return array
	 */
	public function getEmail(Notification $notification, array $email_data)
	{
		global $txt;

		return array($txt['notification_likes_post_subject'], $this->getText($notification));
	}
}