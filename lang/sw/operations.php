<?php

return [
    'ui' => [
        'eyebrow' => 'Uhakikisho na urejeshaji wa huduma', 'title' => 'Kituo cha utayari wa uendeshaji',
        'description' => 'Vipimo vya utegemezi, vipimo vya SLO, nakala rudufu zenye jumla hakiki, ushahidi wa urejeshaji uliotengwa, vidhibiti vilivyoratibiwa, na historia ya matoleo na urejeshaji iliyothibitishwa kwa uhuru.',
        'ms' => 'ms', 'operational_alerts' => 'Tahadhari za uendeshaji', 'operational_alerts_description' => 'tahadhari za viwango zinazosimamiwa zenye kuondoa marudio, uthibitisho na ushahidi wa urejeshaji otomatiki. Viwango ni vya muda hadi kuidhinishwa na mmiliki wa huduma.',
        'failed_queue_jobs' => 'Kazi za foleni zilizoshindwa', 'failed_queue_jobs_description' => 'hitilafu zilizohifadhiwa. Maudhui ya mzigo na makosa yamefichwa; waendeshaji hupokea jumla hakiki na uainishaji salama.',
        'immutable_recovery_evidence' => 'Ushahidi wa urejeshaji usiobadilika', 'immutable_recovery_evidence_description' => 'Matokeo ya hivi karibuni ya kurudisha foleni yanayohusishwa na mwendeshaji; kazi zilizofaulu zinaweza kuondoka kwenye rejesta ya hitilafu lakini ushahidi huu unabaki.',
        'performance_assurance_evidence' => 'Ushahidi wa uhakikisho wa utendaji', 'performance_assurance_evidence_description' => 'majaribio ya HTTP ya wakati mmoja yasiyobadilika yenye jumla hakiki. Viwango ni picha za mazingira na si uthibitisho wa uzalishaji wa Konza.',
        'release_rollback_evidence' => 'Ushahidi wa matoleo na urejeshaji', 'release_rollback_evidence_description' => 'Upelekaji unahitaji uthibitisho huru kabla ya kuwa lengo la urejeshaji lililoidhinishwa.',
        'latest_service_measurements' => 'Vipimo vya hivi karibuni vya huduma', 'separator' => '·', 'measurements_empty' => 'Vipimo vitaonekana baada ya uchunguzi wa uendeshaji ulioratibiwa.', 'scheduled_controls' => 'Vidhibiti vilivyoratibiwa',
        'view_alert_evidence' => 'Tazama ushahidi wa tahadhari', 'immutable_timeline' => 'Mfuatano usiobadilika', 'showing_latest' => 'Inaonyesha za hivi karibuni', 'of' => 'kati ya', 'retained_events' => 'matukio yaliyohifadhiwa.',
        'accountable_response_note' => 'Dokezo la jibu lenye uwajibikaji', 'acknowledge_alert' => 'Thibitisha tahadhari', 'view_evidence' => 'Tazama ushahidi', 'performance_run_evidence' => 'Ushahidi wa jaribio la utendaji', 'threshold_snapshot' => 'Picha ya kiwango',
        'view_recovery_evidence' => 'Tazama ushahidi wa urejeshaji', 'failed' => 'ilishindwa', 'requeue_description' => 'Rudisha mzigo uliohifadhiwa kwenye foleni bila kuufichua. Hitilafu ya awali huondoka kwenye rejesta hai baada tu ya foleni kuukubali, na jaribio lisilobadilika linalohusishwa huhifadhiwa kwa vyovyote.',
        'retry_failed_job' => 'Jaribu tena kazi iliyoshindwa', 'backup_request_description' => 'Mfanyakazi wa foleni atarekodi ukubwa wa sanaa, jumla hakiki ya SHA-256, mihuri ya muda na hitilafu yoyote. Uthibitishaji wa urejeshaji ni kitendo tofauti kinachodhibitiwa.', 'queue_backup' => 'Panga nakala rudufu',
        'record_deployment' => 'Rekodi upelekaji', 'restore_description' => 'Panga urejeshaji uliotengwa kwenye hifadhidata ya muda inayozalishwa. Mhakiki huhesabu majedwali yaliyorejeshwa na kuondoa lengo hilo pekee baada ya kuthibitisha.',
        'verify_isolated_restore' => 'Hakiki urejeshaji uliotengwa', 'independently_validate' => 'Thibitisha kwa uhuru', 'record_rollback' => 'Rekodi urejeshaji', 'validate_release' => 'Thibitisha toleo',
        'record_rollback_decision' => 'Rekodi uamuzi wa urejeshaji', 'backup_restore_evidence' => 'Ushahidi wa nakala rudufu na urejeshaji', 'recovery_artifacts' => 'sanaa za urejeshaji', 'export' => 'Hamisha',
    ],
    'readiness' => [
        'search_indexes_available' => 'Faharasa :count zinazohitajika za utafutaji zinapatikana.',
        'search_indexes_missing' => 'Faharasa zinazohitajika za utafutaji hazipatikani: :indexes',
    ],
    'labels' => ['unknown_queued_job' => 'Kazi ya foleni isiyojulikana'],
    'outcomes' => [
        'release_recorded' => 'Rekodi ya upelekaji imeundwa kwa uthibitishaji huru.', 'release_validated' => 'Toleo limethibitishwa kwa uhuru.',
        'rollback_recorded' => 'Uamuzi wa urejeshaji umerekodiwa. Tekeleza mwongozo wa upelekaji ulioidhinishwa na uambatishe ushahidi wa jukwaa.', 'backup_queued' => 'Nakala rudufu ya hifadhidata imewekwa kwenye foleni.',
        'restore_verification_queued' => 'Uthibitishaji wa urejeshaji uliotengwa umewekwa kwenye foleni.', 'failed_job_requeued' => 'Kazi iliyoshindwa imerudishwa kwenye foleni pamoja na ushahidi wa urejeshaji usiobadilika.',
        'failed_job_rejected' => 'Mtoa huduma wa foleni amekataa ombi la urejeshaji; kazi iliyoshindwa bado inapatikana.', 'alert_acknowledged' => 'Tahadhari ya uendeshaji imethibitishwa pamoja na ushahidi wa jibu usiobadilika.',
    ],
    'audit' => ['release_recorded' => 'Toleo :version limerekodiwa kwa mazingira ya :environment.'],
    'performance' => [
        'errors' => ['base_url_required' => 'URL msingi iliyosanidiwa na njia ya ruti zinahitajika.', 'request_count_range' => 'Idadi ya maombi iko nje ya kiwango salama kilichosanidiwa.', 'concurrency_range' => 'Idadi ya maombi ya wakati mmoja iko nje ya kiwango salama kilichosanidiwa.', 'route_not_approved' => 'Ruti iliyoombwa haijaidhinishwa kwa kipimo cha utendaji.', 'target_not_approved' => 'Lengo lazima liwe seva ya HTTPS iliyoidhinishwa katika mazingira haya haya.'],
        'cli' => ['evidence' => 'Ushahidi', 'outcome' => 'Matokeo', 'requests_per_second' => 'Maombi/sekunde', 'p95_ms' => 'P95 ms', 'failures' => 'Hitilafu', 'checksum' => 'Jumla hakiki', 'unavailable' => 'haipatikani'],
    ],
    'backup' => ['errors' => [
        'temporary_backup_path' => 'Imeshindikana kutenga njia ya muda ya nakala rudufu.', 'persist_backup' => 'Imeshindikana kuhifadhi nakala rudufu ya hifadhidata kwenye diski iliyosanidiwa.',
        'completed_required' => 'Nakala rudufu zilizokamilika pekee ndizo zinaweza kuthibitishwa.', 'temporary_restore_path' => 'Imeshindikana kutenga njia ya muda ya urejeshaji.',
        'read_artifact' => 'Imeshindikana kusoma sanaa ya nakala rudufu kwa uthibitishaji.', 'checksum_failed' => 'Uthibitishaji wa jumla hakiki ya nakala rudufu umeshindwa.',
        'manifest_parse_failed' => 'Orodha ya nakala rudufu haikuweza kuchanganuliwa.', 'manifest_empty' => 'Orodha ya nakala rudufu haina majedwali ya programu.',
        'unsafe_restore_target' => 'Lengo la jaribio la urejeshaji si salama.', 'restored_table_count' => 'Idadi ya majedwali yaliyorejeshwa iko chini ya idadi ya orodha ya nakala rudufu.',
        'postgresql_required' => 'Nakala rudufu ya uendeshaji kwa sasa inahitaji PostgreSQL.',
    ]],
];
