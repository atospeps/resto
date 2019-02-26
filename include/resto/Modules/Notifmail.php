<?php
// Destinataire

//class Notifmail extends RestoModule{
    /*
     * Resto context
     */
  //  public $context;
    
    /*
     * Current user (only set for administration on a single user)
     */
   // public $user = null;
    
    /*
     * segments
     */
   // public $segments;
    
    /*
     * Database handler
     */
 //   private $dbh;
    
    
    
    /**
     * Constructor
     * 
     * @param RestoContext $context
     * @param RestoUser $user
     */
    
  /*  public function __construct($context, $user) {
        parent::__construct($context, $user);
        
        // Set user
        $this->user = $user;
        
        // Set context
        $this->context = $context;
        
        // Database handler
        $this->dbh = $this->getDatabaseHandler();
    }
    
    public function run($segments, $data = array())
    {
        
        if ($this->user->profile['userid'] == -1) {
            RestoLogUtil::httpError(401);
        }
    }
    
    
    
private function sendMailNotif($params){
    
    
    // Only admin users can notify users of the publication of new products
    if (!$this->user->isAdmin()) {
        RestoLogUtil::httpError(403);
    }
    
    
    
    $query = "SELECT  u.email, j.userid, j.status , j.acknowledge, j.notifmail"
        . " FROM usermanagement.jobs j"
            . " INNER JOIN usermanagement.users u"
                . " ON u.userid=j.userid WHERE u.activated=1 AND  j.notifmail = true AND j.acknowledge = FALSE AND (j.status = 'ProcessSucceeded' OR j.status = 'ProcessFailed')" ;
                
                $jobsnotifs = pg_query($this->dbh, $query);
                if (!$jobsnotifs){
                    throw new Exception("Jobs Drive - An unexpected error has occurred. $query", 500);
                }
                
                while ($row = pg_fetch_assoc($jobsnotifs)) {
                    
                    $this->sendMail(array(
                        'to' => $row['email'],
                        'senderName' => $this->context->mail['senderName'],
                        'senderEmail' => $this->context->mail['senderEmail'],
                        'subject' => 'object mail',
                        'message' => 'le corps du message'
                    ));
                    
                    
                    
                }             
                
                
                
    
    
    
 
}

    
    
    
}*/
    



 $to = "awa.dia@atos.net";
 // Sujet
 $subject = 'Notification par mail de la fin du traitement ';
 
 // Message
 $message = '
 <html>
 <head>
 <title>Notification par mail de la fin du traitement</title>
 </head>
 <body>
 <table width="100%" border="0" cellspacing="0" cellpadding="5">
 <tr>
 <td align="center">

 <p>


 Bonjour,        <br><br>
 Votre taritement est complet    <br><br>
 Pour y acceder veuillez vous connectez sur Peps <br><br>
 Cordialement        <br><br> 
 Equipe resto 


</p>
 </td>
 </tr>
 </table>
 </body>
 </html>
 ';
 
 $rn = "\r\n";
 $uid = md5(uniqid(time()));
 // En-têtes
 $headers = "MIME-Version: 1.0" . "\n";
 $headers .= "Content-type: text/html; charset=utf-8" . $rn;
 
 // En-têtes additionnels
 $headers .= 'From: admin <restoadmin@localhost.localdomain>' . $rn;
 $headers .= 'X-Mailer: PHP/' . phpversion() . $rn;
 $headers .= 'X-Priority: 3' . $rn;
 $headers .= 'MIME-Version: 1.0' . $rn;
 $headers .= 'Content-Type: multipart/mixed; boundary="' . $uid .'"' . $rn;
 
 // Envoie
 $resultat = mail($to, $subject, $message, $headers);
 ?>
 
 
 
    
    


