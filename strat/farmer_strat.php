<?php
/**
 * Farmer strategy
 *
 * PHP Version 7
 *
 * @category Strat
 *
 * @package EENPC
 *
 * @author Julian Haagsma <jhaagsma@gmail.com>
 *
 * @license All files licensed under the MIT license.
 *
 * @link https://github.com/jhaagsma/ee_npc
 */

namespace EENPC;

/**
 * Play the farmer strat
 *
 * @param  ?? $server Contains the server information
 *
 * @return null
 */
function play_farmer_strat($server)
{
    global $cnum;
    global $cpref;
    out("Playing ".FARMER." turns for #$cnum ".siteURL($cnum));
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    $c->setIndy('pro_spy');
    //$c = get_advisor();     //c as in country! (get the advisor)


    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    if (!isset($cpref->target_land) || $cpref->target_land == null) {
      $cpref->target_land = Math::purebell(20000, 40000, 5000);
      save_cpref($cnum,$cpref);
      out('Setting target acreage for #'.$cnum.' to '.$cpref->target_land);
    }

    out("Agri: {$c->pt_agri}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    //out_data($c) && exit;             //ouput the advisor data
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 12:
                Government::change($c, 'D');
                break;
            case $rand < 20:
                Government::change($c, 'I');
                break;
            case $rand < 50:
                Government::change($c, 'T');
                break;
            default:
                Government::change($c, 'F');
                break;
        }
    }


    out($c->turns.' turns left');
    out('Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    while ($c->turns > 0) {

        $result = play_farmer_turn($c);

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
        $c->updateMain(); //we probably don't need to do this *EVERY* turn

        $hold = $hold || money_management($c);
        $hold = $hold || food_management($c);

        if ($hold) { break; }

        //market actions
        if ($c->income < 0 && $c->money < -5 * $c->income) { //sell 1/4 of all military on PM
            out("Almost out of money! Sell 10 turns of income in food!");   //Text for screen

            //sell 1/4 of our military
            $pm_info = PrivateMarket::getRecent();
            PrivateMarket::sell($c, ['m_bu' => min($c->food, floor(-10 * $c->income / $pm_info->sell_price->m_bu))]);
        }

        // 40 turns of food
        if (turns_of_food($c) > 50
            && turns_of_money($c) > 50
            && $c->money > 3500 * 500
            && ($c->built() > 80 || $c->money > $c->fullBuildCost())
        ) {
            $spend = $c->money - $c->fullBuildCost(); //keep enough money to build out everything

            if ($spend > abs($c->income) * 5) {
                //try to batch a little bit...
                buy_farmer_goals($c, $spend/100);
            }
        }
        buy_cheap_military($c,1500000000,200);
        buy_cheap_military($c);
    }

    $c->countryStats(FARMER, farmerGoals($c));
    return $c;
}//end play_farmer_strat()

function play_farmer_turn(&$c)
{
 //c as in country!

    $target_bpt = 60;
    $target_land = 9000;
    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');

    //*****START UP STRATEGY**********//
    if ($c->protection == 1) { 

		sell_all_military($c,1);

	        if ($c->turns_played % 6 < 4) {
        	    Build::cs();
	        }
	        elseif ($c->turns_played % 6 > 3) {
	            Build::farmer($c);
	        }
	        if ($c->built() > 50) {
        	    explore($c);
	        }	

     		 if (turnsoffood($c) > 5) { sell_all_food($c); }

	return true;	 

    }

    //**OUT OF PROTECTION**//
    if ($c->protection == 0) { 

	    if ($c->food > $c->foodnet * 10 && $c->turns == 1) {
		return sellextrafood($c);
	    }

	//*****GET TO BPT TARGET**********//
	if ($c->bpt < $target_bpt) {
		return run_turns_to_target_bpt($c, 'F');
	}

	//*****GET TO LAND TARGET**********//
	elseif ($c->land < $target_land) {
		return run_turns_to_target_land($c, 'F');
	}


	//*****STOCK!!!**********//
	else {
		return run_turns_to_stock($c, 'F');
	}

    }


    if ($c->protection == 1) {
      sell_all_military($c,1);
      if (turnsoffood($c) > 10) { sell_all_food($c); }
    }

    if ($c->protection == 0 && $c->food > 7000
        && (
            $c->foodnet > 0 && $c->foodnet > 3 * $c->foodcon && $c->food > 30 * $c->foodnet
            || $c->turns == 1
        )
    ) { //Don't sell less than 30 turns of food unless you're on your last turn (and desperate?)
        return sellextrafood($c);
    } elseif ($c->shouldBuildCS()) {
        return Build::cs();
    } elseif ($c->shouldBuildFullBPT()) {
      return Build::farmer($c);
    } elseif ($c->shouldExplore())  {
      return explore($c);
    } elseif (turns_of_money($c) && turns_of_food($c)) {
      return cash($c);
    }
}//end play_farmer_turn()

function buy_farmer_goals(&$c, $spend = null)
{
    $goals = farmerGoals($c);

    Country::countryGoals($c, $goals, $spend);
}//end buy_farmer_goals()


function farmerGoals(&$c)
{
    return [
        //what, goal, priority
        ['t_agri',227,10],
        ['t_bus',174,2],
        ['t_res',174,2],
        ['t_mil',95,2],
        ['nlg',$c->nlgTarget(),5],
        ['dpa',$c->defPerAcreTarget(1.0),5],
        ['food', 1000000000, 5],
    ];
}//end farmerGoals()
