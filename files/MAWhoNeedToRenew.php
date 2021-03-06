<?php
define('ZWP_TOOLS', dirname(__FILE__).'/');
require_once(ZWP_TOOLS . 'lib/mysql.php');
require_once(ZWP_TOOLS . 'lib/registrationDateUtil.php');
require_once(ZWP_TOOLS . 'lib/util.php');

// Find out the value of $since
$since = null;
if (isset($_GET["since"])){
  try {
    $since = new DateTime($_GET["since"]);
  } catch(Exception $e){
    // Nothing to do. If we reach this point it means the parameter was badly formatted.
    // We leave `$since` to `null`, it will be handle afterwards
  }
}
if (is_null($since)){
  $registrationDateUtil = new RegistrationDateUtil(new DateTime());
  $since = $registrationDateUtil->getDateAfterWhichMembershipIsConsideredValid();
}

// Find out the value of $keepTests
$keepTests = isset($_GET["keepTests"]);


// Retrieve the data
$mysqlConnector = new MysqlConnector();
$simplifiedRegistrationEvents = $mysqlConnector->getOrderedListOfLastRegistrations($since);
if(!$keepTests){
  $simplifiedRegistrationEvents = keepOnlyActualRegistrations($simplifiedRegistrationEvents);
}

?>
<html>
<head>
  <title>Date d'adhésion</title>
  <meta charset="UTF-8">
</head>
<body>
  <form action="<?php echo $_SERVER["PHP_SELF"];?>" method="get" >
    Remonter jusqu'à <input name="since" type="date" value="<?php echo $since->format("Y-m-d"); ?>" /><br />
    Afficher les inscriptions de test <input type="checkbox" name="keepTests" <?php echo ($keepTests ? "checked" : ""); ?> /></br>
    <input type="submit" value="Rafraichir" />
  </form>

  <table>
    <tr><th>Dernière date d'adhésion</th><th>Nom</th><th>Mail</th><th>Code Postal</th></tr>
<?php
  foreach($simplifiedRegistrationEvents as $event){
    echo "<tr><td>" . $event->event_date . "</td><td>" . $event->first_name . " " . $event->last_name . "</td><td>" . $event->email . '</td><td>' . $event->postal_code . '</td></tr>';
  }
?>
  </table>
</body>
</html>
