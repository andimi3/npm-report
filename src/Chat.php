<?php
namespace MyApp;
require dirname(__DIR__) . '../vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {

    protected $clients;

    protected $employee; //this is a secret value

    protected $employeResourceId;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {

        echo "server hit";

        //get query parameter "secret" <--- ideally saved on server
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $querryArray);

        $querryArray = $querryArray["employee"];

        //if i employee connecting, attach and return
        if($querryArray == "employee") { 
            $this->employee = $querryArray; 
            $this->clients->attach($conn, $querryArray); 
            $this->employeResourceId = $conn->resourceId;
            $conn->send("employeeConnected-".$conn->resourceId); 
            return; 
        }

        //if employee is online..send employee the client info and send client there resource id(not used for anything)
        foreach ($this->clients as $client) {

            if($this->clients[$client] == "employee") { 
                $this->clients->attach($conn, $querryArray); //attaching name here... dont really have to
                $client->send("addToArray-".$conn->resourceId."-".$querryArray);
                echo "New connection! ({$conn->resourceId})\n";
                return; 
            }

        }

            //if there is no employee online, send client message saying the following
            $conn->send("Unfortunatley, there is no employee online to answer your questions..."); 
            $conn->close(); 

    }

    public function onMessage(ConnectionInterface $from, $msg) {

        echo $msg;

        //this top piece is only used for the purpose of the clientId from the client
        $clientId = null;
        $myMessage = null;
        $ifEmployeeMessage = explode("-", $msg);

        //if message coming from employee
        if($ifEmployeeMessage[2] && $ifEmployeeMessage[2] == $this->employee) {
            $myMessage = $ifEmployeeMessage[0];
            $clientId = $ifEmployeeMessage[1];
        }    

        foreach ($this->clients as $client) {

            //if message from client send to client
            if ($from->resourceId == $client->resourceId && $client->resourceId !== $this->employeResourceId) { 
                $client->send($msg."-fromThem");
            }

            //if message from me, send to client via there client id
            if($clientId == $client->resourceId) {
                $client->send($myMessage."-fromMe");
            }

            //send every message back to me from both
            if($this->clients[$client] == $this->employee) {

                //coming from them
                if($clientId == null) {
                    $client->send($msg."-".$from->resourceId."-fromThem"); //resource id for pushing elem on screen (could add name here)
                }

                //coming from me
                if($clientId !== null) {
                    $client->send($myMessage."-".$clientId."-".$from->resourceId);
                }

            }

        }

    }

    public function onClose(ConnectionInterface $conn) {

        //if employee disconnects, disconnect everyone
        if($conn->resourceId == $this->employeResourceId) {
            foreach ($this->clients as $client) {
                    $this->clients->detach($client);
            }
        }

        //if client disconnects while employee online, send employee resource id to update dasboard
        foreach ($this->clients as $client) {
            if($this->clients[$client] == "employee") { 
                $client->send("removeFromArray-".$conn->resourceId);
                $this->clients->detach($conn);
                return;
            }
        }

        echo "error: employee not online and person disconnected OR THIS IS THERE FIRST ATTEMPT CONNECTION";
        $this->clients->detach($conn);

    }

    public function onError(ConnectionInterface $conn, \Exception $e) {

        //if employee disconnects, disconnect everyone
        if($conn->resourceId == $this->employeResourceId) {
            foreach ($this->clients as $client) {
                    $this->clients->detach($client);
            }
        }

        //if client error, rmeove array on employee end and update dashboard
        foreach ($this->clients as $client) {
            if($this->clients[$client] == "employee") { 
                $client->send("removeFromArray-".$conn->resourceId);
                $conn->close();
                return;
            }
        }

        echo "error: employee not online and person disconnected OR THIS IS THERE FIRST ATTEMPT CONNECTION";
        $conn->close();

    }

}
?>