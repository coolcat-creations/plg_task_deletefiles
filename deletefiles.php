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
			'call'            => 'deletefiles',
			'form'            => 'deletefilesTaskForm'
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
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'routineHandler',
			'onContentPrepareForm' => 'manipulateForms'
		];
	}

	/**
	 * @param   ExecuteTaskEvent  $event  onExecuteTask Event
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 * @since  __DEPLOY_VERSION
	 */
	public function routineHandler(ExecuteTaskEvent $event): void
	{
		if (!array_key_exists($routineId = $event->getRoutineId(), self::TASKS_MAP))
		{
			return;
		}

		$this->taskStart($event);

		// Access to task parameters
		$timeout = $params->timeout ?? 1;
		$timeout = ((int) $timeout) ?: 1;

		// Plugin does whatever it wants

		if (array_key_exists('call', self::TASKS_MAP[$routineId]))
		{
//			$this->{self::TASKS_MAP[$routineId]['call']}();

			$params = $event->getArgument('params');

			$this->initWithParameters($params);

			// Initialize global variables
			$currentTime = time();

			// Scan and delete
			$this->deleteOlderItems();

		}

		$this->taskEnd($event, 0);
	}

	/**
	 * @param   Event  $event  The onContentPrepareForm event.
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

		if ($context === 'com_scheduler.task')
		{
			$this->enhanceTaskItemForm($form, $data);
		}
	}

	public function deleteDirectory($directoryPath)
	{

		// Add directory separator if necessary
		if (!endsWith($directoryPath, DIRECTORY_SEPARATOR))
		{
			$directoryPath .= DIRECTORY_SEPARATOR;
		}

		if (is_dir($directoryPath))
		{

			$items = scandir($directoryPath);

			foreach ($items as $item)
			{

				if ($item != '.' && $item != '..')
				{

					$itemPath = $directoryPath . $item;

					if (is_dir($itemPath) && !is_link($itemPath))
					{

						if (!$this->deleteDirectory($itemPath))
						{

							return false;

						}

					}
					else
					{

						if (!unlink($itemPath))
						{

							return false;

						}

					}
				}
			}

			rmdir($directoryPath);

			return true;

		}
		else
		{

			return false;
		}

	}

	public function initWithParameters($params)
	{

		$this->addTaskLog('This plugin is checkin the params');

		// Initializes global variables from command line parameters
		function endsWith($haystack, $needle) {

			// Checks whether a string ends with the specified substring

			$length = strlen($needle);

			if ($length == 0) {
				return true;
			}

			return (substr($haystack, -$length) === $needle);
		}

		global $directoryPath, $dayCount, $includeDirectories, $debugMode;

		if (!isset($params))
		{
			// No parameters
			$this->addTaskLog('No parameters', 'error');
		}
		else
		{
			if (!$params->directory || !$params->days)
			{
				// Missing important params
				$this->addTaskLog('This script requires the directory and the age of the files.');
			}

			else
			{

				// Get and validate the first parameter as a directory path
				$directoryPath = $params->directory;

				if (!endsWith($directoryPath, '/'))
				{
					$directoryPath .= DIRECTORY_SEPARATOR;
				}

				if (!is_dir($directoryPath))
				{
					$this->addTaskLog('No directory found at ' . $directoryPath . '. Please specify a valid directory path as the first parameter.');
				}

				// Get and validate the second parameter as an integer
				if ((string) (int) $params->days != $params->days)
				{
					$this->addTaskLog('The file age parameter is invalid. Please specify an integer for the count of days.');
				}

				$dayCount = (int) $params->days;

				// Determine parameter value to include directories or not
				$includeDirectories = false;

				if ($params->deletedirectories)
				{
					if ($params->deletedirectories == '--delete-directories')
					{
						$includeDirectories = true;
					}
					else
					{
						$this->addTaskLog('Directories will be untouched');
					}
				}

				$debugMode = false;
				$debugMode = $params->debugMode;

				if ($params->debugMode)
				{
					if ($params->debugMode == '1')
					{
						$debugMode = true;
					}
					else
					{
						$debugMode = false;
					}
				}


			}
		}

	}

	public function deleteOlderItems()
	{

		// Scans the directory for older items and deletes them

		global $directoryPath, $dayCount, $includeDirectories, $currentTime;

		$this->addTaskLog('Starting now the task');


		$ignoredItems = ['.', '..'];

		$scan = scandir($directoryPath);

		foreach ($scan as $key => $itemName)
		{

			if (!in_array($itemName, $ignoredItems))
			{

				$itemPath = $directoryPath . $itemName;
				$isDirectory = is_dir($itemPath);

				if ($includeDirectories || !$isDirectory)
				{

					$creationTime = filemtime($itemPath);

					$ageInDays = floor(($currentTime - $creationTime) / 60 / 60 / 24);

					if ($ageInDays >= $dayCount)
					{

						if ($isDirectory)
						{

							if ($this->deleteDirectory($itemPath))
							{
								$this->addTaskLog('Deleted directory ' . dayCountWithSuffix($ageInDays) . ' old: ' . $itemPath);
							}
							else
							{
								$this->addTaskLog('Could not delete directory ' . dayCountWithSuffix($ageInDays) . ' old: ' . $itemPath);
							}

						}
						else
						{

							if (unlink($itemPath))
							{
								$this->addTaskLog('Deleted file ' . dayCountWithSuffix($ageInDays) . ' old: ' . $itemPath);
							}
							else
							{
								$this->addTaskLog('Could not delete file ' . dayCountWithSuffix($ageInDays) . ' old: ' . $itemPath);
							}

						}

					}

				}

			}

			$this->addTaskLog('Processed:' .  $itemName);


		}

		$this->addTaskLog('I think I am ready');


	}


}
