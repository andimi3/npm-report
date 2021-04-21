<?php
namespace MyApp;
require dirname(__DIR__) . '../vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

//if you want to do this the usual way... add { x:y,x:y,x:y } inside of spl object (this is just a tut and i am not using a lot of data because i do not need to)
//also, if you want the standard for creating rooms, use wamp server. its much easier
//also, session data can be passed either by the sessid from your http server and generated on your tcp server(here) for insertions OR just use symphony. they have a built in library for grabbing sessions OR use zeromq(or something like that) to broadcast to your tcp server from your http server where you can pass in your sessions[dont quote me on this]. however this uses a conn->resource id that is hidden
//if you want to store the messages in a db as you go, you could just insert first, grab last id, check on pull if its you, push db last_id to socket message. then you can remove that message value...(ajax or zeroMq or whatever its called)
//only down side to this is the "-" <-- you wouldnt use this traditionally but it works here. just escape if they pass that in as there name on the client or add some code to let them. or just change the value to some long passcode instead of "-".

class Chat implements MessageComponentInterface {

    protected $clients;

    protected $employee; //this is a secret value

    protected $employeResourceId;

    public function __construct() {
        $this->clients = new \SplObjectStorage;

        //
        // { conn, employee } <-- me
        // {conn, ""} <-- them
        //
        //
    }

    public function onOpen(ConnectionInterface $conn) {

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

        //this top piece is only used for the purpose of the clientId from the client
        $clientId = null;
        $myMessage = null;
        $ifEmployeeMessage = explode("-", $msg);

        //if removing singleClientConnection
        if($ifEmployeeMessage[0] == "removeSingleClient") {
           $this->clients[$ifEmployeeMessage[1]]->send("remove this client");
           return;
        }         

        //if message coming from employee
        if(isset($ifEmployeeMessage[2]) && $ifEmployeeMessage[2] == $this->employee) {
            $myMessage = $ifEmployeeMessage[0];
            $clientId = $ifEmployeeMessage[1];
        }    


        //replace below loop with pointing instead. whoops
        





        //so you dont need to loop here ... you can just point to their resource id... /: doesnt matter
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
                $client->close();
                $client->send("remove this client");
            }
            return;
        }

        //if client disconnects while employee online, send employee resource id to update dasboard
        foreach ($this->clients as $client) {
            if($this->clients[$client] == "employee") { 
                $client->send("removeFromArray-".$conn->resourceId);
                $this->clients->detach($conn);
                $conn->close();
                $client->send("remove this client");
                return;
            }
        }

        echo "error: employee not online and person disconnected OR THIS IS THERE FIRST ATTEMPT CONNECTION";

    }

    public function onError(ConnectionInterface $conn, \Exception $e) {

        //if employee disconnects, disconnect everyone
        if($conn->resourceId == $this->employeResourceId) {
            foreach ($this->clients as $client) {
                    $this->clients->detach($client);
                    $client->close();
                    $client->send("remove this client");
            }
            return;
        }

        //if client error, rmeove array on employee end and update dashboard
        foreach ($this->clients as $client) {
            if($this->clients[$client] == "employee") { 
                $client->send("removeFromArray-".$conn->resourceId);
                $conn->close();
                $client->send("remove this client");
                return;
            }
        }

        echo "error: employee not online and person disconnected OR THIS IS THERE FIRST ATTEMPT CONNECTION";
        $conn->close();

    }

}
?>