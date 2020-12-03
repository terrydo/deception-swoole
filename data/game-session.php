<?php
// Utility Functions
// include_once 'utils.php';

// Loading Gamedata
include('sides.php');
include('death_locations.php');
include('roles.php');
include('cause_of_death.php');
include('normal_hints.php');
// PLAYER CARDS
	// Blue cards
	include('weapons.php');

	// Red cards
	include('evidence.php');


const SERVER_CONFIG = [
	'weaponCardsUrl' => '/static/images/weaponCards',
	'crimeSceneCardsUrl' => '/static/images/crimeSceneCards',
];

const PHASES = [
	"Phase1Murderer",
	"Phase1FS",
	"Arguing",
	"PhaseCardChange",
	"Arguing",
	"PhaseCardChange",
	"LastArguing",
	"EndGame", /*Always be the last phase.*/
];

const MURDERER_LAST_CHANCE_PHASE = "MurdererLastChance";

class GameSession {

	protected 	$gameSession   	= array();

	protected 	$currentPhase  	= 0;

	protected 	$playerCount 	= 0;

	protected 	$evidence 		= EVIDENCE;

	protected	$weapons 		= WEAPONS;

	protected	$normalHints 	= NORMAL_HINTS;

	protected	$roles 			= array();
	protected	$goodSideCount 	= 0; 

	protected	$allEvidenceCards 	= array();
	protected	$allWeaponCards 	= array();

	protected	$hasWitness 	= false;

	/**
	 * Check if forensic scientist has completed picking first 6 hint cards.
	 * @var boolean
	 */
	private 	$isFSDonePickingFirstTime 	= false;

	private 	$totalHintCardsPicked 		= 0;

	function __construct($playerList){
		$this->playerCount = sizeof($playerList);

		shuffle($this->evidence);
		shuffle($this->weapons);
		shuffle($this->normalHints);

		$this->setupGame();
		$this->gameSession['players'] 	= $this->generatePlayersData($playerList);
		$this->gameSession['gameData'] 	= $this->generateGameData();
	}

	/**
	 * GENERATORS / SETUP
	 */
		private function setupGame(){
			$playerCount = $this->playerCount;

			// 4-5 players
			if ( 3 < $playerCount && $playerCount < 6 ){
				array_push($this->roles, ROLES[0]); // Add Murderer
				array_push($this->roles, ROLES[2]); // Add Forensic Scientist
				for ($i=0; $i < $playerCount - 2; $i++) { 
					array_push($this->roles, ROLES[4]); // Add Investigator
				}
				$this->goodSideCount = $playerCount - 2; // 2 for murderer and fs
			}
			// 6 players or above
			else if ($playerCount >= 6) {
				array_push($this->roles, ROLES[0]); // Add Murderer
				array_push($this->roles, ROLES[1]); // Add Accomplice
				array_push($this->roles, ROLES[2]); // Add Forensic Scientist
				array_push($this->roles, ROLES[3]); // Add Witness
				for ($i=0; $i < $playerCount - 4; $i++) {
					array_push($this->roles, ROLES[4]); // Add Investigator
				}
				$this->hasWitness = true;

				$this->goodSideCount = $playerCount - 3; // 3 for murderer, fs and accomplice
			}

			shuffle($this->roles);
		}

		private function generatePlayersData($playerList){
			$playerData = array();

			$index = 0;
			foreach ($playerList as $key => $player) {
				// Player Roles

				$playerData[$player['clientId']] = [
					'id'			=> $player['clientId'],
					'fd' 			=> $player['fd'],
					/*This is created to prevent player from hacking. Otherwise all other players' data might be exposed!*/
					'accessToken'	=> Utils::randomHash(),
					// 'accessToken'	=> $role['codename'],
					'playerName'	=> $player['playerName'],
					'role' 			=> $this->roles[$index++],
					'isVoteable' 	=> true,
					'evidenceCards' => $this->generateEvidence(),
					'weaponCards'	=> $this->generateWeapons(),
				];
			}

			return $playerData;
		}

		private function generateEvidence(){
			
			$result = array();
			
			for ($i=0; $i < 4 ; $i++) {

				$evidenceCard = array_shift($this->evidence);
	
				$cardId = Utils::randomHash(5);
				$evidenceCard["cardId"] = $cardId;

				array_push($result, $evidenceCard);
				$this->allEvidenceCards[$cardId] = $evidenceCard;
			}

			return $result;
		}

		private function generateWeapons(){
			
			$result = array();

			for ($i=0; $i < 4 ; $i++) {

				$weapon = array_shift($this->weapons);
				
				$cardId = Utils::randomHash(5);
				$weapon["cardId"] = $cardId;

				array_push($result, $weapon);
				$this->allWeaponCards[$cardId] = $weapon;
			}

			return $result;
		}

		private function generateGameData(){
			$gameData = array();

			$gameData['hintCards'] 				= $this->generateHintCards();
			$gameData['currentPhase']			= PHASES[0];
			$gameData['murdererPick']			= array("weaponId" => null, "evidenceId" => null);
			
			$gameData['goodSideLeft'] 			= $this->goodSideCount; 
			$gameData['winSide']				= null;
			$gameData['logs'] 					= null;

			return $gameData;
		}

		private function generateHintCards(){
			$hintCards = array();

			$hintCards[0]['hintName']	= "Location of Crime";
			$hintCards[0]['slots'] 		= $this->generateDeathLocations();
			$hintCards[0]['selected'] 	= null;

			$hintCards[1]['hintName'] 	= "Cause of death";
			$hintCards[1]['slots'] 		= $this->generateCauseOfDeath();
			$hintCards[1]['selected'] 	= null;
			
			$normalHints				= $this->generateNormalHints(4);

			foreach ($normalHints as $normalHint) {
				$hintCards[] = $normalHint;
			}

			return $hintCards;
		}

		private function generateDeathLocations(){
			$death_locations = DEATH_LOCATIONS;
			
			shuffle($death_locations);

			return $death_locations[0];
		}

		private function generateCauseOfDeath(){
			return CAUSE_OF_DEATH;
		}

		private function generateNormalHints($numberOfCard = 1){
			$result = array();

			for ($i = 0; $i < $numberOfCard ; $i++) { 

				$normalHint = array_shift($this->normalHints);

				$hintName 	= array_shift($normalHint);

				$result[$i]['hintName'] 	= $hintName;
				$result[$i]['slots'] 		= $normalHint;
				$result[$i]['selected'] 	= null;

			}

			return $result;
		}

	// Server-only logical FUNCTIONS.
	// When I wrote this, only God and I understood what I was doing
	// Now, only God knows.

		private function _getGameSession(){
			return $this->gameSession;
		}

		private function _getPlayerData($clientId){
			return $this->gameSession['players'][$clientId];
		}

		private function _getAllPlayersData(){
			return $this->gameSession['players'];
		}

		private function _getPlayersByCodename($codename){
			$result = array();

			foreach ($this->_getAllPlayersData() as $key => $player) {
				if ($player["role"]['codename'] == $codename){
					array_push($result, $player);
				}
			}
			return $result;
		}

		private function _isRole($codename, $accessToken){
			$item = $this->_getPlayersByCodename($codename);

			if (!$item) return false;

			$item = $item[0];

			if ($item['accessToken'] == $accessToken){
				return true;
			}
			return false;
		}

		private function isForensicScientist($accessToken){
			return $this->_isRole('FS', $accessToken);
		}

		private function isAccomplice($accessToken){
			return $this->_isRole('A', $accessToken);
		}

		private function isWitness($accessToken){
			return $this->_isRole('W', $accessToken);
		}

		private function isMurderer($accessToken){
			return $this->_isRole('M', $accessToken);
		}

		private function getWeaponById($id){
			return isset($this->allWeaponCards[$id]) ? $this->allWeaponCards[$id] : false;
		}

		private function getEvidenceById($id){
			return isset($this->allEvidenceCards[$id]) ? $this->allEvidenceCards[$id] : false;
		}

		private function endGame($side){
			$phases = PHASES;
			$this->gameSession['gameData']['currentPhase'] 	= end($phases);
			$this->gameSession['gameData']['winSide'] 		= $side;
		}

	/**
	 * When investigators successfully convicted.
	 */
	private function murdererLastChance(){
		$this->gameSession['gameData']['currentPhase'] = MURDERER_LAST_CHANCE_PHASE;
	}

	private function getCurrentPhaseString(){
		return $this->gameSession['gameData']['currentPhase'];
	}

	private function nextPhase(){
		if ((int) $this->currentPhase + 1 >= sizeof(PHASES) || $this->getCurrentPhaseString() == MURDERER_LAST_CHANCE_PHASE){
			return false;
		}

		$this->currentPhase = (int) $this->currentPhase + 1;
		echo "Current fucking phase: " . $this->currentPhase;
	
		$this->gameSession['gameData']['currentPhase'] = PHASES[$this->currentPhase];
		return true;
	}

	private function writeLog($string){
		$this->gameSession['gameData']['logs'][] = $string;
	}

	/**
	 * GAME SESSION ACTIONS
	 */
		public function getAccessToken($fd){

			if (!$fd) return;

			foreach ($this->gameSession['players'] as $player) {
				if ($player['fd'] == $fd) return $player['accessToken'];
			}
		}

		public function getGameSession(){
			return $this->gameSession;
		}

		public function murdererPick($weaponId, $evidenceId, $accessToken){

			if (!$this->isMurderer($accessToken)) return;

			$this->gameSession['gameData']['murdererPick']['weaponId'] 		= $weaponId;
			$this->gameSession['gameData']['murdererPick']['evidenceId'] 	= $evidenceId;
			$this->nextPhase();
		}

		// Show the card that murderer/accomplice is going to pick
		public function pingCard($accessToken){
			if ($this->isMurderer($accessToken)){
				return $this->_getPlayersByCodename('A')[0]['id'];
			}
			else if ($this->isAccomplice($accessToken)){
				return $this->_getPlayersByCodename('M')[0]['id'];
			}
		}

		/**
		 * Player convicts.
		 * @param  [string] $weaponId
		 * @param  [string] $evidenceId
		 * @param  [string] $voterId <The id of the convicting player>.
		 * @return [mixed] $result
		 */
		public function convict($weaponId, $evidenceId, $voterId, $accessToken){
			$voter 		= $this->_getPlayerData($voterId)['playerName'];
			$weapon 	= $this->getWeaponById($weaponId)['name'];
			$evidence   = $this->getEvidenceById($evidenceId)['name'];

			$logStr  	= "{$voter} chose {$weapon} and {$evidence}";

			$this->writeLog($logStr);

			if (
				$this->gameSession['gameData']['murdererPick']['weaponId'] 		== $weaponId &&
				$this->gameSession['gameData']['murdererPick']['evidenceId'] 	== $evidenceId &&
				!$this->isForensicScientist($accessToken)
			){
				if ($this->hasWitness){
					$this->murdererLastChance();
				}
				else {
					$this->endGame(GOOD_SIDE);
				}
				return true;
			}
			else {

				if (!isset($this->gameSession['players'][$voterId])){
					echo "Invalid voter";
					return false;
				}

				$voter = &$this->gameSession['players'][$voterId];
				$voter['isVoteable'] = false;



				if ($voter['role']['side'] == GOOD_SIDE){
					$this->gameSession['gameData']['goodSideLeft']--;
				}

				if ($this->gameSession['gameData']['goodSideLeft'] <= 0){
					$this->endGame(BAD_SIDE);
				}

				return false;
			}
		}

		public function murdererWitnessChoose($clientId, $accessToken){
			if (!$this->isMurderer($accessToken)) return null;
			

			if ($this->gameSession['players'][$clientId]['role']['codename'] == 'W'){
				$this->endGame(BAD_SIDE);
				return true;
			}
			else {
				$this->endGame(GOOD_SIDE);
				return false;
			} 
		}

		/**
		 * Forensic Scientist picks.
		 */
		public function selectHintCard($hintCardIndex, $slotIndex, $accessToken){
			if (!empty($this->gameSession['gameData']['hintCards'][$hintCardIndex]['selected'])
				|| !$this->isForensicScientist($accessToken)
			){
				return false;
			}

			$totalHintCardsPicked = &$this->totalHintCardsPicked;
			$totalHintCardsPicked++;

			if ($totalHintCardsPicked == 6 && !$this->isFSDonePickingFirstTime){
				$this->isFSDonePickingFirstTime = true;
				$this->nextPhase();
			}

			$this->gameSession['gameData']['hintCards'][$hintCardIndex]['selected'] = $slotIndex;

			return true;
		}

		public function getWinner(){
			return $this->gameSession['gameData']['winSide'];
		}

		/**
		 * Get client's player data. This will include your role blah blah
		 * @param [string] $clientId
		 * @param [string] $accessToken <This token is generated for every player when the game session started.>
	 	 */
		public function getPlayerData($clientId, $accessToken){
			$playerInfo = &$this->gameSession['players'][$clientId];
			
			if (!empty($this->gameSession['players'][$clientId]) && $this->gameSession['players'][$clientId]['accessToken'] == $accessToken)
				return $playerInfo;

			return array('error' => "STOP PUSSYFOOT AROUND DUMB ASS...");
		}

		/**
		 * Get all players' data except sensitive information.
		 * @param  [clientId] [<Id of current player>]
		 * @return [type] [All public other players' data]
		 */
		public function getOtherPlayersData($clientId, $accessToken = null){
			$playersInfo = $this->gameSession['players'];

			unset($playersInfo[$clientId]);

			// var_dump($playersInfo);

			// Foresic Scientist can see all other players' roles.
			if ($accessToken && $this->isForensicScientist($accessToken))
				return $playersInfo;

			foreach ($playersInfo as &$player) {	
				echo "unsetting token..";

				// var_dump($player);

				// Hide all player roles except forensic scientist
				if ($player['role']['codename'] == 'FS') continue;

				// Show Accomplice to Murderer
				if ($player['role']['codename'] == 'A' && $this->isMurderer($accessToken)) continue;
				
				// Show Murderer to Accomplice
				if ($player['role']['codename'] == 'M' && $this->isAccomplice($accessToken)) continue;

				// Show Murder and Accomplice to Witness as 2 bad guys.
				if ($player['role']['codename'] == 'M' || $player['role']['codename'] == 'A'){
					if ($this->isWitness($accessToken)) {
						$player['role']['codename'] = 'BG';
						$player['role']['name']		= 'Bad Guy';
						continue;
					}
				}

				unset($player['accessToken']);
				unset($player['role']);

			}

			return $playersInfo;
		}

		/**
		 * Hide Murderer's picked cards
		 */
		public function getGameData($accessToken){

			$gameData =  $this->gameSession['gameData'];

			if (!$this->isForensicScientist($accessToken) && !$this->isMurderer($accessToken) && !$this->isAccomplice($accessToken)){
				unset($gameData['murdererPick']);
			}

			return $gameData;
		}

		public function fsNextPhase($accessToken){
			if ($this->isForensicScientist($accessToken)){
				return $this->nextPhase();
			}
			return false;
		}

		public function fsReplaceHintCard($replaceHintCardIndex, $accessToken){
			// You cannot replace cause of death and death location cards.
			if (!$replaceHintCardIndex || !$this->isForensicScientist($accessToken) || in_array($replaceHintCardIndex, array(0,1))) return false;

			$this->gameSession['gameData']['hintCards'][$replaceHintCardIndex] = $this->generateNormalHints()[0];
			$this->nextPhase();
			return true;
		}

};


/**
 * FOR TESTING PURPOSES.
 */

// echo "WARNING: YOU ARE DEBUGGING!";

// $playerList = array(
// 	'p1' => [
// 		'clientId' 		=> 'p1',
// 		'fd' 			=> '2',
// 		'playerName' 	=> 'Trung',
// 	],
// 	'p2' => [
// 		'clientId'		=> 'p2',
// 		'fd' 			=> '3',
// 		'playerName' 	=> 'Hieu',
// 	],
// 	'p3' => [
// 		'clientId'		=> 'p3',
// 		'fd' 			=> '4',
// 		'playerName' 	=> 'Minh',
// 	],
// 	'p4' => [
// 		'clientId'		=> 'p4',
// 		'fd' 			=> '5',
// 		'playerName' 	=> 'Dat',
// 	],
// 	'p5' => [
// 		'clientId'		=> 'p5',
// 		'fd' 			=> '4',
// 		'playerName' 	=> 'Hai',
// 	],
// 	'p6' => [
// 		'clientId'		=> 'p6',
// 		'fd' 			=> '5',
// 		'playerName' 	=> 'Thanh',
// 	],
// );

// $gameSession = new GameSession($playerList);

// echo "<pre>";

// $gameSession->murdererPick('a0cd', 'c945');
// var_dump($gameSession->getGameSession());
// var_dump($gameSession->getPlayerData('p1', 'invalidToken'));
// var_dump($gameSession->getPlayerData('p1', 'token'));

// var_dump($gameSession->getWinner());


// $gameSession->convict("abc", "ASD", "p1", "M");
// var_dump($gameSession->getGameData("p1", "M"));

// var_dump($gameSession->convict("abcd", "qwes", 'p1'));
// var_dump($gameSession->convict("abcd", "qwes", 'p2'));
// var_dump($gameSession->convict("abcd", "qwes", 'p3'));
// var_dump($gameSession->convict("abcd", "qwes", 'p4'));
// var_dump($gameSession->convict("abcd", "qwes", 'p5'));
// var_dump($gameSession->convict("abcd", "qwes", 'p6'));

// var_dump($gameSession->gameSession);

// var_dump($gameSession->getGameSession());

// var_dump($gameSession->selectHintCard(2,3));
// var_dump($gameSession->selectHintCard(3,2));
// var_dump($gameSession->selectHintCard(4,1));
// var_dump($gameSession->selectHintCard(5,3));
// var_dump($gameSession->selectHintCard(1,3));
// var_dump($gameSession->selectHintCard(0,1));



// var_dump($gameSession->fsNextPhase("token"));
// var_dump($gameSession->fsNextPhase("token"));

// var_dump($gameSession->getGameData()['hintCards']);

// var_dump($gameSession->fsReplaceHintCard(3, "token"));

// var_dump($gameSession->getGameData()['hintCards']);

// var_dump($gameSession->fsNextPhase("token"));
// var_dump($gameSession->fsNextPhase("token"));
// var_dump($gameSession->fsNextPhase("token"));


// echo "</pre>";

// RESULT: 
// array(2) {
//   ["players"]=>
//   array(6) {
//     ["p1"]=>
//     array(8) {
//       ["id"]=>
//       string(2) "p1"
//       ["fd"]=>
//       string(1) "2"
//       ["accessToken"]=>
//       string(5) "token"
//       ["playerName"]=>
//       string(5) "Trung"
//       ["role"]=>
//       array(4) {
//         ["name"]=>
//         string(8) "Murderer"
//         ["codename"]=>
//         string(1) "M"
//         ["image"]=>
//         string(12) "murderer.jpg"
//         ["side"]=>
//         int(1)
//       }
//       ["isVoteable"]=>
//       bool(false)
//       ["evidenceCards"]=>
//       array(4) {
//         [0]=>
//         array(2) {
//           ["name"]=>
//           string(6) "Puzzle"
//           ["cardId"]=>
//           string(5) "7d000"
//         }
//         [1]=>
//         array(2) {
//           ["name"]=>
//           string(5) "Badge"
//           ["cardId"]=>
//           string(5) "3632a"
//         }
//         [2]=>
//         array(2) {
//           ["name"]=>
//           string(9) "Stockings"
//           ["cardId"]=>
//           string(5) "01bf4"
//         }
//         [3]=>
//         array(2) {
//           ["name"]=>
//           string(9) "Signature"
//           ["cardId"]=>
//           string(5) "2892b"
//         }
//       }
//       ["weaponCards"]=>
//       array(4) {
//         [0]=>
//         array(3) {
//           ["name"]=>
//           string(10) "Explosives"
//           ["imageUrl"]=>
//           string(14) "explosives.jpg"
//           ["cardId"]=>
//           string(5) "d7eda"
//         }
//         [1]=>
//         array(3) {
//           ["name"]=>
//           string(10) "Explosives"
//           ["imageUrl"]=>
//           string(14) "explosives.jpg"
//           ["cardId"]=>
//           string(5) "04d86"
//         }
//         [2]=>
//         array(3) {
//           ["name"]=>
//           string(5) "Drown"
//           ["imageUrl"]=>
//           string(9) "drown.jpg"
//           ["cardId"]=>
//           string(5) "c21c6"
//         }
//         [3]=>
//         array(3) {
//           ["name"]=>
//           string(10) "Ice Skates"
//           ["imageUrl"]=>
//           string(14) "ice-skates.jpg"
//           ["cardId"]=>
//           string(5) "e91fe"
//         }
//       }
//     }
//     ["p2"]=>
//     array(8) {
//       ["id"]=>
//       string(2) "p2"
//       ["fd"]=>
//       string(1) "3"
//       ["accessToken"]=>
//       string(5) "token"
//       ["playerName"]=>
//       string(4) "Hieu"
//       ["role"]=>
//       array(4) {
//         ["name"]=>
//         string(7) "Witness"
//         ["codename"]=>
//         string(1) "W"
//         ["image"]=>
//         string(11) "witness.jpg"
//         ["side"]=>
//         int(0)
//       }
//       ["isVoteable"]=>
//       bool(false)
//       ["evidenceCards"]=>
//       array(4) {
//         [0]=>
//         array(2) {
//           ["name"]=>
//           string(7) "Jewelry"
//           ["cardId"]=>
//           string(5) "63156"
//         }
//         [1]=>
//         array(2) {
//           ["name"]=>
//           string(5) "Bread"
//           ["cardId"]=>
//           string(5) "cb5eb"
//         }
//         [2]=>
//         array(2) {
//           ["name"]=>
//           string(14) "Cleaning Cloth"
//           ["cardId"]=>
//           string(5) "b55b2"
//         }
//         [3]=>
//         array(2) {
//           ["name"]=>
//           string(6) "Peanut"
//           ["cardId"]=>
//           string(5) "91801"
//         }
//       }
//       ["weaponCards"]=>
//       array(4) {
//         [0]=>
//         array(3) {
//           ["name"]=>
//           string(10) "Box Cutter"
//           ["imageUrl"]=>
//           string(14) "box-cutter.jpg"
//           ["cardId"]=>
//           string(5) "f09d9"
//         }
//         [1]=>
//         array(3) {
//           ["name"]=>
//           string(10) "Ice Skates"
//           ["imageUrl"]=>
//           string(14) "ice-skates.jpg"
//           ["cardId"]=>
//           string(5) "9f2fc"
//         }
//         [2]=>
//         array(3) {
//           ["name"]=>
//           string(6) "Amoeba"
//           ["imageUrl"]=>
//           string(10) "amoeba.jpg"
//           ["cardId"]=>
//           string(5) "4fa8e"
//         }
//         [3]=>
//         array(3) {
//           ["name"]=>
//           string(4) "Work"
//           ["imageUrl"]=>
//           string(8) "work.jpg"
//           ["cardId"]=>
//           string(5) "57055"
//         }
//       }
//     }
//     ["p3"]=>
//     array(8) {
//       ["id"]=>
//       string(2) "p3"
//       ["fd"]=>
//       string(1) "4"
//       ["accessToken"]=>
//       string(5) "token"
//       ["playerName"]=>
//       string(4) "Minh"
//       ["role"]=>
//       array(4) {
//         ["name"]=>
//         string(10) "Accomplice"
//         ["codename"]=>
//         string(1) "A"
//         ["image"]=>
//         string(14) "accomplice.jpg"
//         ["side"]=>
//         int(1)
//       }
//       ["isVoteable"]=>
//       bool(false)
//       ["evidenceCards"]=>
//       array(4) {
//         [0]=>
//         array(2) {
//           ["name"]=>
//           string(10) "Light Bulb"
//           ["cardId"]=>
//           string(5) "c74cd"
//         }
//         [1]=>
//         array(2) {
//           ["name"]=>
//           string(7) "Luggage"
//           ["cardId"]=>
//           string(5) "70765"
//         }
//         [2]=>
//         array(2) {
//           ["name"]=>
//           string(10) "Tea Leaves"
//           ["cardId"]=>
//           string(5) "5cdc6"
//         }
//         [3]=>
//         array(2) {
//           ["name"]=>
//           string(5) "Model"
//           ["cardId"]=>
//           string(5) "3352d"
//         }
//       }
//       ["weaponCards"]=>
//       array(4) {
//         [0]=>
//         array(3) {
//           ["name"]=>
//           string(6) "Pistol"
//           ["imageUrl"]=>
//           string(10) "pistol.jpg"
//           ["cardId"]=>
//           string(5) "c070e"
//         }
//         [1]=>
//         array(3) {
//           ["name"]=>
//           string(9) "Chemicals"
//           ["imageUrl"]=>
//           string(13) "chemicals.jpg"
//           ["cardId"]=>
//           string(5) "0cd01"
//         }
//         [2]=>
//         array(3) {
//           ["name"]=>
//           string(7) "Arsenic"
//           ["imageUrl"]=>
//           string(11) "arsenic.jpg"
//           ["cardId"]=>
//           string(5) "b1ebe"
//         }
//         [3]=>
//         array(3) {
//           ["name"]=>
//           string(12) "Packing Tape"
//           ["imageUrl"]=>
//           string(16) "packing-tape.jpg"
//           ["cardId"]=>
//           string(5) "5e3fb"
//         }
//       }
//     }
//     ["p4"]=>
//     array(8) {
//       ["id"]=>
//       string(2) "p4"
//       ["fd"]=>
//       string(1) "5"
//       ["accessToken"]=>
//       string(5) "token"
//       ["playerName"]=>
//       string(3) "Dat"
//       ["role"]=>
//       array(4) {
//         ["name"]=>
//         string(12) "Investigator"
//         ["codename"]=>
//         string(1) "I"
//         ["image"]=>
//         string(16) "investigator.jpg"
//         ["side"]=>
//         int(0)
//       }
//       ["isVoteable"]=>
//       bool(false)
//       ["evidenceCards"]=>
//       array(4) {
//         [0]=>
//         array(2) {
//           ["name"]=>
//           string(3) "Map"
//           ["cardId"]=>
//           string(5) "93593"
//         }
//         [1]=>
//         array(2) {
//           ["name"]=>
//           string(11) "Fingernails"
//           ["cardId"]=>
//           string(5) "c742e"
//         }
//         [2]=>
//         array(2) {
//           ["name"]=>
//           string(8) "Banknote"
//           ["cardId"]=>
//           string(5) "78285"
//         }
//         [3]=>
//         array(2) {
//           ["name"]=>
//           string(12) "Steamed Buns"
//           ["cardId"]=>
//           string(5) "4a757"
//         }
//       }
//       ["weaponCards"]=>
//       array(4) {
//         [0]=>
//         array(3) {
//           ["name"]=>
//           string(12) "Packing Tape"
//           ["imageUrl"]=>
//           string(16) "packing-tape.jpg"
//           ["cardId"]=>
//           string(5) "14d2a"
//         }
//         [1]=>
//         array(3) {
//           ["name"]=>
//           string(11) "Candlestick"
//           ["imageUrl"]=>
//           string(15) "candlestick.jpg"
//           ["cardId"]=>
//           string(5) "361c8"
//         }
//         [2]=>
//         array(3) {
//           ["name"]=>
//           string(7) "Surgery"
//           ["imageUrl"]=>
//           string(11) "surgery.jpg"
//           ["cardId"]=>
//           string(5) "caed1"
//         }
//         [3]=>
//         array(3) {
//           ["name"]=>
//           string(4) "Bury"
//           ["imageUrl"]=>
//           string(8) "bury.jpg"
//           ["cardId"]=>
//           string(5) "4e309"
//         }
//       }
//     }
//     ["p5"]=>
//     array(8) {
//       ["id"]=>
//       string(2) "p5"
//       ["fd"]=>
//       string(1) "4"
//       ["accessToken"]=>
//       string(5) "token"
//       ["playerName"]=>
//       string(3) "Hai"
//       ["role"]=>
//       array(4) {
//         ["name"]=>
//         string(12) "Investigator"
//         ["codename"]=>
//         string(1) "I"
//         ["image"]=>
//         string(16) "investigator.jpg"
//         ["side"]=>
//         int(0)
//       }
//       ["isVoteable"]=>
//       bool(false)
//       ["evidenceCards"]=>
//       array(4) {
//         [0]=>
//         array(2) {
//           ["name"]=>
//           string(4) "Soap"
//           ["cardId"]=>
//           string(5) "fe690"
//         }
//         [1]=>
//         array(2) {
//           ["name"]=>
//           string(4) "Ring"
//           ["cardId"]=>
//           string(5) "68c63"
//         }
//         [2]=>
//         array(2) {
//           ["name"]=>
//           string(3) "Rat"
//           ["cardId"]=>
//           string(5) "9ca64"
//         }
//         [3]=>
//         array(2) {
//           ["name"]=>
//           string(6) "Coffee"
//           ["cardId"]=>
//           string(5) "1acb1"
//         }
//       }
//       ["weaponCards"]=>
//       array(4) {
//         [0]=>
//         array(3) {
//           ["name"]=>
//           string(3) "Bat"
//           ["imageUrl"]=>
//           string(7) "bat.jpg"
//           ["cardId"]=>
//           string(5) "22b93"
//         }
//         [1]=>
//         array(3) {
//           ["name"]=>
//           string(13) "Poisonous Gas"
//           ["imageUrl"]=>
//           string(17) "poisonous-gas.jpg"
//           ["cardId"]=>
//           string(5) "fc993"
//         }
//         [2]=>
//         array(3) {
//           ["name"]=>
//           string(6) "Trowel"
//           ["imageUrl"]=>
//           string(10) "trowel.jpg"
//           ["cardId"]=>
//           string(5) "a4103"
//         }
//         [3]=>
//         array(3) {
//           ["name"]=>
//           string(10) "Box Cutter"
//           ["imageUrl"]=>
//           string(14) "box-cutter.jpg"
//           ["cardId"]=>
//           string(5) "dbc29"
//         }
//       }
//     }
//     ["p6"]=>
//     array(8) {
//       ["id"]=>
//       string(2) "p6"
//       ["fd"]=>
//       string(1) "5"
//       ["accessToken"]=>
//       string(5) "token"
//       ["playerName"]=>
//       string(5) "Thanh"
//       ["role"]=>
//       array(4) {
//         ["name"]=>
//         string(18) "Forensic Scientist"
//         ["codename"]=>
//         string(2) "FS"
//         ["image"]=>
//         string(22) "forensic-scientist.jpg"
//         ["side"]=>
//         int(99)
//       }
//       ["isVoteable"]=>
//       bool(false)
//       ["evidenceCards"]=>
//       array(4) {
//         [0]=>
//         array(2) {
//           ["name"]=>
//           string(15) "Invitation Card"
//           ["cardId"]=>
//           string(5) "7c753"
//         }
//         [1]=>
//         array(2) {
//           ["name"]=>
//           string(8) "Graffiti"
//           ["cardId"]=>
//           string(5) "e61f4"
//         }
//         [2]=>
//         array(2) {
//           ["name"]=>
//           string(7) "Bandage"
//           ["cardId"]=>
//           string(5) "6eb47"
//         }
//         [3]=>
//         array(2) {
//           ["name"]=>
//           string(4) "Bone"
//           ["cardId"]=>
//           string(5) "d57ce"
//         }
//       }
//       ["weaponCards"]=>
//       array(4) {
//         [0]=>
//         array(3) {
//           ["name"]=>
//           string(9) "Pesticide"
//           ["imageUrl"]=>
//           string(13) "pesticide.jpg"
//           ["cardId"]=>
//           string(5) "955c6"
//         }
//         [1]=>
//         array(3) {
//           ["name"]=>
//           string(13) "Bite And Tear"
//           ["imageUrl"]=>
//           string(17) "bite-and-tear.jpg"
//           ["cardId"]=>
//           string(5) "b5b65"
//         }
//         [2]=>
//         array(3) {
//           ["name"]=>
//           string(7) "Lighter"
//           ["imageUrl"]=>
//           string(11) "lighter.jpg"
//           ["cardId"]=>
//           string(5) "392b5"
//         }
//         [3]=>
//         array(3) {
//           ["name"]=>
//           string(4) "Push"
//           ["imageUrl"]=>
//           string(8) "push.jpg"
//           ["cardId"]=>
//           string(5) "26b4a"
//         }
//       }
//     }
//   }
//   ["gameData"]=>
//   array(5) {
//     ["hintCards"]=>
//     array(3) {
//       ["deathLocations"]=>
//       array(2) {
//         ["slots"]=>
//         array(6) {
//           [0]=>
//           string(11) "Living Room"
//           [1]=>
//           string(7) "Bedroom"
//           [2]=>
//           string(9) "Storeroom"
//           [3]=>
//           string(8) "Bathroom"
//           [4]=>
//           string(7) "Kitchen"
//           [5]=>
//           string(7) "Balcony"
//         }
//         ["selected"]=>
//         NULL
//       }
//       ["causeOfDeath"]=>
//       array(2) {
//         ["slots"]=>
//         array(6) {
//           [0]=>
//           string(11) "Suffocation"
//           [1]=>
//           string(13) "Severe Injury"
//           [2]=>
//           string(13) "Loss of Blood"
//           [3]=>
//           string(16) "Illness/ Disease"
//           [4]=>
//           string(9) "Poisoning"
//           [5]=>
//           string(8) "Accident"
//         }
//         ["selected"]=>
//         NULL
//       }
//       ["normalHints"]=>
//       array(4) {
//         [0]=>
//         array(3) {
//           ["hintName"]=>
//           string(16) "Victim's Clothes"
//           ["slots"]=>
//           array(6) {
//             [0]=>
//             string(4) "Neat"
//             [1]=>
//             string(6) "Untidy"
//             [2]=>
//             string(7) "Elegant"
//             [3]=>
//             string(6) "Shabby"
//             [4]=>
//             string(7) "Bizarre"
//             [5]=>
//             string(5) "Naked"
//           }
//           ["selected"]=>
//           NULL
//         }
//         [1]=>
//         array(3) {
//           ["hintName"]=>
//           string(19) "Social Relationship"
//           ["slots"]=>
//           array(6) {
//             [0]=>
//             string(9) "Relatives"
//             [1]=>
//             string(7) "Friends"
//             [2]=>
//             string(10) "Colleagues"
//             [3]=>
//             string(18) "Employer/ Employee"
//             [4]=>
//             string(6) "Lovers"
//             [5]=>
//             string(9) "Strangers"
//           }
//           ["selected"]=>
//           NULL
//         }
//         [2]=>
//         array(3) {
//           ["hintName"]=>
//           string(14) "Hint on Corpse"
//           ["slots"]=>
//           array(6) {
//             [0]=>
//             string(4) "Head"
//             [1]=>
//             string(5) "Chest"
//             [2]=>
//             string(4) "Hand"
//             [3]=>
//             string(3) "Leg"
//             [4]=>
//             string(7) "Partial"
//             [5]=>
//             string(8) "All-over"
//           }
//           ["selected"]=>
//           NULL
//         }
//         [3]=>
//         array(3) {
//           ["hintName"]=>
//           string(4) "Neat"
//           ["slots"]=>
//           array(5) {
//             [0]=>
//             string(6) "Untidy"
//             [1]=>
//             string(7) "Elegant"
//             [2]=>
//             string(6) "Shabby"
//             [3]=>
//             string(7) "Bizarre"
//             [4]=>
//             string(5) "Naked"
//           }
//           ["selected"]=>
//           NULL
//         }
//       }
//     }
//     ["currentPhase"]=>
//     string(12) "Phase1Murderer"
//     ["murdererPick"]=>
//     array(2) {
//       ["weaponId"]=>
//       NULL
//       ["evidenceId"]=>
//       NULL
//     }
//     ["goodSideLeft"]=>
//     int(0)
//     ["winSide"]=>
//     int(1)
//   }
// }