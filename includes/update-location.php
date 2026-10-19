session_start();
include '../includes/db.php';

$id = $_SESSION['user_id'];
$lat = $_POST['lat'];
$lng = $_POST['lng'];

mysqli_query($conn,
"UPDATE technicians SET latitude='$lat', longitude='$lng' WHERE id=$id");