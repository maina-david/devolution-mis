<?php

return [
    'validation' => [
        'minimum_counties' => 'Kiwango cha chini cha kaunti hakiwezi kuzidi kaunti zilizochaguliwa.',
    ],
    'messages' => [
        'uat_campaign_created' => 'Kampeni ya majaribio ya UAT :code imeundwa kama mpango; haimaanishi utekelezaji au ukubalifu.',
        'uat_scenario_created' => 'Tukio la UAT :code limeongezwa kwenye kampeni.',
        'uat_execution_recorded' => 'Ushahidi usiobadilika wa utekelezaji wa UAT umehifadhiwa kwa matokeo :outcome.',
        'uat_finding_transitioned' => 'Hoja ya UAT sasa iko katika hali ya :status.',
        'uat_campaign_submitted' => 'Kampeni imewasilishwa kwa ukubalifu huru.',
        'uat_campaign_decided' => 'Uamuzi huru wa UAT umehifadhiwa kama :decision.',
    ],
    'uat_errors' => [
        'county_missing' => 'Kila kaunti ya UAT iliyochaguliwa lazima iwepo.', 'county_scope' => 'Kaunti ya UAT iliyochaguliwa iko nje ya wigo wako.', 'campaign_scope' => 'Kampeni hii ya UAT iko nje ya kaunti ulizoidhinishwa.',
        'scenario_planning_only' => 'Matukio yanaweza kuongezwa wakati kampeni iko katika upangaji pekee.', 'execution_closed' => 'Matukio yaliyo tayari katika kampeni wazi pekee ndiyo yanaweza kutekelezwa.', 'actor_role' => 'Tukio hili lazima litekelezwe na mtumiaji mwakilishi wa jukumu lililosanidiwa.',
        'execution_county_scope' => 'Kaunti ya utekelezaji iko nje ya wigo wako.', 'execution_county_campaign' => 'Kaunti ya utekelezaji si sehemu ya kampeni hii.', 'finding_owner_scope' => 'Mmiliki wa marekebisho hajaidhinishwa kwa kaunti hii.',
        'resolve_separation' => 'Mmiliki aliyeteuliwa kwa uhuru pekee ndiye anaweza kutatua hoja wazi.', 'verify_separation' => 'Uhakiki unahitaji hoja iliyotatuliwa na mhakiki huru.', 'reopen_separation' => 'Mkaguzi huru pekee ndiye anaweza kufungua tena hoja.',
        'submit_state' => 'Kampeni iliyotekelezwa au kukataliwa isiyo na ukaguzi unaosubiri pekee ndiyo inaweza kuwasilishwa.', 'submit_coverage' => 'Kampeni inahitaji kiwango cha chini cha kaunti na angalau tukio moja linalohitajika.', 'submit_evidence' => 'Uwasilishaji unahitaji matokeo ya kupita kwa kila jozi ya tukio na kaunti, majukumu yote na hoja zilizohakikiwa kwa uhuru.',
        'decision_state' => 'Ukaguzi wa kampeni unaosubiri pekee ndio unaweza kuamuliwa.', 'decision_separation' => 'Mwandishi, mwasilishaji na wapimaji hawawezi kuamua ukubalifu kwa uhuru.',
    ],
    'uat' => [
        'tab' => 'UAT ya majaribio',
        'eyebrow' => 'Upimaji wakilishi na ukubalifu rasmi',
        'title' => 'Ukubalifu unaosimamiwa wa majaribio',
        'description' => 'Panga matukio wakilishi, hifadhi ushahidi usiobadilika wa utekelezaji wa kaunti, funga hoja kwa uhuru na tunza historia rasmi ya ukubalifu.',
        'new_campaign' => 'Panga kampeni ya UAT',
        'empty_title' => 'Hakuna kampeni za UAT katika wigo',
        'empty_description' => 'Unda mpango unaosimamiwa wa majaribio bila kudokeza kuwa upimaji au ukubalifu umefanyika.',
        'campaigns' => 'Kampeni katika wigo', 'scenarios' => 'Matukio yanayohitajika', 'executions' => 'Utekelezaji uliohifadhiwa', 'open_findings' => 'Hoja zilizo wazi',
        'search' => 'Tafuta kampeni za UAT', 'status' => 'Hali', 'county' => 'Kaunti', 'campaign' => 'Kampeni', 'environment' => 'Mazingira', 'counties' => 'Kaunti', 'acceptance' => 'Ukubalifu', 'not_submitted' => 'Haijawasilishwa',
        'export_evidence' => 'Hamisha ushahidi wa UAT',
        'open_actions' => 'Fungua vitendo vya kampeni ya UAT', 'open_record' => 'Fungua rekodi kamili ya UAT', 'catalogue' => 'Katalogi', 'period' => 'Kipindi cha majaribio', 'creator' => 'Imeundwa na', 'required_roles' => 'Majukumu wakilishi yanayohitajika', 'acceptance_criteria' => 'Vigezo vya ukubalifu',
        'no_scenarios' => 'Hakuna matukio yaliyosanidiwa', 'no_scenarios_description' => 'Ongeza matukio wakilishi ya mwanzo hadi mwisho kabla ya kuhifadhi ushahidi.', 'national' => 'Kitaifa',
        'new_campaign_description' => 'Eleza wigo na vigezo. Kuhifadhi kunarekodi mpango pekee.', 'catalogue_required' => 'Chapisha kwanza katalogi kamili inayotumika.', 'code' => 'Msimbo', 'name' => 'Jina', 'objective' => 'Lengo', 'starts_on' => 'Inaanza', 'ends_on' => 'Inaisha', 'minimum_counties' => 'Kiwango cha chini cha kaunti', 'acceptance_criterion' => 'Kigezo cha ukubalifu', 'save_plan' => 'Hifadhi mpango',
        'add_scenario' => 'Ongeza tukio', 'add_scenario_description' => 'Eleza safari ya jukumu, ufikivu na mtandao hafifu.', 'title_label' => 'Kichwa cha tukio', 'module' => 'Moduli ya ToR', 'actor_role' => 'Jukumu la mwakilishi', 'priority' => 'Kipaumbele', 'journey' => 'Safari ya mwanzo hadi mwisho', 'precondition' => 'Sharti la awali', 'step' => 'Hatua ya jaribio', 'expected_result' => 'Matokeo yanayotarajiwa', 'accessibility_variant' => 'Jaribio la ufikivu', 'low_connectivity_variant' => 'Jaribio la mtandao hafifu', 'save_scenario' => 'Hifadhi tukio',
        'record_execution' => 'Hifadhi utekelezaji', 'representative_role' => 'Jukumu wakilishi linalohitajika', 'outcome' => 'Matokeo', 'actual_result' => 'Matokeo yaliyoonekana', 'evidence_reference' => 'Rejeleo la ushahidi', 'started_at' => 'Ilianza', 'completed_at' => 'Ilikamilika', 'finding_owner' => 'Mmiliki wa marekebisho', 'severity' => 'Uzito', 'finding_title' => 'Kichwa cha hoja', 'finding_description' => 'Maelezo ya hoja', 'finding_due_on' => 'Hoja ifikapo', 'record_immutable_evidence' => 'Hifadhi ushahidi usiobadilika',
        'transition_finding' => 'Badilisha hali ya hoja', 'review_finding' => 'Kagua hoja', 'action' => 'Kitendo', 'resolution' => 'Suluhisho na ushahidi', 'save_transition' => 'Hifadhi mabadiliko',
        'submit_acceptance' => 'Wasilisha kwa ukubalifu', 'submit_acceptance_description' => 'Mfumo utakagua kila jozi ya tukio na kaunti, jukumu na hoja.', 'confirm_criteria' => 'Ninathibitisha vigezo viko tayari kwa ukaguzi huru wa mfumo.',
        'decide_acceptance' => 'Amua ukubalifu', 'evidence_checksum' => 'Alama ya ushahidi', 'decision' => 'Uamuzi', 'decision_reason' => 'Sababu huru ya uamuzi', 'record_decision' => 'Hifadhi uamuzi huru',
    ],
];
