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
    if (isset($_GET['function']) && $_GET['function'] === 'todas')
    {
      $sql = $dbConn->prepare("SELECT user.name, user.id, purchase.*  FROM user
	                             INNER JOIN purchase ON user.id = purchase.user_id
	                             WHERE purchase.delete IS NULL
	                             ORDER BY pagado, date desc;");
      $sql->execute();
      $sql->setFetchMode(PDO::FETCH_ASSOC);
      header("HTTP/1.1 200 OK");
      echo json_encode( $sql->fetchAll()  );
      exit();
    }
    elseif (isset($_GET['function']) && $_GET['function'] === 'porGrupo'){

      if( isExpiredToken($_GET['authorization']) ){

          $data = array(
            'status' => 'error',
            'message' => 'Su sesión ha caducado. Vuelva a ingresar sus datos.'
          );
          header("Content-type: application/json; charset=utf-8");
          echo json_encode($data);
          exit();

      }else{
        /*$sql = $dbConn->prepare("SELECT user.name, user.id, user.foto, purchase.*  FROM user
	                             INNER JOIN purchase ON user.id = purchase.user_id
	                             WHERE purchase.delete IS NULL and purchase.id_grupo =:idGrupo
                                 ORDER BY pagado, date desc;");*/
       /* $sql = $dbConn->prepare("SELECT user.name, user.id, user.foto, nua.nombre_agenda, purchase.*  FROM user
                                  INNER JOIN purchase ON user.id = purchase.user_id
                                  INNER JOIN nombre_usuario_agenda nua ON nua.telefono_agenda = purchase.telefono_id AND nua.id_usuario=:userId
                                  WHERE purchase.delete IS NULL and purchase.id_grupo =:idGrupo
                                    ORDER BY pagado, date desc;");   */  
        $sql = $dbConn->prepare("SELECT user.name, user.id, user.foto, nua.nombre_agenda, purchase.*  FROM purchase
                                LEFT JOIN user ON user.telephone = purchase.telefono_id
                                INNER JOIN nombre_usuario_agenda nua ON nua.telefono_agenda = purchase.telefono_id AND nua.id_usuario=:userId
                                WHERE purchase.delete IS NULL and purchase.id_grupo =:idGrupo
                                ORDER BY pagado, date desc, purchase.id desc;");                                                          
        $sql->bindValue(':idGrupo', $_GET['idGrupo']);
        $sql->bindValue(':userId', $_GET['userLogado']);
        $sql->execute();
        $sql->setFetchMode(PDO::FETCH_ASSOC);
        $data = array(
          'status' => 'success',
          'message' => 'Tickets del grupo',
          'tickets' => $sql->fetchAll()
        );
        header("Content-type: application/json; charset=utf-8");
        echo json_encode($data);
        exit();
      /*  header("HTTP/1.1 200 OK");
        echo json_encode( $sql->fetchAll()  );
        exit(); */
      }
    }
    elseif (isset($_GET['id']))
    {
      //Mostrar un post
      $sql = $dbConn->prepare("SELECT * FROM purchase where id=:id");
      $sql->bindValue(':id', $_GET['id']);
      $sql->execute();
      header("HTTP/1.1 200 OK");
      echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
      exit();
	  }
    else {
      //Mostrar lista de post
      $sql = $dbConn->prepare("SELECT * FROM purchase");
      $sql->execute();
      $sql->setFetchMode(PDO::FETCH_ASSOC);
      header("HTTP/1.1 200 OK");
      echo json_encode( $sql->fetchAll()  );
      exit();
	}
}

// Crear un nuevo post

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
    $input = $_POST;
    if( isExpiredToken($input['authorization']) ){

        $data = array(
          'status' => 'error',
          'message' => 'Su sesión ha caducado. Vuelva a ingresar sus datos.'
        );
        header("Content-type: application/json; charset=utf-8");
        echo json_encode($data);
        exit();

    }else{
        $generatedName= '';
        if(isset($_FILES['image'])){
            $path = 'uploads/';
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
            $originalName = $_FILES['image']['name'];
            $ext = '.'.pathinfo($originalName, PATHINFO_EXTENSION);
            $t=time();
            $generatedName = md5($t.$originalName).$ext;
            $filePath = $path.$generatedName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
              $input['nameFile'] = $generatedName;
            }
        }


        $ticket = json_decode($input['json']);
        $datos = array();
        $fecha = date('Y-m-d H:i:s');
        
       
        foreach($ticket as $param => $value)
        {
            $datos[$param] = $value;
        }
        $date = date('Y-m-d', strtotime(substr($datos['date'],0,10)));

        $sql = $dbConn->prepare(" INSERT INTO purchase
                  (title, count, description, user_id, date, pagado, id_grupo, imagen, telefono_id)
                  VALUES
                  (:title, :count, :description, :user_id, :date, :pagado, :id_grupo, :img, :telefono_id)" );

        $sql->bindValue(':title', ucfirst($datos['title']));
        $sql->bindValue(':count', $datos['count']);
        $sql->bindValue(':description', $datos['description']);
        $sql->bindValue(':user_id', $datos['user_id']);
        $sql->bindValue(':date', $date);
        $sql->bindValue(':pagado', $datos['pagado']);
        $sql->bindValue(':id_grupo', $datos['id_grupo']);
        $sql->bindValue(':img', $generatedName);
        $sql->bindValue(':telefono_id', $datos['telefono_id']);
        $sql->execute();

        $postId = $dbConn->lastInsertId();

        // Insertamos participantes
        if(count( $datos['participantes'] ) >0 )
        {
            $importe = round($datos['count']/ (count( $datos['participantes'] ) ), 2);
            insertarParticipantesGasto( $datos['participantes'], $importe, $postId, $dbConn, $datos['user_id'],$datos['telefono_id']  );
        }

        if($postId)
        {
          $data = array(
            'status' => 'success',
            'message' => 'Ticket añadido a tu grupo de gasto.',
            'id' =>$postId,
          );
          header("Content-type: application/json; charset=utf-8");
          echo json_encode($data);
          exit();
        }
    }// else

}

//Borrar
if ($_SERVER['REQUEST_METHOD'] == 'DELETE')
{

  if( isExpiredToken($_GET['authorization']) ){

      $data = array(
        'status' => 'error',
        'message' => 'Su sesión ha caducado. Vuelva a ingresar sus datos.'
      );
      header("Content-type: application/json; charset=utf-8");
      echo json_encode($data);
      exit();

  }else{
    $id = $_GET['id'];

        // Eliminamos la imagen si tiene adjunta
        $sql = $dbConn->prepare("SELECT imagen FROM purchase where id=:id");
        $statement->bindValue(':id', $id);
        $statement->execute();
        if($sql->rowCount() == 0){
           $row = $sql->fetch();
           if(is_file( 'uploads/'.$row['imagen'] ))
             unlink( 'uploads/'.$row['imagen'] );
        }

        $statement = $dbConn->prepare("SELECT foto FROM user WHERE id=:id");
        $statement->bindValue(':id', $user->id);
        $statement->execute();
        if($statement->rowCount() > 0){
           $row = $statement->fetch();
           $imagenName = $row['foto'];
           if(is_file( 'photos/'.$imagenName ))
               unlink( 'photos/'.$imagenName );
        }

        $statement = $dbConn->prepare("DELETE FROM purchase where id=:id");
        $statement->bindValue(':id', $id);
        $statement->execute();
        $statement = $dbConn->prepare("DELETE FROM purchase_participante where id_purchase=:id");
        $statement->bindValue(':id', $id);
        $statement->execute();
        $data = array(
          'status' => 'success',
          'message' => 'Ticket eliminado correctamentee.',
        );
        header("Content-type: application/json; charset=utf-8");
        echo json_encode($data);
        exit();
  }
}

//Actualizar
if ($_SERVER['REQUEST_METHOD'] == 'PUT')
{
    $input = $_GET;
    $postId = $input['id'];

    if( isExpiredToken($input['authorization']) ){

        $data = array(
          'status' => 'error',
          'message' => 'Su sesión ha caducado. Vuelva a ingresar sus datos.'
        );
        header("Content-type: application/json; charset=utf-8");
        echo json_encode($data);
        exit();

    }else{

        $function = isset($input['function']) ? $input['function'] : '' ;

        $fechab = date('Y-m-d H:i:s');

        if(intval($postId)>0){

               if( $function == 'pay'){
                    $sql = "UPDATE purchase p SET p.pagado=1, p.fecha_pagado='".$fechab."' WHERE id=". $postId;
                    $statement = $dbConn->prepare($sql);
                    $statement->execute();
                    $sql = "UPDATE purchase_participante SET pagado=1 WHERE id_purchase=". $postId;
                    $statement = $dbConn->prepare($sql);
                    $statement->execute();
               }
               else {
                   // Borramos
                   // Eliminamos la imagen si tiene adjunta

                    $statement = $dbConn->prepare("SELECT imagen FROM purchase WHERE id=:id");
                    $statement->bindValue(':id', $postId);
                    $statement->execute();
                    if($statement->rowCount() > 0){
                       $row = $statement->fetch();
                       $imagenName = $row['imagen'];
                       if(is_file( 'uploads/'.$imagenName ))
                           unlink( 'uploads/'.$imagenName );
                    }

                    $sql = "DELETE FROM purchase WHERE id=".$postId.";
                            DELETE FROM purchase_participante WHERE id_purchase=". $postId;
                    $statement = $dbConn->prepare($sql);
                    $statement->execute();

                    $data = array(
                      'status' => 'success',
                      'message' => 'Ticket eliminado correctamente.',
                    );
                    header("Content-type: application/json; charset=utf-8");
                    echo json_encode($data);
                    exit();
               }
        }else {
            // LIQUIDAMOS TODOS NUESTROS GASTOS -> (Ponemos a pagado todo lo que se nos debe.)
            $sql = $dbConn->prepare("SELECT id FROM purchase WHERE user_id=".intval($input['idUsuario'])." AND id_grupo=:idGrupo");
            $sql->bindValue(':idGrupo', $input['idGrupo']);
            $sql->execute();
            while ($row = $sql->fetch()){
                $idPurchase = $row['id'];
                $update = "UPDATE purchase_participante SET pagado=1 WHERE id_purchase=". $idPurchase;
                $statement = $dbConn->prepare($update);
                $statement->execute();
            }
           	$sql = "UPDATE purchase SET pagado=1, fecha_pagado='".$fechab."' WHERE user_id=".intval($input['idUsuario'])." AND id_grupo=".intval($input['idGrupo']) ;
    		$statement = $dbConn->prepare($sql);
    		$statement->execute();
        }

        echo json_encode('Registro actualizado');
        header("HTTP/1.1 200 OK");
        exit();
    }
}


//En caso de que ninguna de las opciones anteriores se haya ejecutado
header("HTTP/1.1 400 Bad Request");

?>
