<?php

#error_reporting(E_ALL);
#ini_set('display_errors', true);
#ini_set('display_startup_errors', true);

require_once 'event.civix.php';

/**
 * Helper: Mapping tussen DB-veld en Interne ID.
 */
function _get_event_role_map() {
    return [
        'Taken_rollen.hoofdleiding_1'   => 'event_hldn1_id',
        'Taken_rollen.hoofdleiding_2'   => 'event_hldn2_id',
        'Taken_rollen.hoofdleiding_3'   => 'event_hldn3_id',
        'Taken_rollen.kernteam_1'       => 'event_kern1_id',
        'Taken_rollen.kernteam_2'       => 'event_kern2_id',
        'Taken_rollen.kernteam_3'       => 'event_kern3_id',
        'Taken_rollen.kernteam_4'       => 'event_kern4_id',
        'Taken_rollen.hoofd_gedrag'     => 'event_gedrag0_id',
        'Taken_rollen.gedrag_team_1'    => 'event_gedrag1_id',
        'Taken_rollen.gedrag_team_2'    => 'event_gedrag2_id',
        'Taken_rollen.hoofd_boekje'     => 'event_boekje0_id',
        'Taken_rollen.boekje_team_1'    => 'event_boekje1_id',
        'Taken_rollen.boekje_team_2'    => 'event_boekje2_id',
        'Taken_rollen.hoofd_keuken'     => 'event_keuken0_id',
        'Taken_rollen.hoofd_keuken_1'   => 'event_keuken1_id',
        'Taken_rollen.hoofd_keuken_2'   => 'event_keuken2_id',
        'Taken_rollen.hoofd_keuken_3'   => 'event_keuken3_id',
        'Taken_rollen.hoofd_ehbo'       => 'event_ehbo0_id',
        'Taken_rollen.ehbo_team_1'      => 'event_ehbo1_id',
        'Taken_rollen.ehbo_team_2'      => 'event_ehbo2_id',
        'Taken_rollen.ehbo_team_3'      => 'event_ehbo3_id',
        'Taken_rollen.hoofd_bhv'        => 'event_bhv_id',
        'Taken_rollen.hoofd_fotos'      => 'event_fotos_id',
        'Taken_rollen.hoofd_blogvlog'   => 'event_blogs_id',
    ];
}

/**
 * Helper: Extract YouTube ID uit een URL string.
 */
function _event_helper_extract_youtube($raw_link) {
    if (empty($raw_link)) return NULL;
    if (substr($raw_link, 0, 4) == 'http') {
        $match = [];
        $youtube_match = preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $raw_link, $match);
        if ($youtube_match && isset($match[1])) return $match[1];
    }
    return NULL;
}

/**
 * Helper: Clean URL (Strip query parameters).
 */
function _event_helper_clean_url($raw_link) {
    if (empty($raw_link)) return NULL;
    if (substr($raw_link, 0, 4) == 'http') {
        $parsed = parse_url($raw_link);
        if (isset($parsed['scheme']) && isset($parsed['host']) && isset($parsed['path'])) {
            return $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
        }
    }
    return NULL;
}

/**
 * HOOK: CUSTOM PRE
 */
function event_civicrm_customPre(string $op, int $groupID, int $entityID, array &$params): void {

    // --- STABILITEIT: ANTI-RECURSIE SLOT ---
    static $is_running = false;
    
    // Als we al bezig zijn in deze functie, stop onmiddellijk.
    if ($is_running) {
        return; 
    }
    
    // Zet het slot erop
    $is_running = true;

    $extdebug = 3;
    $todaydatetime = date('YmdHis');

    // M61: Whitelist check.
    if (!in_array($groupID, [101, 160, 211, 334, 335])) {
        return;
    }

    // =========================================================================
    // FIX 1: DE WASSTRAAT (Speciaal voor Richard)
    // =========================================================================
    // CiviCRM Core crasht als we een lege string ("") sturen naar een Integer 
    // of ContactReference veld. Omdat we de Core niet patchen, wassen we 
    // de data hier schoon. We maken van "" een nette NULL.
    // =========================================================================
    if (is_array($params)) {
        foreach ($params as $k => &$field_def) {
            if (is_array($field_def) && array_key_exists('value', $field_def) && $field_def['value'] === '') {
                $field_def['value'] = NULL;
            }
        }
        // VOEG DIT HIER TOE:
        drupal_timestamp_sweep($params); 
    }
    // =========================================================================

    // 1. Haal oude data op
    static $old_data_cache = [];
    if (!isset($old_data_cache[$entityID])) {
        $old_data_cache[$entityID] = civicrm_api4('Event', 'get', [
            'checkPermissions' => FALSE,
            'where' => [['id', '=', $entityID]],
        ])->first();
    }
    $old_data = $old_data_cache[$entityID];

    if ($groupID == 101) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### EVENT [PRE] 101: AUTO-FILL EVENT KENMERKEN",        "[EID: $entityID]");
        wachthond($extdebug,2, "########################################################################");

        // Haal de basis event info op
        $event_type_id          = $old_data['event_type_id'] ?? NULL;
        $start_date             = $old_data['start_date']    ?? NULL;

        // Alleen uitvoeren voor de specifieke kampen (11 t/m 33) en als de datum bekend is
        if (in_array($event_type_id, [11,12,13,14,21,22,23,24,33]) && !empty($start_date)) {
            
            $kampjaar           = date('Y', strtotime($start_date));

            // -------------------------------------------------------------------------
            // 1. DATA: EIGENSCHAPPEN PER KAMPTYPE
            // -------------------------------------------------------------------------
            $type_map = [
                11 => ['naam' => 'Kinderkamp week 1', 'kort' => 'kk1', 'type' => 'kinderkamp', 'week' => 1,    'cat' => '07_11', 'leid' => 18],
                21 => ['naam' => 'Kinderkamp week 2', 'kort' => 'kk2', 'type' => 'kinderkamp', 'week' => 2,    'cat' => '07_11', 'leid' => 18],
                12 => ['naam' => 'Brugkamp week 1',   'kort' => 'bk1', 'type' => 'brugkamp',   'week' => 1,    'cat' => '12_13', 'leid' => 19],
                22 => ['naam' => 'Brugkamp week 2',   'kort' => 'bk2', 'type' => 'brugkamp',   'week' => 2,    'cat' => '12_13', 'leid' => 19],
                13 => ['naam' => 'Tienerkamp week 1', 'kort' => 'tk1', 'type' => 'tienerkamp', 'week' => 1,    'cat' => '14_15', 'leid' => 20],
                23 => ['naam' => 'Tienerkamp week 2', 'kort' => 'tk2', 'type' => 'tienerkamp', 'week' => 2,    'cat' => '14_15', 'leid' => 20],
                14 => ['naam' => 'Jeugdkamp week 1',  'kort' => 'jk1', 'type' => 'jeugdkamp',  'week' => 1,    'cat' => '16_17', 'leid' => 21],
                24 => ['naam' => 'Jeugdkamp week 2',  'kort' => 'jk2', 'type' => 'jeugdkamp',  'week' => 2,    'cat' => '16_17', 'leid' => 21],
                33 => ['naam' => 'Topkamp',           'kort' => 'top', 'type' => 'topkamp',    'week' => NULL, 'cat' => '18_99', 'leid' => 30],
            ];

            $map                    = $type_map[$event_type_id];

            // -------------------------------------------------------------------------
            // 2. DEFINITIE: MAPPING OP NAAM (Hook column_name -> APIv4 naam)
            // -------------------------------------------------------------------------
            $name_map = [
                'kampnaam_1751'          => 'Event_Kenmerken.kampnaam',
                'kampkort_917'           => 'Event_Kenmerken.kampkort',
                'kamptype_naam_1753'     => 'Event_Kenmerken.kamptype_naam',
                'kamptype_id_1752'       => 'Event_Kenmerken.kamptype_id',
                'kampsoort_1759'         => 'Event_Kenmerken.kampsoort',
                'kampweek_nr_1017'       => 'Event_Kenmerken.kampweek_nr',
                'eventjaar_1755'         => 'Event_Kenmerken.eventjaar',
                'kampjaar_1754'          => 'Event_Kenmerken.kampjaar',
                'leeftijdscategorie_370' => 'Event_Kenmerken.leeftijdscategorie',
                'leeftijd_leiding_915'   => 'Event_Kenmerken.leeftijd_leiding',
            ];

            // -------------------------------------------------------------------------
            // 3. DOELWAARDEN BEPALEN
            // -------------------------------------------------------------------------
            $update_kenmerken = [
                'Event_Kenmerken.kampnaam'           => $map['naam'],
                'Event_Kenmerken.kampkort'           => $map['kort'],
                'Event_Kenmerken.kamptype_naam'      => $map['type'], 
                'Event_Kenmerken.kamptype_id'        => $event_type_id,
                'Event_Kenmerken.kampsoort'          => $map['type'], 
                'Event_Kenmerken.kampweek_nr'        => $map['week'], 
                'Event_Kenmerken.eventjaar'          => date('YmdHis', strtotime($start_date)), // <-- HIER OPMAAK TOEGEPAST
                'Event_Kenmerken.kampjaar'           => $kampjaar,    
                'Event_Kenmerken.leeftijdscategorie' => $map['cat'],  
                'Event_Kenmerken.leeftijd_leiding'   => (string)$map['leid'], 
            ];

            // Filter NULL values eruit (bijv. kampweek bij Topkamp)
            $update_kenmerken       = array_filter($update_kenmerken, function($val) {
                return $val !== NULL;
            });

            wachthond($extdebug, 2, "Doelwaarden voor Kenmerken 101", $update_kenmerken);

            // =========================================================================
            // 4. INTERCEPTOR: PAS DE DATA 'IN-FLIGHT' AAN VIA DE MAPPING
            // =========================================================================
            foreach ($params as &$p) {
                $col_name = $p['column_name'] ?? '';
                
                if (array_key_exists($col_name, $name_map)) {
                    $api_key = $name_map[$col_name];
                    
                    if (array_key_exists($api_key, $update_kenmerken)) {
                        // 1. Overschrijf formulier (Exact zoals intake_inject_params doet)
                        $p['value'] = $update_kenmerken[$api_key];
                        wachthond($extdebug, 3, "Interceptor overschrijft", "$col_name -> {$p['value']}");
                        
                        // 2. Haal uit de lijst
                        unset($update_kenmerken[$api_key]); 
                    }
                }
            }
            // =========================================================================

            // 5. VEILIGE API UPDATE VOOR RESTERENDE VELDEN (Gebaseerd op jouw intake wrapper)
            if (!empty($update_kenmerken)) {
                wachthond($extdebug, 2, "Overgebleven API Updates", $update_kenmerken);
                
                try {
                    civicrm_api4('Event', 'update', [
                        'checkPermissions'  => FALSE,
                        'values'            => array_merge(['id' => $entityID], $update_kenmerken)
                    ]);
                    wachthond($extdebug, 2, "API UPDATE SUCCESS", "Event ID $entityID kenmerken succesvol bijgewerkt.");
                } catch (\Exception $e) {
                    // Foutafhandeling: Log de error, maar stop het script niet!
                    wachthond($extdebug, 1, "API UPDATE ERROR", "Fout bij updaten Event ID $entityID: " . $e->getMessage());
                }
            }
        }
    }

    if ($groupID == 160) {

        wachthond($extdebug,1, "#########################################################################");
        wachthond($extdebug,1, "### EVENT [PRE] 160: TAKEN, ROLLEN & VACATURES",        "[EID: $entityID]");
        wachthond($extdebug,1, "#########################################################################");

        wachthond($extdebug,1, "#########################################################################");
        wachthond($extdebug,1, "### EVENT [PRE] 160: STAP A: ACL WIJZIGINGEN BIJ WIJZIGING IN ROLLEN");
        wachthond($extdebug,1, "#########################################################################");

        $role_map = _get_event_role_map();
        // Let op: $params is hier een array van arrays (metadata), we moeten even slim mappen
        // We maken een platte lijst van wat er binnenkomt om makkelijk te checken
        $incoming_values = [];
        foreach($params as $p) {
            if(isset($p['column_name'])) $incoming_values['Taken_rollen.'.$p['column_name']] = $p['value'];
        }

        foreach ($role_map as $db_field_key => $internal_key) {
            // Kijk of dit veld in de inkomende parameters zit. 
            // We strippen 'Taken_rollen.' even voor de match met column_name als dat nodig is, 
            // maar hierboven hebben we $incoming_values al met prefix opgebouwd.
            
            if (array_key_exists($db_field_key, $incoming_values)) {
                $old_contact_id = $old_data[$db_field_key] ?? NULL;
                $new_contact_id = $incoming_values[$db_field_key] ?? NULL;

                if ($old_contact_id && ($old_contact_id != $new_contact_id)) {
                    if (function_exists('core_civicrm_trigger_acl')) {
                        core_civicrm_trigger_acl($old_contact_id);
                    }
                }
            }
        }

        wachthond($extdebug,1, "#########################################################################");
        wachthond($extdebug,1, "### EVENT [PRE] 160: STAP B: HOOFDLEIDING INFO SYNC (NAAR LINKJES GROEP)");
        wachthond($extdebug,1, "#########################################################################");

        $hl_update_params = [];
        
        $hl_map = [
            'Taken_rollen.hoofdleiding_1' => ['DN' => 'HL1_DN', 'FN' => 'HL1_FN', 'PH' => 'HL1_PH', 'IMG' => 'HL1_IMG'],
            'Taken_rollen.hoofdleiding_2' => ['DN' => 'HL2_DN', 'FN' => 'HL2_FN', 'PH' => 'HL2_PH', 'IMG' => 'HL2_IMG'],
            'Taken_rollen.hoofdleiding_3' => ['DN' => 'HL3_DN', 'FN' => 'HL3_FN', 'PH' => 'HL3_PH', 'IMG' => 'HL3_IMG'],
        ];

        foreach ($hl_map as $db_field => $target_fields) {
            if (array_key_exists($db_field, $incoming_values)) {
                $cid = $incoming_values[$db_field];
                
                if ($cid) {
                    $contact = civicrm_api4('Contact', 'get', [
                        'checkPermissions' => FALSE,
                        'select' => ['display_name', 'first_name', 'image_URL'],
                        'where' => [['id', '=', $cid]]
                    ])->first();
                    
                    $phone = civicrm_api4('Phone', 'get', [
                        'checkPermissions' => FALSE,
                        'select' => ['phone'],
                        'where' => [['contact_id', '=', $cid], ['location_type_id', '=', 1]]
                    ])->first();

                    $image_bn = $contact['image_URL'] ? basename($contact['image_URL']) : '';
                    if ($image_bn) $image_bn = explode('?', $image_bn)[0];

                    $hl_update_params['Event_Kenmerken_Linkjes.' . $target_fields['DN']]    = $contact['display_name'];
                    $hl_update_params['Event_Kenmerken_Linkjes.' . $target_fields['FN']]    = $contact['first_name'];
                    $hl_update_params['Event_Kenmerken_Linkjes.' . $target_fields['IMG']]   = $image_bn;
                    $hl_update_params['Event_Kenmerken_Linkjes.' . $target_fields['PH']]    = $phone['phone'] ?? '';
                } else {
                    foreach ($target_fields as $tf) $hl_update_params['Event_Kenmerken_Linkjes.' . $tf] = '';
                }
            }
        }

        if (!empty($hl_update_params)) {
            wachthond($extdebug, 2, "HL Sync Triggered", count($hl_update_params) . " fields");
            civicrm_api4('Event', 'update', [
                'checkPermissions' => FALSE,
                'values' => array_merge(['id' => $entityID], $hl_update_params)
            ]);
        }

        wachthond($extdebug,1, "#########################################################################");
        wachthond($extdebug,1, "### EVENT [PRE] 160: STAP C: VACATURES BEREKENEN");
        wachthond($extdebug,1, "#########################################################################");

        // Helper om waarde te pakken uit inkomende data OF oude data
        $val = function($field) use ($incoming_values, $old_data) {
            $v = isset($incoming_values[$field]) ? $incoming_values[$field] : ($old_data[$field] ?? NULL);
            return ($v > 0) ? $v : FALSE;
        };

        // Bereken nieuwe statussen (0=Vol, 1=Open)
        $new_hl     = ($val('Taken_rollen.hoofdleiding_1')  && $val('Taken_rollen.hoofdleiding_2')  && $val('Taken_rollen.hoofdleiding_3')) ? 0 : 1;
        $new_keuken = ($val('Taken_rollen.hoofd_keuken')    && $val('Taken_rollen.hoofd_keuken_1')  && $val('Taken_rollen.hoofd_keuken_2')  && $val('Taken_rollen.hoofd_keuken_3')) ? 0 : 1;
        $new_ehbo   = ($val('Taken_rollen.hoofd_ehbo')      && $val('Taken_rollen.ehbo_team_1')     && $val('Taken_rollen.ehbo_team_2')     && $val('Taken_rollen.ehbo_team_3')) ? 0 : 1;
        $new_kern   = ($val('Taken_rollen.kernteam_1')      && $val('Taken_rollen.kernteam_2')      && $val('Taken_rollen.kernteam_3')      && $val('Taken_rollen.kernteam_4')) ? 0 : 1;

        // =========================================================================
        // FIX 2: SPOOKVELD PREVENTIE
        // =========================================================================
        // Oorspronkelijk werden deze waarden direct in $params gezet:
        // $params['Event_Vacatures.vacature_hoofdleiding'] = ...
        // Dit is FOUT in een PRE hook van Groep 160. CiviCRM snapt die sleutels niet
        // omdat ze bij een andere groep horen, en maakt er "Spookvelden" van (`` = NULL).
        // OPLOSSING: We verzamelen ze en updaten ze netjes via de API.
        // =========================================================================
        
        $vacature_update = [];
        $vacature_update['Event_Vacatures.vacature_hoofdleiding'] = $new_hl;
        $vacature_update['Event_Vacatures.vacature_keuken']       = $new_keuken;
        $vacature_update['Event_Vacatures.vacature_ehbo']         = $new_ehbo;
        $vacature_update['Event_Vacatures.vacature_kernteam']     = $new_kern;

        wachthond($extdebug,1, "#########################################################################");
        wachthond($extdebug,1, "### EVENT [PRE] 160: STAP D: DATUM WIJZIGING EN VACATURES OPSLAAN");
        wachthond($extdebug,1, "#########################################################################");

        // Check of er iets gewijzigd is in de vacatures t.o.v. oud
        $changed = false;
        if (($new_hl != ($old_data['Event_Vacatures.vacature_hoofdleiding'] ?? -1)) || 
            ($new_keuken != ($old_data['Event_Vacatures.vacature_keuken'] ?? -1))) {
            $changed = true;
        }

        // Als er iets gewijzigd is, of we hebben vacature updates, voer API call uit
        if (!empty($vacature_update)) {
            $api_values = array_merge(['id' => $entityID], $vacature_update);
            
            if ($changed) {
                $api_values['Event_Kenmerken_Status.Datum_Update_Werving'] = $todaydatetime;
            }

            civicrm_api4('Event', 'update', [
                'checkPermissions' => FALSE,
                'values' => $api_values
            ]);
        }
    }

    if ($groupID == 211) {
        // ... (De rest van de functie blijft identiek, hieronder plakken wat er stond voor 211 en 335)
        
        wachthond($extdebug,1, "#########################################################################");
        wachthond($extdebug,1, "### EVENT [PRE] 211: WERVING TEKSTEN / WACHTLIJST / SMILEYS");
        wachthond($extdebug,1, "#########################################################################");
        
        // Let op: $params is hier complex, we gebruiken de helper van eerder of directe access als het simpele keys zijn?
        // In Pre hooks van custom groups zijn params vaak arrays.
        // Voor veiligheid gebruiken we hier ook een helper om de values te vinden
        
        $find_val = function($key) use ($params, $old_data) {
            foreach($params as $p) {
                if (isset($p['column_name']) && ('Event_Kenmerken_Werving.'.$p['column_name'] == $key)) return $p['value'];
                // Soms heten de keys anders in $params, even checken. 
                // Maar voor nu gaan we er vanuit dat de API4 get hierboven $old_data goed vult.
            }
            return $old_data[$key] ?? '';
        };

        // Omdat de structuur van $params in Pre hooks lastig is (array van arrays),
        // en we hierboven al $incoming_values hebben gebruikt voor 160, is het voor 211 veiliger
        // om gewoon te vertrouwen op wat we via API kunnen doen of de logica simpel te houden.
        // Echter, de originele code deed: $params['Event_Kenmerken_Werving.Plekken_jongens'].
        // Dat suggereert dat $params soms WEL platte keys heeft? 
        // Nee, de logica hierboven bewees van niet ([0]=>Array...).
        
        // OPLOSSING VOOR 211: We moeten de waarden uit de array vissen.
        $plek_j = ''; 
        $plek_m = '';
        
        // Loop door params om de waarden te vinden
        foreach($params as $p) {
            if(isset($p['column_name'])) {
                if($p['column_name'] == 'Plekken_jongens_2285') $plek_j = $p['value'];
                if($p['column_name'] == 'Plekken_meisjes_2286') $plek_m = $p['value'];
            }
        }
        // Fallback naar old data
        if ($plek_j === '') $plek_j = $old_data['Event_Kenmerken_Werving.Plekken_jongens'] ?? '';
        if ($plek_m === '') $plek_m = $old_data['Event_Kenmerken_Werving.Plekken_meisjes'] ?? '';

        // Definitie van de teksten
        $txt_j_free_m_free = "Er is op dit moment nog plek voor zowel jongens als meisjes";
        $txt_j_wait_m_wait = "Dit kamp is op dit moment zo goed als vol..."; 
        $txt_j_wait_m_free = "LET OP: Voor dit kamp is nog voldoende plek voor meisjes. Voor jongens...";
        $txt_j_free_m_wait = "LET OP: Voor dit kamp is nog voldoende plek voor jongens. Voor meisjes...";
        $txt_j_full_m_free = "LET OP: Voor dit kamp is op dit moment geen plek meer voor jongens...";
        $txt_j_free_m_full = "LET OP: Voor dit kamp is op dit moment geen plek meer voor meisjes...";
        $txt_j_full_m_wait = "LET OP: Voor dit kamp is op dit moment geen plek meer voor jongens. Meisjes wachtlijst...";
        $txt_j_wait_m_full = "LET OP: Voor dit kamp is op dit moment geen plek meer voor meisjes. Jongens wachtlijst...";
        $txt_full          = "Helaas zijn er voor dit kamp geen plekken meer beschikbaar.";
        $txt_not_open      = "De aanmeldingen voor dit kamp zijn nog niet geopend.";
        $txt_cancelled     = "Dit kamp is geannuleerd.";

        $update_info = []; 

        // 1. Geannuleerd
        if ($plek_j == ":`-(" && $plek_m == ":`-(") {
            $update_info = ['has_waitlist' => 0, 'max_participants' => 1, 'waitlist_text' => $txt_cancelled, 'Event_Status.status_label' => 'Geannuleerd'];
        }
        // 2. Nog niet open
        elseif ($plek_j == ":-D" && $plek_m == ":-D") {
             $update_info = ['has_waitlist' => 0, 'max_participants' => 1, 'waitlist_text' => $txt_not_open, 'Event_Status.status_label' => 'Nog niet open'];
        }
        // 3. Volledig Open
        elseif (in_array($plek_j, [";-)",":-)"]) && in_array($plek_m, [";-)",":-)"])) {
             $update_info = ['has_waitlist' => 0, 'max_participants' => 200, 'waitlist_text' => $txt_j_free_m_free, 'Event_Status.status_label' => 'Open'];
        }
        // 4. Deels Wachtlijst / Vol scenarios
        elseif ($plek_j == ":-|" && in_array($plek_m, [";-)",":-)"])) { 
             $update_info = ['has_waitlist' => 1, 'max_participants' => 1, 'waitlist_text' => $txt_j_wait_m_free, 'Event_Status.status_label' => 'Wachtlijst'];
        }
        elseif (in_array($plek_j, [";-)",":-)"]) && $plek_m == ":-|") { 
             $update_info = ['has_waitlist' => 1, 'max_participants' => 1, 'waitlist_text' => $txt_j_free_m_wait, 'Event_Status.status_label' => 'Wachtlijst'];
        }
        elseif ($plek_j == ":-|" && $plek_m == ":-|") { 
             $update_info = ['has_waitlist' => 1, 'max_participants' => 1, 'waitlist_text' => $txt_j_wait_m_wait, 'Event_Status.status_label' => 'Wachtlijst'];
        }
        elseif ($plek_j == ":-(" && in_array($plek_m, [";-)",":-)"])) { 
             $update_info = ['has_waitlist' => 1, 'max_participants' => 1, 'waitlist_text' => $txt_j_full_m_free, 'Event_Status.status_label' => 'Wachtlijst'];
        }
        elseif (in_array($plek_j, [";-)",":-)"]) && $plek_m == ":-(") { 
             $update_info = ['has_waitlist' => 1, 'max_participants' => 1, 'waitlist_text' => $txt_j_free_m_full, 'Event_Status.status_label' => 'Wachtlijst'];
        }
        elseif ($plek_j == ":-(" && $plek_m == ":-(") { 
             $update_info = ['has_waitlist' => 0, 'max_participants' => 1, 'waitlist_text' => $txt_full, 'Event_Status.status_label' => 'Vol'];
        }

        wachthond($extdebug,1, "#########################################################################");
        wachthond($extdebug,1, "### EVENT [PRE] 211: UPDATE DATUM WIJZIGING PLEKKEN BESCHIKBAAR");
        wachthond($extdebug,1, "#########################################################################");

        if (!empty($update_info)) {
            wachthond($extdebug, 2, "Status Logic Triggered", $update_info['Event_Status.status_label'] ?? 'Unknown');
            $update_info['Event_Kenmerken_Status.Datum_Update_Plekken'] = $todaydatetime;
            $update_info['id'] = $entityID;
            civicrm_api4('Event', 'update', ['checkPermissions' => FALSE, 'values' => $update_info]);
        }
    }

    if ($groupID == 335) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### EVENT [PRE] 335: LINKJES, THEMA & CLEANUP",         "[EID: $entityID]");
        wachthond($extdebug,2, "########################################################################");

        // -------------------------------------------------------------------------
        // 1. DEFINITIE: MAPPING OP NAAM (Uit de logfile gehaald)
        // -------------------------------------------------------------------------
        $name_map_335 = [
            'thema_naam_2089'    => 'Event_Kenmerken_Linkjes.thema_naam',
            'thema_info_2090'    => 'Event_Kenmerken_Linkjes.thema_info',
            'goeddoel_naam_2086' => 'Event_Kenmerken_Linkjes.goeddoel_naam',
            'goeddoel_link_2088' => 'Event_Kenmerken_Linkjes.goeddoel_link',
            'welkomvideo_1511'   => 'Event_Kenmerken_Linkjes.welkomvideo',
            'slotvideo_2001'     => 'Event_Kenmerken_Linkjes.slotvideo',
            'playlist_1858'      => 'Event_Kenmerken_Linkjes.playlist',
            'doc_link_2064'      => 'Event_Kenmerken_Linkjes.doc_link',
            'foto_album_2119'    => 'Event_Kenmerken_Linkjes.foto_album',
        ];

        // -------------------------------------------------------------------------
        // 2. WAARDEN OPHALEN (Kijk eerst in Formulier, anders in Database)
        // -------------------------------------------------------------------------
        $current_vals = [];
        foreach ($params as $p) {
            if (isset($p['column_name']) && isset($name_map_335[$p['column_name']])) {
                $current_vals[$name_map_335[$p['column_name']]] = $p['value'];
            }
        }
        
        $get_val = function($key) use ($current_vals, $old_data) {
            return $current_vals[$key] ?? ($old_data[$key] ?? '');
        };

        // -------------------------------------------------------------------------
        // 3. DOELWAARDEN BEPALEN (Wat moet er opgeschoond/gevuld worden?)
        // -------------------------------------------------------------------------
        $update_linkjes = [];

        // YouTube opschonen
        $welkom = $get_val('Event_Kenmerken_Linkjes.welkomvideo');
        if (!empty($welkom)) {
            $extracted = _event_helper_extract_youtube($welkom);
            if ($extracted && $extracted != $welkom) $update_linkjes['Event_Kenmerken_Linkjes.welkomvideo'] = $extracted;
        }
        
        $slot   = $get_val('Event_Kenmerken_Linkjes.slotvideo');
        if (!empty($slot)) {
            $extracted = _event_helper_extract_youtube($slot);
            if ($extracted && $extracted != $slot) $update_linkjes['Event_Kenmerken_Linkjes.slotvideo'] = $extracted;
        }

        // URL's opschonen (Zonder tracking parameters)
        $pl     = $get_val('Event_Kenmerken_Linkjes.playlist');
        if (!empty($pl)) {
            $cleaned = _event_helper_clean_url($pl);
            if ($cleaned && $cleaned != $pl) $update_linkjes['Event_Kenmerken_Linkjes.playlist'] = $cleaned;
        }

        $doc    = $get_val('Event_Kenmerken_Linkjes.doc_link');
        if (!empty($doc)) {
            $cleaned = _event_helper_clean_url($doc);
            if ($cleaned && $cleaned != $doc) $update_linkjes['Event_Kenmerken_Linkjes.doc_link'] = $cleaned;
        }
        
        // Simpele trim voor foto album
        $foto   = $get_val('Event_Kenmerken_Linkjes.foto_album');
        if (!empty($foto) && trim($foto) != $foto) {
            $update_linkjes['Event_Kenmerken_Linkjes.foto_album'] = trim($foto);
        }

        // Thema Defaults (Als we voor 20 juni zitten en de velden zijn leeg)
        if ($todaydatetime < date("Y") . "-06-20 00:00:00") {
            $defaults = [
                'Event_Kenmerken_Linkjes.thema_naam'    => "Circus",
                'Event_Kenmerken_Linkjes.thema_info'    => "Het thema is circus...",
                'Event_Kenmerken_Linkjes.goeddoel_naam' => "Stichting Knert",
                'Event_Kenmerken_Linkjes.goeddoel_link' => "https://www.knertje.nl/",
                'Event_Kenmerken_Linkjes.welkomvideo'   => "xAzBzA3IrgA", 
                'Event_Kenmerken_Linkjes.slotvideo'     => "qBtAvuHS3ck",   
                'Event_Kenmerken_Linkjes.playlist'      => "https://open.spotify.com/playlist/2fqudOwP42zGKPvuXNJTsq"
            ];
            
            foreach ($defaults as $key => $default_val) {
                if (empty($get_val($key))) {
                    $update_linkjes[$key] = $default_val;
                }
            }
        }

        // =========================================================================
        // 4. INTERCEPTOR: PAS DE DATA 'IN-FLIGHT' AAN VIA DE MAPPING
        // =========================================================================
        foreach ($params as &$p) {
            $col_name = $p['column_name'] ?? '';
            
            if (array_key_exists($col_name, $name_map_335)) {
                $api_key = $name_map_335[$col_name];
                
                if (array_key_exists($api_key, $update_linkjes)) {
                    $p['value'] = $update_linkjes[$api_key];
                    wachthond($extdebug, 3, "Interceptor 335 overschrijft", "$col_name -> {$p['value']}");
                    unset($update_linkjes[$api_key]); 
                }
            }
        }

        // =========================================================================
        // 5. VEILIGE API UPDATE VOOR RESTERENDE VELDEN (Hidden fields)
        // =========================================================================
        if (!empty($update_linkjes)) {
            wachthond($extdebug, 2, "Overgebleven API Updates", $update_linkjes);
            try {
                civicrm_api4('Event', 'update', [
                    'checkPermissions'  => FALSE,
                    'values'            => array_merge(['id' => $entityID], $update_linkjes)
                ]);
            } catch (\Exception $e) {
                wachthond($extdebug, 1, "API UPDATE ERROR", "Fout bij updaten Event ID $entityID: " . $e->getMessage());
            }
        }
        
        wachthond($extdebug, 2, "Linkjes gecontroleerd en opgeschoond", "OK");
    }

    drupal_timestamp_sweep($params);

    wachthond($extdebug, 3, "FINAL params voor groep $groupID", $params);

    // Haal het slot eraf voor de volgende echte aanroep
    $is_running = false;
}

/**
 * HOOK: BUILD FORM
 */
function event_civicrm_buildForm($formName, &$form) {
    
    if ($formName == 'CRM_Event_Form_ManageEvent_Registration') {
        $defaults = [
            'maxParticipants' => 6161,
            'is_monetary' => 1,
            'is_map' => 0,
            'is_online_registration' => 1,
            'allow_same_participant_emails' => 1,
            'is_notify' => 0
        ];
        $form->setDefaults($defaults);
        
        // Forceer ook via setVar voor de zekerheid
        foreach($defaults as $k => $v) $form->setVar($k, $v);
    }
    
    if ($formName == 'CRM_Event_Form_Participant') {
        $form->setDefaults(['is_notify' => 0]);
    }
}

/*
    #######################################################################################
    // BEPAAL WELKKAMPLANG EN WEEKNUMMER VOOR KKBKTKJK & TOP
    #######################################################################################

    // M61: Hier valt te kiezen waar de info vandaan komt: vanuit (verplicht veld) $eventkamp_event_type_id_label en daarvan het label: 
    //      bv. Kinderkamp week 1 of vanuit Event Kenmerken? (eerste is safer?)
    //      $event_kampnaam = $eventkamp_kamptype_label;// vanuit Event Kenmerken

    if ($ditevent_event_type_id == 1) {             // ONLY FOR LEIDING
        $kampkort   = $ditevent_leid_welkkamp;
        $weeknr     = substr($ditevent_leid_welkkamp, -1);
    } else {
        $kampkort   = $eventkamp_kampkort;
        $weeknr     = $eventkamp_event_weeknr;
    }

    // only letters & numbers and dashes

    $eventkamp_kampnaam         = $eventkamp_event_type_id_label;

    $eventkamp_kampkort         = preg_replace('/[^ \w-]/','',strtolower(trim($eventkamp_kampkort)));
    $eventkamp_kampkort_low     = preg_replace('/[^ \w-]/','',strtolower(trim($eventkamp_kampkort)));
    $eventkamp_kampkort_cap     = preg_replace('/[^ \w-]/','',strtoupper(trim($eventkamp_kampkort)));

    $kampkort                   = preg_replace('/[^ \w-]/','',strtolower(trim($kampkort)));
    $kampkort_low               = preg_replace('/[^ \w-]/','',strtolower(trim($kampkort)));
    $kampkort_cap               = preg_replace('/[^ \w-]/','',strtoupper(trim($kampkort)));
*/

/**
 * ============================================================================
 * HOOK 2: CUSTOM (POST) - Triggered NA opslaan
 * Hier doen we de zware TEST registraties (Dummy & Test Leiding)
 * ============================================================================
 */

function event_civicrm_custom($op, $groupID, $entityID, &$params) {

    // --- STOP ONEINDIGE LOOPS ---
    static $is_event_bezig = false;

    if ($is_event_bezig) {
        return; 
    }
    $is_event_bezig = true;
    
    $extdebug       = 3;
    $exttestreg     = 0; // Zet op 1 om test-leiding registraties aan te zetten
    $extdummyreg    = 0; // Zet op 1 om dummy-deelnemer registraties aan te zetten
    $extdummyupd    = 0; // Zet op 1 om bestaande dummy's te updaten
    $extwrite       = 1; // Master switch voor schrijven naar DB

    // Alleen uitvoeren als we de Event Info (Groep 101) opslaan
    if ($groupID != 101) {
        return;
    }

    // Start Timer
    if (function_exists('core_microtimer')) {
        wachthond($extdebug, 1, "civicrm_timing", core_microtimer("START event_civicrm_custom [GID:$groupID]"));
    }

    // 1. Haal Event Info op (nodig voor datums en type)
    $event_info = civicrm_api4('Event', 'get', [
        'checkPermissions' => FALSE,
        'select' => ['start_date', 'title', 'event_type_id'],
        'where' => [['id', '=', $entityID]]
    ])->first();

    if (function_exists('core_microtimer')) {
        wachthond($extdebug, 1, "civicrm_timing", core_microtimer("Event Info Opgehaald"));
    }

    if (!$event_info) return;

    $event_startdate = $event_info['start_date'];
    $event_title     = $event_info['title'];
    $event_type_id   = $event_info['event_type_id'];
    $event_id        = $entityID;
    
    $eventjaar       = date('Y', strtotime($event_startdate));
    $regdate         = ($eventjaar.'-01-01');

    if ($extdummyreg == 1) {
        
        wachthond($extdebug,1, "#########################################################################");
        wachthond($extdebug,1, "### EVENT [POST] 3.1 REGISTER TESTDEEL DUMMY",               "[$event_id]");
        wachthond($extdebug,1, "#########################################################################");

        $dummydeel_eid  = 295; // Hardcoded ID
        $leeftijd_kk    = 8; 
        $birthjaar_kk   = date('Y', strtotime('-'. $leeftijd_kk .'year',  strtotime($event_startdate)));
        $birthdate_kk   = ($birthjaar_kk.'-01-01');

        $dummy_contact = civicrm_api4('Contact','get', [
            'checkPermissions' => FALSE,
            'select' => ['id'],
            'where' => [['first_name', '=', 'Testdeel'], ['last_name', '=', 'Dummy']]
        ])->first();

        if ($dummy_contact) {
            $cid = $dummy_contact['id'];
            $target_eid = $dummydeel_eid; 

            $existing = civicrm_api4('Participant', 'get', [
                'checkPermissions' => FALSE,
                'select' => ['id'],
                'where' => [['event_id', '=', $target_eid], ['contact_id', '=', $cid], ['is_test', 'IN', [TRUE, FALSE]]]
            ])->first();
            $pid = $existing['id'] ?? null;

            // CREATE
            if (!$pid && $extwrite) {
                civicrm_api4('Participant', 'create', [
                    'checkPermissions' => FALSE,
                    'values' => [
                        'contact_id'                => $cid,
                        'event_id'                  => $target_eid,
                        'status_id'                 => 1,
                        'register_date'             => $regdate,
                        'role_id'                   => [7],
                        'PART_DEEL.Groep_klas'      => 'groep_5',
                        'PART_INTERN.groep_letter'  => 'Q',
                        'PART_INTERN.groep_kleur'   => 'rood',
                        'PART_INTERN.groep_naam'    => 'Team Oempaloempa',
                    ]
                ]);
                wachthond($extdebug, 1, "Dummy created", "$cid -> Event $target_eid");
            } 
            // UPDATE
            elseif ($pid && $extdummyupd && $extwrite) {
                // Update de geboortedatum van het contact
                civicrm_api4('Contact', 'update', [
                    'checkPermissions' => FALSE,
                    'values' => [
                        'id'         => $cid,
                        'birth_date' => $birthdate_kk,
                    ],
                ]);

                // Update de deelnemer gegevens (registratiedatum en groep)
                civicrm_api4('Participant', 'update', [
                    'checkPermissions' => FALSE,
                    'values' => [
                        'id'                       => $pid,
                        'register_date'            => $regdate,
                        'PART_INTERN.groep_letter' => 'Q',
                    ],
                ]);

                wachthond($extdebug, 1, "Dummy updated", $cid);
            }
        }
    }

    if (function_exists('core_microtimer')) {
        wachthond($extdebug, 1, "civicrm_timing", core_microtimer("Einde Dummy Logic"));
    }

    if (function_exists('core_microtimer')) wachthond($extdebug, 1, "civicrm_timing", core_microtimer("Einde 3.1 Dummy"));

    if ($exttestreg == 1) {
        
        wachthond($extdebug,1, "#########################################################################");
        wachthond($extdebug,1, "### EVENT [POST] 3.2 REGISTER TESTDEEL (Team Oempaloempa)", "[$event_id]");
        wachthond($extdebug,1, "#########################################################################");

        // Alleen doen als het event nog niet gestart is
        if (strtotime($todaydatetime) < strtotime($event_startdate)) {
            
            $conf = null;
            // Mapping: TypeID => [ContactID, Leeftijd, Klas/Groep, Letter, Kleur]
            switch ($event_type_id) {
                case 11: $conf = ['cid'=>14336, 'age'=>8,  'grp'=>'groep_5', 'let'=>'A', 'col'=>'rood'];   break; // KK1
                case 21: $conf = ['cid'=>14337, 'age'=>8,  'grp'=>'groep_5', 'let'=>'A', 'col'=>'rood'];   break; // KK2
                case 12: $conf = ['cid'=>14338, 'age'=>12, 'grp'=>'klas_1',  'let'=>'B', 'col'=>'oranje']; break; // BK1
                case 22: $conf = ['cid'=>14339, 'age'=>12, 'grp'=>'klas_1',  'let'=>'B', 'col'=>'oranje']; break; // BK2
                case 13: $conf = ['cid'=>14340, 'age'=>14, 'grp'=>'klas_2',  'let'=>'C', 'col'=>'groen'];  break; // TK1
                case 23: $conf = ['cid'=>14341, 'age'=>14, 'grp'=>'klas_2',  'let'=>'C', 'col'=>'groen'];  break; // TK2
                case 14: $conf = ['cid'=>14342, 'age'=>16, 'grp'=>'klas_4',  'let'=>'D', 'col'=>'blauw'];  break; // JK1
                case 24: $conf = ['cid'=>14343, 'age'=>16, 'grp'=>'klas_4',  'let'=>'D', 'col'=>'blauw'];  break; // JK2
                case 33: $conf = ['cid'=>13876, 'age'=>20, 'grp'=>'vervolg', 'let'=>'A', 'col'=>'paars'];  break; // TOP
            }

            if ($conf) {
                $cid = $conf['cid'];
                $birthyear_conf = date('Y', strtotime('-'.$conf['age'].'year', strtotime($event_startdate)));
                
                $month_offset = '01'; 
                if ($conf['age'] == 12) $month_offset = '02';
                if ($conf['age'] == 14) $month_offset = '03';
                if ($conf['age'] == 16) $month_offset = '04';
                if ($conf['age'] == 20) $month_offset = '05';
                
                $birthdate_conf = "$birthyear_conf-$month_offset-01";

                $existing = civicrm_api4('Participant', 'get', [
                    'checkPermissions' => FALSE,
                    'select' => ['id'],
                    'where' => [['event_id', '=', $entityID], ['contact_id', '=', $cid], ['is_test', 'IN', [TRUE, FALSE]]]
                ])->first();
                $pid = $existing['id'] ?? null;

                // CREATE
                if (!$pid && $extwrite) {
                    civicrm_api4('Participant', 'create', [
                        'checkPermissions' => FALSE,
                        'values' => [
                            'contact_id'                => $cid,
                            'event_id'                  => $entityID,
                            'status_id'                 => 1,
                            'register_date'             => $regdate,
                            'role_id'                   => [7],
                            'PART_DEEL.Groep_klas'      => $conf['grp'],
                            'PART_INTERN.groep_letter'  => $conf['let'],
                            'PART_INTERN.groep_kleur'   => $conf['col'],
                            'PART_INTERN.groep_naam'    => "Team Oempaloempa",
                        ]
                    ]);
                    wachthond($extdebug, 1, "TestDeel created", "$cid -> Event $entityID");
                }
                // UPDATE
                elseif ($pid && $extwrite) {
                    civicrm_api4('Contact', 'update', [
                        'checkPermissions' => FALSE,
                        'values' => ['id' => $cid, 'birth_date' => $birthdate_conf, 'gender_id' => 2]
                    ]);
                    civicrm_api4('Participant', 'update', [
                        'checkPermissions' => FALSE,
                        'values' => ['id' => $pid, 'register_date' => $regdate, 'PART_DEEL.Groep_klas' => $conf['grp']]
                    ]);
                    wachthond($extdebug, 1, "TestDeel updated", $cid);
                }
            }
        }
    }

    if (function_exists('core_microtimer')) wachthond($extdebug, 1, "civicrm_timing", core_microtimer("Einde 2.1 TestDeel"));

    if ($exttestreg == 1) {
        
        wachthond($extdebug,1, "#########################################################################");
        wachthond($extdebug,1, "### EVENT [POST] 4.X REGISTER TESTLEID", "[$event_id]");
        wachthond($extdebug,1, "#########################################################################");

        // Alleen als event nog niet gestart is
        if (strtotime($todaydatetime) < strtotime($event_startdate)) {

            $conf = null;
            // Mapping voor Leiding (IDs: 14432 e.v.)
            switch ($event_type_id) {
                case 11: $conf = ['cid'=>14432, 'kamp'=>'KK1', 'age'=>20, 'let'=>'A', 'col'=>'rood'];   break;
                case 21: $conf = ['cid'=>14433, 'kamp'=>'KK2', 'age'=>20, 'let'=>'A', 'col'=>'rood'];   break;
                case 12: $conf = ['cid'=>14434, 'kamp'=>'BK1', 'age'=>22, 'let'=>'B', 'col'=>'oranje']; break;
                case 22: $conf = ['cid'=>14435, 'kamp'=>'BK2', 'age'=>22, 'let'=>'B', 'col'=>'oranje']; break;
                case 13: $conf = ['cid'=>14436, 'kamp'=>'TK1', 'age'=>24, 'let'=>'C', 'col'=>'groen'];  break;
                case 23: $conf = ['cid'=>14437, 'kamp'=>'TK2', 'age'=>24, 'let'=>'C', 'col'=>'groen'];  break;
                case 14: $conf = ['cid'=>14438, 'kamp'=>'JK1', 'age'=>26, 'let'=>'D', 'col'=>'blauw'];  break;
                case 24: $conf = ['cid'=>14439, 'kamp'=>'JK2', 'age'=>26, 'let'=>'D', 'col'=>'blauw'];  break;
                case 33: $conf = ['cid'=>14440, 'kamp'=>'TOP', 'age'=>30, 'let'=>'A', 'col'=>'paars'];  break;
            }

            if ($conf) {
                $regdate_leid = date('Y', strtotime($event_startdate)) . '-01-01';
                $ditjaarleid_eid = 291; // Hardcoded ID
                $cid = $conf['cid'];
                
                $birthyear_conf = date('Y', strtotime('-'.$conf['age'].'year', strtotime($event_startdate)));
                $birthdate_conf = "$birthyear_conf-01-01";

                $existing = civicrm_api4('Participant', 'get', [
                    'checkPermissions' => FALSE,
                    'select' => ['id'],
                    'where' => [['event_id', '=', $ditjaarleid_eid], ['contact_id', '=', $cid], ['is_test', 'IN', [TRUE, FALSE]]]
                ])->first();
                $pid = $existing['id'] ?? null;

                if (!$pid && $extwrite) {
                    civicrm_api4('Participant', 'create', [
                        'checkPermissions' => FALSE,
                        'values' => [
                            'contact_id'                => $cid,
                            'event_id'                  => $ditjaarleid_eid,
                            'status_id'                 => 1,
                            'register_date'             => $regdate_leid,
                            'role_id'                   => [6], // Leiding
                            'PART_LEID.Functie'         => 'groepsleiding',
                            'PART_LEID.Welk_kamp'       => $conf['kamp'],
                            'PART_INTERN.groep_letter'  => $conf['let'],
                            'PART_INTERN.groep_kleur'   => $conf['col'],
                            'PART_INTERN.groep_naam'    => "Team Oempaloempa",
                        ]
                    ]);
                    wachthond($extdebug, 1, "TestLeid created", "$cid -> Event $ditjaarleid_eid");
                }
                elseif ($pid && $extwrite) {
                    civicrm_api4('Contact', 'update', [
                        'checkPermissions' => FALSE, 
                        'values' => ['id' => $cid, 'birth_date' => $birthdate_conf]
                    ]);
                    civicrm_api4('Participant', 'update', [
                        'checkPermissions' => FALSE,
                        'values' => ['id' => $pid, 'register_date' => $regdate_leid, 'PART_LEID.Welk_kamp' => $conf['kamp']]
                    ]);
                    wachthond($extdebug, 1, "TestLeid updated", $cid);
                }
            }
        }
    }

    // --- VLAG WEER UITZETTEN ---
    $is_event_bezig = false;

    if (function_exists('core_microtimer')) {
        wachthond($extdebug, 1, "civicrm_timing", core_microtimer("EINDE event_civicrm_custom"));
    }
}

function event_civicrm_config(&$config): void { _event_civix_civicrm_config($config); }
function event_civicrm_install(): void { _event_civix_civicrm_install(); }
function event_civicrm_enable(): void { _event_civix_civicrm_enable(); }