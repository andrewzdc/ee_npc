<?php

namespace EENPC;

$military_list = ['m_tr','m_j','m_tu','m_ta'];

function play_indy_strat($server)
{
    global $cnum;
    global $cpref;
    out("Playing ".INDY." Turns for #$cnum ".siteURL($cnum));
    //$main = get_main();     //get the basic stats
    //out_data($main);          //output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    $c->setIndyFromMarket(true); //CHECK DPA
    //$c = get_advisor();     //c as in country! (get the advisor)
    out("Indy: {$c->pt_indy}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    //out_data($c) && exit;             //ouput the advisor data
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 5:
                Government::change($c, 'I');
                break;
            case $rand < 10:
                Government::change($c, 'D');
                break;
            case $rand < 15:
                Government::change($c, 'D');
                break;
            default:
                Government::change($c, 'C');
                break;
        }
    }

    out($c->turns.' turns left');
    out('Explore Rate: '.$c->explore_rate.'; Min Rate: '.$c->explore_min);
    //$pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);       //output the PM info
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);       //output the PM info

    if ($c->m_spy > 10000) {
        Allies::fill('spy');
    }

    if (!isset($cpref->target_land) || $cpref->target_land == null) {
      $cpref->target_land = Math::purebell(10000, 30000, 5000);
      save_cpref($cnum,$cpref);
      out('Setting target acreage for #'.$cnum.' to '.$cpref->target_land);
    }

    $owned_on_market_info = get_owned_on_market_info(); //find out what we have on the market
    //out_data($owned_on_market_info);  //output the Owned on Public Market info

    while ($c->turns > 0) {

    out("Target BPT: ".$c->target_bpt());
    out("BPT: ".$c->bpt);
    out("BuildCS: ".($c->shouldBuildCS()));
    out("Farms: ".$c->b_farm);
    out("FullBPT: ".$c->shouldBuildFullBPT());
    out("Explore: ".$c->shouldExplore());
    out("Built: ".$c->built()."~~");

        $result = play_indy_turn($c);

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
        
        $hold = $hold || money_management($c) || food_management($c);

        if ($hold) { break; }

        //market actions
        if (turns_of_food($c) > 10 && turns_of_money($c) > 10 && $c->money > $c->bpt * $c->build_cost * 5 && ($c->built() > 80
            || $c->money > $c->fullBuildCost() - $c->runCash())
        ) {
            buy_indy_goals($c, $c->money - $c->fullBuildCost() - $c->runCash());
        }

    }

    $c->countryStats(INDY, indyGoals($c));

    return $c;
}//end play_indy_strat()

function play_indy_turn(&$c)
{
 //c as in country!

    $target_bpt = 60;
    $target_land = 10000;
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
	            Build::indy($c);
	        }
	        if ($c->built() > 50) {
        	    explore($c);
	        }	

		sell_all_military($c,1);

	return true;	 

    }

    //**OUT OF PROTECTION**//
    if ($c->protection == 0) { 

	    if (total_cansell_military($c) > 7500 && $c->turns == 1) {
	        return sell_max_military($c);
	    }

	//*****GET TO BPT TARGET**********//
	if ($c->bpt < $target_bpt) {
		
		out("Turns Played: ".$c->turns_played);
		out("Turns Played div 12: ".$c->turns_played % 12);
	        if ($c->turns_played % 12 < 10) {
		    out("HEHAHEHA!");
        	    return Build::cs(1);
	        }
	        
		else {
			if ($c->shouldBuildFullBPT()) {
		            Build::indy($c);
		        }
		        elseif ($c->shouldBuildFullBPT() == 0) {
		            Build::cs(1);
		        }
		        if ($c->shouldExplore()) {
        		    explore($c);
		        }
		}	

	}

	elseif ($c->land < $target_land) {

		if ($c->money < $c->income + $c->bpt * $c->build_cost * 1.5 && turns_of_money($c) && turns_of_food($c)) {
			cash($c);
			if (total_cansell_military($c) > 7500) { 
				sell_max_military($c);
				return;
		        }
		}

		if ($c->shouldBuildFullBPT()) {
		      return Build::indy($c);
		}

		if ($c->shouldExplore()) {
		      return explore($c);
		}


	}


	//*****STOCK!!!**********//
	else {

	}

    }

    if ($c->protection == 0 && total_cansell_military($c) > 7500 && $c->turns == 1
    ) {
        return sell_max_military($c);
    } elseif ($c->shouldBuildCS()) {
      return Build::cs();
    } elseif ($c->shouldBuildFullBPT()) {
      return Build::indy($c);
    } elseif ($c->shouldExplore()) {
      return explore($c);
    } elseif ($c->money < $c->income + $c->bpt * $c->build_cost * 1.5 && turns_of_money($c) && turns_of_food($c)) {
	cash($c);
	if ($c->protection == 0 && total_cansell_military($c) > 7500) { 
		sell_max_military($c);
		return;
        }
    } elseif (onmarket_value($c) == 0 && $c->built() < 75) {
        return sell_all_military($c,0.25) ?? cash($c);
    } elseif (turns_of_money($c) && turns_of_food($c)) {
      return cash($c);
    }
}//end play_indy_turn()

function sellmilitarytime(&$c)
{
    global $military_list;
    $sum = $om = 0;
    foreach ($military_list as $mil) {
        $sum += $c->$mil;
        $om  += onmarket($mil, $c);
    }
    if ($om < $sum / 6) {
        return true;
    }

    return false;
}//end sellmilitarytime()


function buy_indy_goals(&$c, $spend = null)
{
    $goals = indyGoals($c);

    Country::countryGoals($c, $goals, $spend);
}//end buy_indy_goals()


function indyGoals(&$c)
{
    return [
        //what, goal, priority
        ['t_indy',158,10],
        ['t_bus',160,4],
        ['t_res',160,4],
        ['t_mil',94,3],
    ];
}//end indyGoals()
