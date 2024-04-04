<?php

    require_once('vendor/autoload.php');
    include "config.php";
    use \Firebase\JWT\JWT;
    //include "utils.php";


    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Allow: GET, POST, OPTIONS, PUT, DELETE");
    $method = $_SERVER['REQUEST_METHOD'];
    if($method == "OPTIONS") {
        die();
    }

  function signup( $telefono, $password, $dbConn, $oneSignalId = '') {

    if($telefono != null && $password != null){
        
        if($oneSignalId != '' && $oneSignalId != 'undefined') { 
          $sql = " UPDATE user SET oneSignalId = '".$oneSignalId."' WHERE telephone like '".$telefono."' AND password like '".$password."';";
          $sql = $dbConn->prepare($sql);
          $sql->execute();
        }  
        $sql = $dbConn->prepare("SELECT * FROM user where telephone like :telefono AND password like :password");
        $sql->bindValue(':telefono', $telefono);
        $sql->bindValue(':password', $password);
        $sql->execute();
        if( $sql->rowCount()>0 ){
          $login = true;
          $data = $sql->fetch(PDO::FETCH_OBJ ) ;
        }
        else{
          $data = 'Usuario o password incorrecto';
        }
    }
    else
      $data = 'Tiene que introducir usuario y password';

    return $data;
  }


  function getUserById( $id, $dbConn ){

    $data = null;
    $sql = $dbConn->prepare("SELECT * FROM user where id = :id ");
    $sql->bindValue(':id', $id);
    $sql->execute();
    if( $sql->rowCount()>0 ){
      $data = $sql->fetch(PDO::FETCH_OBJ ) ;
    }
    return $data;

  }

  function getUserByTel( $tel, $dbConn ){

    $data = null;
    $sql = $dbConn->prepare("SELECT * FROM user where telephone = :telephone ");
    $sql->bindValue(':telephone', $tel);
    $sql->execute();
    if( $sql->rowCount()>0 ){
      $data = $sql->fetch(PDO::FETCH_OBJ ) ;
    }
    return $data;

  }

  function createToken( $userLogado ) {

    $key = "Zent";
    $time = time();

    $payload = array(
        "id" => $userLogado->id,
        "name" => $userLogado->name,
        "telephone" =>$userLogado->telephone,
        "iat" => $time,
        "exp" => strtotime('+30 day', $time)
    );

    $jwt = JWT::encode($payload, $key);
    return $jwt;
  }

  function isExpiredToken( $jwt ) {

    $key = "Zent";
    $decoded = JWT::decode($jwt, $key, array('HS256'));
    if( time() > $decoded->exp )
      return true;
    else
      return false;

  }

  function idUsuarioToken ( $jwt ){
    $key = "Zent";
    $decoded = JWT::decode($jwt, $key, array('HS256'));
    if( isset($decoded->id) )
      return $decoded->id;
    return 0;
  }

  //Abrir conexion a la base de datos
  function connect($db)
  {
      try {
          $conn = new PDO("mysql:host={$db['host']};dbname={$db['db']};charset=utf8", $db['username'], $db['password']);

          // set the PDO error mode to exception
          $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

          return $conn;
      } catch (PDOException $exception) {
          exit($exception->getMessage());
      }
  }



 //Obtener parametros para updates
 function getParams($input)
 {
    $filterParams = [];
    foreach($input as $param => $value)
    {
            $filterParams[] = "$param=:$param";
    }
    return implode(", ", $filterParams);
	}

  //Asociar todos los parametros a un sql
	function bindAllValues($statement, $params)
    {
		foreach($params as $param => $value)
        {
				$statement->bindValue(':'.$param, $value);
		}
		return $statement;
   }

  function insertProductsList( $idLista, $products ){
      $insert = '';
      $sql = "INSERT INTO lista_compra_producto( id_lista_compra, id_producto, comprado, manual ) VALUES ";
      foreach( $products as $idProduct ){
          if( $idProduct != null )
            $insert.= "( ".$idLista.", ".$idProduct.", 0, 0 ),";
      }
      if($insert != '')
        return substr( $sql.$insert, 0, -1) ;
      return null;
  }


  function updateProductsList( $idLista, $products ){
      $sql = '';
      foreach( $products as $id ){
          $sql.= "UPDATE lista_compra_producto SET comprado=1 WHERE id=".$id." AND id_lista_compra=".$idLista."; ";
      }
      return substr( $sql, 0, -1) ;
  }


  function formatearTelefono ( $telefono ){

    $telefono =str_replace(' ', '', $telefono);

    if(strlen($telefono) >= 9)
        $telf = substr($telefono, -9);

    return $telf;
  }

  function insertarParticipantes ($participantes, $grupoId, $conexion) {

    foreach( $participantes as $participante ){
        if( !existeParticipanteGrupo( $participante, $grupoId, $conexion) ){
                $sql = "INSERT INTO usuarios_grupo( id_grupo, telefono_usuario )
                     VALUES ( ".$grupoId.", '".formatearTelefono($participante->phoneNumber)."')";
                $sql = $conexion->prepare($sql);
                $sql->execute();
        }
    }

  }

  function eliminaParticipante ($telefonoParticipante, $grupoId, $conexion) {

    $sql = "  DELETE purchase_participante FROM purchase_participante  
              INNER JOIN purchase ON purchase_participante.id_purchase = purchase.id AND purchase.id_grupo = ".$grupoId." 
              WHERE telefono_usuario LIKE '".$telefonoParticipante."';
              DELETE purchase FROM purchase INNER JOIN user ON user.id = purchase.user_id
              WHERE user.telephone = '".$telefonoParticipante."' AND id_grupo=".$grupoId.";
              DELETE FROM usuarios_grupo WHERE telefono_usuario='".$telefonoParticipante."' AND id_grupo=".$grupoId;
    $sql = $conexion->prepare($sql);
    $sql->execute();

  }

  function tieneCuentasPendientesParticipante( $telefonoParticipante, $idGrupo, $conexion ) {
     $sql = "SELECT * FROM purchase_participante pp INNER JOIN purchase p ON pp.id_purchase = p.id AND p.id_grupo = ".$idGrupo."
                WHERE (pp.pagado=0 OR pp.pagado = 2) AND telefono_usuario LIKE '".$telefonoParticipante."'" ;  
      $sql = $conexion->prepare($sql);
      $sql->execute();
      if($sql->rowCount() > 0){
          return true;
      }
      return false;
  }

  /*

  function insertarParticipantesGasto($participantes, $importe, $purchaseId, $conexion, $idCreadorGasto) {

     foreach( $participantes as $participante ){
                $pagado=0;
                $importeDeuda = $importe;
                if( $participante->id == $idCreadorGasto){
                    $pagado = 2;
                    $importeDeuda = ($importe * count($participantes)) - $importe;
                }
                $sql = "INSERT INTO purchase_participante( id_purchase, telefono_usuario, count, pagado )
                     VALUES ( ".$purchaseId.", '".$participante->telefono_usuario."', ".$importeDeuda.", ".$pagado.")";
                $sql = $conexion->prepare($sql);
                $sql->execute();
    };

  }

  */

  function insertarParticipantesGasto($participantes, $importe, $purchaseId, $conexion, $idCreadorGasto, $telefonoCreador) {

    $creadorParticipa = false;
    foreach( $participantes as $participante ){
          $pagado=0;
          $importeDeuda = $importe;
          if( $participante->telefono_usuario == $telefonoCreador){
              $pagado = 2;
              $importeDeuda = ($importe * count($participantes)) - $importe;
              $creadorParticipa = true;
          }
          $sql = "INSERT INTO purchase_participante( id_purchase, telefono_usuario, count, participaEnGasto, pagado )
              VALUES ( ".$purchaseId.", '".$participante->telefono_usuario."', ".$importeDeuda.", 1, ".$pagado.")";
          $sql = $conexion->prepare($sql);
          $sql->execute();
    };
    if( !$creadorParticipa ){
      $total = $importe * count($participantes);
      $pagado = 2;
      $sql = "INSERT INTO purchase_participante( id_purchase, telefono_usuario, count, participaEnGasto, pagado )
        VALUES ( ".$purchaseId.", '".$telefonoCreador."', ".$total.", 0,".$pagado.")";
      $sql = $conexion->prepare($sql);
      $sql->execute();
    }

 }



  function guardarNombreUserAgenda ( $idUserPadre, $usuariosHijos, $conexion ) {

      foreach( $usuariosHijos as $usuario ){
              $sql = " SELECT * FROM nombre_usuario_agenda
                      WHERE id_usuario=".$idUserPadre." AND telefono_agenda='".formatearTelefono($usuario->phoneNumber)."'";
              $sql = $conexion->prepare($sql);
              $sql->execute();
              if($sql->rowCount() == 0){
                  $name = preg_replace('/[^A-Za-z0-9\-\@]/', '', $usuario->name);
                  $sql = "INSERT INTO nombre_usuario_agenda( id_usuario, telefono_agenda, nombre_agenda )
                          VALUES ( ".$idUserPadre.", '".formatearTelefono($usuario->phoneNumber)."', '".$name."')";
                  $sql = $conexion->prepare($sql);
                  $sql->execute();
              }
      }
  }


  function updateNombreUserAgenda ( $idUserPadre, $usersNotName, $conexion ) {

    foreach( $usersNotName as $usuario ){
      $name = preg_replace('/[^A-Za-z0-9\-\@]/', '', $usuario->name);
      $sql = " UPDATE nombre_usuario_agenda SET nombre_agenda = '".$name."' WHERE telefono_agenda = '".formatearTelefono($usuario->phoneNumber)."' AND id_usuario = ".$idUserPadre.";";
      $sql = $conexion->prepare($sql);
      $sql->execute();
    }
}



  function existeParticipanteGrupo( $participante, $idGrupo, $conexion ) {
    $sql = " SELECT * FROM usuarios_grupo
                 WHERE id_grupo=".$idGrupo." AND telefono_usuario='".formatearTelefono($participante->phoneNumber)."' AND fecha_baja IS NULL";
    $sql = $conexion->prepare($sql);
    $sql->execute();
    if($sql->rowCount() > 0){
        return true;
    }
    return false;
  }

  function esAdminGrupo( $idGrupo, $idUser, $conexion ) {
    $sql = " SELECT * FROM grupo
                 WHERE id_user_creador=".$idUser." AND id=".$idGrupo;
    $sql = $conexion->prepare($sql);
    $sql->execute();
    if($sql->rowCount() > 0){
        return true;
    }
    return false;
  }


  function deleteImage( $path ) {
      if( is_file($path) )
        unlink($path = 'uploads/'.$imagenName);
  }

  function obtenerOneSignaId ($participantes, $conexion, $campoTelefono, $telefonoCreadorGasto = null) {

    $oneSignalIds = array();
    //unset($participantes[0]);
    $in = '(';
    foreach( $participantes as $participante ){
       if($telefonoCreadorGasto != $participante->$campoTelefono)
          $in.="'".formatearTelefono($participante->$campoTelefono)."',";
    }
    $in = trim($in, ',');
    $in.=')';
    $consulta = " SELECT oneSignalId FROM user WHERE telephone IN ".$in." AND oneSignalId IS NOT NULL AND oneSignalId <> '' AND recibir_push=1";
    $sql = $conexion->prepare($consulta);
    $sql->execute();
    while ($row = $sql->fetch()){
        array_push($oneSignalIds, $row['oneSignalId']) ;
    }
    return $oneSignalIds;
  }

  function obtenerOneSignaIdMoroso( $moroso, $conexion ){
     $oneSignalIds = array();
    $consulta = " SELECT oneSignalId FROM user WHERE telephone = ".$moroso->telefono_usuario." AND recibir_push=1";
    $sql = $conexion->prepare($consulta);
    $sql->execute();
    while ($row = $sql->fetch()){
        array_push($oneSignalIds, $row['oneSignalId']) ;
    }
    return $oneSignalIds;
  }

  function getIdUserSendPush( $participantes, $conexion, $tipo, $telefonoCreadorGasto = null ){

    $ids = array();

    if($tipo == '3') // a moroso
        $consulta = " SELECT id FROM user WHERE telephone = ".$participantes->telefono_usuario." AND recibir_push=1";
    else{
        if($tipo == 1) // creando grupo
            $campoTelefono = 'phoneNumber';
        else // creando gasto
           $campoTelefono = 'telefono_usuario';

       // unset($participantes[0]);
        $in = '(';
        foreach( $participantes as $participante ){
          if($telefonoCreadorGasto != $participante->$campoTelefono)
              $in.="'".formatearTelefono($participante->$campoTelefono)."',";
        }
        $in = trim($in, ',');
        $in.=')';
        $consulta = " SELECT id FROM user WHERE telephone IN ".$in." AND oneSignalId IS NOT NULL AND oneSignalId <> '' AND recibir_push=1";
    }


    $sql = $conexion->prepare($consulta);
    $sql->execute();
    while ($row = $sql->fetch()){
        array_push($ids, $row['id']) ;
    }
    return $ids;

  }


  function notificacionCreada( $notificationId, $conexion ){

    $consulta = " SELECT * FROM notificaciones WHERE notificationID='".$notificationId."'";
    $sql = $conexion->prepare($consulta);
    $sql->execute();
    if($sql->rowCount() > 0){
        return true;
    }
        return false;
  }

  function comprobrarDeudaSaldada( $idTicket, $conexion ) {
       $consulta = " SELECT * FROM purchase_participante pp
            INNER JOIN purchase p ON pp.id_purchase = p.id AND pp.pagado = 0
       WHERE id_purchase = ".$idTicket;
       $sql = $conexion->prepare($consulta);
       $sql->execute();
       if($sql->rowCount() == 0){
           $consulta = "UPDATE purchase SET pagado=1 WHERE id=".$idTicket;
           $statement = $conexion->prepare($consulta);
           $statement->execute();
                return true;
        }
        return false;
  }


  function request_headers() {
          $arh = array();
          $rx_http = '/\AHTTP_/';
          foreach($_SERVER as $key => $val) {
                  if( preg_match($rx_http, $key) ) {
                          $arh_key = preg_replace($rx_http, '', $key);
                          $rx_matches = array();
                          // do string manipulations to restore the original letter case
                          $rx_matches = explode('_', $arh_key);
                          if( count($rx_matches) > 0 and strlen($arh_key) > 2 ) {
                                  foreach($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst($ak_val);
                                  $arh_key = implode('-', $rx_matches);
                          }
                          $arh[$arh_key] = $val;
                  }
          }
          return( $arh );

          /*
          $headers = request_headers();
          $response = array();

          $api_key = $headers['X-API-KEY'];
          echo $api_key;
          */
  }
  
  function writeInFile( $text ) {
        $file = fopen("error.txt", "w");
        fwrite($file, $text . PHP_EOL);
        fclose($file);
  }



 ?>
