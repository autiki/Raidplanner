<?php
/**
*
* @author alightner
* @author Sajaki
* @package bbDKP Raidplanner
* @copyright (c) 2009 alightner
* @copyright (c) 2014 Sajaki : refactoring, adapting to bbdkp
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
* @version 1.0.2
*/
namespace bbdkp\views\raidplanner;
use  bbdkp\views\Raidplan_display;
/**
 * @ignore
 */
if ( !defined('IN_PHPBB') OR !defined('IN_BBDKP') )
{
	exit;
}

if (!class_exists('\bbdkp\controller\raidplanner\rpevents'))
{
    include($phpbb_root_path . 'includes/bbdkp/controller/raidplanner/Rpevents.' . $phpEx);
}
/**
 * the base class
 *
 */
abstract class RaidCalendar
{
	/**
	 * core date object.
	 *
	 * @var array
	 */
	public $date = array();

	/**
	 * month names
	 *
	 * @var array
	 */
	public $month_names = array();

	/**
	 * names of days. depends on acp setting
	 *
	 * @var array
	 */
	public $daynames = array();


	/**
	 * number of days in month
	 *
	 * @var int
	 */
    public $days_in_month = 0;

    private $eventlist;
    /**
     * @param \bbdkp\controller\raidplanner\rpevents $eventlist
     */
    public function setEventlist($eventlist)
    {
        $this->eventlist = $eventlist;
    }

    /**
     * @return \bbdkp\controller\raidplanner\rpevents
     */
    public function getEventlist()
    {
        return $this->eventlist;
    }

	/**
	 *
	 *
	 * @var unknown_type
	 */
	public $group_options;
	public $period_start;
	public $period_end;
	public $timestamp;

	public $timezone;
	/**
	 *
	 */
	function __construct( \bbdkp\views\viewPlanner $viewPlanner)
	{
		global $user, $config;

        //fetch event list and inject it through
        $this->eventlist= new \bbdkp\controller\raidplanner\rpevents($viewPlanner->dkpsys_id);
        $this->eventlist = $this->eventlist->events;

		//set month names (common.php lang entry)
		$this->month_names[1] = "January";
		$this->month_names[2] = "February";
		$this->month_names[3] = "March";
		$this->month_names[4] = "April";
		$this->month_names[5] = "May";
		$this->month_names[6] = "June";
		$this->month_names[7] = "July";
		$this->month_names[8] = "August";
		$this->month_names[9] = "September";
		$this->month_names[10] = "October";
		$this->month_names[11] = "November";
		$this->month_names[12] = "December";

		//get the selected date and set it into an array
		$this->date['day'] = request_var('calD', date("d", time()));
		$this->date['month'] = $this->month_names[ request_var('calM', date("n", time()))] ;
		$this->date['month_no'] = request_var('calM', date("n", time()) );
		$this->date['year'] = request_var('calY', date("Y", time()) );
		$this->date['dayname'] = date('l', strtotime($this->date['year'].'/'.$this->date['month_no']."/".$this->date['day']));

		$this->date['prev_month'] = $this->date['month'] - 1;
		$this->date['next_month'] = $this->date['month'] + 1;

		if(!function_exists('cal_days_in_month'))
		{
 			function cal_days_in_month($calendar,$month, $year)
			{
				// $calendar just gets ignored, assume gregorian
				// calculate number of days in a month
					return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31);
			}
	  	}
        if (!defined('CAL_GREGORIAN'))
        {
            define('CAL_GREGORIAN', 1);
        }

        $this->days_in_month = cal_days_in_month(CAL_GREGORIAN, $this->date['month_no'], $this->date['year']);

		//set day names
		$this->get_weekday_names();

		//get utc date
		$this->timestamp = 	gmmktime(0, 0, 0, $this->date['month_no'], $this->date['day'], $this->date['year']);

		// we need to find out the time zone to display
		if ($user->data['user_id'] == ANONYMOUS)
		{
		 	//grab board default
		 	$tz = $config['board_timezone'];
		}
		else
		{
			// get user setting
			$tz = (int) $user->data['user_timezone'];
		}
		$this->timezone = $user->lang['tz'][$tz];

		$this->group_options = $this->get_sql_group_options();
	}

	/**
	 * get gmt timestamp for first day for current (gmt) timestamp
	 *
	 * @param int $inDate
	 * @return int
	 */
	protected function Get1stDayofMonth($inDate)
	{
		//in  1321056000
		//GMT: Sat, 12 Nov 2011 00:00:00 GMT
		//Your time zone: Sat Nov 12 01:00:00 2011 GMT+1
		$firstDate = gmmktime(0,0,0, gmdate('m',$inDate), 01, gmdate('Y',$inDate)) ;
		//GMT: Tue, 01 Nov 2011 00:00:00 GMT
		// Your time zone: Tue Nov 1 01:00:00 2011 GMT+1
		return $firstDate;
	}

	/**
	 * get gmt timestamp for last day for current gmt timestamp
	 *
	 * @param int $inDate
	 * @return int
	 */
	protected function GetLastDayofMonth($inDate)
	{
		//in  1321056000
		//GMT: Sat, 12 Nov 2011 00:00:00 GMT
		//Your time zone: Sat Nov 12 01:00:00 2011 GMT+1
		global $user;
		$month = gmdate('m', $inDate);
		$year = gmdate('Y', $inDate);
		date_default_timezone_set('UTC');
		$result = strtotime("{$year}-{$month}-01");
		//go back 1 second
		$dateEnd = strtotime('-1 second',strtotime('+1 month', $result ));
		//GMT: Wed, 30 Nov 2011 23:59:59 GMT
		//Your time zone: Thu Dec 1 00:59:59 2011 GMT+1
		return $dateEnd;
	}

	/**
	 * Displays header, week, month, or day (see implementations)
	 *
	 */
	public abstract function display();


    /**
     * fday is used to determine in what day we are starting with in week view
     *
     * @param int $day
     * @param int $month
     * @param int $year
     * @internal param int $first_day_of_week
     * @return int
     */
	protected function get_firstday($day, $month, $year)
	{
		global $config;

		/**
		 * 0=mon
		 * 1=tue
		 * 2=wed
		 * 3=thu
		 * 4=fri
		 * 5=sat
		 * 6=sun
		 */
		$fday = gmdate("N",gmmktime(0,0,0, $month, $day, $year)) - 1;

		// first day 0 being monday in acp,
		$fday = $fday -  (int) $config['rp_first_day_of_week'];
		if( $fday < 0 )
		{
			$fday = $fday + 7;
		}
		return $fday;
	}

    /**
     * Generates array of birthdays for the given UTC range for users/founders
     *
     * @param $from
     * @param $end
     * @internal param int $day
     * @internal param int $month
     * @internal param int $year
     * @return string
     */
	protected function generate_birthday_list($from, $end)
	{
		global $db, $user, $config;

		$birthday_list = "";
		if ($config['load_birthdays'] && $config['allow_birthdays'])
		{
			$day1= gmdate("j", $from);
			$month1= gmdate("n", $from);
			$year1= gmdate("Y", $from);

			$day2= gmdate("j", $end);
			$month2= gmdate("n", $end);
			$year2= gmdate("Y", $end);

			$sql = 'SELECT user_id, username, user_colour, user_birthday
					FROM ' . USERS_TABLE . "
					WHERE (( user_birthday >= '" . $db->sql_escape(sprintf('%2d-%2d-%4d', $day1, $month1, $year1 )) . "'
					AND user_birthday <= '" . $db->sql_escape(sprintf('%2d-%2d-%4d', $day2, $month2, $year2 )) . "')
					AND user_birthday " . $db->sql_like_expression($db->any_char . '-' . sprintf( '%2d', $month2)  .'-' . $db->any_char) . ' )
					AND user_type IN (' . USER_NORMAL . ', ' . USER_FOUNDER . ')
					ORDER BY user_birthday ASC';

            // cache the list of birthdays for a month.
			$result = $db->sql_query($sql, 2419200);
			$oldday= $newday = "";
			while ($row = $db->sql_fetchrow($result))
			{
				$birthday_str = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);
				$age = (int) substr($row['user_birthday'], -4);
				$birthday_str .= ' (' . ($year2 - $age) . ')';

				$newday = trim(substr($row['user_birthday'],0, 2));

				if($oldday != $newday)
				{
					// new birthday found, make new string
					$daystr = $birthday_str;
					$birthday_list[$newday] = array(
						'day' => $row['user_birthday'],
						'bdays' =>  $user->lang['BIRTHDAYS'].": ". $daystr,
					);


				}
				else
				{
					// other bday on same day, add it
					$daystr = $birthday_list[$oldday]['bdays'] .", ". $birthday_str;
					// modify array entry
					$birthday_list[$oldday] = array(
						'day' => $row['user_birthday'],
						'bdays' =>  $daystr,
					);

				}
				$oldday = $newday;

			}
			$db->sql_freeresult($result);
		}

		return $birthday_list;
	}

	/*
	 * return group list
	 */
	private function get_sql_group_options()
	{
		global $user, $db;

        /*
          What groups is this user a member of?
          don't check for hidden group setting -
          if raidplan was made by the admin for a hidden group -
          members of the hidden group need to be able to see the raidplan in the calendar
        */

		$sql = 'SELECT g.group_id, g.group_name, g.group_type
				FROM ' . GROUPS_TABLE . ' g, ' . USER_GROUP_TABLE . ' ug
				WHERE ug.user_id = '.$db->sql_escape($user->data['user_id']).'
					AND g.group_id = ug.group_id
					AND ug.user_pending = 0
				ORDER BY g.group_type, g.group_name';
        // cache the list of groups for a week.
		$result = $db->sql_query($sql, 604800);

		$group_options = '';
		while ($row = $db->sql_fetchrow($result))
		{
			if( $group_options != "" )
			{
				$group_options .= " OR ";
			}
			$group_options .= "group_id = ".$row['group_id']. " OR group_id_list LIKE '%,".$row['group_id']. ",%'";
		}
		$db->sql_freeresult($result);
		return $group_options;
	}

	/**
	* Fill smiley templates (or just the variables) with smilies, either in a window or inline
	*
	*/
	public function generate_calendar_smilies($mode)
	{
		global $db, $user, $config, $template, $phpEx, $phpbb_root_path;

		if ($mode == 'window')
		{
			page_header($user->lang['SMILIES']);

			$template->set_filenames(array(
				'body' => 'posting_smilies.html')
			);
		}

		$display_link = false;
		if ($mode == 'inline')
		{
			$sql = 'SELECT smiley_id
				FROM ' . SMILIES_TABLE . '
				WHERE display_on_posting = 0';
            //cache smiley list forever
			$result = $db->sql_query_limit($sql, 1, 0, 29030400);

			if ($row = $db->sql_fetchrow($result))
			{
				$display_link = true;
			}
			$db->sql_freeresult($result);
		}

		$last_url = '';

		$sql = 'SELECT *
			FROM ' . SMILIES_TABLE .
			(($mode == 'inline') ? ' WHERE display_on_posting = 1 ' : '') . '
			ORDER BY smiley_order';
		$result = $db->sql_query($sql, 29030400);

		$smilies = array();
		while ($row = $db->sql_fetchrow($result))
		{
			if (empty($smilies[$row['smiley_url']]))
			{
				$smilies[$row['smiley_url']] = $row;
			}
		}
		$db->sql_freeresult($result);

		if (sizeof($smilies))
		{
			foreach ($smilies as $row)
			{
				$template->assign_block_vars('smiley', array(
					'SMILEY_CODE'	=> $row['code'],
					'A_SMILEY_CODE'	=> addslashes($row['code']),
					'SMILEY_IMG'	=> $phpbb_root_path . $config['smilies_path'] . '/' . $row['smiley_url'],
					'SMILEY_WIDTH'	=> $row['smiley_width'],
					'SMILEY_HEIGHT'	=> $row['smiley_height'],
					'SMILEY_DESC'	=> $row['emotion'])
				);
			}
		}

		if ($mode == 'inline' && $display_link)
		{
			$template->assign_vars(array(
				'S_SHOW_SMILEY_LINK' 	=> true,
				'U_MORE_SMILIES' 		=> append_sid("{$phpbb_root_path}calendarpost.$phpEx", 'mode=smilies'))
			);
		}

		if ($mode == 'window')
		{
			page_footer();
		}
	}


	/*
	 * "shift" names of weekdays depending on which day we want to display as the first day of the week
	*/
	private function get_weekday_names()
	{
		global $config, $user;
		switch((int) $config['rp_first_day_of_week'])
		{
			case 0:
				//monday
				$this->daynames[6] = $user->lang['datetime']['Sunday'];
				$this->daynames[0] = $user->lang['datetime']['Monday'];
				$this->daynames[1] = $user->lang['datetime']['Tuesday'];
				$this->daynames[2] = $user->lang['datetime']['Wednesday'];
				$this->daynames[3] = $user->lang['datetime']['Thursday'];
				$this->daynames[4] = $user->lang['datetime']['Friday'];
				$this->daynames[5] = $user->lang['datetime']['Saturday'];
				break;
			case 1:
				//tue
				$this->daynames[5] = $user->lang['datetime']['Sunday'];
				$this->daynames[6] = $user->lang['datetime']['Monday'];
				$this->daynames[0] = $user->lang['datetime']['Tuesday'];
				$this->daynames[1] = $user->lang['datetime']['Wednesday'];
				$this->daynames[2] = $user->lang['datetime']['Thursday'];
				$this->daynames[3] = $user->lang['datetime']['Friday'];
				$this->daynames[4] = $user->lang['datetime']['Saturday'];
				break;
			case 2:
				//wed
				$this->daynames[4] = $user->lang['datetime']['Sunday'];
				$this->daynames[5] = $user->lang['datetime']['Monday'];
				$this->daynames[6] = $user->lang['datetime']['Tuesday'];
				$this->daynames[0] = $user->lang['datetime']['Wednesday'];
				$this->daynames[1] = $user->lang['datetime']['Thursday'];
				$this->daynames[2] = $user->lang['datetime']['Friday'];
				$this->daynames[3] = $user->lang['datetime']['Saturday'];
				break;
			case 3:
				//thu
				$this->daynames[3] = $user->lang['datetime']['Sunday'];
				$this->daynames[4] = $user->lang['datetime']['Monday'];
				$this->daynames[5] = $user->lang['datetime']['Tuesday'];
				$this->daynames[6] = $user->lang['datetime']['Wednesday'];
				$this->daynames[0] = $user->lang['datetime']['Thursday'];
				$this->daynames[1] = $user->lang['datetime']['Friday'];
				$this->daynames[2] = $user->lang['datetime']['Saturday'];
				break;
			case 4:
				//fri
				$this->daynames[2] = $user->lang['datetime']['Sunday'];
				$this->daynames[3] = $user->lang['datetime']['Monday'];
				$this->daynames[4] = $user->lang['datetime']['Tuesday'];
				$this->daynames[5] = $user->lang['datetime']['Wednesday'];
				$this->daynames[6] = $user->lang['datetime']['Thursday'];
				$this->daynames[0] = $user->lang['datetime']['Friday'];
				$this->daynames[1] = $user->lang['datetime']['Saturday'];
				break;
			case 5:
				//sat
				$this->daynames[1] = $user->lang['datetime']['Sunday'];
				$this->daynames[2] = $user->lang['datetime']['Monday'];
				$this->daynames[3] = $user->lang['datetime']['Tuesday'];
				$this->daynames[4] = $user->lang['datetime']['Wednesday'];
				$this->daynames[5] = $user->lang['datetime']['Thursday'];
				$this->daynames[6] = $user->lang['datetime']['Friday'];
				$this->daynames[0] = $user->lang['datetime']['Saturday'];
				break;
			case 6:
				//sun
				$this->daynames[0] = $user->lang['datetime']['Sunday'];
				$this->daynames[1] = $user->lang['datetime']['Monday'];
				$this->daynames[2] = $user->lang['datetime']['Tuesday'];
				$this->daynames[3] = $user->lang['datetime']['Wednesday'];
				$this->daynames[4] = $user->lang['datetime']['Thursday'];
				$this->daynames[5] = $user->lang['datetime']['Friday'];
				$this->daynames[6] = $user->lang['datetime']['Saturday'];
				break;
		}
	}


}
