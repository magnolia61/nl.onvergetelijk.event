<?php

error_reporting(E_ALL);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);

require_once 'kampevent.civix.php';

function kampevent_civicrm_custom($op, $groupID, $entityID, &$params) {

	$extdebug	= 1;

	//if (!in_array($groupID, array("101", "103", "139", "190", "181"))) {
	if (!in_array($groupID, array("211"))) { // ALLEEN PART + EVENT PROFILES
	//if (!in_array($groupID, array("139","190","165"))) { // ALLEEN PART PROFILES
		// 101  EVENT KENMERKEN
		// 211  EVENT KENMERKEN WERVING 
		// 103	TAB  kampevent
		// 139	PART DEEL
		// 190	PART LEID
		// (140	PART LEID VOG)
		// 181	TAB  INTAKE
		// 165	PART REFERENTIE
		//if ($extdebug == 1) { watchdog('php', '<pre>--- SKIP EXTENSION CV (not in proper group) [groupID: '.$groupID.'] [op: '.$op.']---</pre>', null, WATCHDOG_DEBUG); }
		return; //   if not, get out of here
	}

	if (in_array($groupID, array("211"))) {
		if ($extdebug == 1) { watchdog('php', '<pre>*** START EXTENSION EVENT KENMERKEN [groupID: '.$groupID.'] [op: '.$op.'] ***</pre>', null, WATCHDOG_DEBUG); }
		if ($extdebug == 1) { watchdog('php', '<pre>entityID:' . print_r($entityID, TRUE) . '</pre>', NULL, WATCHDOG_DEBUG); }

		//$event_plekken_jongens = NULL:
		//$event_plekken_meisjes = NULL:

    	$result = civicrm_api3('Event', 'get', array(
      		'sequential' => 1,
      		'return' => array("event_type_id", "custom_658", "custom_657", "has_waitlist", "waitlist_text", "event_full_text", "max_participants", "custom_516"),
      		'id' => $entityID,
    	));

	//if ($extdebug == 1) { watchdog('php', '<pre>geteventinfo_result:' . print_r($result, TRUE) . '</pre>', NULL, WATCHDOG_DEBUG); }

    	//$event_type_id	= $result['values'][0]['event_type_id'];
    	$event_plekken_jongens	= $result['values'][0]['custom_658'];
    	$event_plekken_meisjes	= $result['values'][0]['custom_657'];
    	$event_haswaitlist	= $result['values'][0]['has_waitlist'];
    	$event_waitlisttext	= $result['values'][0]['waitlist_text'];
   	$event_fulltext		= $result['values'][0]['event_full_text'];
    	$event_max_participants = $result['values'][0]['max_participants'];
    	$event_lastminute		= $result['values'][0]['custom_516'];

	if ($extdebug == 1) { watchdog('php', '<pre>event_plekken_jongens:' . print_r($event_plekken_jongens, TRUE) . '</pre>', NULL, WATCHDOG_DEBUG); }
    	if ($extdebug == 1) { watchdog('php', '<pre>event_plekken_meisjes:' . print_r($event_plekken_meisjes, TRUE) . '</pre>', NULL, WATCHDOG_DEBUG); }
    	if ($extdebug == 1) { watchdog('php', '<pre>event_haswaitlist:' . print_r($event_haswaitlist, TRUE) . '</pre>', NULL, WATCHDOG_DEBUG); }
    	//if ($extdebug == 1) { watchdog('php', '<pre>event_waitlisttext:' . print_r($event_waitlisttext, TRUE) . '</pre>', NULL, WATCHDOG_DEBUG); }
    	//if ($extdebug == 1) { watchdog('php', '<pre>event_fulltext:' . print_r($event_fulltext, TRUE) . '</pre>', NULL, WATCHDOG_DEBUG); }
    	if ($extdebug == 1) { watchdog('php', '<pre>event_max_participants:' . print_r($event_max_participants, TRUE) . '</pre>', NULL, WATCHDOG_DEBUG); }
    	if ($extdebug == 1) { watchdog('php', '<pre>event_lastminute:' . print_r($event_lastminute, TRUE) . '</pre>', NULL, WATCHDOG_DEBUG); }

    	$event_jong_wait_meis_wait	= "Dit kamp is op dit moment zo goed als vol. Per deelnemer moeten we bekijken of er nog plek is. Dit heeft te maken met de verhouding jongens/meisjes en de beschikbare plekken in de slaapzalen. U kunt uw kind aanmelden voor de wachtlijst. We zullen u op de hoogte stellen of, en zo ja, wanneer de aanmelding alsnog doorgang kan vinden.";

    	$event_jong_wait_meis_free	= "LET OP: Voor dit kamp is nog voldoende plek voor meisjes. Voor jongens moeten nog puzzelen en daarom komen ze op de wachtlijst. Meisjes komen trouwens eerst ook op de wachtlijst maar we sturen u vrij snel na de aanmelding een linkje om de aanmelding van uw dochter alsnog af te kunnen ronden.";
    	$event_jong_free_meis_wait	= "LET OP: Voor dit kamp is nog voldoende plek voor jongens. Voor meisjes moeten nog puzzelen en daarom komen ze op de wachtlijst. Jongens komen trouwens eerst ook op de wachtlijst maar we sturen u vrij snel na de aanmelding een linkje om de aanmelding van uw zoon alsnog af te kunnen ronden.";

    	$event_jong_full_meis_free	= "LET OP: Voor dit kamp is op dit moment geen plek meer voor jongens. Er is alleen nog plek voor meisjes. Meisjes komen eerst op de wachtlijst maar we sturen u vrij snel na de aanmelding een linkje om de aanmelding van uw dochter alsnog af te kunnen ronden.";
    	$event_jong_free_meis_full	= "LET OP: Voor dit kamp is op dit moment geen plek meer voor meisjes. Er is alleen nog plek voor jongens. Jongens komen eerst op de wachtlijst maar we sturen u vrij snel na de aanmelding een linkje om de aanmelding van uw zoon alsnog af te kunnen ronden.";

    	$event_jong_full_meis_wait	= "LET OP: Voor dit kamp is op dit moment geen plek meer voor jongens. Voor meisjes zijn we aan het puzzelen met de groeps- en slaapzaalindeling en daarom kunt u uw dochter aanmelden voor de wachtlijst. We zullen u op de hoogte stellen of, en zo ja, wanneer de aanmelding alsnog doorgang kan vinden.";
    	$event_jong_wait_meis_full	= "LET OP: Voor dit kamp is op dit moment geen plek meer voor meisjes. Voor jongens zijn we aan puzzelen met de groeps- en slaapzaalindeling en daarom kunt u uw zoon aanmelden voor de wachtlijst. We zullen u op de hoogte stellen of, en zo ja, wanneer de aanmelding alsnog doorgang kan vinden.";

    	$event_waitlist_naarjk		= "Hierboven ziet u wat de beschikbaarheid is voor jongens en voor meisjes. Op dit moment komt iedereen sowieso eerst op de wachtlijst. Voor wie er toch plek is sturen we een email om de aanmelding af te ronden. Jongens en meiden die rond december 16 worden zijn van harte welkom om mee te gaan met het Jeugdkamp in plaats van het Tienerkamp.";

    	//$event_fulltext 		= "Helaas zijn er voor dit kamp geen plekken meer beschikbaar. De aanmeldingen voor 2020 gaan op 1 januari weer open. We verwijzen u voor deze zomer graag naar onze collega kamporganisaties zoals o.a. Kaleb, YoY kampen, Camps4kids, Oase kampen, Wegwijzerkampen en Geloofshelden.";
    	$event_fulltext 		= "Helaas zijn er voor dit kamp geen plekken meer beschikbaar. Mogelijk is er nog wel plek in de andere week. Kijk voor de beschikbaarheid op www.onvergetelijk.nl/ouders/aanmelden";

    	$params_event = array(
   		'id' 			=> $entityID,
      		//'has_waitlist' 	=> 1, 	// default: wachtlijst is aan (indien aanmeldingen > max_participants)
      		'max_participants'	=> 1,
      		'waitlist_text' 	=> $event_jong_wait_meis_wait,
      		'event_full_text'	=> $event_fulltext,
      		//'custom_516'		=> 0,	// M61: deze staat dicht vanwege issues
    	);
    	// PLEK VOOR JONGENS & MEISJES
    	if (in_array($event_plekken_meisjes, array(";-)",":-)"), true) 	AND in_array($event_plekken_jongens, array(";-)",":-)"), true)) {
    		$params_event['has_waitlist'] 		= 0;
    		$params_event['max_participants'] 	= 200;
    		$params_event['waitlist_text']		= $event_jong_wait_meis_wait;
    		//$params_event['custom_516']		= 1; // op de lastminutelijst?
    		if ($extdebug == 1) { watchdog('php', '<pre>PLEK VOOR JONGENS & MEISJES</pre>', NULL, WATCHDOG_DEBUG); }
    	}
    	// WACHTLIJST VOOR JONGENS & PLEK VOOR MEISJES
    	if (in_array($event_plekken_jongens, array(":-|"), true)) {
    		$params_event['has_waitlist'] 		= 1;
     		$params_event['max_participants'] 	= 1;
    		$params_event['waitlist_text']		= $event_jong_wait_meis_free;
    		//$params_event['custom_516'] 		= 1; // op de lastminutelijst?
    		if ($extdebug == 1) { watchdog('php', '<pre>WACHTLIJST VOOR JONGENS & PLEK VOOR MEISJES</pre>', NULL, WATCHDOG_DEBUG); }
    	}
    	// WACHTLIJST VOOR MEISJES & PLEK VOOR JONGENS
    	if (in_array($event_plekken_meisjes, array(":-|"), true)) {
    		$params_event['has_waitlist'] 		= 1;
     		$params_event['max_participants'] 	= 1;
    		$params_event['waitlist_text']		= $event_jong_free_meis_wait;
    		//$params_event['custom_516'] 		= 1; // op de lastminutelijst?
    		if ($extdebug == 1) { watchdog('php', '<pre>WACHTLIJST VOOR MEISJES & PLEK VOOR JONGENS</pre>', NULL, WATCHDOG_DEBUG); }
    	}
    	// WACHTLIJST VOOR JONGENS & MEISJES
    	if (in_array($event_plekken_jongens, array(":-|"), true) 		AND in_array($event_plekken_meisjes, array(":-|"), true)) {
    		$params_event['has_waitlist'] 		= 1;
     		$params_event['max_participants'] 	= 1;
    		$params_event['waitlist_text']		= $event_jong_wait_meis_wait;
    		//$params_event['custom_516'] 		= 0; // op de lastminutelijst?
    		if ($extdebug == 1) { watchdog('php', '<pre>WACHTLIJST VOOR JONGENS & MEISJES</pre>', NULL, WATCHDOG_DEBUG); }
    	}
    	// VOL VOOR JONGENS & PLEK VOOR MEISJES
    	if (in_array($event_plekken_jongens, array(":-("), true) 		AND in_array($event_plekken_meisjes, array(":-)"), true)) {
    		$params_event['has_waitlist'] 		= 1;
     		$params_event['max_participants'] 	= 1;
    		$params_event['waitlist_text']		= $event_jong_full_meis_free;
    		//$params_event['custom_516'] 		= 1; // op de lastminutelijst?
    		if ($extdebug == 1) { watchdog('php', '<pre>VOL VOOR JONGENS & PLEK VOOR MEISJES</pre>', NULL, WATCHDOG_DEBUG); }
    	}
    	// PLEK VOOR JONGENS & VOL VOOR MEISJES
    	if (in_array($event_plekken_jongens, array(":-)"), true) 		AND in_array($event_plekken_meisjes, array(":-("), true)) {
    		$params_event['has_waitlist'] 		= 1;
     		$params_event['max_participants'] 	= 1;
    		$params_event['waitlist_text']		= $event_jong_free_meis_full;
    		//$params_event['custom_516'] 		= 1; // op de lastminutelijst?
    		if ($extdebug == 1) { watchdog('php', '<pre>PLEK VOOR JONGENS & VOL VOOR MEISJES</pre>', NULL, WATCHDOG_DEBUG); }
    	}
    	// VOL VOOR JONGENS & MEISJES
    	if (in_array($event_plekken_jongens, array(":-("), true) 		AND in_array($event_plekken_meisjes, array(":-("), true)) {
    		$params_event['has_waitlist'] 		= 0;
     		$params_event['max_participants'] 	= 1;
     		$params_event['waitlist_text']		= $event_jong_wait_meis_wait;
    		//$params_event['custom_516'] 		= 0; // op de lastminutelijst?
    		if ($extdebug == 1) { watchdog('php', '<pre>VOL VOOR JONGENS & MEISJES</pre>', NULL, WATCHDOG_DEBUG); }
    	}
    	// VOL VOOR JONGENS & WAIT VOOR MEISJES
    	if (in_array($event_plekken_jongens, array(":-("), true) 		AND in_array($event_plekken_meisjes, array(":-|"), true)) {
    		$params_event['has_waitlist'] 		= 1;
     		$params_event['max_participants'] 	= 1;
    		$params_event['waitlist_text']		= $event_jong_full_meis_wait;
    		//$params_event['custom_516'] 		= 1; // op de lastminutelijst?
    		if ($extdebug == 1) { watchdog('php', '<pre>VOL VOOR JONGENS & WACHTLIJST VOOR MEISJES</pre>', NULL, WATCHDOG_DEBUG); }
    	}
    	// VOL VOOR MEISJES & WAIT VOOR JONGENS
    	if (in_array($event_plekken_jongens, array(":-|"), true) 		AND in_array($event_plekken_meisjes, array(":-("), true)) {
    		$params_event['has_waitlist'] 		= 0;
     		$params_event['max_participants'] 	= 1;
     		$params_event['waitlist_text']		= $event_jong_wait_meis_full;
    		//$params_event['custom_516'] 		= 0; // op de lastminutelijst?
    		if ($extdebug == 1) { watchdog('php', '<pre>VOL VOOR MEISJES & WACHTLIJST VOOR MEISJES</pre>', NULL, WATCHDOG_DEBUG); }
    	}

		if ($extdebug == 1) { watchdog('php', '<pre>params_event:' . print_r($params_event, TRUE) . '</pre>', NULL, WATCHDOG_DEBUG); }
		$result = civicrm_api3('Event', 'create', $params_event);
		if ($extdebug == 1) { watchdog('php', '<pre>*** END EXTENSION EVENT KENMERKEN [groupID: '.$groupID.'] [op: '.$op.'] ***</pre>', null, WATCHDOG_DEBUG); }
		return; //   if not, get out of here
	}
}

/**
 * Implementation of hook_civicrm_config
 */
function kampevent_civicrm_config(&$config) {
	_curriculum_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function kampevent_civicrm_xmlMenu(&$files) {
	_curriculum_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function kampevent_civicrm_install() {
	#CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, __DIR__ . '/sql/auto_install.sql');
	return _curriculum_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function kampevent_civicrm_uninstall() {
	#CRM_Utils_File::sourceSQLFile(CIVICRM_DSN, __DIR__ . '/sql/auto_uninstall.sql');
	return _curriculum_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function kampevent_civicrm_enable() {
	return _curriculum_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function kampevent_civicrm_disable() {
	return _curriculum_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function kampevent_civicrm_managed(&$entities) {
	return _curriculum_civix_civicrm_managed($entities);
}

?>
