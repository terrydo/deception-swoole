<?php 
	require_once 'v-socket-server.php';
	require_once 'utils/index.php';

	require_once 'data/game-session.php';

	class DeceptionServer extends VSocketServer
	{
		private $roomList = array();

		private function getConnectionIDsInRoom($roomId){
			if (!isset($this->roomList[$roomId])) return;

			$room = $this->roomList[$roomId];

			$connectionInRooms = array();

			foreach ($room['joinedClients'] as $player) {
				$playerId = $player['clientId'];
				isset($this->connectionIDs[$playerId]) && array_push($connectionInRooms, $this->connectionIDs[$playerId]);
			}

			return $connectionInRooms;
		}

		private function getGameSession($roomId){
			return $this->roomList[$roomId]['gameSession'];
		}

		private function getPayload($payload, $key){
			return isset($payload->$key) ? $payload->$key : null;
		}

		private function roomExist($roomId = null){
			if (!$roomId) return false;
			return isset($this->roomList[$roomId]);
		}
		/**
		 * Override
		 */
		protected function handleClose(){  
	        $this->on('close', function ($ser, $fd) {
	            foreach ($this->connectionIDs as $clientId => $clientFd) {
	                if ($clientFd == $fd) unset($this->connectionIDs[$clientId]);
	            }
	            foreach ($this->roomList as $roomId => &$room) {
	            	foreach ($room['joinedClients'] as &$client) {
	            		if ($fd == $client['fd']) {
	            			// Create fake player request to quit room
	            			$payload = new stdClass(); 
	            			$payload->clientId 	= $client['clientId'];
	            			$payload->roomId 	= $roomId;
	
	            			$this->quitRoom($payload);
	            		}
	            	}
	            }
	        });
	    }

		public function registerPlayer($payload, $frame){
			$clientId = $payload->clientId;

			echo $payload->playerName;

			if (empty($playerName = $payload->playerName)) return;

			$this->clientsData[$clientId]['playerName'] = $playerName;
			$this->clientsData[$clientId]['clientId'] 	= $clientId;

			return $this->clientsData[$clientId];
		}

		public function getRooms(){
			$roomList = $this->roomList;
			foreach ($roomList as &$room) {
				unset($room['gameSession']);
			}
			return $roomList;
		}

		/**
		 * Get room's information by id
		 */
		public function getRoom($payload){
			$roomId = $payload->roomId;

			if (!$roomId || !isset($this->roomList[$roomId])) return;

			$room 	= $this->roomList[$roomId];
			unset($room['gameSession']);
			return $this->roomList[$roomId];
		}

		protected function roomBroadcast($roomId, $action, $data = null, $excludeIds = array()){
			$this->broadcast($action, $this->getConnectionIDsInRoom($roomId), $excludeIds, $data);
		}

		protected function roomBroadcastMessage($roomId, $message){
			$this->broadcast("showMessage", $this->getConnectionIDsInRoom($roomId), null, array("message" => $message));
		}

		public function quitRoom($payload, $frame = null){
			$roomId 	= $payload->roomId;
			$clientId 	= $payload->clientId;

			if (!$roomId 
				|| !$clientId 
				|| !$this->roomExist($roomId)
				|| !isset($this->clientsData[$clientId])
				|| !isset($this->clientsData[$clientId]['playerName'])
			) return false;

			$room = &$this->roomList[$roomId];

			// Last player quit room
			if (!empty($room['joinedClients']) 
				&& sizeof($room['joinedClients']) <= 1
			){
				if (sizeof($this->roomList) <= 1){
					$this->roomList = array();
				}
				else unset($this->roomList[$roomId]);
				
				// If frame is set => player clicks Back button on browser else player closed his connection.
				if ($frame){
			    	$this->broadcast("getRooms", null, array($frame->fd), $roomId);
				}
			}

			else {
				if ($room['hostId'] == $clientId){
					$room['hostId'] 	= end($room['joinedClients'])['clientId'];
					$room['hostName'] 	= end($room['joinedClients'])['playerName'];	
				}
				unset($room['joinedClients'][$clientId]);
				$this->roomBroadcast($roomId, "getRoom");
			} 
		}

		public function createRoom($payload){
			$hostId = $payload->clientId;

			$roomId = Utils::randomHash();

			$this->roomList[$roomId] = array(
				'hostId' 		=> $hostId,
				'roomId'		=> $roomId,
				'hostName'		=> $this->clientsData[$hostId]['playerName'],
				'joinedClients' => array(),
				'gameSession' 	=> null,
				'isStarted'		=> false,
			);
			$this->broadcast("getRooms");
		}

		public function joinRoom($payload){

			$roomId 	= $payload->roomId;
			$clientId 	= $payload->clientId;

			if (!$roomId 
				|| !$this->roomExist($roomId) 
				|| empty($this->clientsData[$clientId]) 
				|| empty($this->clientsData[$clientId]['playerName'])
			) return false;

			$this->roomList[$roomId]['joinedClients'][$payload->clientId] = $this->clientsData[$payload->clientId];

			$this->roomBroadcast($roomId, "getRoom");

			return true;
		}

		public function startRoom($payload, $frame){
			$roomId = $payload->roomId;

			$result = [];

			if (!$roomId || !isset($this->roomList[$roomId])) {
				$result['msg'] = 'Room doesn\'t exist';
				return $result;
			}

			if (sizeof($this->roomList[$roomId]['joinedClients']) < 4){
				$result['msg'] = "It's required to have at least 4 players to start the game.";
				return $result;
			}

			$room = &$this->roomList[$roomId];

			$room['isStarted'] 	= true;

			$room['gameSession'] = new GameSession($room['joinedClients']);

			$this->roomBroadcast($roomId, "getAccessToken");
		}

		public function resetRoom($payload, $frame){
			$roomId 		= $this->getPayload($payload, "roomId");
			$this->roomList[$roomId]['gameSession'] = null;
			$this->roomList[$roomId]['isStarted'] 	= false;

			$this->roomBroadcast($roomId, "resetRoom");
		}

		public function getAccessToken($payload, $frame){
			$gameSession = $this->getGameSession($payload->roomId);
			$token = $gameSession->getAccessToken($frame->fd);
			echo "token: " . $token;
			return $token;
		}

		public function getGameData($payload){
			$gameSession = $this->getGameSession($payload->roomId);
			$accessToken = $this->getPayload($payload, "accessToken");
			return $gameSession->getGameData($accessToken);
		}

		public function getOtherPlayersData($payload){
			$gameSession = $this->getGameSession($payload->roomId);

			$clientId 		= $this->getPayload($payload, "clientId");
			$accessToken 	= $this->getPayload($payload, "accessToken");

			if (!$clientId || !$accessToken) return;

			return $gameSession->getOtherPlayersData($clientId, $accessToken);
		}

		public function getPlayerData($payload){
			$gameSession = $this->getGameSession($payload->roomId);

			return $gameSession->getPlayerData($payload->clientId, $payload->accessToken);
		}

		public function murdererPickConfirm($payload){

			$roomId 		= $this->getPayload($payload, "roomId");
			
			$pickedCards 	= $this->getPayload($payload, "pickedCards");

			$accessToken 	= $this->getPayload($payload, "accessToken");

			if (!$pickedCards || !$accessToken || !$roomId) return;

			$gameSession 	= $this->getGameSession($roomId);

			$gameSession->murdererPick($pickedCards[0], $pickedCards[1], $accessToken);

			$this->roomBroadcast($roomId, "refreshGameData");
		}

		public function murdererWitnessChoose($payload){
			$roomId 		= $this->getPayload($payload, "roomId");
			
			$targetId 		= $this->getPayload($payload, "targetId");

			$accessToken 	= $this->getPayload($payload, "accessToken");

			if (!$roomId || !$targetId || !$accessToken) return;

			$gameSession 	= $this->getGameSession($roomId);

			$result = $gameSession->murdererWitnessChoose($targetId, $accessToken);

			if ($result === true){
				$this->roomBroadcastMessage($roomId, "Murderer has killed the Witness!");
			}
			else if ($result === false){
				$this->roomBroadcastMessage($roomId, "Murderer has failed.");
			}

			$this->roomBroadcast($roomId, "refreshGameData");
		}

		public function pingCard($payload){
			$roomId 		= $this->getPayload($payload, "roomId");
			
			$pingedCard 	= $this->getPayload($payload, "pingedCard");

			$accessToken 	= $this->getPayload($payload, "accessToken");

			if (!$pingedCard || !$roomId || !$accessToken) return;

			$gameSession = $this->getGameSession($roomId);

			$receiverId = $gameSession->pingCard($accessToken);

			if ($receiverId){
				$this->broadcast("getPingedCard", array($this->connectionIDs[$receiverId]), null, array('pingedCard' => $pingedCard));
			}
		}

		public function convict($payload){
			$roomId 		= $this->getPayload($payload, "roomId");
			$weaponId 		= $this->getPayload($payload, "weaponId");
			$evidenceId 	= $this->getPayload($payload, "evidenceId");
			$accessToken 	= $this->getPayload($payload, "accessToken");
			$voterId 		= $this->getPayload($payload, "clientId");
			
			if (!$weaponId || !$evidenceId || !$accessToken || !$voterId || !$roomId) return;

			$gameSession 	= $this->getGameSession($roomId);

			$result = $gameSession->convict($weaponId, $evidenceId, $voterId, $accessToken);

			$this->roomBroadcast($roomId, "refreshAllGameData");

			$voterName = $this->clientsData[$voterId]['playerName'];

			if ($result){
				$this->broadcast("showMessage", $this->getConnectionIDsInRoom($roomId), null, array("message" => $voterName . " has convicted successfully!"));	
			}
			else {
				$this->broadcast("showMessage", $this->getConnectionIDsInRoom($roomId), null, array("message" => $voterName . " has convicted wrong person, thus lost the voting right."));
			}
			$this->roomBroadcast($roomId, "getGameData");
		}

		public function nextPhase($payload){
			$roomId 		= $this->getPayload($payload, "roomId");

			$accessToken 	= $this->getPayload($payload, "accessToken");

			if (!$roomId || !$accessToken) return;

			$gameSession 	= $this->getGameSession($roomId);
			$gameSession->fsNextPhase($accessToken);
			$this->roomBroadcast($roomId, "refreshGameData");
		}

		public function selectHintCard($payload){
			$roomId 	= $this->getPayload($payload, "roomId");
			$cardIndex 	= $this->getPayload($payload, "cardIndex");
			$slotIndex 	= $this->getPayload($payload, "slotIndex");
			$accessToken= $this->getPayload($payload, "accessToken");

			if (!$roomId || $cardIndex === false || $slotIndex === false || !$accessToken) return;

			$gameSession = $this->getGameSession($roomId);

			if ($gameSession->selectHintCard($cardIndex, $slotIndex, $accessToken)){
				$this->roomBroadcast($roomId, "refreshGameData");
			}
			
		}

		public function changeHintCard($payload){
			$roomId 			= $this->getPayload($payload, "roomId");
			$replaceCardIndex 	= $this->getPayload($payload, "cardIndex");
			$accessToken 		= $this->getPayload($payload, "accessToken");

			if (!$roomId || $replaceCardIndex === false || !$accessToken) return;

			$gameSession = $this->getGameSession($roomId);

			$gameSession->fsReplaceHintCard($replaceCardIndex, $accessToken);

			$this->roomBroadcast($roomId, "refreshGameData");
		}
	}
?>