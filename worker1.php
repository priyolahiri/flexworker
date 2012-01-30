<?php
require_once 'swift_required.php';
include (__DIR__.'/config.php');
//script starts
echo "Starting\n"; 
# Create our worker object.
$gmworker = new GearmanWorker();
 
# Add default server (localhost).
$gmworker->addServer($gearserver);
 
# Register function "reverse" with the server. Change the worker function to
# "reverse_fn_fast" for a faster worker with no output.
$gmworker->addFunction("mailsend", "mailout");
print "Waiting for job...\n";
while($gmworker->work())
{
	if ($gmworker->returnCode() != GEARMAN_SUCCESS)
  	{
    	echo "return_code: " . $gmworker->returnCode() . "\n";
   		break;
  	}
}

function mailout($job) {
	$jobdetails = json_decode($job->workload());
	//var_dump($jobdetails);
	$dest = $jobdetails->dest;
	$sender = $jobdetails->sender;
	$html = $jobdetails->html;
	$text = $jobdetails->text;
	$subject = $jobdetails->subject;
	$campaign = $jobdetails->campaign;
	if ($dest == "") {
		echo ("skipping mail as dest invalid \n");
		return "skipping";
	}
	echo ("attempting mail: $dest -> $sender -> $subject -> $campaign \n");
	try {
		$message = Swift_Message::newInstance()
  		//Give the message a subject
  		->setSubject($subject)
  		// Set the From address with an associative array
  		->setFrom(array($sender => ''))
  		// Set the To addresses with an associative array
  		->setTo(array($dest => ''))
  		// Give it a body
  		->setBody($html, 'text/html')
  		// And optionally an alternative body
  		->addPart($text, 'text/plain');
	} catch (Exception $e) {
		$mailedout = false;
		$error = $e->getMessage();
	}
	$transport = Swift_SmtpTransport::newInstance($mailserver, $mailport, $mailauth)
	->setUsername($mailuser)->setPassword($mailpass);
	// Create the Mailer using your created Transport
	$mailer = Swift_Mailer::newInstance($transport);
	if (!$mailedout) {
	 try {
		$mailer->send($message);
	 } catch (Exception $e) {
		$mailedout = false;
		$error = $e->getMessage();
		echo ("Message Failed | $campaign | $dest | ".$e->getMessage()."\n");
	 }
	}
	 $m = new Mongo();
	 $mdb = $m->flexmailer;
	 $report = $mdb->report;
	 if (!$error) {
		$report->insert(array(
			"campaignname" => $campaign,
			"email" => $dest,
			"status" => true,
			"error" => false
		));
	 } else {
		$report->insert(array(
			"campaignname" => $campaign,
			"email" => $dest,
			"status" => false,
			"error" => $error
		));
		echo ("Message Sent | $campaign | $dest\n");
	 }
}

