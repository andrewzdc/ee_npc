<?php

namespace EENPC;

$techlist = ['t_mil','t_med','t_bus','t_res','t_agri','t_war','t_ms','t_weap','t_indy','t_spy','t_sdi'];

function play_techer_strat($server)
{
    global $cnum;
    global $cpref;
    out("Playing ".TECHER." Turns for #$cnum ".siteURL($cnum));
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    $c->setIndy('pro_spy');

    out("Land Goal: ".$c->target_land());


    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    if ($c->b_lab > 2000) {
        Allies::fill('res');
    }

    if (!isset($cpref->target_land) || $cpref->target_land == null) {
      $cpref->target_land = Math::purebell(5000, 13000, 4000);
      save_cpref($cnum,$cpref);
      out('Setting target acreage for #'.$cnum.' to '.$cpref->target_land);
    }

    out($c->turns.' turns left');
    out('Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);

    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 40:
                Government::change($c, 'H');
                break;
            case $rand < 80:
                Government::change($c, 'D');
                break;
            default:
                Government::change($c, 'T');
                break;
        }
    }

    //out_data($c);             //ouput the advisor data
    //$pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info); //output the owned on market info


    while ($c->turns > 0) {
        //$result = PublicMarket::buy($c,array('m_bu'=>100),array('m_bu'=>400));
        $result = play_techer_turn($c);

        if ($result === false) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }

        if ($result === null) {
          $hold = true;
        } else {
          update_c($c, $result);
          $hold = false;
        }

        $c = get_advisor();
        $c->updateMain();

        $hold = $hold || money_management($c);
        $hold = $hold || food_management($c);

        if ($hold) { break; }

        //market actions

        if (turns_of_food($c) > 50 && turns_of_money($c) > 50 && $c->money > 3500 * 500 && ($c->built() > 80 || $c->money > $c->fullBuildCost() - $c->runCash()) && $c->tpt > 200) { // 40 turns of food
            buy_techer_goals($c, $c->money - $c->fullBuildCost() - $c->runCash()); //keep enough money to build out everything
        }
    }
    //buy_cheap_military($c,1500000000,200);
    //buy_cheap_military($c);

    $c->countryStats(TECHER, techerGoals($c));

    return $c;
}//end play_techer_strat()


function play_techer_turn(&$c)
{

    global $turnsleep, $mktinfo, $server_avg_land;
    $mktinfo = null;
    usleep($turnsleep);

    if ($c->protection == 1) {
      sell_all_military($c,1);
      if (turns_of_food($c) > 10) { sell_all_food($c); }
    }

    if ($c->land > $c->target_land()) {
	return run_turns_to_stock($c, 'T');
    }

    if ($c->protection == 0 && $c->turns == 1 && total_cansell_tech($c) > 20) {
        //if one turn left and out of protection, sell at least 2 turns worth of tech
        return sell_max_tech($c);
    } elseif ($c->protection == 1)  {
        if ($c->turns_played % 3 == 0) {
            Build::cs(4);
        }
        if ($c->turns_played % 3 != 0) {
            Build::farmer($c);
        }
        if ($c->built() > 50) {
            explore($c);
        }
        return true;
    } elseif ($c->shouldBuildCS(0.8)) { //target 80% of turns on cs rather than default 50
      return Build::cs();
    } elseif ($c->shouldBuildFullBPT()) {
      return $c->protection ? Build::farmer($c) : Build::techer($c);
    } elseif ($c->shouldExplore())  {
      return explore($c);
    } elseif ($c->turns < 11 && $c->money < $c->income + $c->bpt * $c->build_cost * 10) {
         tech($c, $c->turns - 1);
	 sell_max_tech($c);
	 return null;
//   } elseif (onmarket_value($c) == 0 && $c->built() < 75) {
//      return tech($c, 1);
    } else {
      return tech($c, max(1, min(turns_of_money($c), turns_of_food($c), 13, $c->turns + 2) - 3));
    }

}//end play_techer_turn()



function selltechtime(&$c)
{
    global $techlist;
    $sum = $om = 0;
    foreach ($techlist as $tech) {
        $sum += $c->$tech;
        $om  += $c->onMarket($tech);
    }
    if ($om < $sum / 6) {
        return true;
    }

    return false;
}//end selltechtime()


function sell_max_tech(&$c)
{
    $c = get_advisor();     //UPDATE EVERYTHING
    $c->updateOnMarket();

    //$market_info = get_market_info();   //get the Public Market info
    //global $market;

    $quantity = [
        'mil' => can_sell_tech($c, 't_mil'),
        'med' => 0, //can_sell_tech($c, 't_med'),
        'bus' => can_sell_tech($c, 't_bus'),
        'res' => can_sell_tech($c, 't_res'),
        'agri' => can_sell_tech($c, 't_agri'),
        'war' => 0, //can_sell_tech($c, 't_war'),
        'ms' => 0, //can_sell_tech($c, 't_ms'),
        'weap' => 0, //can_sell_tech($c, 't_weap'),
        'indy' => can_sell_tech($c, 't_indy'),
        'spy' => 0, //can_sell_tech($c, 't_spy'),
        'sdi' => 0 //can_sell_tech($c, 't_sdi')
    ];

    if (array_sum($quantity) == 0) {
        out('Techer computing Zero Sell!');
        $c = get_advisor();
        $c->updateOnMarket();

        Debug::on();
        Debug::msg('This Quantity: '.array_sum($quantity).' TotalCanSellTech: '.total_cansell_tech($c));
        return;
    }


    $nogoods_high   = 7500;
    $nogoods_low    = 2000;
    $nogoods_stddev = 1500;
    $nogoods_step   = 1;
    $rmax           = 1.20; //percent
    $rmin           = 0.90; //percent
    $rstep          = 0.01;
    $rstddev        = 0.10;
    $price          = [];
    foreach ($quantity as $key => $q) {
        if ($q == 0) {
            $price[$key] = 0;
        } elseif (PublicMarket::price($key) != null) {
            // additional check to make sure we aren't repeatedly undercutting with minimal goods
            if ($q < 100 && PublicMarket::available($key) < 1000) {
                $price[$key] = PublicMarket::price($key);
            } else {
                Debug::msg("sell_max_tech:A:$key");
                $max = $c->goodsStuck($key) ? 0.98 : $rmax; //undercut if we have goods stuck
                Debug::msg("sell_max_tech:B:$key");

                $price[$key] = min(
                    7500,
                    floor(PublicMarket::price($key) * Math::purebell($rmin, $max, $rstddev, $rstep))
                );

                Debug::msg("sell_max_tech:C:$key");
            }
        } else {
            $price[$key] = floor(Math::purebell($nogoods_low, $nogoods_high, $nogoods_stddev, $nogoods_step));
        }
    }

    $result = PublicMarket::sell($c, $quantity, $price);
    if ($result == 'QUANTITY_MORE_THAN_CAN_SELL') {
        out("TRIED TO SELL MORE THAN WE CAN!?!");
        $c = get_advisor();     //UPDATE EVERYTHING
    }

    return $result;
}//end sell_max_tech()

function buy_techer_goals(&$c, $spend = null)
{
    $goals = techerGoals($c);
    Country::countryGoals($c, $goals, $spend);
}//end buy_techer_goals()


function techerGoals(&$c)
{
    return [
        //what, goal, priority
        ['dpa', $c->defPerAcreTarget(1.0), 1],
        ['nlg', $c->nlgTarget(),1 ],
    ];
}//end techerGoals()
