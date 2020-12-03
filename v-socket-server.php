<?php

require_once 'utils/index.php';

class VSocketServer extends swoole_websocket_server
{
    // Map clientId with connection Id
    protected $connectionIDs = array();

    // Clients might have different connection id but always one data.
    protected $clientsData = array();

    final protected function handleOpen(){
        $this->on('open', function (swoole_websocket_server $server, $request) {

            if (
                !empty($clientId = $request->get['clientId']) 
                && isset($this->clientsData[$clientId]) 
            ) {
                $response = array('clientData' => $this->clientsData[$clientId]);
                $response['clientData']['fd'] = $request->fd;

                $this->response($request->fd, $response);

                // Reassign connection id to that client
                $this->connectionIDs[$clientId]         = $request->fd;
                $this->clientsData[$clientId]['fd']     = $request->fd;
                return;
            }

            $clientId = \Utils::randomHash(10);

            // Reassign connection id to that client
            $this->connectionIDs[$clientId]         = $request->fd;

            echo "Server: handshake success with fd{$request->fd}\n";

            $this->clientsData[$clientId] = array(
                'clientId'          => $clientId,
                'fd'                => $request->fd,
                'connectionTime'    => time(),
            );

            $response = array('clientData' => $this->clientsData[$clientId]);

            $this->response($request->fd, $response);
        });
    }

    final protected function handleMessage(){
        $this->on('message', function (swoole_websocket_server $server, $frame) {

            if (!$parsedClientData = \Utils::parseJson($frame->data)){
                echo "Invalid JSON";
                return $this->response($frame->fd, array('error' => 'You must emit client\'s data as JSON!'));
            }

            if (!isset($parsedClientData->action)){
                echo "No action was passed.";
                return $this->response($frame->fd, array('error' => 'No action was passed.'));
            }

            if (empty($parsedClientData->payload->clientId)){
                echo "Invalid client Id.";
                return $this->response($frame->fd, array('error' => 'Invalid client Id.'));
            }

            $submittedFunc = $parsedClientData->action;

            echo $submittedFunc . ' from ' . $frame->fd . "\n";

            if (method_exists($this, $submittedFunc))
            {
                $reflection = new ReflectionMethod($this, $submittedFunc);

                if (!$reflection->isPublic()) {
                    echo "Malicious action from client #" . $frame->fd . "\n";
                    return;
                }

                $payload = $parsedClientData->payload;

                $responseData = $this->$submittedFunc($payload, $frame);

                $result = array('submittedFunc' => $submittedFunc);

                $result['responseData'] = $responseData;
                $this->response($frame->fd, $result);
                
                return;
            }

            return $this->response($frame->fd, array('error' => 'Invalid action ' . $submittedFunc));
            
        });
    }

    protected function handleClose(){  
        $this->on('close', function ($ser, $fd) {
            echo "Client {$fd} closed\n";
            foreach ($this->connectionIDs as $clientId => $clientFd) {
                if ($clientFd == $fd) unset($this->connectionIDs[$clientId]);
            }
        });
    }

    /**
     * Send data to a client
     * @param  [int] $id   [client's id]
     * @param [mixed] $data
     */
    final protected function response($id, $data = array()){
        $this->push($id, json_encode($data));
    }

    /**
     * Request all VSocket client to run a function.
     * @param  [string] $broadcastFunc [Name of the function which is going to run in client-side]
     * @param  [array] $includeId [List of receiving clients' ids]
     * @param  [array] $excludeId [List of excluded clients' ids]
     * @param  [string] $data
     */
    final protected function broadcast($broadcastFunc, $includeId = array(), $excludeId = array(), $data = null){
        $response = array(
            'broadcastFunc' => $broadcastFunc,
            'broadcastFuncData' => $data,
        );

        if (!empty($includeId) && is_array($includeId) && sizeof($includeId) > 0){
            foreach ($includeId as $id) {
                $this->response($id, $response);
            }
            return;
        }

        foreach ($this->connectionIDs as $id) {
            if (!empty($excludeId) and in_array($id, $excludeId)) {
                echo "Exclude #" . $id;
                continue;
            }
            $this->response($id, $response);
        }
    }

    /********************
     * PUBLIC FUNCTIONS *
     ********************/
    public function start(){
        $this->handleOpen();
        $this->handleMessage();
        $this->handleClose();
        parent::start();
    }

    public function getClientData($payload){
        return $this->clientsData[$payload->clientId];
    }

}