<?php
include "config.php";
include "utils.php";

require_once('vendor/autoload.php');

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


        if (isset($_GET['id']) && !isset($_GET['option'])){

              $sql = $dbConn->prepare("SELECT g.*, u.name as nombre_usuario_creador FROM grupo g
                                        INNER JOIN user u ON g.id_user_creador = u.id
                                        WHERE g.id=:id");
              $sql->bindValue(':id', $_GET['id']);
              $sql->execute();
              header("HTTP/1.1 200 OK");
              echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
              exit();

        }else{
             switch ($_GET['option']) {

                case 'all':
                    $sql = $dbConn->prepare("SELECT g.*, u.name as nombre_usuario_creador
                                                FROM grupo g INNER JOIN user u ON g.id_user_creador = u.id
                                                WHERE fecha_baja IS NULL ORDER BY fecha_alta");
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    // header("HTTP/1.1 200 OK");

                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;

                case 'usuarios_por_grupo':
                    $sql = $dbConn->prepare("SELECT u.* FROM user u
                                                    INNER JOIN usuarios_grupo ug ON u.id = ug.id_usuario AND ug.id_grupo=:id
                                                    WHERE ug.fecha_baja IS NULL ");
                    $sql->bindValue(':id', $_GET['id']);
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    // header("HTTP/1.1 200 OK");

                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;

                 case 'grupo_por_usuario':

                   if( isExpiredToken($_GET['authorization']) ){

                     $data = array(
                       'status' => 'FailToken',
                       'grupos' => 0,
                       'message' => 'Error, tu sesión ha expirado.'
                     );
                     header("Content-type: application/json; charset=utf-8");
                     echo json_encode($data);
                     exit();

                   }else{

                      $sql = $dbConn->prepare("SELECT DISTINCT g.* FROM grupo g INNER JOIN usuarios_grupo ug ON g.id = ug.id_grupo
                                  WHERE ug.telefono_usuario =:telefono AND g.fecha_baja IS NULL ");
                      $sql->bindValue(':telefono', $_GET['telefono']);
                      $sql->execute();
                      $sql->setFetchMode(PDO::FETCH_ASSOC);
                      $grupos =  $sql->fetchAll();
                      $data = array(
                        'status' => 'success',
                        'grupos' => $grupos,
                        'message' => 'OK.'
                      );
                      header("Content-type: application/json; charset=utf-8");
                      echo json_encode($data);
                      exit();
                    }

                case 'deudores_grupo':
                    /*$sql = $dbConn->prepare("
                    SELECT nombre_agenda, telefono_usuario, SUM(deuda) AS 'deuda', idUserCreador as idUserCreador, recibir_push AS 'recibir_push'  FROM 
                      ( SELECT nua.nombre_agenda, pp.telefono_usuario, SUM(pp.count) AS 'deuda', 1 AS 'usuario_logado', u.id as 'idUserCreador', u.recibir_push as 'recibir_push' FROM purchase p
                        INNER JOIN purchase_participante pp ON pp.id_purchase = p.id AND pp.pagado=0 AND p.pagado=0 AND pp.telefono_usuario<>:telefonoUsuario
                        INNER JOIN nombre_usuario_agenda nua ON pp.telefono_usuario = nua.telefono_agenda AND nua.id_usuario = :idUsuario
                        LEFT JOIN user u ON pp.telefono_usuario = u.telephone
                        WHERE p.id_grupo=:idGrupo AND p.user_id=:idUsuario
                        GROUP BY pp.telefono_usuario UNION
                        SELECT nua.nombre_agenda, pp.telefono_usuario, 0-SUM(pp.count) AS 'deuda', 0 AS 'usuario_logado', u.id as 'idUserCreador', u.recibir_push as 'recibir_push'  FROM purchase_participante pp
                        INNER JOIN purchase p ON p.id = pp.id_purchase AND pp.pagado=0 AND p.pagado=0 AND p.user_id <> :idUsuario
                        INNER JOIN user u ON p.user_id = u.id
                        INNER JOIN nombre_usuario_agenda nua ON u.telephone = nua.telefono_agenda AND nua.id_usuario=:idUsuario
                        WHERE pp.telefono_usuario=:telefonoUsuario AND p.id_grupo=:idGrupo
                        GROUP BY pp.telefono_usuario, u.id ) AS tt GROUP 
                        BY tt.nombre_agenda ");*/
                    $sql = $dbConn->prepare("                
                        SELECT nombre_agenda, telefono_usuario, SUM(deuda) AS 'deuda', idUserCreador as idUserCreador, recibir_push AS 'recibir_push'  FROM 
                        ( SELECT nua.nombre_agenda, pp.telefono_usuario, SUM(pp.count) AS 'deuda', 1 AS 'usuario_logado', u.id as 'idUserCreador', u.recibir_push as 'recibir_push' FROM purchase p
                          INNER JOIN purchase_participante pp ON pp.id_purchase = p.id AND pp.pagado=0 AND p.pagado=0 AND pp.telefono_usuario<>:telefonoUsuario
                          INNER JOIN nombre_usuario_agenda nua ON pp.telefono_usuario = nua.telefono_agenda AND nua.id_usuario = :idUsuario
                          LEFT JOIN user u ON pp.telefono_usuario = u.telephone
                          WHERE p.id_grupo=:idGrupo AND p.telefono_id =:telefonoUsuario
                          GROUP BY pp.telefono_usuario UNION
                          SELECT nua.nombre_agenda, nua.telefono_agenda, 0-SUM(pp.count) AS 'deuda', 0 AS 'usuario_logado', null, u.recibir_push as 'recibir_push' FROM purchase_participante pp
                          INNER JOIN purchase p ON p.id = pp.id_purchase AND pp.pagado=0 AND p.pagado=0 AND p.telefono_id <> :telefonoUsuario and pp.telefono_usuario  = :telefonoUsuario
                          INNER JOIN nombre_usuario_agenda nua ON p.telefono_id = nua.telefono_agenda AND nua.id_usuario=:idUsuario
                          LEFT JOIN user u ON pp.telefono_usuario = u.telephone
                          WHERE pp.telefono_usuario=:telefonoUsuario AND p.id_grupo=:idGrupo
                          GROUP BY p.telefono_id ) AS tt GROUP 
                          BY tt.nombre_agenda; ");    
                    $sql->bindValue(':idUsuario', $_GET['idUsuario']);
                    $sql->bindValue(':idGrupo', $_GET['idGrupo']);
                    $sql->bindValue(':telefonoUsuario', $_GET['telefonoUsuario']);
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    // header("HTTP/1.1 200 OK");

                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;

                case 'detalle_me_debe_usuario':
                    $sql = $dbConn->prepare("SELECT pp.id, p.title, pp.count, pp.pagado AS pagado_por_usuario, p.pagado AS ticket_pagado, pp.id_purchase as id_ticket FROM purchase p
                                 INNER JOIN purchase_participante pp ON p.id = pp.id_purchase AND pp.telefono_usuario=:telefonoUsuario AND p.user_id=:idUsuario
                                 WHERE id_grupo=:idGrupo;");
                    $sql->bindValue(':telefonoUsuario', $_GET['telefonoUsuario']);
                    $sql->bindValue(':idUsuario', $_GET['idUsuario']);
                    $sql->bindValue(':idGrupo', $_GET['idGrupo']);
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;

                case 'detalle_debo_a_usuario':
                    $sql = $dbConn->prepare("SELECT pp.id, p.title, pp.count, pp.pagado AS pagado_por_usuario, p.pagado AS ticket_pagado, pp.id_purchase as id_ticket FROM purchase p
                                 INNER JOIN purchase_participante pp ON p.id = pp.id_purchase AND pp.telefono_usuario=:telefonoUsuario
                                 WHERE id_grupo=:idGrupo and p.user_id=:idCreadorGasto ;");
                    $sql->bindValue(':telefonoUsuario', $_GET['telefonoUsuario']);
                    $sql->bindValue(':idGrupo', $_GET['idGrupo']);
                    $sql->bindValue(':idCreadorGasto', $_GET['idCreadorGasto']);
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;

                case 'todas_deudas':
                  $sql = $dbConn->prepare(" SELECT * FROM (
                        -- ME DEBE
                        SELECT pp.id, p.id as 'idTicket', p.title, pp.count, p.date, 1 AS tipo, p.date as fecha FROM purchase p 
                        INNER JOIN purchase_participante pp ON p.id = pp.id_purchase AND pp.telefono_usuario LIKE :telefonoUsuario AND pp.pagado = 0 and p.pagado = 0
                        WHERE telefono_id=:telefonoUsuarioLogado AND id_grupo=:idGrupo
                        UNION ALL
                        -- LE DEBO	
                        SELECT pp.id, p.id as 'idTicket', p.title, 0 - pp.count, p.date, 2 as tipo, p.date as fecha  FROM purchase p 
                        INNER JOIN purchase_participante pp ON p.id = pp.id_purchase AND pp.telefono_usuario LIKE :telefonoUsuarioLogado AND pp.pagado = 0 and p.pagado = 0
                        WHERE telefono_id=:telefonoUsuario AND id_grupo=:idGrupo
                        UNION ALL
                        -- ME HA PAGADO
                        SELECT pp.id, p.id as 'idTicket', p.title, pp.count, p.date, 3 AS tipo, p.date as fecha  FROM purchase p 
                        INNER JOIN purchase_participante pp ON p.id = pp.id_purchase AND pp.telefono_usuario LIKE :telefonoUsuario AND (pp.pagado = 1 or p.pagado = 1)
                        WHERE user_id=:idUsuario AND id_grupo=:idGrupo ) a ORDER BY fecha DESC
                      ");
                      $sql->bindValue(':telefonoUsuario', $_GET['telefonoUsuario']);
                      $sql->bindValue(':idUsuario', $_GET['idUsuarioLogado']);
                      $sql->bindValue(':idGrupo', $_GET['idGrupo']);
                      $sql->bindValue(':idCreadorGasto', $_GET['idCreadorGasto']);
                      $sql->bindValue(':telefonoUsuarioLogado', $_GET['telefonoUsuarioLoago']);
                      $sql->execute();
                      $sql->setFetchMode(PDO::FETCH_ASSOC);
                      echo json_encode( $sql->fetchAll() );
                      exit();
                      break;
                break;

    }

}
}


// Crear un nuevo post
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{

    if(!isset($_GET['function'])){

        $input = $_POST;

        if( isExpiredToken($input['authorization']) ){
          $data = array(
            'status' => 'success',
            'id' => 0,
            'message' => 'Error, tu sesión ha expirado.'
          );
          header("Content-type: application/json; charset=utf-8");
          echo json_encode($data);
          exit();
        }
        else{
          $grupo = json_decode($input['json']);
          $datos = array();
          $fecha = date('Y-m-d H:i:s');

          foreach($grupo as $param => $value)
            $datos[$param] = $value;

          $sql = $dbConn->prepare("INSERT INTO grupo
                ( nombre, descripcion, fecha_alta, id_user_creador, id_tipo, listaCompra)
                VALUES
                ( :nombre, :descripcion, '".$fecha."', :id_usuario, :id_tipo, :listaCompra )" );

          $sql->bindValue(':nombre', ucfirst($datos['nombre']));
          $sql->bindValue(':descripcion', $datos['descripcion']);
          $sql->bindValue(':id_usuario', $datos['id_user_creador']);
          $sql->bindValue(':id_tipo', $datos['id_tipo']);
          $sql->bindValue(':listaCompra', $datos['listaCompra']);
          $sql->execute();


          $grupoId = $dbConn->lastInsertId();

          // Insertamos participantes
          if(count( $datos['participantes'] ) >0 )
          {
              insertarParticipantes( $datos['participantes'], $grupoId, $dbConn );
              guardarNombreUserAgenda( $datos['id_user_creador'], $datos['participantes'], $dbConn );
          }

          if($grupoId)
          {
            $data = array(
              'status' => 'success',
              'message' => 'Grupo añadido.',
              'id' =>$grupoId,
            );
            header("Content-type: application/json; charset=utf-8");
            echo json_encode($data);
            exit();
         }
        }
    }
    else{
        $input = $_POST;
        if(isset($input['json']))
          $participantes = json_decode($input['json']);
        $resp = array();
        switch ($_GET['function']) {


            case 'nuevoParticipante':

                 if(count( $participantes ) > 0){
                    guardarNombreUserAgenda( $_GET['idUser'], $participantes, $dbConn );
                }
                header("HTTP/1.1 200 OK");
                echo json_encode($_GET['idUser']);
                exit();
                break;

            break;

            case 'updateNameParticipante':

              if(count( $participantes ) > 0){
                updateNombreUserAgenda( $_GET['idUser'], $participantes, $dbConn );
              }
              header("HTTP/1.1 200 OK");
              echo json_encode($_GET['idUser']);
              exit();
            break;

            case 'aniadirParticipantes':

                if(count( $participantes ) >0 ){
                    //if( !existeParticipanteGrupo( $participantes[0], $_GET['idGrupo'], $dbConn) ){
                        insertarParticipantes($participantes, $_GET['idGrupo'], $dbConn);
                        guardarNombreUserAgenda( $_GET['idUsuaioPadre'], $participantes, $dbConn );
                        $resp['mensaje'] = 'Participante añadido correctamente';
                  //  }
                  //  else
                  //      $resp['mensaje'] = 'Participante está ya en el grupo';
                }
                else{
                    $resp['mensaje'] = 'Seleccione al menos un participante';
                }
            break;

            case 'eliminaParticipantes':
                $fechab = date('Y-m-d H:i:s');
                  if( !tieneCuentasPendientesParticipante( $_GET['telefonoParticipante'], $_GET['idGrupo'], $dbConn ) ){
                    eliminaParticipante( $_GET['telefonoParticipante'], $_GET['idGrupo'], $dbConn );
                  $resp['mensaje'] = 'Participante eliminado del grupo';
                }else{
                  $resp['mensaje'] = 'Para eliminar participante tiene antes que saldar sus deudas';
                }
            break;

            case 'pagar_deuda':
                $sql = "UPDATE purchase_participante SET pagado=1 WHERE id=".$_GET['idDeuda'];
                $statement = $dbConn->prepare($sql);
                $statement->execute();
                $sql = "UPDATE purchase_participante SET count=count-".$_GET['cantidad']." WHERE id_purchase = ".$_GET['idTicket']." AND pagado=2";
                $statement = $dbConn->prepare($sql);
                $statement->execute();
                if(comprobrarDeudaSaldada($_GET['idTicket'] ,$dbConn))
                    $resp['mensaje'] = 'Deuda saldada por todos los participantes';
                else
                    $resp['mensaje'] = 'Deuda pagada';
            break;

            case 'pagar_todas_deuda':

            /*$userDeudor = getUserByTel( $_GET['telefonoUsuarioDeudor'], $dbConn);
            $userDeudor2 = getUserById( $_GET['idUsuario'], $dbConn);
             
              $sql = $dbConn->prepare(
                 "SELECT pp.id as id_pp, p.id as idTicket, pp.count as cantidad FROM purchase_participante pp 
                  INNER JOIN purchase p ON pp.id_purchase = p.id AND pp.telefono_usuario=:telefono_usuario_deudor
                  WHERE p.user_id=:userId AND id_grupo=:idGrupo AND pp.pagado=0
                  UNION 
                  SELECT pp.id as id_pp, p.id as idTicket, pp.count as cantidad FROM purchase_participante pp 
                  INNER JOIN purchase p ON pp.id_purchase = p.id AND pp.telefono_usuario=:telefono_usuario_deudor_2
                  WHERE p.user_id=:userId_2 AND id_grupo=:idGrupo AND pp.pagado=0");
              $sql->bindValue(':idGrupo', $_GET['idGrupo']);
              $sql->bindValue(':userId', $_GET['idUsuario']);
              $sql->bindValue(':telefono_usuario_deudor', $_GET['telefonoUsuarioDeudor']);
              $sql->bindValue(':telefono_usuario_deudor_2', $userDeudor2->telephone);
              if( isset($userDeudor->id) )
                $sql->bindValue(':userId_2', $userDeudor->id);
              else
                $sql->bindValue(':userId_2', 'XXXXXXX');
              $sql->execute();
              */
              
              $sql = $dbConn->prepare(
                "SELECT pp.id as id_pp, p.id as idTicket, pp.count as cantidad FROM purchase_participante pp 
                 INNER JOIN purchase p ON pp.id_purchase = p.id AND pp.telefono_usuario=:telefono_usuario_deudor
                 WHERE p.telefono_id=:telefonoCreador AND id_grupo=:idGrupo AND pp.pagado=0
                 UNION 
                 SELECT pp.id as id_pp, p.id as idTicket, pp.count as cantidad FROM purchase_participante pp 
                 INNER JOIN purchase p ON pp.id_purchase = p.id AND pp.telefono_usuario=:telefonoCreador
                 WHERE p.telefono_id=:telefono_usuario_deudor AND id_grupo=:idGrupo AND pp.pagado=0");
             $sql->bindValue(':idGrupo', $_GET['idGrupo']);
             //$sql->bindValue(':userId', $_GET['idUsuario']);
             $sql->bindValue(':telefono_usuario_deudor', $_GET['telefonoUsuarioDeudor']);
             $sql->bindValue(':telefonoCreador', $_GET['telefonoCreador']);
             if( isset($userDeudor->id) )
               $sql->bindValue(':userId_2', $userDeudor->id);
             else
               $sql->bindValue(':userId_2', 'XXXXXXX');
             $sql->execute();

              //$sql->setFetchMode(PDO::FETCH_ASSOC);
              while($data = $sql->fetch( PDO::FETCH_ASSOC )){
                $sql_up = " UPDATE purchase_participante SET pagado=1 WHERE id=".$data['id_pp'] ;
                $statement = $dbConn->prepare($sql_up);
                $statement->execute();
                $sql_up = " UPDATE purchase_participante SET count=count-".$data['cantidad']." WHERE pagado=2 AND id_purchase=".$data['idTicket'];
                $statement = $dbConn->prepare($sql_up);
                $statement->execute();
              }

              $sql = "UPDATE purchase  INNER JOIN purchase_participante  ON purchase.id = purchase_participante.id_purchase 
                      AND purchase_participante.pagado = 2 AND purchase_participante.count = 0
                      SET purchase.pagado = 1";
              $statement = $dbConn->prepare($sql);
              $statement->execute();
              $resp['mensaje'] = 'Deudas saldadas';

            break;

        }
        header("HTTP/1.1 200 OK");
        echo json_encode($resp);
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
    $token =  $input['authorization'];
    if( isExpiredToken($token) ){
      $data = array(
        'status' => 'success',
        'message' => 'Error, tu sesión ha expirado.'
      );
    }
    else{
      $idGrupo = $input['idGrupo'];
      $idUserToken = idUsuarioToken( $token );
      if(esAdminGrupo($idGrupo, $idUserToken, $dbConn)){

          $sql = "DELETE FROM grupo WHERE id=".$idGrupo;
          $statement = $dbConn->prepare($sql);
          $statement->execute();

          $sql = $dbConn->prepare("SELECT id, imagen FROM purchase WHERE id_grupo=:idGrupo");
          $sql->bindValue(':idGrupo', $idGrupo);
          $sql->execute();
          while ($row = $sql->fetch()){
              $idPurchase = $row['id'];
              $imagenName = $row['imagen'];
              if(is_file( 'uploads/'.$imagenName ))
                 unlink( 'uploads/'.$imagenName );
              $update = " DELETE FROM purchase_participante WHERE id_purchase=".$idPurchase;
              $statement = $dbConn->prepare($update);
              $statement->execute();
          }

          $sql = "DELETE FROM usuarios_grupo WHERE id_grupo=".$idGrupo;
          $statement = $dbConn->prepare($sql);
          $statement->execute();

          $sql = "DELETE FROM purchase WHERE id_grupo=".$idGrupo;
          $statement = $dbConn->prepare($sql);
          $statement->execute();
          $data = array(
            'status' => 'success',
            'message' => 'Grupo y todos sus gastos eliminados.'
          );
        }else{ // No es administrador del grupo
          $data = array(
            'status' => 'error',
            'message' => 'Sólo el administrador puede eliminar el grupo.'
          );
        }
    }
    header("Content-type: application/json; charset=utf-8");
    echo json_encode($data);
    exit();
}


//En caso de que ninguna de las opciones anteriores se haya ejecutado
header("HTTP/1.1 400 Bad Request");

?>
