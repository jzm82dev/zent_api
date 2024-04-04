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


        if (isset($_GET['id']) && $_GET['option']==''){

              $sql = $dbConn->prepare("SELECT p.nombre, lcp.comprado FROM `lista_compra` l
	                                    LEFT join lista_compra_producto lcp ON l.id = lcp.id_lista_compra
                                        INNER JOIN producto p on lcp.id_producto = p.id
                                        WHERE l.id = :id");
              $sql->bindValue(':id', $_GET['id']);
              $sql->execute();
              header("HTTP/1.1 200 OK");
              echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
              exit();

        }else{
             switch ($_GET['option']) {

                case 'all':
                    $sql = $dbConn->prepare("SELECT * FROM lista_compra WHERE id_grupo=:idGrupo AND borrada=0");
                    $sql->bindValue(':idGrupo', $_GET['idGrupo']);
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;

                case 'pending':
                    $sql = $dbConn->prepare("SELECT lc.id as id, titulo, fecha_alta, fecha_fin, finalizada, SUM( IF(comprado=0,1,0) ) quedan, SUM(comprado) comprados
                                                FROM lista_compra lc INNER JOIN
                                                lista_compra_producto lcp ON lc.id = lcp.id_lista_compra  AND borrada=0
                                                WHERE id_grupo=:idGrupo
                                                GROUP BY lc.id;");
                    $sql->bindValue(':idGrupo', $_GET['idGrupo']);
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;

                case 'productos_lista':
                    $sql = $dbConn->prepare(" SELECT lcp.id, p.nombre, lcp.comprado FROM `lista_compra` l
    	                                            LEFT join lista_compra_producto lcp ON l.id = lcp.id_lista_compra
                                                    INNER JOIN producto p on lcp.id_producto = p.id AND lcp.manual = 0
                                                    WHERE l.id = :id
                                                UNION
                                                SELECT lcp.id, p.nombre, lcp.comprado FROM `lista_compra` l
    	                                            LEFT join lista_compra_producto lcp ON l.id = lcp.id_lista_compra
                                                    INNER JOIN producto_manual p on lcp.id_producto = p.id AND lcp.manual = 1
                                                    WHERE l.id = :id ;
                                                ");
                    $sql->bindValue(':id', $_GET['id']);
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
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
    $lista = json_decode($input['json']);
    $datos = array();
    $fecha = date('Y-m-d H:i:s');

    foreach($lista as $param => $value)
    {
	    $datos[$param] = $value;
	}



    $sql = $dbConn->prepare("INSERT INTO lista_compra
          ( titulo, finalizada, fecha_alta, borrada, id_grupo)
          VALUES
          ( :titulo, 0, '".$fecha."', 0, :idGrupo )" );

    $sql->bindValue(':titulo', $datos['titulo']);
    $sql->bindValue(':idGrupo', $datos['idGrupo']);
    $sql->execute();


    $listId = $dbConn->lastInsertId();
    if($listId)
    {
        $sql = insertProductsList($listId, $datos['productos']);
        if($sql != null){
            $sql = $dbConn->prepare($sql);
            $sql->execute();
        }
    }
    if($listId)
    {
        if(count( $datos['productosManuales'] ) >0 )
        {
            foreach( $datos['productosManuales'] as $nombre ){
                if( strlen($nombre) >0 ){
                    // Insertamos el producto
                    $sql = "INSERT INTO producto_manual( nombre )
                             VALUES ( '".$nombre."')";
                    $sql = $dbConn->prepare($sql);

                    $sql->execute();
                    $idProducto = $dbConn->lastInsertId();
                    //Ahora insertamos en la lista
                    $sql = "INSERT INTO lista_compra_producto( id_lista_compra, id_producto, comprado, manual )
                            VALUES ( ".$listId.", ".$idProducto.", 0, 1 )";
                    $sql = $dbConn->prepare($sql);
                    $sql->execute();
                }
            }
        }
    }

    if($listId)
    {
      $input['id'] = $listId;
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
    parse_str(file_get_contents("php://input"), $input);
    $lista = json_decode($input['json']);

    $datos = array();
    $fecha = date('Y-m-d H:i:s');

    foreach($lista as $param => $value)
    {
	    $datos[$param] = $value;
	}

    $idLista = $datos['id'];

    if(isset($idLista) && count($datos['productos'])>0 && intval($datos['finalizada'])==0)
    {
        $sql = updateProductsList($idLista, $datos['productos']);
        $sql = $dbConn->prepare($sql);
        $sql->execute();
    }

    if( intval($datos['finalizada'])==1 && isset($idLista)){  //Lista finaizada
        $sql = "UPDATE lista_compra SET finalizada=1, fecha_fin='".$fecha."' WHERE id=".$idLista;
        $sql = $dbConn->prepare($sql);
        $sql->execute();
    }

    if( intval($datos['borrada'])==1 && isset($idLista)){  //Lista finaizada
        $sql = "UPDATE lista_compra SET borrada=1 WHERE id=".$idLista;
        $sql = $dbConn->prepare($sql);
        $sql->execute();
    }

    //$statement->execute();
    echo json_encode( 'Lista actualizada' );
    header("HTTP/1.1 200 OK");
    exit();
}


//En caso de que ninguna de las opciones anteriores se haya ejecutado
header("HTTP/1.1 400 Bad Request");

?>
