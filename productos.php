<?php
include "config.php";
include "utils.php";


$dbConn =  connect($db);


/*
  listar todos los posts o solo uno
 */
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");
$method = $_SERVER['REQUEST_METHOD'];
if($method == "OPTIONS") {
    die();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET')
{
    
        
        if (isset($_GET['id'])){
            
              $sql = $dbConn->prepare("SELECT * FROM categoria where id=:id");
              $sql->bindValue(':id', $_GET['id']);
              $sql->execute();
              header("HTTP/1.1 200 OK");
              echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
              exit();
              
        }else{
             switch ($_GET['option']) {
                 
                case 'all':
                    $sql = $dbConn->prepare("SELECT * FROM producto ORDER BY nombre");
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    // header("HTTP/1.1 200 OK");
                
                    echo json_encode( $sql->fetchAll() );
                    exit();    
                    break;
                    
                    
               
    }
    
}
}


// Crear un nuevo post
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
    
    $input = $_POST;
    $user = json_decode($input['json']);
    
    $sql = "INSERT INTO categoria
          ( name)
          VALUES
          ( :name )";
          
    $statement = $dbConn->prepare($sql);
    bindAllValues($statement, $user);
    $statement->execute();
    $postId = $dbConn->lastInsertId();
    if($postId)
    {
      $input['id'] = $postId;
      header("HTTP/1.1 200 OK");
      echo json_encode($input);
      exit();
	 }
}

//Borrar
if ($_SERVER['REQUEST_METHOD'] == 'DELETE')
{
    $id = $_GET['id'];
    $statement = $dbConn->prepare("DELETE FROM categoria where id=:id");
    $statement->bindValue(':id', $id);
    $statement->execute();
	header("HTTP/1.1 200 OK");
	exit();
}

//Actualizar
if ($_SERVER['REQUEST_METHOD'] == 'PUT')
{
    $input = $_GET;
    $postId = $input['id'];
    $fields = getParams($input);

    $sql = "
          UPDATE categoria
          SET $fields
          WHERE id='$postId'
           ";

    $statement = $dbConn->prepare($sql);
    bindAllValues($statement, $input);

    $statement->execute();
    header("HTTP/1.1 200 OK");
    exit();
}


//En caso de que ninguna de las opciones anteriores se haya ejecutado
header("HTTP/1.1 400 Bad Request");

?>
