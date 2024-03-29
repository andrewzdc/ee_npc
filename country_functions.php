<?php

namespace EENPC;



function run_turns_in_protection(&$c, $strat = 'R', $fraction_cs = 0.7)
{
    $c->updateMain();

	sell_all_military($c,1);

	if ($c->turns_played % 10 < $fraction_cs * 10) {
		Build::cs();
	}
	elseif ($c->turns_played % 10 >= $fraction_cs * 10) {
		switch($strat) {
			case 'F': out(Build::farmer($c)); break;
			case 'T': out(Build::farmer($c)); break;
			case 'C': out(Build::casher($c)); break;
			case 'I': out(Build::indy($c)); break;
			case 'O': out(Build::farmer($c)); break;
			case 'R': out(Build::farmer($c)); break;
		}
	
	}
	if ($c->built() > 50) {
		out(explore($c));
	}	

	if (turnsoffood($c) > 5) { out(sell_all_food($c)); }


}//end run_turns_in_protection()


function run_turns_to_target_bpt($c, $strat = 'R', $fraction_cs = 0.8)
{
    $c->updateMain();


		out("Turns Played: ".$c->turns_played);
		out("Turns Played div 12: ".$c->turns_played % 12);
		out("Empty: ".$c->empty);
		out("Money1: ".$c->money);

		//Before running turns, buy a little tech or goods
		switch($strat) {
			case 'F': out(buy_farmer_goals($c, $c->money / 10)); break;
			case 'T': break;
			case 'C': out(buy_casher_goals($c, $c->money / 10)); break;
			case 'I': out(buy_indy_goals($c, $c->money / 10)); break;
			case 'O': out(buy_oiler_goals($c, $c->money / 10)); break;
			case 'R':  break;
		}
		

		if ($c->empty == 0 && $c->shouldExplore()) {
        	    explore($c);
	 	}
	        if ($c->turns_played % 12 < $fraction_cs * 12) {
        	    return Build::cs();
	        }
	        
		else {

			if ($c->shouldBuildFullBPT()) {
				switch($strat) {
					case 'F': out(Build::farmer($c)); break;
					case 'T': out(Build::techer($c)); break;
					case 'C': out(Build::casher($c)); break;
					case 'I': out(Build::indy($c)); break;
					case 'O': out(Build::oiler($c)); break;
					case 'R': out(Build::rainbow($c)); break;
				}
		        }
		        elseif ($c->shouldBuildFullBPT() == 0) {
		            Build::cs();
		        }
			if ($c->shouldExplore()) {
        		    explore($c);
	 		}
		}	

}//end run_turns_to_target_bpt()


function run_turns_to_target_land($c, $strat = 'R')
{
    $c->updateMain();


	out("Money2: ".$c->money);

	//***BUY TECH GOALS***//
	switch($strat) {
		case 'F': out(buy_farmer_goals($c, $c->money / 15)); break;
		case 'C': out(buy_farmer_goals($c, $c->money / 15)); break;
		case 'I': out(buy_farmer_goals($c, $c->money / 15)); break;
		case 'O': out(buy_farmer_goals($c, $c->money / 15)); break;
	}
	

		if ($c->money < $c->income + $c->bpt * $c->build_cost * 1.5 && turns_of_money($c) && turns_of_food($c)) {
			cash($c);
			if ($c->foodnet > 0 && $c->foodnet > 3 * $c->foodcon && $c->food > 30 * $c->foodnet) { 
				sellextrafood($c);
				return;
		        }
		}

	//***BUILD IF YOU NEED TO***//
	if ($c->shouldBuildFullBPT()) {
		switch($strat) {
			case 'F': return Build::farmer($c); break;
			case 'T': return Build::techer($c); break;
			case 'C': return Build::casher($c); break;
			case 'I': return Build::indy($c); break;
			case 'O': return Build::oiler($c); break;
			case 'R': return Build::rainbow($c); break;
		}
	}

	//***EXPLORE WHEN FULLY BUILT or OTHERWISE***//
	if ($c->shouldExplore()) {
	      return explore($c);
	}




}//end run_turns_to_target_land()



function run_turns_to_stock($c, $strat = 'R')
{

    $c->updateMain();
    $curr_money = $c->money;

    //***DO THINGS ON MARKET***//

    //Sell stock that returned from market if not a farmer
    if($strat != 'F' && $c->food > $c->foodnet * -100) {
			out("PRICING FOOD HIGH!! ");
			out(sellextrafood($c, 0.900, 1));
			$c->updateMain();
    } 	



		if ($strat != 'F' && $c->turns == 2 && $c->food > $c->foodnet * -3 && $c->money > 100000000) {

			out("STOCKING!! ");
			while ($curr_money > 50000000) {
				$buy_amt = min(PublicMarket::available('m_bu'), ($c->money - 50000000 + 10000) / PublicMarket::price('m_bu'));
				$spend_amt = PublicMarket::price('m_bu') * $buy_amt;
				out(PublicMarket::buy($c, ['m_bu' => $buy_amt], ['m_bu' => PublicMarket::price('m_bu')]));
				$c->updateMain();
				$curr_money = $c->money;
			}
			$foodtosell = $c->food - $c->foodnet * -3;
			out("Food to sell: ".$foodtosell);
			out("Food on hand: ".$c->food);
			out("Food net: ".$c->foodnet);
			out(sellextrafood($c, round($foodtosell / $c->food, 2), 1));
			return sell_max_tech($c);
		}

    
    //Sell food every 10 turns if a farmer
    if($strat == 'F' && $c->food > $c->foodnet * 10) {
			out("STOCKING!! ");
			out(sellextrafood($c, 1, 1));
		}


    //***PLAY A TURN***//
    switch($strat) {

	case 'F': return cash($c, min(10, max(1, $c->turns -2))); break;
	case 'T': return tech($c, max(1, $c->turns - 2)); break;
	case 'C': return cash($c); break;
	case 'I': return cash($c); break;
	case 'O': return cash($c); break;
	case 'R': return tech($c, 1); break;
    }



}//end run_turns_to_stock()


function buy_cheap_military(&$c, $max = 1000000000, $dpnw = 175) {
  $c = get_advisor();
  if ($c->money < $max) { return; }
  $prev = $c->money;
  $surplus = $c->money - $max;
  while ($surplus < $prev) {
    $prev = $surplus;
    out('Looking for bargains ('.engnot($surplus).' to spend)...');
    buy_public_below_dpnw($c, $dpnw, $surplus);
  }
}


function destock($server, $cnum)
{
    $c = get_advisor();     //c as in country! (get the advisor)
    out("Destocking #$cnum!");  //Text for screen

    if ($c->food > 0) {
        PrivateMarket::sell($c, ['m_bu' => $c->food]);   //Sell 'em
    }

    $dpnw  = 200;
    $first = true;
    $prev  = $c->money;

    while ($c->money > 1000 && $dpnw < 2500) {
        if ($c->money != $prev) {
            $first = true;
        }

        $prev = $c->money;
        out(
            "Try to buy goods at $dpnw dpnw or below!".($first ? null : str_pad("\r", 65, ' ', STR_PAD_LEFT)),
            $first
        );    //Text for screen
        buy_public_below_dpnw($c, $dpnw);
        buy_private_below_dpnw($c, $dpnw);
        $dpnw += 4;
        if ($dpnw > 500) {
            $dpnw += 50;
        }
        $first = false;
    }
    if ($c->money <= 1000) {
        out("Done Destocking!");    //Text for screen
    } else {
        out("Ran out of goods?");   //Text for screen
    }
}//end destock()




function buy_public_below_dpnw(&$c, $dpnw, &$money = null, $shuffle = false, $defOnly = false)
{
    //out("Stage 1");
    //$market_info = get_market_info();
    //out_data($market_info);
    if (!$money || $money < 0) {
        $money   = $c->money;
        $reserve = 0;
    } else {
        $reserve = $c->money - $money;
    }

    $tr_price = round($dpnw * 0.5 / $c->tax());  //THE PRICE TO BUY THEM AT
    $j_price  = $tu_price = round($dpnw * 0.6 / $c->tax());  //THE PRICE TO BUY THEM AT
    $ta_price = round($dpnw * 2 / $c->tax());  //THE PRICE TO BUY THEM AT

    $tr_cost = ceil($tr_price * $c->tax());  //THE COST OF BUYING THEM
    $j_cost  = $tu_cost = ceil($tu_price * $c->tax());  //THE COST OF BUYING THEM
    $ta_cost = ceil($ta_price * $c->tax());  //THE COST OF BUYING THEM

    //We should probably just do these a different way so I don't have to do BS like this
    $bah = $j_price; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $tr_cost; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $j_cost; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $tu_cost; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $ta_cost; //keep the linter happy; we DO use these vars, just dynamically
    $bah = $bah;


    $units = ['tu','tr','ta','j'];
    if ($defOnly) {
        $units = ['tu','tr','ta'];
    }

    if ($shuffle) {
        shuffle($units);
    }

    static $last = 0;
    foreach ($units as $subunit) {
        $unit = 'm_'.$subunit;
        if (PublicMarket::price($unit) != null && PublicMarket::available($unit) > 0) {
            $price = $subunit.'_price';
            $cost  = $subunit.'_cost';
            //out("Stage 1.4");
            while (PublicMarket::price($unit) <= $$price
                && $money > $$cost
                && PublicMarket::available($unit) > 0
                && $money > 50000
            ) {
                //out("Stage 1.4.x");
                //out("Money: $money");
                //out("$subunit Price: $price");
                //out("Buy Price: {$market_info->buy_price->$unit}");
                $quantity = min(
                    floor($money / ceil(PublicMarket::price($unit) * $c->tax())),
                    PublicMarket::available($unit)
                );
                if ($quantity == $last) {
                    $quantity = max(0, $quantity - 1);
                }
                $last = $quantity;
                out("$quantity $unit at $".PublicMarket::price($unit));
                //Buy UNITS!
                $result = PublicMarket::buy($c, [$unit => $quantity], [$unit => PublicMarket::price($unit)]);
                PublicMarket::update();
                $money = $c->money - $reserve;
                if ($result === false
                    || !isset($result->bought->$unit->quantity)
                    || $result->bought->$unit->quantity == 0
                ) {
                    out("Breaking@$unit");
                    break;
                }
            }
        }
    }
}//end buy_public_below_dpnw()


function buy_private_below_dpnw(&$c, $dpnw, $money = 0, $shuffle = false, $defOnly = false)
{
    //out("Stage 2");
    $pm_info = PrivateMarket::getRecent($c);   //get the PM info

    if (!$money || $money < 0) {
        $money   = $c->money;
        $reserve = 0;
    } else {
        $reserve = min($c->money, $c->money - $money);
    }

    $tr_price = round($dpnw * 0.5);
    $j_price  = $tu_price = round($dpnw * 0.6);
    $ta_price = round($dpnw * 2);

    $order = [1,2,3,4];

    if ($defOnly) {
        $order = [1, 2, 4];
    }

    if ($shuffle) {
        shuffle($order);
    }


    // out("1.Hash: ".spl_object_hash($c));
    foreach ($order as $o) {
        $money = max(0, $c->money - $reserve);

        if ($o == 1
            && $pm_info->buy_price->m_tr <= $tr_price
            && $pm_info->available->m_tr > 0
            && $money > $pm_info->buy_price->m_tr
        ) {
            $q = min(floor($money / $pm_info->buy_price->m_tr), $pm_info->available->m_tr);
            Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_tr}; Q: ".$q);
            PrivateMarket::buy($c, ['m_tr' => $q]);
        } elseif ($o == 2
            && $pm_info->buy_price->m_ta <= $ta_price
            && $pm_info->available->m_ta > 0
            && $money > $pm_info->buy_price->m_ta
        ) {
            $q = min(floor($money / $pm_info->buy_price->m_ta), $pm_info->available->m_ta);
            Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_ta}; Q: ".$q);
            PrivateMarket::buy($c, ['m_ta' => $q]);
        } elseif ($o == 3
            && $pm_info->buy_price->m_j <= $j_price
            && $pm_info->available->m_j > 0
            && $money > $pm_info->buy_price->m_j
        ) {
            $q = min(floor($money / $pm_info->buy_price->m_j), $pm_info->available->m_j);
            Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_j}; Q: ".$q);
            PrivateMarket::buy($c, ['m_j' => $q]);
        } elseif ($o == 4
            && $pm_info->buy_price->m_tu <= $tu_price
            && $pm_info->available->m_tu > 0
            && $money > $pm_info->buy_price->m_tu
        ) {
            $q = min(floor($money / $pm_info->buy_price->m_tu), $pm_info->available->m_tu);
            Debug::msg("BUY_PM: Money: $money; Price: {$pm_info->buy_price->m_tu}; Q: ".$q);
            PrivateMarket::buy($c, ['m_tu' => $q]);
        }

        // out("Country has \${$c->money}");
        // out("3.Hash: ".spl_object_hash($c));

    }
}//end buy_private_below_dpnw()


function sell_cheap_units(&$c, $unit = 'm_tr', $fraction = 1)
{
    $fraction   = max(0, min(1, $fraction));
    $sell_units = [$unit => floor($c->$unit * $fraction)];
    if (array_sum($sell_units) == 0) {
        out("No Military!");
        return;
    }
    return PrivateMarket::sell($c, $sell_units);  //Sell 'em
}//end sell_cheap_units()


function sell_all_food(&$c, $fraction = 1)
{
    $c->updateMain();
    $fraction   = max(0, min(1, $fraction));
    $sell_units = [
        'm_bu'  => floor($c->food * $fraction)
    ];
    if (array_sum($sell_units) == 0) {
        out("No Food!");
        return;
    }
    return PrivateMarket::sell($c, $sell_units);
}//end sell_all_food()

function sellextrafood($c, $fraction = 0.999, $stocking = 0)
{
    $c = get_advisor();     //UPDATE EVERYTHING

    $quantity = ['m_bu' => $c->food * $fraction]; //sell it all! :)

    $pm_info = PrivateMarket::getRecent();

    $rmax    = 1.10; //percent
    $rmin    = 0.95; //percent
    $rstep   = 0.01;
    $rstddev = 0.10;
    $max     = $c->goodsStuck('m_bu') ? 0.99 : $rmax;
    $price   = round(
        max(
            $pm_info->sell_price->m_bu + 1,
            PublicMarket::price('m_bu') * Math::purebell($rmin, $max, $rstddev, $rstep)
        )
    );

    if ($stocking == 1) {
	$rmax    = 1.3; //percent
	$rmin    = 0.7; //percent
	$stockprice = 88;
	$price   = round($stockprice * Math::purebell($rmin, $rmax, $rstddev, $rstep));
    }

    if ($price <= max(35, $pm_info->sell_price->m_bu / $c->tax()))
    {
        return PrivateMarket::sell($c, $quantity);
    }

    $price   = ['m_bu' => $price];

    return PublicMarket::sell($c, $quantity, $price);
}//end sellextrafood()



function sell_all_military(&$c, $fraction = 1)
{
    $fraction   = max(0, min(1, $fraction));
    $sell_units = [
        'm_spy'     => floor($c->m_spy * $fraction),         //$fraction of spies
        'm_tr'  => floor($c->m_tr * $fraction),      //$fraction of troops
        'm_j'   => floor($c->m_j * $fraction),       //$fraction of jets
        'm_tu'  => floor($c->m_tu * $fraction),      //$fraction of turrets
        'm_ta'  => floor($c->m_ta * $fraction)       //$fraction of tanks
    ];
    if (array_sum($sell_units) == 0) {
        out("No Military!");
        return;
    }
    return PrivateMarket::sell($c, $sell_units);  //Sell 'em
}//end sell_all_military()


function turns_of_food(&$c)
{
    if ($c->foodnet >= 0) {
        return 1000; //POSITIVE FOOD, CAN LAST FOREVER BASICALLY
    }
    $foodloss = -1 * $c->foodnet;
    return floor($c->food / $foodloss);
}//end turns_of_food()


function turns_of_money(&$c)
{
    if ($c->income > 0) {
        return 1000; //POSITIVE INCOME
    }
    $incomeloss = -1 * $c->income;
    return floor($c->money / $incomeloss);
}//end turns_of_money()


function money_management(&$c)
{
    while (turns_of_money($c) < 4) {
        //$foodloss = -1 * $c->foodnet;

        if ($c->turns_stored <= 30 && total_cansell_military($c) > 7500) {
            out("Selling max military, and holding turns.");
            sell_max_military($c);
            return true;
        } elseif ($c->turns_stored > 30 && $c->turns_stored + $c->turns > 120 && total_military($c) > 1000) {
            out("We have stored turns / too many turns + stored turns or can't sell on public; sell 10% of military.");   //Text for screen
            sell_all_military($c, 1 / 10);
        } else {
            out("Low stored turns ({$c->turns_stored}); can't sell? (".total_cansell_military($c).')');
            return true;
        }
    }

    return false;
}//end money_management()


function food_management(&$c)
{
    //RETURNS WHETHER TO HOLD TURNS OR NOT
    $reserve = max(130, $c->turns);
    if (turns_of_food($c) >= $reserve) {
        return false;
    }

    //out("food management");
    $foodloss  = -1 * $c->foodnet;
    $turns_buy = min($reserve - turns_of_food($c), 20);

    //$c = get_advisor();     //UPDATE EVERYTHING
    //global $market;
    //$market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);
    $pm_info = PrivateMarket::getRecent($c);   //get the PM info

    while ($turns_buy > 1 && $c->food <= $turns_buy * $foodloss && PublicMarket::price('m_bu') != null) {
        $turns_of_food = $foodloss * $turns_buy;
        $market_price  = PublicMarket::price('m_bu');
        //out("Market Price: " . $market_price);
        if ($c->food < $turns_of_food && $c->money > $turns_of_food * $market_price * $c->tax() && $c->money - $turns_of_food * $market_price * $c->tax() + $c->income * $turns_buy > 0) { //losing food, less than turns_buy turns left, AND have the money to buy it
            $quantity = min($foodloss * $turns_buy, PublicMarket::available('m_bu'));
            // out(
            //     "--- FOOD:  - Buy Public ".str_pad('('.$turns_buy, 17, ' ', STR_PAD_LEFT).
            //     " turns)".str_pad(" @ \$".$market_price, 18, ' ', STR_PAD_LEFT).
            //     str_pad($quantity. ' Bu', 28, ' ', STR_PAD_LEFT).
            //     " ".str_pad("(".$c->foodnet."/turn)", 15, ' ', STR_PAD_LEFT),
            //     true,
            //     'brown'
            // );     //Text for screen
            out("--- LOW FOOD ---", true, 'brown'); //Text for screen


            //Buy 3 turns of food off the public at or below the PM price
            $result = PublicMarket::buy($c, ['m_bu' => $quantity], ['m_bu' => $market_price]);
            if ($result === false) {
                PublicMarket::update();
            }
            //PublicMarket::relaUpdate('m_bu', $quantity, $result->bought->m_bu->quantity);

            $c = get_advisor();     //UPDATE EVERYTHING
        }
        /*else
            out("$turns_buy: " . $c->food . ' < ' . $turns_of_food . ';
                 $' . $c->money . ' > $' . $turns_of_food*$market_price);*/

        $turns_buy--;
    }
    $turns_buy     = min(3, max(1, $turns_buy));
    $turns_of_food = $foodloss * $turns_buy;

    if ($c->food > $turns_of_food) {
        return false;
    }

    //WE HAVE MONEY, WAIT FOR FOOD ON MKT
    if ($c->protection == 0 && $c->turns_stored < 30 && $c->income > $pm_info->buy_price->m_bu * $foodloss) {
        //Text for screen
        out("We make enough to buy food if we want to; hold turns for now, and wait for food on MKT.");
        return true;
    }

    //WAIT FOR GOODS/TECH TO SELL
    if ($c->protection == 0 && $c->turns_stored < 30 && onmarket_value() > $pm_info->buy_price->m_bu * $foodloss) {
        out("We have goods on market; hold turns for now.");    //Text for screen
        return true;
    }


    // if ($c->food < $turns_of_food && $c->money > $turns_buy * $foodloss * $pm_info->buy_price->m_bu) {
    //     //losing food, less than turns_buy turns left, AND have the money to buy it
    //     //Text for screen
    //     out(
    //         "Less than $turns_buy turns worth of food! (".$c->foodnet."/turn) ".
    //         "We're rich, so buy food on PM (\${$pm_info->buy_price->m_bu})!~"
    //     );
    //     $result = PrivateMarket::buy($c, ['m_bu' => $turns_buy * $foodloss]);  //Buy 3 turns of food!
    //     return false;
    // } elseif ($c->food < $turns_of_food && total_military($c) > 50) {
    //     out("We're too poor to buy food! Sell 1/10 of our military");   //Text for screen
    //     sell_all_military($c, 1 / 10);     //sell 1/4 of our military
    //     $c = get_advisor();     //UPDATE EVERYTHING
    //     return food_management($c); //RECURSION!
    // }

    // out('We have exhausted all food options. Valar Morguhlis.');
    return false;
}//end food_management()


function minDpnw(&$c, $onlyDef = false)
{
    $pm_info = PrivateMarket::getRecent($c);   //get the PM info

    PublicMarket::update();
    $pub_tr = PublicMarket::price('m_tr') * $c->tax() / 0.5;
    $pub_j  = PublicMarket::price('m_j') * $c->tax() / 0.6;
    $pub_tu = PublicMarket::price('m_tu') * $c->tax() / 0.6;
    $pub_ta = PublicMarket::price('m_ta') * $c->tax() / 2;

    $dpnws = [
        'pm_tr' => round($pm_info->buy_price->m_tr / 0.5),
        'pm_j' => round($pm_info->buy_price->m_j / 0.6),
        'pm_tu' => round($pm_info->buy_price->m_tu / 0.6),
        'pm_ta' => round($pm_info->buy_price->m_ta / 2),
        'pub_tr' => $pub_tr == 0 ? 9000 : $pub_tr,
        'pub_j' => $pub_j == 0 ? 9000 : $pub_j,
        'pub_tu' => $pub_tu == 0 ? 9000 : $pub_tu,
        'pub_ta' => $pub_ta == 0 ? 9000 : $pub_ta,
    ];

    if ($onlyDef) {
        unset($dpnws['pm_j']);
        unset($dpnws['pub_j']);
    }

    return min($dpnws);
}//end minDpnw()


function defend_self(&$c, $reserve_cash = 50000, $dpnwMax = 380)
{
    if ($c->protection) {
        return;
    }
    //BUY MILITARY?
    $spend      = $c->money - $reserve_cash;
    $nlg_target = $c->nlgTarget();
    $dpnw       = minDpnw($c, true); //ONLY DEF
    $nlg        = $c->nlg();
    $dpat       = $c->dpat ?? $c->defPerAcreTarget();
    $dpa        = $c->defPerAcre();
    $outonce    = false;

    while (($nlg < $nlg_target || $dpa < $dpat) && $spend >= 100000 && $dpnw < $dpnwMax) {
        if (!$outonce) {
            if ($dpa < $dpat) {
                out("--- DPA Target: $dpat (Current: $dpa)");  //Text for screen
            } else {
                out("--- NLG Target: $nlg_target (Current: $nlg)");  //Text for screen
            }

            $outonce = true;
        }

        // out("0.Hash: ".spl_object_hash($c));

        $dpnwOld = $dpnw;
        $dpnw    = minDpnw($c, $dpa < $dpat); //ONLY DEF
        //out("Old DPNW: ".round($dpnwOld, 1)."; New DPNW: ".round($dpnw, 1));
        if ($dpnw <= $dpnwOld) {
            $dpnw = $dpnwOld + 1;
        }

        buy_public_below_dpnw($c, $dpnw, $spend, true, true); //ONLY DEF

        // out("7.Hash: ".spl_object_hash($c));

        $spend = max(0, $c->money - $reserve_cash);
        $nlg   = $c->nlg();
        $dpa   = $c->defPerAcre();
        $c     = get_advisor();     //UPDATE EVERYTHING

        // out("8.Hash: ".spl_object_hash($c));

        if ($spend < 100000) {
            break;
        }

        buy_private_below_dpnw($c, $dpnw, $spend, true, true); //ONLY DEF
        $dpnwOld = $dpnw;
        $dpnw    = minDpnw($c, $dpa < $dpat); //ONLY DEF if dpa < dpat
        if ($dpnw <= $dpnwOld) {
            $dpnw = $dpnwOld + 1;
        }
        $c     = get_advisor();     //UPDATE EVERYTHING
        $spend = max(0, $c->money - $reserve_cash);
        $nlg   = $c->nlg();
        $dpa   = $c->defPerAcre();
    }
}//end defend_self()



function sell_max_military(&$c)
{
    $c = get_advisor();     //UPDATE EVERYTHING
    //$market_info = get_market_info();   //get the Public Market info

    $pm_info = PrivateMarket::getRecent($c);   //get the PM info

    global $military_list;

    $quantity = [];
    foreach ($military_list as $unit) {
        $quantity[$unit] = can_sell_mil($c, $unit);
    }

    $rmax    = 1.30; //percent
    $rmin    = 0.80; //percent
    $rstep   = 0.01;
    $rstddev = 0.10;
    $price   = [];
    foreach ($quantity as $key => $q) {
        if ($q == 0) {
            $price[$key] = 0;
        } elseif (PublicMarket::price($key) == null || PublicMarket::price($key) == 0) {
            $price[$key] = floor($pm_info->buy_price->$key * Math::purebell(0.5, 1.0, 0.3, 0.01));
        } else {
            $max         = (turns_of_money($c) < 10) && $c->goodsStuck($key) ? 0.99 : $rmax; //undercut if we have goods stuck and are running low of cash
            $price[$key] = min(
                $pm_info->buy_price->$key,
                floor(PublicMarket::price($key) * Math::purebell($rmin, $max, $rstddev, $rstep))
            );
        }

        if ($price[$key] > 0 && $price[$key] * $c->tax() <= $pm_info->sell_price->$key) {
            //out("Public is too cheap for $key, sell on PM");
            sell_cheap_units($c, $key, 0.5);
            $price[$key]    = 0;
            $quantity[$key] = 0;
            return;
        }
    }
    /*
    $randomup = 120; //percent
    $randomdown = 80; //percent
    $price = array(
        'm_tr'=>    $quantity['m_tr'] == 0
        ? 0
        : floor(($market_info->buy_price->m_tr != null
            ? $market_info->buy_price->m_tr
            : rand(110,144))*(rand($randomdown,$randomup)/100)),
        'm_j' =>    $quantity['m_j']  == 0
            ? 0
            : floor(($market_info->buy_price->m_j  != null
                ? $market_info->buy_price->m_j
                : rand(110,192))*(rand($randomdown,$randomup)/100)),
        'm_tu'=>    $quantity['m_tu'] == 0
            ? 0
            : floor(($market_info->buy_price->m_tu != null
                ? $market_info->buy_price->m_tu
                : rand(110,200))*(rand($randomdown,$randomup)/100)),
        'm_ta'=>    $quantity['m_ta'] == 0
            ? 0
            : floor(($market_info->buy_price->m_ta != null
                ? $market_info->buy_price->m_ta
                : rand(400,560))*(rand($randomdown,$randomup)/100))
    );*/

    $result = PublicMarket::sell($c, $quantity, $price);
    if ($result == 'QUANTITY_MORE_THAN_CAN_SELL') {
        out("TRIED TO SELL MORE THAN WE CAN!?!");
        $c = get_advisor();     //UPDATE EVERYTHING
    }
    global $mktinfo;
    $mktinfo = null;
    return $result;
}//end sell_max_military()


/**
 * Return a url to the AI Bot spyop for admins
 *
 * @param  int $cnum Country Number
 *
 * @return string    Spyop URL
 */
function siteURL($cnum)
{
    global $config, $server;
    $name  = $config['server'];
    $round = $server->round_num;

    return "https://qz.earthempires.com/$name/$round/ranks/$cnum";
}//end siteURL()
