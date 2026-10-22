<?php
session_start();
require_once 'base.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['position'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}
$user_id      = $_SESSION['user_id'] ?? '';
$admin_id     = $user_id;  //
$admin_name   = htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Admin');
$current_page = 'ingredient-analytics';

/* ── Date filter ── */
$range = $_GET['range'] ?? 'all';
if (!in_array($range,['today','7days','30days','all'])) $range='all';
$date_sql='';
switch($range){
    case 'today':  $date_sql="AND DATE(o.created_at)=CURDATE()"; break;
    case '7days':  $date_sql="AND o.created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)"; break;
    case '30days': $date_sql="AND o.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)"; break;
}
$range_labels=['today'=>'Today','7days'=>'Last 7 Days','30days'=>'Last 30 Days','all'=>'All Time'];
$range_label=$range_labels[$range];

/* ── Detect column ── */
$opts_col=null; $has_opts_col=false;
foreach(['selected_options','custom_options'] as $c){
    $chk=mysqli_query($connect,"SHOW COLUMNS FROM order_items LIKE '$c'");
    if($chk&&mysqli_num_rows($chk)>0){$opts_col=$c;$has_opts_col=true;break;}
}

/* ── Debug sample ── */
$debug_sample=null;
if($has_opts_col){
    $dbg=mysqli_query($connect,"SELECT `{$opts_col}` AS opts FROM order_items
        WHERE `{$opts_col}` IS NOT NULL AND `{$opts_col}`!='' AND `{$opts_col}`!='null' LIMIT 1");
    if($dbg) $debug_sample=mysqli_fetch_assoc($dbg);
}

/* ── Label maps ── */
$known_removes=[
    'no_onion'=>'🧅 No Onion','no_chili'=>'🌶 No Chili','no_garlic'=>'🧄 No Garlic',
    'no_ginger'=>'🫚 No Ginger','no_sauce'=>'🥫 No Sauce','no_egg'=>'🥚 No Egg',
    'no_coriander'=>'🌿 No Coriander','no_peanut'=>'🥜 No Peanut','no_mushroom'=>'🍄 No Mushroom',
    'no_tomato'=>'🍅 No Tomato','no_pepper'=>'🫑 No Pepper','no_lemon'=>'🍋 No Lemon',
    'no_cream'=>'🥛 No Cream','no_butter'=>'🧈 No Butter','no_cheese'=>'🧀 No Cheese',
    'no_prawn'=>'🦐 No Prawn','no_meat'=>'🥩 No Meat','no_lettuce'=>'🥬 No Lettuce',
    'no_pickle'=>'🥒 No Pickle','no_sugar'=>'🍬 Less Sugar','no_ice'=>'🧊 Less Ice',
    'no_milk'=>'🥛 Less Milk','no_capsicum'=>'🫑 No Capsicum','no_corn'=>'🌽 No Corn',
    'no_carrot'=>'🥕 No Carrot','no_cabbage'=>'🥬 No Cabbage','no_nuts'=>'🌰 No Nuts',
    'no_basil'=>'🌿 No Basil','no_pineapple'=>'🍍 No Pineapple','no_vinegar'=>'🍶 No Vinegar',
    'no_sausage'=>'🌭 No Sausage','no_lemongrass'=>'🌾 No Lemongrass',
    'no_parmesan'=>'🧀 No Parmesan','no_pancake'=>'🫓 No Pancake','no_curry_leaf'=>'🌿 No Curry Leaf',
];
$known_addons=[
    'add_rice'    =>['label'=>'🍚 Add Rice','price'=>2.00],
    'add_sauce'   =>['label'=>'🥫 Extra Sauce','price'=>1.00],
    'add_meat'    =>['label'=>'🥩 Extra Meat','price'=>5.00],
    'add_egg'     =>['label'=>'🥚 Extra Egg','price'=>2.00],
    'add_prawn'   =>['label'=>'🦐 Extra Prawn','price'=>6.00],
    'add_cheese'  =>['label'=>'🧀 Extra Cheese','price'=>2.00],
    'add_tofu'    =>['label'=>'🟨 Extra Tofu','price'=>2.00],
    'add_mushroom'=>['label'=>'🍄 Extra Mushroom','price'=>2.00],
    'add_bread'   =>['label'=>'🍞 Bread/Bun','price'=>2.00],
    'add_cream'   =>['label'=>'🍦 Whipped Cream','price'=>1.00],
    'add_peanut'  =>['label'=>'🥜 Extra Peanut','price'=>1.00],
    'add_extra'   =>['label'=>'➕ Extra Portion','price'=>3.00],
    'add_salad'   =>['label'=>'🥗 Side Salad','price'=>2.00],
    'add_sugar'   =>['label'=>'🍬 Extra Sugar','price'=>0.00],
    'add_milk'    =>['label'=>'🥛 Extra Milk','price'=>0.50],
    'add_shot'    =>['label'=>'☕ Extra Shot','price'=>2.00],
    'add_pancake' =>['label'=>'🫓 Extra Pancake','price'=>2.00],
    'add_onion'   =>['label'=>'🧅 Extra Onion','price'=>0.50],
    'add_nuts'    =>['label'=>'🌰 Extra Nuts','price'=>1.00],
    'add_coriander'=>['label'=>'🌿 Extra Coriander','price'=>0.50],
];

function rl(string $key, ?string $pj, array $km): string {
    if(isset($km[$key])) return $km[$key];
    if($pj){$po=@json_decode($pj,true);foreach($po['remove']??[] as $r)if(($r['key']??'')===$key)return $r['label']??$key;}
    return ucwords(str_replace(['no_','_'],['No ',' '],$key));
}
function al(string $key, ?string $pj, array $km): array {
    if(isset($km[$key])) return $km[$key];
    if($pj){$po=@json_decode($pj,true);foreach($po['addons']??[] as $a)if(($a['key']??'')===$key)return['label'=>$a['label']??$key,'price'=>(float)($a['price']??0)];}
    return['label'=>ucwords(str_replace(['add_','_'],['',  ' '],$key)),'price'=>0.0];
}

/* ── Aggregation ── */
$removes_agg=[];$addons_agg=[];$spice_agg=[];
$total_orders_with_opts=0;$total_removes=0;$total_addons=0;

if($has_opts_col){
    $sql="SELECT oi.`{$opts_col}` AS opts_json, oi.quantity,
                 p.name AS product_name, p.custom_options AS product_opts
          FROM order_items oi
          JOIN orders o ON oi.orderid=o.orderid
          LEFT JOIN products p ON oi.productid=p.productid
          WHERE oi.`{$opts_col}` IS NOT NULL
            AND oi.`{$opts_col}`!=''
            AND oi.`{$opts_col}`!='{}'
            AND oi.`{$opts_col}`!='null'
            $date_sql";
    $res=mysqli_query($connect,$sql);
    if($res){
        while($row=mysqli_fetch_assoc($res)){
            $opts=@json_decode($row['opts_json'],true);
            if(!is_array($opts)||empty($opts)) continue;
            $total_orders_with_opts++;

            /*
             * customizeModal.php saves:
             *   "removes": {"no_onion":false,"no_chili":true}   ← KEY "removes" plural, assoc bool
             *   "addons":  {"add_sauce":true,"add_rice":false}   ← KEY "addons"  plural, assoc bool
             *
             * Admin product form (alternative) saves:
             *   "remove": [{"key":"no_onion","label":"..."}]     ← KEY "remove"  singular, indexed
             *   "addons": [{"key":"add_sauce","label":"..."}]    ← KEY "addons"  plural,  indexed
             *
             * Flat root (legacy):
             *   {"no_onion":true, "add_sauce":true}
             */

            /* ── Removes ── */
            $removes=[];
            $rm_src = $opts['removes'] ?? ($opts['remove'] ?? null); // "removes" first!
            if($rm_src!==null&&is_array($rm_src)){
                if(array_values($rm_src)===$rm_src){
                    foreach($rm_src as $r){
                        if(is_string($r)) $removes[]=$r;
                        elseif(is_array($r)&&isset($r['key'])) $removes[]=$r['key'];
                    }
                } else {
                    foreach($rm_src as $k=>$v)
                        if($v===true||$v===1||$v==='1'||$v==='true') $removes[]=$k;
                }
            } else {
                foreach($opts as $k=>$v)
                    if(is_string($k)&&str_starts_with($k,'no_')&&($v===true||$v===1||$v==='1'||$v==='true'))
                        $removes[]=$k;
            }
            foreach($removes as $rem){
                $lbl=rl((string)$rem,$row['product_opts'],$known_removes);
                $removes_agg[$lbl]=($removes_agg[$lbl]??0)+1;
                $total_removes++;
            }

            /* ── Addons ── */
            $addons=[];
            $ad_src = $opts['addons'] ?? ($opts['addon'] ?? null); // "addons" first!
            if($ad_src!==null&&is_array($ad_src)){
                if(array_values($ad_src)===$ad_src){
                    foreach($ad_src as $a){
                        if(is_string($a)) $addons[]=['key'=>$a,'label'=>null,'price'=>null];
                        elseif(is_array($a)&&isset($a['key'])) $addons[]=$a;
                    }
                } else {
                    foreach($ad_src as $k=>$v)
                        if($v===true||$v===1||$v==='1'||$v==='true')
                            $addons[]=['key'=>$k,'label'=>null,'price'=>null];
                }
            } else {
                foreach($opts as $k=>$v)
                    if(is_string($k)&&str_starts_with($k,'add_')&&($v===true||$v===1||$v==='1'||$v==='true'))
                        $addons[]=['key'=>$k,'label'=>null,'price'=>null];
            }
            foreach($addons as $addon){
                $key=$addon['key']??''; if($key==='') continue;
                $info=al($key,$row['product_opts'],$known_addons);
                $lbl=!empty($addon['label'])?$addon['label']:$info['label'];
                $price=isset($addon['price'])&&$addon['price']!==null?(float)$addon['price']:$info['price'];
                if(!isset($addons_agg[$lbl])) $addons_agg[$lbl]=['count'=>0,'revenue'=>0.0,'price'=>$price];
                $addons_agg[$lbl]['count']++;
                $addons_agg[$lbl]['revenue']+=$price*(int)$row['quantity'];
                $total_addons++;
            }

            /* ── Spice ── */
            $spice_val=$opts['spice_level']??($opts['spice']??null);
            if($spice_val!==null&&$spice_val!==false&&(string)$spice_val!==''&&(string)$spice_val!=='null'){
                $pname=$row['product_name']??'Unknown';
                if(!isset($spice_agg[$pname])) $spice_agg[$pname]=['original'=>0,'mild'=>0,'spicy'=>0,'extra_spicy'=>0];
                $sl=strtolower(trim((string)$spice_val));
                if(in_array($sl,['original','normal','regular','none','no spice'])) $spice_agg[$pname]['original']++;
                elseif(in_array($sl,['mild','less spicy','less']))                  $spice_agg[$pname]['mild']++;
                elseif(in_array($sl,['extra spicy','extra_spicy','very spicy']))    $spice_agg[$pname]['extra_spicy']++;
                else                                                                 $spice_agg[$pname]['spicy']++;
            }
        }
    }
}

arsort($removes_agg);
uasort($addons_agg,fn($a,$b)=>$b['count']-$a['count']);
$top_removes=array_slice($removes_agg,0,10,true);
$top_addons =array_slice($addons_agg, 0,10,true);
$total_addon_rev=array_sum(array_column($addons_agg,'revenue'));
$rem_max=$top_removes?max(array_values($top_removes)):1;
$add_max=$top_addons ?max(array_column(array_values($top_addons),'count')):1;

/* ── Insights ── */
$insights=[];
if(!empty($removes_agg)){
    $tr=array_key_first($removes_agg);$trn=$removes_agg[$tr];
    $clean=trim(preg_replace('/[^\w\s]/u','',$tr));
    $insights[]=['icon'=>'💡','type'=>'Menu Tip','color'=>'blue',
        'title'=>'Consider a Variant Without '.trim($clean),
        'body'=>"<strong>".htmlspecialchars($tr)."</strong> was removed <strong>{$trn}×</strong>. Offering a dedicated variant could improve satisfaction and reduce kitchen errors."];
}
if(!empty($addons_agg)){
    $ta=array_key_first($addons_agg);$tad=$addons_agg[$ta];
    if($tad['price']>0){
        $extra=number_format($tad['count']*0.5,2);
        $insights[]=['icon'=>'💰','type'=>'Pricing','color'=>'green',
            'title'=>'Pricing Opportunity — '.htmlspecialchars($ta),
            'body'=>"Ordered <strong>{$tad['count']}×</strong>, generating <strong>RM ".number_format($tad['revenue'],2)."</strong>. A RM 0.50 price increase could earn an extra <strong>RM {$extra}</strong>."];
    }
}
if($total_addon_rev>0){
    $rr=mysqli_fetch_assoc(mysqli_query($connect,"SELECT SUM(oi.subtotal) AS t FROM order_items oi JOIN orders o ON oi.orderid=o.orderid WHERE 1=1 $date_sql"));
    $grand=(float)($rr['t']??0);
    if($grand>0){$ap=round($total_addon_rev/$grand*100,1);
        $insights[]=['icon'=>'📊','type'=>'Revenue','color'=>'purple',
            'title'=>"Add-ons Drive {$ap}% of Your Revenue",
            'body'=>"Add-on upsells generated <strong>RM ".number_format($total_addon_rev,2)."</strong> ({$ap}% of total). Keep promoting add-ons at checkout."];}
}
if(!empty($spice_agg)){
    $sp=0;$or=0;
    foreach($spice_agg as $s){$sp+=$s['spicy']+$s['extra_spicy'];$or+=$s['original'];}
    $all=$sp+$or+array_sum(array_column($spice_agg,'mild'));
    if($all>0){$spp=round($sp/$all*100);
        if($spp>=40) $insights[]=['icon'=>'🌶','type'=>'Spice','color'=>'red','title'=>'Your Customers Love Spice!',
            'body'=>"<strong>{$spp}%</strong> of spice picks were Spicy/Extra Spicy. Consider a dedicated spicy menu section."];
        elseif($spp<20) $insights[]=['icon'=>'🌿','type'=>'Spice','color'=>'green','title'=>'Original Flavour Dominates',
            'body'=>"Only <strong>{$spp}%</strong> go spicy — original flavour is the clear favourite."];}
}

$no_data=(!$has_opts_col||$total_orders_with_opts===0);
$chart_colors=['#d4650f','#f0874a','#22c55e','#3b82f6','#a855f7','#ec4899','#f59e0b','#14b8a6','#ef4444','#64748b'];
$show_debug=isset($_GET['debug']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingredient Analytics — FOODTIME Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        .filter-bar-wrap{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:20px;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 18px;}
        .filter-label{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
        .range-tabs{display:flex;gap:6px;flex-wrap:wrap;}
        .range-tab{padding:7px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--surface-2);color:var(--muted);font-size:12px;font-weight:700;text-decoration:none;transition:all .15s;white-space:nowrap;}
        .range-tab:hover{border-color:var(--accent);color:var(--accent);}
        .range-tab.active{background:var(--accent);border-color:var(--accent);color:#fff;}
        .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;}
        @media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr);}}
        .insights-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:24px;}
        @media(max-width:800px){.insights-grid{grid-template-columns:1fr;}}
        .insight-card{border-radius:12px;padding:16px 18px;display:flex;align-items:flex-start;gap:14px;border:1.5px solid;}
        .insight-card.blue{background:rgba(59,130,246,.07);border-color:rgba(59,130,246,.25);}
        .insight-card.green{background:rgba(34,197,94,.07);border-color:rgba(34,197,94,.25);}
        .insight-card.red{background:rgba(239,68,68,.07);border-color:rgba(239,68,68,.25);}
        .insight-card.purple{background:rgba(168,85,247,.07);border-color:rgba(168,85,247,.25);}
        .insight-icon{font-size:22px;flex-shrink:0;margin-top:1px;}
        .insight-type{display:inline-flex;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:800;background:var(--surface-3);color:var(--muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;}
        .insight-title{font-size:13px;font-weight:800;color:var(--text);margin-bottom:5px;}
        .insight-body{font-size:12px;color:var(--muted);line-height:1.6;}
        .insight-body strong{color:var(--text);}
        .charts-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
        @media(max-width:900px){.charts-row{grid-template-columns:1fr;}}
        .chart-panel{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px;}
        .chart-panel-title{font-size:14px;font-weight:800;color:var(--text);margin-bottom:18px;display:flex;align-items:center;gap:8px;}
        .chart-panel-title i{color:var(--accent);font-size:13px;}
        .cpt-sub{font-size:11px;color:var(--muted);font-weight:600;margin-left:auto;}
        .hbar-list{display:flex;flex-direction:column;gap:11px;}
        .hbar-top{display:flex;justify-content:space-between;margin-bottom:4px;font-size:12px;}
        .hbar-name{font-weight:700;color:var(--text);}
        .hbar-val{color:var(--muted);font-size:11px;font-weight:700;}
        .hbar-track{height:8px;background:var(--surface-3);border-radius:8px;overflow:hidden;}
        .hbar-fill{height:100%;border-radius:8px;transition:width .8s ease;}
        .section-head{display:flex;align-items:center;gap:10px;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border);}
        .section-head-icon{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;}
        .section-head-title{font-size:16px;font-weight:800;color:var(--text);}
        .section-head-sub{font-size:12px;color:var(--muted);margin-left:auto;font-weight:600;}
        .donut-wrap{position:relative;width:200px;height:200px;margin:0 auto 14px;}
        .donut-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;}
        .donut-val{font-size:22px;font-weight:900;color:var(--accent);line-height:1;}
        .donut-lbl{font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-top:3px;}
        .legend-wrap{display:flex;flex-direction:column;gap:6px;}
        .legend-item{display:flex;align-items:center;gap:8px;font-size:12px;}
        .legend-dot{width:9px;height:9px;border-radius:3px;flex-shrink:0;}
        .legend-name{flex:1;color:var(--text);font-weight:600;}
        .legend-val{font-weight:800;color:var(--accent);}
        .legend-pct{color:var(--muted);font-size:10px;min-width:32px;text-align:right;}
        .addon-table{width:100%;border-collapse:collapse;font-size:12px;}
        .addon-table th{text-align:left;font-size:10px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:0 10px 10px;border-bottom:1px solid var(--border);}
        .addon-table th:not(:first-child){text-align:right;}
        .addon-table td{padding:9px 10px;border-bottom:1px solid var(--border);vertical-align:middle;}
        .addon-table tr:last-child td{border-bottom:none;}
        .addon-table td:not(:first-child){text-align:right;}
        .price-badge{display:inline-flex;padding:2px 7px;border-radius:6px;background:var(--surface-3);font-size:10px;font-weight:800;color:var(--muted);}
        .free-badge{display:inline-flex;padding:2px 7px;border-radius:6px;background:rgba(34,197,94,.12);font-size:10px;font-weight:800;color:#22c55e;}
        .spice-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:20px;}
        .spice-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;}
        .spice-card-title{font-size:13px;font-weight:800;color:var(--text);margin-bottom:14px;display:flex;align-items:center;gap:8px;}
        .spice-bar-wrap{margin-bottom:10px;}
        .spice-bar-top{display:flex;justify-content:space-between;font-size:11px;margin-bottom:4px;}
        .spice-bar-lbl{font-weight:700;color:var(--text);}
        .spice-bar-pct{font-weight:800;}
        .spice-bar-track{height:7px;background:var(--surface-3);border-radius:7px;overflow:hidden;}
        .spice-bar-fill{height:100%;border-radius:7px;transition:width .8s ease;}
        .empty-panel{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:60px 20px;text-align:center;margin-bottom:20px;}
        .empty-panel i{font-size:40px;color:var(--muted);opacity:.4;display:block;margin-bottom:14px;}
        .empty-panel p{color:var(--muted);font-size:14px;}
        .empty-panel code{background:var(--surface-3);padding:2px 6px;border-radius:4px;font-size:12px;}
        .debug-panel{background:var(--surface-2);border:1.5px dashed var(--border);border-radius:12px;padding:16px;margin-bottom:20px;font-size:12px;}
        .debug-panel h4{margin:0 0 10px;font-size:13px;color:var(--muted);}
        .debug-panel pre{background:var(--surface-3);border-radius:8px;padding:10px;overflow-x:auto;font-size:11px;color:var(--text);margin:6px 0 0;}
        .debug-link{display:inline-flex;align-items:center;gap:5px;font-size:11px;color:var(--muted);text-decoration:none;padding:4px 10px;border:1px solid var(--border);border-radius:6px;margin-bottom:14px;}
        .debug-link:hover{color:var(--accent);border-color:var(--accent);}
    </style>
</head>
<body>
<?php include 'admin_sidebar.php'; ?>
<main class="main">
    <div class="topbar">
        <div class="topbar-title">Ingredient <span>Analytics</span></div>
        <div class="topbar-right"><div class="topbar-date"><i class="far fa-calendar-alt"></i> <?= date('l, d F Y') ?></div></div>
    </div>
    <div class="content">

        <!-- Filter -->
        <div class="filter-bar-wrap">
            <span class="filter-label"><i class="fas fa-calendar-alt"></i> Period</span>
            <div class="range-tabs">
                <?php foreach(['today'=>'Today','7days'=>'7 Days','30days'=>'30 Days','all'=>'All Time'] as $k=>$v): ?>
                <a href="?range=<?=$k?><?=$show_debug?'&debug=1':''?>" class="range-tab <?=$range===$k?'active':''?>"><?=$v?></a>
                <?php endforeach; ?>
            </div>
            <span style="margin-left:auto;font-size:11px;color:var(--muted);font-weight:700;">
                <?=number_format($total_orders_with_opts)?> orders with customisations · <?=$range_label?>
            </span>
        </div>

        <!-- Debug toggle -->
        <a href="?range=<?=$range?>&debug=<?=$show_debug?'0':'1'?>" class="debug-link">
            <i class="fas fa-bug"></i> <?=$show_debug?'Hide':'Show'?> Debug Info
        </a>

        <?php if($show_debug): ?>
        <div class="debug-panel">
            <h4>🔧 Debug Information</h4>
            <p><strong>Column detected:</strong> <?=$opts_col?"<code>{$opts_col}</code>":'<span style="color:red">Not found — add selected_options or custom_options to order_items</span>'?></p>
            <p><strong>Rows scanned:</strong> <?=$total_orders_with_opts?> &nbsp;|&nbsp; <strong>Removes found:</strong> <?=$total_removes?> &nbsp;|&nbsp; <strong>Addons found:</strong> <?=$total_addons?></p>
            <?php if($debug_sample): ?>
            <p><strong>Sample raw JSON from DB:</strong></p>
            <pre><?=htmlspecialchars(json_encode(json_decode($debug_sample['opts']),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre>
            <?php else: ?>
            <p style="color:#dc2626;"><strong>⚠ No non-null rows found in column.</strong> The column exists but all values are NULL/empty — check your checkout code is saving selected_options.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if(!$has_opts_col): ?>
        <div class="empty-panel">
            <i class="fas fa-database"></i>
            <p>No customisation column found in <code>order_items</code>.</p>
            <p style="font-size:12px;margin-top:8px;opacity:.7;">Expected: <code>selected_options</code> or <code>custom_options</code> (JSON column).</p>
        </div>

        <?php elseif($no_data): ?>
        <div class="empty-panel">
            <i class="fas fa-sliders-h"></i>
            <p>No customisation data for <strong><?=$range_label?></strong>.</p>
            <p style="font-size:12px;margin-top:8px;opacity:.7;">Column <code><?=htmlspecialchars($opts_col)?></code> found but no parseable data. Click <strong>Show Debug Info</strong> above to inspect a sample row and verify your JSON format.</p>
        </div>

        <?php else: ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card" style="background:var(--surface);border:1px solid var(--border);">
                <div class="sc-icon" style="background:rgba(212,101,15,.12);color:#d4650f;"><i class="fas fa-clipboard-list"></i></div>
                <div class="sc-val"><?=number_format($total_orders_with_opts)?></div>
                <div class="sc-label">Orders with Options</div>
            </div>
            <div class="stat-card stat-accent">
                <div class="sc-icon"><i class="fas fa-ban"></i></div>
                <div class="sc-val"><?=number_format($total_removes)?></div>
                <div class="sc-label">Total Removes</div>
            </div>
            <div class="stat-card stat-success">
                <div class="sc-icon"><i class="fas fa-plus-circle"></i></div>
                <div class="sc-val"><?=number_format($total_addons)?></div>
                <div class="sc-label">Total Add-ons</div>
            </div>
            <div class="stat-card stat-warning">
                <div class="sc-icon"><i class="fas fa-coins"></i></div>
                <div class="sc-val">RM <?=number_format($total_addon_rev,2)?></div>
                <div class="sc-label">Add-on Revenue</div>
            </div>
        </div>

        <!-- Insights -->
        <?php if(!empty($insights)): ?>
        <div class="section-head" style="margin-bottom:14px;">
            <div class="section-head-icon" style="background:rgba(245,158,11,.12);color:#f59e0b;">💡</div>
            <div class="section-head-title">Business Insights</div>
            <span class="section-head-sub"><?=count($insights)?> recommendation<?=count($insights)>1?'s':''?> · <?=$range_label?></span>
        </div>
        <div class="insights-grid">
            <?php foreach($insights as $ins): ?>
            <div class="insight-card <?=$ins['color']?>">
                <div class="insight-icon"><?=$ins['icon']?></div>
                <div>
                    <div class="insight-type"><?=htmlspecialchars($ins['type'])?></div>
                    <div class="insight-title"><?=htmlspecialchars($ins['title'])?></div>
                    <div class="insight-body"><?=$ins['body']?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Removes -->
        <div class="section-head">
            <div class="section-head-icon" style="background:rgba(239,68,68,.12);color:#ef4444;">🚫</div>
            <div class="section-head-title">Most Removed Ingredients</div>
            <span class="section-head-sub"><?=number_format($total_removes)?> total removes</span>
        </div>
        <?php if(!empty($top_removes)): ?>
        <div class="charts-row">
            <div class="chart-panel">
                <div class="chart-panel-title"><i class="fas fa-ban"></i> Removal Frequency<span class="cpt-sub">Top <?=count($top_removes)?></span></div>
                <div class="hbar-list">
                    <?php $ri=0;foreach($top_removes as $lbl=>$cnt): $pct=$rem_max>0?round($cnt/$rem_max*100):0; ?>
                    <div>
                        <div class="hbar-top"><span class="hbar-name"><?=htmlspecialchars($lbl)?></span><span class="hbar-val"><?=number_format($cnt)?>×</span></div>
                        <div class="hbar-track"><div class="hbar-fill" style="width:<?=$pct?>%;background:<?=$chart_colors[$ri%10]?>;"></div></div>
                    </div>
                    <?php $ri++;endforeach; ?>
                </div>
            </div>
            <div class="chart-panel">
                <div class="chart-panel-title"><i class="fas fa-chart-pie"></i> Removes Breakdown<span class="cpt-sub">Top <?=min(6,count($top_removes))?></span></div>
                <?php $rem6=array_slice($top_removes,0,6,true); ?>
                <div class="donut-wrap">
                    <canvas id="donutRemoves"></canvas>
                    <div class="donut-center"><div class="donut-val"><?=number_format($total_removes)?></div><div class="donut-lbl">Removes</div></div>
                </div>
                <div class="legend-wrap">
                    <?php $li=0;foreach($rem6 as $lbl=>$cnt): $pct=$total_removes>0?round($cnt/$total_removes*100,1):0; ?>
                    <div class="legend-item">
                        <div class="legend-dot" style="background:<?=$chart_colors[$li%10]?>"></div>
                        <span class="legend-name"><?=htmlspecialchars(mb_substr($lbl,0,22))?></span>
                        <span class="legend-val"><?=number_format($cnt)?></span>
                        <span class="legend-pct"><?=$pct?>%</span>
                    </div>
                    <?php $li++;endforeach; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="chart-panel" style="margin-bottom:20px;text-align:center;padding:30px;">
            <p style="color:var(--muted);font-size:13px;">No removal requests for <strong><?=$range_label?></strong>.</p>
        </div>
        <?php endif; ?>

        <!-- Addons -->
        <div class="section-head">
            <div class="section-head-icon" style="background:rgba(34,197,94,.12);color:#22c55e;">➕</div>
            <div class="section-head-title">Add-on Performance</div>
            <span class="section-head-sub">Total add-on revenue: RM <?=number_format($total_addon_rev,2)?></span>
        </div>
        <?php if(!empty($top_addons)): ?>
        <div class="charts-row" style="margin-bottom:20px;">
            <div class="chart-panel">
                <div class="chart-panel-title"><i class="fas fa-plus-circle"></i> Most Popular Add-ons<span class="cpt-sub">By orders</span></div>
                <div class="hbar-list">
                    <?php $ai=0;foreach($top_addons as $lbl=>$d): $pct=$add_max>0?round($d['count']/$add_max*100):0; ?>
                    <div>
                        <div class="hbar-top">
                            <span class="hbar-name"><?=htmlspecialchars($lbl)?></span>
                            <span class="hbar-val"><?=number_format($d['count'])?>×
                                <?php if($d['revenue']>0): ?> · <span style="color:#22c55e;font-weight:800;">+RM <?=number_format($d['revenue'],2)?></span>
                                <?php else: ?> · <span style="color:var(--muted);">Free</span><?php endif; ?>
                            </span>
                        </div>
                        <div class="hbar-track"><div class="hbar-fill" style="width:<?=$pct?>%;background:<?=$chart_colors[$ai%10]?>;"></div></div>
                    </div>
                    <?php $ai++;endforeach; ?>
                </div>
            </div>
            <div class="chart-panel">
                <div class="chart-panel-title"><i class="fas fa-coins"></i> Add-on Revenue Split<span class="cpt-sub">RM generated</span></div>
                <?php if($total_addon_rev>0): $add6=array_slice($top_addons,0,6,true); ?>
                <div class="donut-wrap">
                    <canvas id="donutAddons"></canvas>
                    <div class="donut-center"><div class="donut-val" style="font-size:15px;">RM <?=number_format($total_addon_rev,0)?></div><div class="donut-lbl">Add-on Rev</div></div>
                </div>
                <div class="legend-wrap">
                    <?php $li=0;foreach($add6 as $lbl=>$d): $pct=$total_addon_rev>0?round($d['revenue']/$total_addon_rev*100,1):0; ?>
                    <div class="legend-item">
                        <div class="legend-dot" style="background:<?=$chart_colors[$li%10]?>"></div>
                        <span class="legend-name"><?=htmlspecialchars(mb_substr($lbl,0,20))?></span>
                        <span class="legend-val" style="color:#22c55e;">RM <?=number_format($d['revenue'],2)?></span>
                        <span class="legend-pct"><?=$pct?>%</span>
                    </div>
                    <?php $li++;endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color:var(--muted);font-size:13px;text-align:center;margin-top:40px;">All add-ons are free — no revenue to display.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Full addon table -->
        <div class="panel" style="margin-bottom:24px;">
            <div class="panel-header"><div class="panel-title"><i class="fas fa-table"></i> Full Add-on Details</div></div>
            <div class="table-wrap">
                <table class="addon-table">
                    <thead><tr><th>Add-on</th><th>Unit Price</th><th>Times Ordered</th><th>Revenue Generated</th><th>Revenue Share</th></tr></thead>
                    <tbody>
                    <?php foreach($addons_agg as $lbl=>$d): $pct=$total_addon_rev>0?round($d['revenue']/$total_addon_rev*100,1):0; ?>
                    <tr>
                        <td><span style="font-weight:700;color:var(--text);font-size:13px;"><?=htmlspecialchars($lbl)?></span></td>
                        <td><?php if($d['price']>0): ?><span class="price-badge">RM <?=number_format($d['price'],2)?></span><?php else: ?><span class="free-badge">Free</span><?php endif; ?></td>
                        <td><span style="font-weight:800;color:var(--accent);"><?=number_format($d['count'])?>×</span></td>
                        <td><span style="font-weight:800;color:#22c55e;">RM <?=number_format($d['revenue'],2)?></span></td>
                        <td><span style="font-size:11px;font-weight:700;color:var(--muted);"><?=$pct?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="chart-panel" style="margin-bottom:20px;text-align:center;padding:30px;">
            <p style="color:var(--muted);font-size:13px;">No add-ons ordered for <strong><?=$range_label?></strong>.</p>
        </div>
        <?php endif; ?>

        <!-- Spice -->
        <?php if(!empty($spice_agg)): ?>
        <div class="section-head">
            <div class="section-head-icon" style="background:rgba(239,68,68,.12);color:#ef4444;">🌶</div>
            <div class="section-head-title">Spice Level Preferences</div>
            <span class="section-head-sub">Per product</span>
        </div>
        <div class="spice-grid">
            <?php foreach($spice_agg as $pname=>$s):
                $tot=array_sum($s); if($tot===0) continue;
                $bars=['Original'=>['v'=>$s['original'],'c'=>'#22c55e'],'Mild'=>['v'=>$s['mild'],'c'=>'#f59e0b'],
                       'Spicy'=>['v'=>$s['spicy'],'c'=>'#ef4444'],'Extra 🔥'=>['v'=>$s['extra_spicy'],'c'=>'#7c3aed']]; ?>
            <div class="spice-card">
                <div class="spice-card-title">🌶 <?=htmlspecialchars(mb_substr($pname,0,26))?><span style="margin-left:auto;font-size:10px;color:var(--muted);font-weight:700;"><?=$tot?> orders</span></div>
                <?php foreach($bars as $bl=>$bd): if($bd['v']===0) continue; $bp=round($bd['v']/$tot*100); ?>
                <div class="spice-bar-wrap">
                    <div class="spice-bar-top"><span class="spice-bar-lbl"><?=$bl?></span><span class="spice-bar-pct" style="color:<?=$bd['c']?>;"><?=$bp?>% (<?=$bd['v']?>)</span></div>
                    <div class="spice-bar-track"><div class="spice-bar-fill" style="width:<?=$bp?>%;background:<?=$bd['c']?>;"></div></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</main>
<script>
const CC=<?=json_encode($chart_colors)?>;
<?php if(!empty($top_removes)): ?>
new Chart(document.getElementById('donutRemoves'),{type:'doughnut',data:{labels:<?=json_encode(array_keys(array_slice($top_removes,0,6,true)))?>,datasets:[{data:<?=json_encode(array_values(array_slice($top_removes,0,6)))?>,backgroundColor:CC,borderWidth:3,borderColor:'#141416',hoverOffset:7}]},options:{cutout:'65%',plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>` ${ctx.label}: ${ctx.parsed}×`}}},animation:{animateRotate:true,duration:900}}});
<?php endif; ?>
<?php if(!empty($top_addons)&&$total_addon_rev>0): ?>
new Chart(document.getElementById('donutAddons'),{type:'doughnut',data:{labels:<?=json_encode(array_keys(array_slice($top_addons,0,6,true)))?>,datasets:[{data:<?=json_encode(array_map(fn($d)=>round($d['revenue'],2),array_slice(array_values($top_addons),0,6)))?>,backgroundColor:CC,borderWidth:3,borderColor:'#141416',hoverOffset:7}]},options:{cutout:'65%',plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>` ${ctx.label}: RM ${ctx.parsed.toFixed(2)}`}}},animation:{animateRotate:true,duration:900}}});
<?php endif; ?>
</script>
</body>
</html>