<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * heartshf implementation : © <Your name here> <Your email address here>
  * 
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  * 
  * heartshf.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );


class heartshf extends Table
{
	function __construct( )
	{
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        
        self::initGameStateLabels( array( 
            //    "my_first_global_variable" => 10,
            //    "my_second_global_variable" => 11,
            //      ...
            //    "my_first_game_variant" => 100,
            //    "my_second_game_variant" => 101,
            //      ...

            // Where to pass cards at the beginning of the round (0 = left, 1 = right, 2 = front, 3 = keep cards)
            "currentHandType" => 10, 
            // Suit of the current trick (0 = none, 1 = spades, 2 = hearts, 3 = clubs, 4 = diamonds)
            "trickSuit" => 11, 
            // Has a player already played a heart card in this round (0 = No, 1 = Yes)
            "alreadyPlayedHearts" => 12 
        ) );

        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");
	}
	
    protected function getGameName( )
    {
		// Used for translations and stuff. Please do not modify.
        return "heartshf";
    }	

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame( $players, $options = array() )
    {    
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_score, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."',100,'$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        //self::setGameStateInitialValue( 'my_first_global_variable', 0 );
        
        self::setGameStateInitialValue('currentHandType', 0); // left
        self::setGameStateInitialValue('trickSuit', 0); // no trick suit
        self::setGameStateInitialValue('alreadyPlayedHearts', 0); // no hearts already played this round

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

        // TODO: setup the initial game situation here

        // Create cards
        $cards = array();
        foreach ($this->suits as $suit_id => $suit) {
            // spade, heart, clubs, diamonds
            for ($value = 2; $value <= 14; $value++) {
                $cards[] = array('type' => $suit_id, 'type_arg' => $value, 'nbr' => 1);
            }
        }
        $this->cards->createCards($cards, 'deck');

        // Shuffle deck
        $this->cards->shuffle('deck');
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $cards = $this->cards->pickCards(13, 'deck', $player_id);
        }

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array();
    
        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );
  
        // TODO: Gather all information about current game situation (visible by player $current_player_id).
  
        // Cards in player hand
        $result['hand'] = $this->cards->getCardsInLocation('hand', $current_player_id);

        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation('cardsontable');

        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    /*
        In this space, you can put any utility methods useful for your game logic
    */



//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in heartshf.action.php)
    */

    /*
    
    Example:

    function playCard( $card_id )
    {
        // Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction( 'playCard' ); 
        
        $player_id = self::getActivePlayerId();
        
        // Add your game logic to play a card there 
        ...
        
        // Notify all players about the card played
        self::notifyAllPlayers( "cardPlayed", clienttranslate( '${player_name} plays ${card_name}' ), array(
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $card_name,
            'card_id' => $card_id
        ) );
          
    }
    
    */

    function giveCards($card_ids) {
        self::checkAction("giveCards");
        $player_id = self::getCurrentPlayerId();

        $sql = "SELECT player_id, player_no FROM player";
        $players = self::getCollectionFromDB($sql, true);

        $player_position = $players[$player_id];
        $current_hand_type = self::getGameStateValue('currentHandType');

        // Get position of the player to whom give the card
        switch($current_hand_type) {
            case 0: // Left
                $other_player_position = ((($player_position - 1) + 1) % 4) + 1;
                break;
            case 1: // Right
                $other_player_position = ((($player_position - 1) + 3) % 4) + 1;
                break;
            case 2: // Front
                $other_player_position = ((($player_position - 1) + 2) % 4) + 1;
                break;
        }
        
        // Get the player id of the player to whom give the 
        $other_player_id = array_search($other_player_position, $players);

        // Update location of cards given to the other player
        $sql = "UPDATE card SET card_location_arg = $other_player_id WHERE card_id IN (";
        $values = array();
        foreach($card_ids as $card_id) {
            $values[] = $card_id;
            $this->cards->moveCard($card_id, "exchanging");
        }
        $sql .= implode(",", $values) . ")";
        self::DbQuery($sql);

        // Make this player unactive now
        // (and tell the machine state to use transtion "giveCards" if all players are now unactive
        $this->gamestate->setPlayerNonMultiactive($player_id, "giveCards");
    }

    function playCard($card_id) {
        self::checkAction("playCard");
        $player_id = self::getActivePlayerId();
        $current_card = $this->cards->getCard($card_id);
        $currentTrickSuit = self::getGameStateValue('trickSuit');
        $is_heart_already_played = self::getGameStateValue('alreadyPlayedHearts');
        $cards_in_hand = $this->cards->getCardsInLocation("hand", $player_id);

        // Rules specific for the first trick of a round
        if (count($cards_in_hand) == 13) {
            // First trick of the round
            // Check if it is the first card of the trick
            if ($currentTrickSuit == 0) {
                // First card of the trick => Must play 2-clubs
                if (!($current_card['type_arg'] == 2 && $current_card['type'] == 3)) {
                    throw new BgaUserException(self::_("You must play the 2 of clubs."));
                }
            } else {
                // Not first card of the trick => Cannot play points
                if ($current_card['type'] == 2 || ($current_card['type_arg'] == 12 && $current_card['type'] == 1)) {
                    throw new BgaUserException(self::_("You can't play points in the first round."));
                }
            }
        }
        // Rules for any trick
        // Check if it is not the first card of the trick
        if ($currentTrickSuit != 0) {
            // Not first card of the trick => Must play a card of the same suit if possible
            if ($current_card['type'] != $currentTrickSuit) {
                // The card is not of the same suit as the current trick
                // Check if the player has any card of the current trick suit in hand
                foreach ($cards_in_hand as $card) {
                    if ($card['type'] == $currentTrickSuit) {
                        throw new BgaUserException( self::_("You must play a card of the current trick suit."));
                    }
                }
            }
        } else {
            // First card of the trick => Cannot start with hearts if no heart has been played yet
            if (!$is_heart_already_played && $current_card['type'] == 2) {
                if (count($this->cards->getCardsOfTypeInLocation($type = 2, $type_arg = null, $location = "hand", $location_arg = $player_id)) != count($cards_in_hand)) {
                    throw new BgaUserException(self::_("You can't start the trick with heart if not heart card has been played before."));
                }
            }
            // Set the current trick suit to the card played
            self::setGameStateValue('trickSuit', $current_card['type']);
        }

        // If no exception was thrown at this stage, the card can be played

        
        
        // If it is the first heart played, set the alreadyPlayedHearts gamestate to true
        if (!self::getGameStateValue('alreadyPlayedHearts') && $current_card['type'] == 2) {
            self::setGameStateValue('alreadyPlayedHearts', 1);
        }

        // Move the card to the table
        $this->cards->moveCard($card_id, 'cardsontable', $player_id);

        // Notify client of all players that the card has been played
        self::notifyAllPlayers("playCard", clienttranslate('${player_name} plays ${value_displayed} ${suit_displayed}'), array(
            'i18n' => array('suit_displayed', 'value_displayed'), 
            'card_id' => $card_id, 
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'value' => $current_card['type_arg'],
            'suit' => $current_card['type'],
            'value_displayed' => $this->values_label[$current_card['type_arg']],
            'suit_displayed' => $this->suits[$current_card['type']]['name']
        ));
        $this->gamestate->nextState('playCard');
    }

    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    /*
    
    Example for game state "MyGameState":
    
    function argMyGameState()
    {
        // Get some values from the current game situation in database...
    
        // return values:
        return array(
            'variable1' => $value1,
            'variable2' => $value2,
            ...
        );
    }    
    */

    function argGiveCards() {
        $direction = self::getGameStateValue('currentHandType');

        switch ($direction) {
            case 0:
                $dir_text = "the player on the left";
                break;
            case 1:
                $dir_text = "the player on the right";
                break;
            case 2:
                $dir_text = "the player across the table";
                break;
            case 3:
                $dir_text = "no one";
        }

        return array(
            "i18n" => array('direction'),
            'direction' => $dir_text
        );
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */
    
    /*
    
    Example for game state "MyGameState":

    function stMyGameState()
    {
        // Do some stuff ...
        
        // (very often) go to another gamestate
        $this->gamestate->nextState( 'some_gamestate_transition' );
    }    
    */

    function stNewHand() {
        // Tak back all cards (from any location => null) to deck
        $this->cards->moveAllCardsInLocation(null, "deck");
        $this->cards->shuffle('deck');

        // Deal 13 cards to each players
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $cards = $this->cards->pickCards(13, 'deck', $player_id);
            // Notify player about his cards
            self::notifyPlayer($player_id, 'newHand', '', array('cards' => $cards));
        }
        self::setGameStateValue('alreadyPlayedHearts', 0);
        $this->gamestate->nextState("");
    }

    function stGiveCards() {
        $hand_type = self::getGameStateValue('currentHandType');

        // Check if it is a round where no cards are exchanged 
        if ($hand_type == 3) {
            $this->gamestate->nextState("skip");
        } else {
            // Active all players (everyone has to choose 3 cards to give)
            $this->gamestate->setAllPlayersMultiactive();
        }
    }

    function stTakeCards() {
        $hand_type = self::getGameStateValue('currentHandType');

        // Check if it is a round where no cards are exchanged 
        if ($hand_type == 3) {
            $this->gamestate->nextState("skip");
        } else {
            $exchanged_cards = $this->cards->getCardsInLocation('exchanging');
            $this->cards->moveAllCardsInLocationKeepOrder('exchanging', 'hand');
            self::notifyAllPlayers("takeCards", "", array(
                'cards' => $exchanged_cards
            ));
            $this->gamestate->nextState("startHand");
        }
    }

    function stNewTrick() {
        // New trick: active the player who wins the last trick, or the player who own the club-2 card
        $twoClubCardOwner = self::getUniqueValueFromDb( "SELECT card_location_arg FROM card
                                                         WHERE card_location='hand'
                                                         AND card_type='3' AND card_type_arg='2' " );
        if ($twoClubCardOwner !== null) {
            $this->gamestate->changeActivePlayer($twoClubCardOwner);
        }

        // Reset trick suit to 0 (= no suit)
        self::setGameStateInitialValue('trickSuit', 0);
        $this->gamestate->nextState();
    }

    function stNextPlayer() {
        // Active next player OR end the trick and go to the next trick OR end the hand
        if ($this->cards->countCardInLocation('cardsontable') == 4) {
            // This is the end of the trick

            $cards_on_table = $this->cards->getCardsInLocation('cardsontable');
            $best_value = 0;
            $best_value_player_id = null;
            $currentTrickSuit = self::getGameStateValue('trickSuit');
            foreach ($cards_on_table as $card) {
                if ($card['type'] == $currentTrickSuit) {
                    if ($best_value_player_id === null || $card['type_arg'] > $best_value) {
                        $best_value_player_id = $card['location_arg']; // Note: location_arg = player who played this card on table
                        $best_value = $card['type_arg']; // Note: type_arg = value of the card
                    }
                }
            }

            // Active this player => he's the one who starts the next trick
            $this->gamestate->changeActivePlayer($best_value_player_id);
            
            // Move all cards to "cardswon" of the given player
            $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', null, $best_value_player_id);

            // Notify
            // Note: we use 2 notifications here in order we can pause the display during the first notification
            //  before we move all cards to the winner (during the second)
            $players = self::loadPlayersBasicInfos();
            self::notifyAllPlayers( 'trickWin', clienttranslate('${player_name} wins the trick'), array(
                'player_id' => $best_value_player_id,
                'player_name' => $players[ $best_value_player_id ]['player_name']
            ) );            
            self::notifyAllPlayers( 'giveAllCardsToPlayer','', array(
                'player_id' => $best_value_player_id
            ) );
        
            if ($this->cards->countCardInLocation('hand') == 0) {
                // End of the hand
                $this->gamestate->nextState("endHand");
            } else {
                // End of the trick
                $this->gamestate->nextState("nextTrick");
            }
        } else {
            // Standard case (not the end of the trick)
            // => just active the next player
            $player_id = self::activeNextPlayer();
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function stEndHand() {
        // Count and score points, then end the game or go to the next hand.
        $players = self::loadPlayersBasicInfos();
        // Gets all "hearts" + queen of spades

        $player_to_points = array ();
        foreach ( $players as $player_id => $player ) {
            $player_to_points [$player_id] = 0;
        }
        $cards = $this->cards->getCardsInLocation("cardswon");
        foreach ( $cards as $card ) {
            $player_id = $card ['location_arg'];
            // Any heart = 1 pt
            if ($card ['type'] == 2) {
                $player_to_points [$player_id] ++;
            }
            // Queen of spades = 13 pts
            if ($card ['type'] == 1 && $card ['type_arg'] == 12 ) {
                $player_to_points [$player_id] += 13 ;
            }
        }
        // Check if a player has all hearts + the queen of spades = he score 0 and the others score 26
        foreach($player_to_points as $player_id => $points) {
            if ($points == 26) {
                $player_to_points[$player_id] = 0;
                foreach($player_to_points as $player_id2 => $points2) {
                    if ($player_id2 != $player_id) {
                        $player_to_points[$player_id2] = 26;
                    }
                }
            }
        }

        // Apply scores to player
        foreach ( $player_to_points as $player_id => $points ) {
            if ($points != 0) {
                $sql = "UPDATE player SET player_score=player_score-$points  WHERE player_id='$player_id'";
                self::DbQuery($sql);
                $heart_number = $player_to_points [$player_id];
                self::notifyAllPlayers("points", clienttranslate('${player_name} gets ${nbr} hearts and looses ${nbr} points'), array (
                        'player_id' => $player_id,'player_name' => $players [$player_id] ['player_name'],
                        'nbr' => $heart_number ));
            } else {
                // No point lost (just notify)
                self::notifyAllPlayers("points", clienttranslate('${player_name} did not get any hearts'), array (
                        'player_id' => $player_id,'player_name' => $players [$player_id] ['player_name'] ));
            }
        }
        $newScores = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true );
        self::notifyAllPlayers( "newScores", '', array( 'newScores' => $newScores ) );

        ///// Test if this is the end of the game
        foreach ( $newScores as $player_id => $score ) {
            if ($score <= 0) {
                // Trigger the end of the game !
                $this->gamestate->nextState("endGame");
                return;
            }
        }

        // Update hand type
        $current_hand_type = self::getGameStateValue('currentHandType');
        self::setGameStateValue('currentHandType', ($current_hand_type + 1) % 4);
        $this->gamestate->nextState("nextHand");
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn( $state, $active_player )
    {
    	$statename = $state['name'];
    	
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                	break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
            
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb( $from_version )
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        // Please add your future database scheme changes here
//
//


    }    
}
