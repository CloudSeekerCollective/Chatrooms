<?php
if(!($sock = socket_create(AF_INET, SOCK_STREAM, 0)))
{
	perror("Could not create socket");
}
echo "Socket created \n";

//Connect socket to remote server
if(!socket_connect($sock , '127.0.0.1' , 80))
{
	perror("Could not connect");
}
echo "Connection established \n";

$message = "GET / HTTP/1.1rnrn";
//Send the message to the server
if( ! socket_send ( $sock , $message , strlen($message) , 0))
{
    perror("Could not send data");
}
echo "Message send successfully \n";

//Now receive reply from server
if(socket_recv ( $sock , $buf , 500 , MSG_WAITALL ) === FALSE)
{
    perror("Could not receive data");
}
echo $buf;

///Function to print socket error message 
function perror($msg)
{
	$errorcode = socket_last_error();
    $errormsg = socket_strerror($errorcode);
    
    die("$msg: [$errorcode] $errormsg \n");
}
