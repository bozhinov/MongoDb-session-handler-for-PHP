<?phprequire_once("MongoCore.php");require_once("MongoSession.php");new MongoSession();$_SESSION['user'] = "John";print_r($_SESSION);?>