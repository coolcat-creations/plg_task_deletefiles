<?php
/**
 * @package       Joomla.Plugins
 * @subpackage    Task.Testtasks
 *
 * @copyright (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

/** A demo Task plugin for com_scheduler. */

// Restrict direct access
defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * The plugin class
 *
 * @since __DEPLOY__VERSION__
 */
class PlgTaskDeletefiles extends CMSPlugin implements SubscriberInterface
{
	use TaskPluginTrait;

	/**
	 * @var string[]
	 * @since __DEPLOY_VERSION__
	 */
	private const TASKS_MAP = [
		'deletefiles' => [
			'langConstPrefix' => 'PLG_TASK_DELETEFILES_TASKS',
			'call' => 'deletefiles',
			'form' => 'deletefilesTaskForm'
		]
	];

	/**
	 * Autoload the language file
	 *
	 * @var boolean
	 * @since __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * An array of supported Form contexts
	 *
	 * @var string[]
	 * @since __DEPLOY_VERSION__
	 */
	private $supportedFormContexts = [
		'com_scheduler.task'
	];

	/**
	 * Returns event subscriptions
	 *
	 * @return string[]
	 *
	 * @since __DEPLOY__
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList' => 'advertiseRoutines',
			'onExecuteTask' => 'routineHandler',
			'onContentPrepareForm' => 'manipulateForms'
		];
	}

	/**
	 * @param ExecuteTaskEvent $event onExecuteTask Event
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 * @since  __DEPLOY_VERSION
	 */
	public function routineHandler(ExecuteTaskEvent $event): void
	{

		// this is obligatory
		if (!array_key_exists($routineId = $event->getRoutineId(), self::TASKS_MAP)) {
			return;
		}

		// starting the task
		$this->taskStart($event);

		// check the timeout
		$timeout = $params->timeout ?? 1;
		$timeout = ((int)$timeout) ?: 1;


		// Plugin does whatever it wants

		if (array_key_exists('call', self::TASKS_MAP[$routineId])) {
//			$this->{self::TASKS_MAP[$routineId]['call']}();

			$this->addTaskLog('Hi there!');


			$params = $event->getArgument('params');

			$this->initWithParameters($params);

			// Scan and delete
			$this->deleteOlderItems();

		}

		$this->taskEnd($event, 0);
	}

	/**
	 * @param Event $event The onContentPrepareForm event.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 * @since  __DEPLOY_VERSION__
	 */
	public function manipulateForms(Event $event): void
	{
		/** @var Form $form */
		$form = $event->getArgument('0');
		$data = $event->getArgument('1');

		$context = $form->getName();

		if ($context === 'com_scheduler.task') {
			$this->enhanceTaskItemForm($form, $data);
		}
	}


	public function initWithParameters($params)
	{

		$this->addTaskLog('I am checking your params');

		global $directoryPath, $dayCount, $deletedirectories, $currentTime, $debugMode;


		$currentTime = new Date();
		$currentTime = $currentTime->toUnix(); // 1354375200

		if (!isset($params)) {
			// No parameters
			$this->addTaskLog('No params found', 'error');
		} else {
			if (!$params->directory || !$params->days) {
				// Missing important params
				$this->addTaskLog('This script requires the directory and the age of the files.', 'error');
			} else {

				// Get and validate the first parameter as a directory path

				$directoryPath = Path::check(JPATH_ROOT . '/' . $params->directory);

				$this->addTaskLog('You want to clear ' . $directoryPath);


				if (!Folder::exists($directoryPath)) {
					$this->addTaskLog('No directory found at ' . $directoryPath . '. Please specify a valid directory path as the first parameter.');
				} else {
					$this->addTaskLog('I found ' . $directoryPath . '.');
				}

				// Get and validate the second parameter as an integer
				if ((string)(int)$params->days != $params->days) {
					$this->addTaskLog('The file age parameter is invalid. Please specify an integer for the count of days.');
				}

				$dayCount = (int)$params->days;

				// Determine parameter value to delete directories or not

				$deletedirectories = false;

				if ($params->deletedirectories) {
					if ($params->deletedirectories == '1') {
						$deletedirectories = true;
						$this->addTaskLog('Directories will be deleted');
					} else {
						$this->addTaskLog('Directories will not be deleted');
					}
				}

				$debugMode = false;
				$debugMode = $params->debugMode;

				if ($params->debugMode) {
					if ($params->debugMode == '1') {
						$debugMode = true;
					} else {
						$debugMode = false;
					}
				}


			}
		}

	}

	public function deleteDirectory($directoryPath)
	{

		if (is_dir($directoryPath)) {

			$this->addTaskLog('its a directory');


			$items = scandir($directoryPath);

			foreach ($items as $item) {

				if ($item != '.' && $item != '..') {

					$itemPath = $directoryPath . '/' . $item;

					if (Folder::exists($itemPath) && !is_link($itemPath)) {

						if (!$this->deleteDirectory($itemPath)) {
							$this->addTaskLog('Line 257');
							return false;
						}

					} else {

						if (!unlink($itemPath)) {
							$this->addTaskLog($itemPath . ' was not deleted');

							return false;

						}

					}
				}
			}

			$this->addTaskLog('Line 278' . $directoryPath);

			rmdir($directoryPath);

			return true;
		} else {

			return false;
		}

	}

	public function deleteOlderItems()
	{

		// Scans the directory for older items and deletes them

		global $directoryPath, $dayCount, $deletedirectories, $currentTime;

		$this->addTaskLog('Starting now the task');

		$ignoredItems = ['.', '..'];

		$scan = scandir($directoryPath);

		foreach ($scan as $key => $itemName) {

			if (!in_array($itemName, $ignoredItems)) {

				$itemPath = $directoryPath . '/' . $itemName;

				$isDirectory = is_dir($itemPath);

				if ($deletedirectories || !$isDirectory) {

					$creationTime = filemtime($itemPath);
					$ageInDays = floor(($currentTime - $creationTime) / 60 / 60 / 24);

					if ($ageInDays >= $dayCount) {
						$this->addTaskLog($itemPath . ' is too old and will be deleted');

						if ($isDirectory) {

							if ($this->deleteDirectory($itemPath)) {
								$this->addTaskLog('Deleted directory ' . $ageInDays . ' old: ' . $itemPath);
							} else {
								$this->addTaskLog('Could not delete directory ' . $ageInDays . ' old: ' . $itemPath);
							}

						} else {

							if (unlink($itemPath)) {
								$this->addTaskLog('Deleted file ' . $ageInDays . ' old: ' . $itemPath);
							} else {
								$this->addTaskLog('Could not delete file ' . $ageInDays . ' old: ' . $itemPath);
							}

						}

					}

				}

			}

			$this->addTaskLog('Processed:' . $itemName);


		}

		$this->addTaskLog('I think I am ready');


	}



}
