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


        if (isset($_GET['id']) && !isset($_GET['function'])){

              $sql = $dbConn->prepare("SELECT * FROM user where id=:id");
              $sql->bindValue(':id', $_GET['id']);
              $sql->execute();
              header("HTTP/1.1 200 OK");
              echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
              exit();

        }else{
             switch ($_GET['function']) {

               case 'login':

                   $login = false;
                   //$email =  (isset($_GET['email'])) ? $_GET['email'] : null;
                   $telefono = (isset($_GET['telefono'])) ? $_GET['telefono'] : null;
                   $password =  (isset($_GET['pwd'])) ? $_GET['pwd'] : null;
                   $oneSignalId = (isset($_GET['oneSignalId'])) ? $_GET['oneSignalId'] : null;

                   $resp = signup( $telefono, $password, $dbConn, $oneSignalId);
                  
                   if( $resp->verificate_code != 1){
                    $statement = $dbConn->prepare("DELETE FROM user where id=:id");
                    $statement->bindValue(':id', $resp->id);
                    $statement->execute();
                    $data = array(
                        'status' => 'no_verify',
                        'login' => 'ok',
                        'message' => 'no verificate code'
                      );
                      header("Content-type: application/json; charset=utf-8");
                      echo json_encode($data);

                      exit();
                      break;
                   }

                   if( is_object($resp) ){
                       $login = true;
                       $token = createToken( $resp );
                       $data = array(
                         'status' => 'success',
                         'login' => $login,
                         'user' => $resp,
                         'token' => $token,
                       );
                   }else{
                     $data = array(
                       'status' => 'success',
                       'login' => $login,
                       'message' => $resp
                     );
                   }

                   header("Content-type: application/json; charset=utf-8");
                   echo json_encode($data);

                   exit();
                   break;


                case 'total':

                    // Obtenemos gastos por participantes
                    $gastosParticipantes = array();
                    $gasto =  $dbConn->prepare(" SELECT p.telefono_id as telefono, SUM(COUNT) AS gastado FROM purchase p
                                                 WHERE p.id_grupo = :id
                                                 GROUP BY p.telefono_id; " );
                    $gasto->bindValue(':id', $_GET['id']);
                    $gasto->execute();
                    $gasto->setFetchMode(PDO::FETCH_ASSOC);
                    $totalGasto = 0;
                    while($data = $gasto->fetch( PDO::FETCH_ASSOC )){
                        $totalGasto += $data['gastado'];
                        $gastosParticipantes[$data['telefono']] = $data['gastado'] ;
                    }

                    // Obtenemos deudas por participantes
                    $deudasParticipantes = array();
                    $deudas =  $dbConn->prepare(" SELECT pp.telefono_usuario as telefono, SUM(pp.count) AS deuda FROM purchase_participante	pp
                                            	INNER JOIN purchase	p ON pp.id_purchase = p.id
                                            	LEFT JOIN user u ON pp.telefono_usuario = u.telephone
                                            	WHERE p.id_grupo = :id AND pp.pagado = 0
                                            	GROUP BY pp.telefono_usuario; ");
                    $deudas->bindValue(':id', $_GET['id']);
                    $deudas->execute();
                    $deudas->setFetchMode(PDO::FETCH_ASSOC);
                    while($data = $deudas->fetch( PDO::FETCH_ASSOC )){
                        $deudasParticipantes[$data['telefono']] = $data['deuda'] ;
                    }

                    // Obtenemso lo que le deben a cada participante
                    $debeAParticipantes  = array();
                    $seLedebe = $dbConn->prepare(" SELECT pp.telefono_usuario as telefono, SUM(pp.count) AS deber FROM purchase_participante	pp
                                            	INNER JOIN purchase	p ON pp.id_purchase = p.id
                                            	LEFT JOIN user u ON pp.telefono_usuario = u.telephone
                                            	WHERE p.id_grupo = :id AND pp.pagado = 2
                                            	GROUP BY pp.telefono_usuario; ");
                    $seLedebe->bindValue(':id', $_GET['id']);
                    $seLedebe->execute();
                    $seLedebe->setFetchMode(PDO::FETCH_ASSOC);
                    while($data = $seLedebe->fetch( PDO::FETCH_ASSOC )){
                        $debeAParticipantes[$data['telefono']] = $data['deber'] ;
                    }
                
                    // Obtenemos los participantes
                    $sql = $dbConn->prepare("SELECT IF(u.id IS NOT NULL, u.id, ug.telefono_usuario) as id, nua.nombre_agenda as name, ug.telefono_usuario as telefono, IF(u.id =:userId , 1, 2) AS ordenar FROM usuarios_grupo ug
                                                    LEFT JOIN nombre_usuario_agenda nua ON nua.telefono_agenda = ug.telefono_usuario
                                                             AND nua.id_usuario=:userId AND ug.fecha_baja IS NULL
                                                    LEFT JOIN user u ON u.telephone = ug.telefono_usuario
                                                    WHERE ug.id_grupo=:id GROUP BY ug.telefono_usuario ORDER BY ordenar, nua.nombre_agenda ");
                    $sql->bindValue(':id', $_GET['id']);
                    $sql->bindValue(':userId', $_GET['userId']);
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    $participantes = array();
                    $num_participantes = $sql->rowCount();
                    while($data = $sql->fetch( PDO::FETCH_ASSOC )){
                        $participante['id'] = $data['id'];
                        $participante['name'] = $data['name'];
                        $participante['telefono_usu'] = $data['telefono'] ;
                        $participante['gasto'] = isset($gastosParticipantes[$data['telefono']]) ? $gastosParticipantes[$data['telefono']] : 0 ;  //$gastosParticipantes[$data['telefono']];
                        $debeA = 0;
                        if(isset($debeAParticipantes[$data['telefono']]))
                            $debeA = $debeAParticipantes[$data['telefono']];
                        $deudaP = 0;
                        if(isset($deudasParticipantes[$data['telefono']]))
                          $deudaP = $deudasParticipantes[$data['telefono']];
                          $participante['balance'] = $debeA - $deudaP;
                        $participante['test'] = $debeA .'-'.$deudaP;  
                        array_push($participantes, $participante);
                    }
                    //print_r($participantes);die();
                    echo json_encode( $participantes );
                    exit();

                break;

                case 'users':
                    $sql = $dbConn->prepare("SELECT * FROM user");
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    // header("HTTP/1.1 200 OK");

                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;

                case 'usersByGroup':
                    /*$sql = $dbConn->prepare("SELECT IF(u.id IS NOT NULL, u.id, ug.telefono_usuario) AS id, nua.nombre_agenda as name, ug.telefono_usuario, IF(u.id =:userId , 1, 2) AS ordenar FROM usuarios_grupo ug
                                                    INNER JOIN nombre_usuario_agenda nua ON nua.telefono_agenda = ug.telefono_usuario
                                                            AND ug.id_grupo=:id AND nua.id_usuario=:userId AND ug.fecha_baja IS NULL
                                                    LEFT JOIN user u ON u.telephone = ug.telefono_usuario
                                                    GROUP BY ug.telefono_usuario ORDER BY ordenar, nua.nombre_agenda" ); */
                    $sql = $dbConn->prepare("SELECT IF(u.telephone IS NOT NULL, u.telephone, ug.telefono_usuario) AS id, nua.nombre_agenda as name, ug.telefono_usuario, IF(u.id =:userId , 1, 2) AS ordenar 
                    FROM usuarios_grupo ug INNER JOIN nombre_usuario_agenda nua ON nua.telefono_agenda = ug.telefono_usuario AND ug.id_grupo=:id AND nua.id_usuario=:userId AND ug.fecha_baja IS NULL
                    LEFT JOIN user u ON u.telephone = ug.telefono_usuario
                    GROUP BY ug.telefono_usuario ORDER BY ordenar, nua.nombre_agenda" );
                    $sql->bindValue(':id', $_GET['idGrupo']);
                    $sql->bindValue(':userId', $_GET['userId']);
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    // header("HTTP/1.1 200 OK");

                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;

                case 'usersByGroupNoAgenda':

                    if( $_GET['idGrupo'] == 0 ){
                        $sql = $dbConn->prepare(" SELECT DISTINCT telefono_usuario FROM usuarios_grupo
    	                                            WHERE id_grupo IN ( SELECT ug.id_grupo FROM usuarios_grupo ug INNER JOIN user u ON ug.telefono_usuario = u.telephone
    							                                WHERE u.id=:id_usuario AND fecha_baja IS NULL)
    		                                          AND telefono_usuario NOT IN ( SELECT telefono_agenda FROM nombre_usuario_agenda WHERE id_usuario=:id_usuario );");
                        $sql->bindValue(':id_usuario', $_GET['idUsuario']);
                    }else{
                      $sql = $dbConn->prepare(" SELECT DISTINCT telefono_usuario FROM usuarios_grupo
                                                WHERE id_grupo =:idGrupo
                                                AND telefono_usuario NOT IN ( SELECT telefono_agenda FROM nombre_usuario_agenda WHERE id_usuario=:id_usuario );");
                      $sql->bindValue(':id_usuario', $_GET['idUsuario']);
                      $sql->bindValue(':idGrupo', $_GET['idGrupo']);
                    }
                    //$sql->bindValue(':id', $_GET['idGrupo']);

                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    // header("HTTP/1.1 200 OK");

                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;

                case 'updateContactsNotName':
                    $sql = $dbConn->prepare(" SELECT nua.telefono_agenda FROM nombre_usuario_agenda nua WHERE nua.nombre_agenda LIKE '@%' AND nua.id_usuario=:id_usuario AND nua.telefono_agenda not like :telefono_usuario;");
                    $sql->bindValue(':id_usuario', $_GET['idUsuario']);
                    $sql->bindValue(':telefono_usuario', $_GET['telefono']);
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    
                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;

                case 'byEmail':

                    $sql = $dbConn->prepare("SELECT * FROM user where email like :email ");
                    $sql->bindValue(':email', $_GET['email']);
                    $sql->execute();
                    header("HTTP/1.1 200 OK");
                    echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
                    exit();
                    break;

                case 'byTelefono':

                    $sql = $dbConn->prepare("SELECT * FROM user where telephone like :telefono ");
                    $sql->bindValue(':telefono', $_GET['telefono']);
                    $sql->execute();
                    header("HTTP/1.1 200 OK");
                    echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
                    exit();
                    break;


                case 'participantes':

                   $sql = $dbConn->prepare("SELECT IF(u.id IS NOT NULL, u.id, 0) AS idUsuario, nua.nombre_agenda as nombre, ug.telefono_usuario, pp.id, pp.pagado
				                            	FROM usuarios_grupo ug
	                                            INNER JOIN nombre_usuario_agenda nua ON nua.telefono_agenda = ug.telefono_usuario AND ug.id_grupo=:idGrupo AND nua.id_usuario=:idUserLogado AND ug.fecha_baja IS NULL
	                                            LEFT JOIN user u ON u.telephone = ug.telefono_usuario
					                            LEFT JOIN purchase_participante pp ON ug.telefono_usuario = pp.telefono_usuario AND pp.id_purchase=:idPurchase AND pp.participaEnGasto = 1
	                                            GROUP BY ug.telefono_usuario");
                    $sql->bindValue(':idPurchase', $_GET['idPurchase']);
                    $sql->bindValue(':idUserLogado', $_GET['idUserLogado']);
                    $sql->bindValue(':idGrupo', $_GET['idGrupo']);
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    // header("HTTP/1.1 200 OK");

                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;

                case 'existe_telefono':
                    //Comprobamos si está verificado sino eliminamos
                    $sql = $dbConn->prepare("SELECT id FROM user WHERE telephone=:telefono and verificate_code<>1");
                    $sql->bindValue(':telefono', $_GET['telefono']);
                    $sql->execute();
                    if( $sql->fetch(PDO::FETCH_ASSOC) ){
                        $statement = $dbConn->prepare("DELETE FROM user where telephone=:telefono and verificate_code<>1");
                        $statement->bindValue(':telefono', $_GET['telefono']);
                        $statement->execute();
                    }
                    $sql = $dbConn->prepare("SELECT id FROM user WHERE telephone=:telefono");
                    $sql->bindValue(':telefono', $_GET['telefono']);
                    $sql->execute();
                    header("HTTP/1.1 200 OK");
                    echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
                    exit();
                    break;


                case 'correct_code':

                    $sql = $dbConn->prepare("SELECT id FROM user WHERE telephone=:telefono AND verificate_code=:code");
                    $sql->bindValue(':telefono', $_GET['telefono']);
                    $sql->bindValue(':code', $_GET['code']);
                    $sql->execute();
                    header("HTTP/1.1 200 OK");
                    echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
                    exit();
                    break;

                case 'tiene_onesignal_id':
                    $sql = $dbConn->prepare("SELECT oneSignalId FROM user WHERE telephone=:telefono");
                    $sql->bindValue(':telefono', $_GET['telefono']);
                    $sql->execute();
                    header("HTTP/1.1 200 OK");
                    echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
                    exit();
                    break;

                  case 'existe_email':

                        $sql = $dbConn->prepare("SELECT id FROM user WHERE email=:email");
                        $sql->bindValue(':email', $_GET['email']);
                        $sql->execute();
                        header("HTTP/1.1 200 OK");
                        echo json_encode(  $sql->fetch(PDO::FETCH_ASSOC)  );
                        exit();
                        break;

                case 'resumen_deuda_participante':

                    /*$sql = $dbConn->prepare(" (SELECT nua.nombre_agenda, p.title, pp.count, 1 AS tipo, u.id FROM purchase p
                    	INNER JOIN purchase_participante pp ON p.id = pp.id_purchase
                    	INNER JOIN user u ON u.id = p.user_id
                    	INNER JOIN nombre_usuario_agenda nua ON nua.telefono_agenda = u.telephone AND nua.id_usuario =:idUsuarioLogado
                    	WHERE p.id_grupo = :idGrupo AND pp.telefono_usuario = :telefonoUsuario AND pp.pagado = 0
                     	ORDER BY u.id) UNION
                        ( SELECT nua.nombre_agenda, p.title, pp.count, 2 AS tipo, u.id FROM purchase p
                    	INNER JOIN purchase_participante pp ON p.id = pp.id_purchase AND pp.pagado=0
                    	INNER JOIN nombre_usuario_agenda nua ON pp.telefono_usuario = nua.telefono_agenda AND nua.id_usuario=:idUsuarioLogado
                    	LEFT JOIN user u ON u.telephone = nua.telefono_agenda
                        WHERE p.user_id = :userId AND p.id_grupo=:idGrupo ORDER BY nua.nombre_agenda)"); */
                        
                    $sql = $dbConn->prepare(" SELECT * FROM (
                    SELECT nua.nombre_agenda, p.title, pp.count, 1 AS tipo, p.telefono_id AS telefono_creador, pp.telefono_usuario as telefono_deudor, p.date as fecha FROM purchase_participante pp 
                        INNER JOIN purchase p  ON p.id = pp.id_purchase AND pp.telefono_usuario  = :telefonoUsuario and pp.pagado = 0
                        INNER JOIN nombre_usuario_agenda nua ON p.telefono_id = nua.telefono_agenda AND nua.id_usuario = :idUsuarioLogado
                    WHERE p.id_grupo = :idGrupo
                    UNION ALL 
                    SELECT nua.nombre_agenda, p.title, pp.count, 2 AS tipo, p.telefono_id AS telefono_creador, pp.telefono_usuario as telefono_deudor, p.date as fecha FROM purchase p 
                        INNER JOIN purchase_participante pp ON p.id = pp.id_purchase AND pp.pagado = 0
                        INNER JOIN nombre_usuario_agenda nua ON pp.telefono_usuario = nua.telefono_agenda AND nua.id_usuario = :idUsuarioLogado
                    WHERE p.id_grupo = :idGrupo AND p.telefono_id = :telefonoUsuario ) a order by fecha DESC");
                    $sql->bindValue(':idUsuarioLogado', $_GET['idUserLog']);
                    $sql->bindValue(':idGrupo', $_GET['idGrupo']);
                    $sql->bindValue(':telefonoUsuario', $_GET['telefonoUserDeudas']);
                    //$sql->bindValue(':userId', intval($_GET['userId']));
                    header("HTTP/1.1 200 OK");
                    $sql->execute();
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    
                    echo json_encode( $sql->fetchAll() );
                    exit();
                    break;


                case 'resumen_deuda':
                    $sql = $dbConn->prepare("SELECT (SELECT sum(pp.count)  FROM purchase p 
                        INNER JOIN grupo g on g.id  = p.id_grupo
                        INNER JOIN purchase_participante pp on p.id = pp.id_purchase and pp.pagado = 0 and p.telefono_id = :telefonoUsuario) as 'me_deben',
                        (SELECT sum(pp.count) as 'debo' from purchase p 
                         INNER JOIN grupo g on g.id  = p.id_grupo
                         INNER JOIN purchase_participante pp on p.id = pp.id_purchase and pp.pagado = 0 and pp.telefono_usuario = :telefonoUsuario) as 'debo'");
                    $sql->bindValue(':telefonoUsuario', $_GET['telefonoUsuario']);
                    header("HTTP/1.1 200 OK");
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

}

//Borrar
if ($_SERVER['REQUEST_METHOD'] == 'DELETE')
{
    $id = $_GET['id'];
    $statement = $dbConn->prepare("DELETE FROM user where id=:id");
    $statement->bindValue(':id', $id);
    $statement->execute();
	$data = array(
        'status' => 'success',
        'message' => 'User verified.',
      );
      header("Content-type: application/json; charset=utf-8");
      echo json_encode($data);
      exit();
}

//Actualizar
if ($_SERVER['REQUEST_METHOD'] == 'PUT')
{
    $input = $_GET;
    $function = isset($input['function']) ? $input['function'] : '' ;

    if( $function == ''){
        
        $postId = $input['id'];
        $fields = getParams($input);

        $sql = "
            UPDATE user
            SET $fields
            WHERE id='$postId'
            ";

        $statement = $dbConn->prepare($sql);
        bindAllValues($statement, $input);

        $statement->execute();
        header("HTTP/1.1 200 OK");
        exit();
    }
    else{
        $userId = $input['user_id'];
        $sql = "UPDATE user SET verificate_code = 1 WHERE id=".$userId ;
        $statement = $dbConn->prepare($sql);
       
        $statement->execute();
        $data = array(
            'status' => 'success',
            'message' => 'User verified.',
          );
          header("Content-type: application/json; charset=utf-8");
          echo json_encode($data);
          exit();
    }
}


//En caso de que ninguna de las opciones anteriores se haya ejecutado
header("HTTP/1.1 400 Bad Request");

?>
