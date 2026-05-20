<?php
/**
 * SmartToll Rule-Based AI Engine v1.0
 * Pure PHP — no external libraries or APIs required.
 * 8 intelligent modules visible in the Smart AI Insights page.
 */
class RuleEngineController {
    private $db;
    public function __construct() {
        Security::requireStrictAdmin();
        $this->db = Database::getInstance();
    }
    public function index(): void {
        $fraud     = $this->runFraudDetection();
        $traffic   = $this->runTrafficPatterns();
        $tollSugg  = $this->runDynamicTollSuggestions();
        $userClass = $this->runUserClassification();
        $anomalies = $this->runAnomalyDetection();
        $alerts    = $this->runSmartAlerts();
        $behaviour = $this->runVehicleBehaviour();
        $queue     = $this->runQueueEstimation();
        Response::view('admin/ai_insights', compact(
            'fraud','traffic','tollSugg','userClass',
            'anomalies','alerts','behaviour','queue'
        ), 'admin');
    }

    // ── 1. FRAUD DETECTION ─────────────────────────────────────
    private function runFraudDetection(): array {
        $flags = array();
        // Rule 1: Impossible travel — same RFID at 2 gates within 5 min
        $rapid = $this->db->fetchAll(
            "SELECT t1.rfid_uid, t1.device_id as g1, t2.device_id as g2,
                    v.plate_number, u.full_name,
                    ABS(TIMESTAMPDIFF(SECOND,t1.processed_at,t2.processed_at)) as gap_sec
             FROM transactions t1
             JOIN transactions t2
               ON t1.rfid_uid=t2.rfid_uid AND t1.device_id!=t2.device_id
               AND ABS(TIMESTAMPDIFF(SECOND,t1.processed_at,t2.processed_at)) BETWEEN 1 AND 300
             LEFT JOIN vehicles v ON t1.vehicle_id=v.id
             LEFT JOIN users u ON t1.user_id=u.id
             WHERE t1.processed_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)
             GROUP BY t1.rfid_uid,g1,g2 LIMIT 10"
        );
        foreach ($rapid as $r) {
            $flags[] = array('level'=>'high','rule'=>'Impossible Travel','icon'=>'fa-route',
                'detail'=>($r['full_name']??'Unknown').' (plate '.($r['plate_number']??'?').') at 2 gates '.$r['gap_sec'].'s apart',
                'plate'=>$r['plate_number']??'');
        }
        // Rule 2: RFID scanned >10 times in 1 hour (replay attack)
        $hiFreq = $this->db->fetchAll(
            "SELECT rfid_uid,COUNT(*) as sc,v.plate_number,u.full_name
             FROM transactions t
             LEFT JOIN vehicles v ON t.vehicle_id=v.id
             LEFT JOIN users u ON t.user_id=u.id
             WHERE t.processed_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)
             GROUP BY rfid_uid HAVING sc>10"
        );
        foreach ($hiFreq as $r) {
            $flags[] = array('level'=>'high','rule'=>'RFID Replay Suspected','icon'=>'fa-repeat',
                'detail'=>($r['full_name']??'?').' RFID scanned '.$r['sc'].' times in 1 hour','plate'=>$r['plate_number']??'');
        }
        // Rule 3: >50% denial rate today
        $den = $this->db->fetchAll(
            "SELECT u.full_name,SUM(CASE WHEN t.status='denied' THEN 1 ELSE 0 END) as denied,COUNT(*) as total
             FROM transactions t JOIN users u ON t.user_id=u.id
             WHERE t.processed_at>=CURDATE()
             GROUP BY u.id HAVING total>=5 AND (denied/total)>0.5"
        );
        foreach ($den as $r) {
            $rate=round($r['denied']/$r['total']*100);
            $flags[] = array('level'=>'medium','rule'=>'High Denial Rate','icon'=>'fa-ban',
                'detail'=>$r['full_name'].' — '.$rate.'% denied ('.$r['denied'].'/'.$r['total'].')','plate'=>'');
        }
        // Rule 4: Duplicate payment references
        $dup = $this->db->fetchAll(
            "SELECT reference_number,COUNT(*) as c FROM topup_requests
             WHERE reference_number IS NOT NULL AND reference_number!=''
             GROUP BY reference_number HAVING c>1 LIMIT 5"
        );
        foreach ($dup as $r) {
            $flags[] = array('level'=>'high','rule'=>'Duplicate Payment Reference','icon'=>'fa-copy',
                'detail'=>'Ref "'.$r['reference_number'].'" used '.$r['c'].' times — possible fraud','plate'=>'');
        }
        // Rule 5: Negative wallet balance after successful transactions
        $neg = $this->db->fetchAll(
            "SELECT u.full_name,u.wallet_balance,COUNT(*) as tx
             FROM transactions t JOIN users u ON t.user_id=u.id
             WHERE t.status='success' AND t.processed_at>=DATE_SUB(NOW(),INTERVAL 6 HOUR)
               AND u.wallet_balance<0
             GROUP BY u.id LIMIT 5"
        );
        foreach ($neg as $r) {
            $flags[] = array('level'=>'high','rule'=>'Negative Balance Exploit','icon'=>'fa-triangle-exclamation',
                'detail'=>$r['full_name'].' balance '.Security::currency().number_format($r['wallet_balance'],2).' after '.$r['tx'].' successful tx','plate'=>'');
        }
        // Rule 6: Blacklisted vehicle attempts
        $bl = $this->db->fetchAll(
            "SELECT v.plate_number,COUNT(*) as attempts FROM transactions t
             JOIN vehicles v ON t.vehicle_id=v.id
             WHERE t.deny_reason='BLACKLISTED' AND t.processed_at>=CURDATE()
             GROUP BY v.plate_number"
        );
        foreach ($bl as $r) {
            $flags[] = array('level'=>'high','rule'=>'Blacklisted Vehicle Attempt','icon'=>'fa-shield-halved',
                'detail'=>'Plate '.$r['plate_number'].' attempted entry '.$r['attempts'].'x today','plate'=>$r['plate_number']);
        }
        return array('flags'=>$flags,'risk_level'=>count($flags)===0?'low':(count($flags)<3?'medium':'high'),'flag_count'=>count($flags));
    }

    // ── 2. TRAFFIC PATTERN ESTIMATION ─────────────────────────
    private function runTrafficPatterns(): array {
        $hourly = $this->db->fetchAll(
            "SELECT HOUR(processed_at) as hr,COUNT(*) as volume
             FROM transactions WHERE DATE(processed_at)=CURDATE() AND status='success'
             GROUP BY hr ORDER BY hr"
        );
        $byHour = array_fill(0,24,0);
        foreach ($hourly as $r) $byHour[(int)$r['hr']]=(int)$r['volume'];
        $maxVol = !empty($byHour) ? max(max($byHour), 1) : 1;
        $peakHr=array_search($maxVol,$byHour);
        $weekly = $this->db->fetchAll(
            "SELECT DAYNAME(d) as day_name, DAYOFWEEK(d) as dow, AVG(dc) as avg_vol
             FROM (
               SELECT DATE(processed_at) as d, COUNT(*) as dc
               FROM transactions
               WHERE processed_at >= DATE_SUB(CURDATE(), INTERVAL 28 DAY)
               AND status = 'success'
               GROUP BY DATE(processed_at)
             ) s
             GROUP BY DAYOFWEEK(d)
             ORDER BY DAYOFWEEK(d)"
        );
        $busiest=$this->db->fetchOne(
            "SELECT d.device_name,COUNT(*) as vol FROM transactions t JOIN devices d ON t.device_id=d.id
             WHERE t.processed_at>=CURDATE() AND t.status='success' GROUP BY d.id ORDER BY vol DESC LIMIT 1"
        );
        $thisHr=$byHour[date('G')]??0;
        $yest=(int)($this->db->fetchOne("SELECT COUNT(*) as c FROM transactions WHERE processed_at BETWEEN DATE_SUB(NOW(),INTERVAL 25 HOUR) AND DATE_SUB(NOW(),INTERVAL 23 HOUR) AND status='success'")['c']??0);
        $trend=$yest>0?round(($thisHr-$yest)/$yest*100):0;
        $nonZero=array_filter($byHour,function($v){return $v>0;});
        $avg=count($nonZero)>0?array_sum($nonZero)/count($nonZero):0;
        $level=$thisHr>$avg*1.5?'high':($thisHr>$avg*0.5?'medium':'low');
        return array('byHour'=>$byHour,'peakHour'=>$peakHr,'peakVol'=>$maxVol,'weekly'=>$weekly,
            'busiest'=>$busiest,'trend'=>$trend,'level'=>$level,'totalToday'=>array_sum($byHour));
    }

    // ── 3. DYNAMIC TOLL SUGGESTIONS ───────────────────────────
    private function runDynamicTollSuggestions(): array {
        $rows=$this->db->fetchAll("SELECT setting_key,setting_value FROM system_settings WHERE setting_key LIKE 'toll_fee_%'");
        $fees=array();
        foreach ($rows as $s) $fees[str_replace('toll_fee_','',$s['setting_key'])]=(float)$s['setting_value'];
        $hr=(int)date('G'); $dow=(int)date('N');
        $isPeak=$dow<=5&&(($hr>=7&&$hr<=9)||($hr>=16&&$hr<=19));
        $isOff=$hr>=22||$hr<=5; $isWknd=$dow>=6;
        $vol=(int)$this->db->fetchOne("SELECT COUNT(*) as c FROM transactions WHERE processed_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR) AND status='success'")['c'];
        $isCong=$vol>30;
        $suggestions=array();
        foreach ($fees as $vtype=>$base) {
            $sug=$base; $reasons=array();
            if ($isCong&&$isPeak)   { $sug=round($base*1.25,2); $reasons[]='Peak+congestion: +25%'; }
            elseif ($isPeak)        { $sug=round($base*1.15,2); $reasons[]='Peak hour: +15%'; }
            elseif ($isOff)         { $sug=round($base*0.80,2); $reasons[]='Off-peak: -20%'; }
            elseif ($isWknd)        { $sug=round($base*0.90,2); $reasons[]='Weekend: -10%'; }
            else                    { $reasons[]='Standard rate'; }
            $suggestions[]=array('vehicle'=>ucfirst($vtype),'current'=>$base,'suggested'=>$sug,
                'change'=>round($sug-$base,2),'reason'=>implode('; ',$reasons),'apply'=>$sug!==$base);
        }
        return array('suggestions'=>$suggestions,'isPeak'=>$isPeak,'isOffPeak'=>$isOff,'isWeekend'=>$isWknd,
            'isCongested'=>$isCong,'lastHrVol'=>$vol,'timeLabel'=>$isPeak?'Peak Hour':($isOff?'Off-Peak':($isWknd?'Weekend':'Normal')));
    }

    // ── 4. USER USAGE CLASSIFICATION ──────────────────────────
    private function runUserClassification(): array {
        $users=$this->db->fetchAll(
            "SELECT u.id,u.full_name,u.wallet_balance,COUNT(t.id) as total_tx,
                    SUM(CASE WHEN t.processed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) THEN 1 ELSE 0 END) as tx_30d,
                    COALESCE(SUM(t.toll_amount),0) as total_spent,MAX(t.processed_at) as last_tx
             FROM users u LEFT JOIN transactions t ON u.id=t.user_id AND t.status='success'
             WHERE u.role='user' AND u.status='active' GROUP BY u.id ORDER BY total_spent DESC LIMIT 100"
        );
        $classes=array('VIP'=>array(),'Frequent'=>array(),'Occasional'=>array(),'Inactive'=>array(),'At-Risk'=>array());
        $thr=(float)($this->db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key='low_balance_alert'")['setting_value']??50);
        foreach ($users as $u) {
            $tx=(int)$u['tx_30d']; $bal=(float)$u['wallet_balance']; $spent=(float)$u['total_spent'];
            $last=$u['last_tx']?(int)((time()-strtotime($u['last_tx']))/86400):999;
            if ($spent>500&&$tx>20)          $c='VIP';
            elseif ($tx>=10)                 $c='Frequent';
            elseif ($tx>=2)                  $c='Occasional';
            elseif ($last>60||!$u['total_tx']) $c='Inactive';
            elseif ($bal<$thr)               $c='At-Risk';
            else                             $c='Occasional';
            $classes[$c][]=array('name'=>$u['full_name'],'tx_30d'=>$tx,'balance'=>$bal,'spent'=>$spent,
                'last_tx'=>$last<999?$last.'d ago':'Never');
        }
        return array('classes'=>$classes,'totals'=>array_map('count',$classes),'topUsers'=>array_slice($users,0,5));
    }

    // ── 5. ANOMALY DETECTION ──────────────────────────────────
    private function runAnomalyDetection(): array {
        $an=array();
        $daily=$this->db->fetchAll(
            "SELECT DATE(processed_at) as d,COALESCE(SUM(toll_amount),0) as rev,COUNT(*) as vol
             FROM transactions WHERE processed_at>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) AND status='success'
             GROUP BY d ORDER BY d"
        );
        if (count($daily)>=7) {
            $revs=array_map(function($r){return(float)$r['rev'];},$daily);
            $vols=array_map(function($r){return(int)$r['vol'];},$daily);
            $mr=array_sum($revs)/count($revs); $mv=array_sum($vols)/count($vols);
            $sr=sqrt(array_sum(array_map(function($v)use($mr){return pow($v-$mr,2);},$revs))/count($revs));
            $sv=sqrt(array_sum(array_map(function($v)use($mv){return pow($v-$mv,2);},$vols))/count($vols));
            foreach ($daily as $r) {
                $zr=$sr>0?abs(((float)$r['rev']-$mr)/$sr):0;
                $zv=$sv>0?abs(((int)$r['vol']-$mv)/$sv):0;
                if ($zr>2.0) {
                    $dir=(float)$r['rev']>$mr?'Spike':'Drop';
                    $an[]=array('type'=>'Revenue '.$dir,'date'=>$r['d'],'value'=>Security::currency().number_format($r['rev'],2),
                        'zscore'=>round($zr,2),'level'=>$zr>3?'high':'medium','icon'=>'fa-chart-line',
                        'color'=>$dir==='Spike'?'var(--accent-green)':'var(--accent-red)');
                }
                if ($zv>2.0) {
                    $dir=(int)$r['vol']>$mv?'Surge':'Drop';
                    $an[]=array('type'=>'Volume '.$dir,'date'=>$r['d'],'value'=>$r['vol'].' tx',
                        'zscore'=>round($zv,2),'level'=>$zv>3?'high':'medium','icon'=>'fa-chart-bar','color'=>'var(--accent-amber)');
                }
            }
        }
        $ga=$this->db->fetchAll(
            "SELECT d.device_name FROM devices d WHERE d.status='online'
             AND (SELECT COUNT(*) FROM transactions t WHERE t.device_id=d.id AND DATE(t.processed_at)=CURDATE())=0
             AND (SELECT COUNT(*) FROM transactions t WHERE t.device_id=d.id AND DATE(t.processed_at)=DATE_SUB(CURDATE(),INTERVAL 1 DAY))>0"
        );
        foreach ($ga as $g) {
            $an[]=array('type'=>'Gate Inactivity','date'=>date('Y-m-d'),'value'=>$g['device_name'].' online but 0 tx today',
                'zscore'=>'N/A','level'=>'medium','icon'=>'fa-satellite-dish','color'=>'var(--accent-amber)');
        }
        usort($an,function($a,$b){return $b['level']==='high'?1:-1;});
        return array('anomalies'=>$an,'count'=>count($an),'has_high'=>!empty(array_filter($an,function($a){return $a['level']==='high';})));
    }

    // ── 6. SMART ALERTS ───────────────────────────────────────
    private function runSmartAlerts(): array {
        $al=array();
        foreach($this->db->fetchAll("SELECT device_name FROM devices WHERE status='offline'") as $d) {
            $al[]=array('level'=>'danger','icon'=>'fa-power-off','title'=>'Gate Offline',
                'body'=>$d['device_name'].' is offline. No transactions possible.','action'=>url('admin/devices'));
        }
        $crit=(int)$this->db->fetchOne("SELECT COUNT(*) as c FROM users WHERE wallet_balance<2 AND status='active' AND role='user'")['c'];
        if($crit>0) $al[]=array('level'=>'danger','icon'=>'fa-wallet','title'=>$crit.' Users Critical Balance',
            'body'=>$crit.' users below '.Security::currency().'2 — denied on next scan.','action'=>url('admin/users'));
        $oldP=(int)$this->db->fetchOne("SELECT COUNT(*) as c FROM topup_requests WHERE status='pending' AND requested_at<DATE_SUB(NOW(),INTERVAL 2 HOUR)")['c'];
        if($oldP>0) $al[]=array('level'=>'warning','icon'=>'fa-clock','title'=>$oldP.' Stale Top-Up Requests',
            'body'=>$oldP.' pending >2 hours. Users may be unable to travel.','action'=>url('admin/topups'));
        $lh=$this->db->fetchOne("SELECT COUNT(*) as total,SUM(CASE WHEN status='denied' THEN 1 ELSE 0 END) as denied FROM transactions WHERE processed_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)");
        if($lh['total']>5&&$lh['denied']/$lh['total']>0.30) {
            $rate=round($lh['denied']/$lh['total']*100);
            $al[]=array('level'=>'warning','icon'=>'fa-ban','title'=>"High Denial Rate: {$rate}%",
                'body'=>$lh['denied'].'/'.$lh['total'].' denied in last hour.','action'=>url('admin/reports'));
        }
        $blH=(int)$this->db->fetchOne("SELECT COUNT(*) as c FROM transactions WHERE deny_reason='BLACKLISTED' AND DATE(processed_at)=CURDATE()")['c'];
        if($blH>0) $al[]=array('level'=>'danger','icon'=>'fa-shield-halved','title'=>$blH.' Blacklist Hit(s) Today',
            'body'=>$blH.' attempt(s) by blacklisted vehicles.','action'=>url('admin/blacklist'));
        $biz=(int)date('G')>=6&&(int)date('G')<=20;
        if($biz&&(int)$this->db->fetchOne("SELECT COUNT(*) as c FROM transactions WHERE processed_at>=DATE_SUB(NOW(),INTERVAL 2 HOUR)")['c']===0) {
            $al[]=array('level'=>'warning','icon'=>'fa-circle-pause','title'=>'No Activity — 2 Hours',
                'body'=>'Zero transactions in 2 hours during business hours.','action'=>url('admin/gate-override'));
        }
        $rev=(float)$this->db->fetchOne("SELECT COALESCE(SUM(toll_amount),0) as r FROM transactions WHERE DATE(processed_at)=CURDATE() AND status='success'")['r'];
        if($rev>200) $al[]=array('level'=>'success','icon'=>'fa-circle-check','title'=>'Daily Target Reached',
            'body'=>'Revenue '.Security::currency().number_format($rev,2).' exceeded '.Security::currency().'200.','action'=>url('admin/reports'));
        return array('alerts'=>$al,'count'=>count($al));
    }

    // ── 7. VEHICLE BEHAVIOUR CLASSIFICATION ───────────────────
    private function runVehicleBehaviour(): array {
        $veh=$this->db->fetchAll(
            "SELECT v.plate_number,v.vehicle_type,COUNT(t.id) as total_tx,AVG(HOUR(t.processed_at)) as avg_hour,
                    SUM(CASE WHEN DAYOFWEEK(t.processed_at) IN(1,7) THEN 1 ELSE 0 END) as wknd,
                    SUM(CASE WHEN HOUR(t.processed_at) BETWEEN 7 AND 9 OR HOUR(t.processed_at) BETWEEN 16 AND 19 THEN 1 ELSE 0 END) as peak,
                    SUM(CASE WHEN HOUR(t.processed_at)<6 OR HOUR(t.processed_at)>22 THEN 1 ELSE 0 END) as offhr,
                    COUNT(DISTINCT t.device_id) as gates_used
             FROM vehicles v JOIN transactions t ON v.id=t.vehicle_id AND t.status='success'
             WHERE t.processed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)
             GROUP BY v.id HAVING total_tx>=3 ORDER BY total_tx DESC LIMIT 50"
        );
        $classified=array(); $summary=array();
        foreach($veh as $v){
            $tot=(int)$v['total_tx'];
            $pr=$tot>0?$v['peak']/$tot:0; $wr=$tot>0?$v['wknd']/$tot:0; $or=$tot>0?$v['offhr']/$tot:0;
            if($or>0.4)            $p='Night Traveller';
            elseif($wr>0.6)        $p='Weekend Driver';
            elseif($pr>0.6)        $p='Rush Hour Commuter';
            elseif((int)$v['gates_used']>1&&$tot>15) $p='Cross-Gate Traveller';
            elseif($tot>20)        $p='Frequent Regular';
            else                   $p='Occasional User';
            $classified[]=array('plate'=>$v['plate_number'],'type'=>ucfirst($v['vehicle_type']),
                'profile'=>$p,'trips'=>$tot,'avgHour'=>round((float)$v['avg_hour'],1),'gates'=>$v['gates_used']);
            $summary[$p]=($summary[$p]??0)+1;
        }
        arsort($summary);
        return array('vehicles'=>$classified,'summary'=>$summary);
    }

    // ── 8. QUEUE ESTIMATION LOGIC ─────────────────────────────
    private function runQueueEstimation(): array {
        $gates=$this->db->fetchAll(
            "SELECT d.id,d.device_name,d.device_code,d.status,
                    COUNT(t.id) as last15min
             FROM devices d
             LEFT JOIN transactions t ON d.id=t.device_id AND t.processed_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)
             GROUP BY d.id ORDER BY d.device_name"
        );
        $total15=0; foreach($gates as $g) $total15+=(int)$g['last15min'];
        $avgPG=count($gates)>0?$total15/max(count($gates),1):0;
        $est=array();
        foreach($gates as $g){
            $vol=(int)$g['last15min']; $on=$g['status']==='online';
            $qd=$on?max(0,$vol-30):0; $wm=round($qd*30/60,1);
            $load=$on?min(100,round($vol/max($avgPG*1.5,1)*100)):0;
            $sl=!$on?'Offline':($load>=80?'Congested':($load>=50?'Busy':($load>=20?'Moderate':'Free Flow')));
            $col=$load>=80?'var(--accent-red)':($load>=50?'var(--accent-amber)':'var(--accent-green)');
            $est[]=array('gate'=>$g['device_name'],'code'=>$g['device_code'],'online'=>$on,
                'vol15'=>$vol,'load'=>$load,'queue_depth'=>$qd,'wait_min'=>$wm,'status'=>$sl,
                'recommended'=>$on&&$load<50,'color'=>$col);
        }
        usort($est,function($a,$b){return $a['load']-$b['load'];});
        $rec=''; foreach($est as $e){if($e['recommended']){$rec=$e['gate'];break;}}
        return array('gates'=>$est,'total15min'=>$total15,'recommendation'=>$rec?:'All gates busy',
            'systemLoad'=>$total15>0?min(100,round($total15/max(count($gates)*30,1)*100)):0);
    }
}
