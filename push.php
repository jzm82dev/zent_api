<?php

date_default_timezone_set("Europe/Madrid");


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

    if ($_SERVER['REQUEST_METHOD'] == 'GET'){

        switch ($_GET['function']) {

          case 'getAllNotifications':

              $sql = $dbConn->prepare("SELECT n.*, u.name, u.foto FROM notificaciones n
                	INNER JOIN user u ON n.user_id_creador = u.id
                	WHERE n.user_id =:userId  ORDER BY n.id DESC" );
              $sql->bindValue(':userId', $_GET['userId']);
              $sql->execute();
              $grupos =  $sql->fetchAll();
              $data = array(
                'status' => 'success',
                'notificaciones' => $grupos,
                'message' => 'OK.'
              );
              header("Content-type: application/json; charset=utf-8");
              echo json_encode($data);
              exit();
              break;


          case 'getNotificationsByGroup':

              $sql = $dbConn->prepare("SELECT n.*, u.name, u.foto FROM notificaciones n
                	INNER JOIN user u ON n.user_id_creador = u.id
                	WHERE n.user_id =:userId and n.grupo_id=:grupoId
                  ORDER BY n.id DESC" );
              $sql->bindValue(':userId', $_GET['userId']);
              $sql->bindValue(':grupoId', $_GET['grupoId']);
              $sql->execute();
              $grupos =  $sql->fetchAll();
              $data = array(
                'status' => 'success',
                'notificaciones' => $grupos,
                'message' => 'OK.'
              );
              header("Content-type: application/json; charset=utf-8");
              echo json_encode($data);
              exit();
              break;

        } //switch

    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST'){

        $url = 'https://onesignal.com/api/v1/notifications';

        //inicializamos el objeto CUrl
        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic OTViZjMzOTAtYzU4MC00NjEwLWFmYjMtMzkxYmMzOGY5ZTNj'
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        switch ($_GET['function']) {

                    case 'sendPush':

                        $input = $_POST;
                        $participantes = json_decode($input['participantes']);
                        $idUserEnviaNotificacion = $_GET['idUserEnviaNot'];
                        $fecha = date('Y-m-d H:i:s');
                        $telefonoCreador = '';


                        $data = array(
                          "grupoId" => "8",
                          "idUsuario" => "25"
                        );

                        switch($_GET['tipo']){

                            case '1':

                                $titulo = 'Has sido incluído al grupo '.$_GET['titulo'];
                                $detalle = '';//'Has sido incluído por '.$_GET['userName'];

                                $heading = array (
                                    "en" => "New group called ".$_GET['titulo'],
                                    "es"=> "Nuevo grupo llamado ".$_GET['titulo']
                                );
                                $contents = array(
                                    "en" => "You have been joined by ".$_GET['userName'],
                                    "es"=> "Has sido incluido al grupo por ".$_GET['userName']
                                );
                                $idsOneSignal = obtenerOneSignaId($participantes, $dbConn, 'phoneNumber');

                                break;

                            case '2':

                                $titulo = 'Te ha incluído al gasto '.$_GET['titulo'].' del grupo '.$_GET['nombreGrupo'];
                                $detalle = 'Importe total '.$_GET['importe'].' €';

                                $heading = array (
                                    "en" => "New spending ".$_GET['titulo']." to ".$_GET['nombreGrupo'],
                                    "es"=> "Nuevo gasto ".$_GET['titulo']." al grupo ".$_GET['nombreGrupo']
                                );

                                $contents = array(
                                    "en" => "You have been joined with a total count of ".$_GET['importe']." €",
                                    "es"=> "Se te ha incluido al gasto con un importe total de ".$_GET['importe']." €"
                                );
                                $telefonoCreador = $_GET['telefonoCreador'];
                                $idsOneSignal = obtenerOneSignaId($participantes, $dbConn, 'telefono_usuario', $telefonoCreador);

                                break;

                            case '3':

                                $titulo = 'Solicitud de deuda';
                                $detalle = 'Te recuerda que le debes '.$_GET['deuda'].' € del grupo '.$_GET['nombreGrupo'] ;

                                $heading = array (
                                    "en" => "New request of debt" ,
                                    "es"=> "Nueva solicitud de deuda"
                                );

                                $contents = array(
                                    "en" => "You owe to ".$_GET['solicitante']." ".$_GET['deuda']." € of  ".$_GET['nombreGrupo'] ,
                                    "es"=> "Le debes a ".$_GET['solicitante']." ".$_GET['deuda']." € del grupo de gasto ".$_GET['nombreGrupo']
                                );

                                $idsOneSignal = obtenerOneSignaIdMoroso($participantes, $dbConn);

                                break;

                            case '4':

                                $titulo = 'Deuda saldada';
                                $detalle = $_GET['solicitante'].' te recuerda que te ha pagado' ;

                                $heading = array (
                                    "en" => "New request of debt" ,
                                    "es"=> "Recordatorio deuda saldada"
                                );

                                $contents = array(
                                    "en" => "You owe to ".$_GET['solicitante']." ".$_GET['deuda']." € of  ".$_GET['nombreGrupo'] ,
                                    "es"=> $_GET['solicitante']." te recuerda que te he pagado los ".$_GET['deuda']." € del grupo ".$_GET['nombreGrupo'].". Puedes proceder a liquidar sus deudas en la pestaña de Deudas"
                                );

                                $idsOneSignal = obtenerOneSignaIdMoroso($participantes, $dbConn);

                                break;

                        }


                        $jsonData = array(
                            "app_id" => "63bb8327-e09f-4a8f-9de3-c50ae2b1f7e8",
                    	    "data"=> $data,
                    	    "contents" => $contents,
                    	    "headings" => $heading,
                        	"include_player_ids" => $idsOneSignal
                        );


                    break;
         }


        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        $notResp =  json_decode($result);

        if( isset($notResp->id) && $_GET['tipo']<>'4'){
            $users = array();
            $users = getIdUserSendPush($participantes, $dbConn, $_GET['tipo'], $telefonoCreador);
         
            foreach ($users as $userId)
            {
                $sql =  $dbConn->prepare("INSERT INTO notificaciones (user_id, notificationID, titulo, detalle, user_id_creador, id_tipo, grupo_id, fecha)
                                        VALUES (:userId, :notificationID, :titulo, :detalle, :idUserCreador, :idTipo, :idGrupo, :fecha)");
                $sql->bindValue(':userId', $userId);
                $sql->bindValue(':notificationID', $notResp->id );
                $sql->bindValue(':titulo', $titulo);
                $sql->bindValue(':detalle', $detalle);
                $sql->bindValue(':fecha', $fecha);
                $sql->bindValue(':idUserCreador', $idUserEnviaNotificacion);
                $sql->bindValue(':idTipo', $_GET['tipo']);
                $sql->bindValue(':idGrupo', $_GET['idGrupo']);
                $sql->execute();
            }

        }


        $resultado = array (
            data => 'success',
            result => $result
        );

        header("Content-type: application/json; charset=utf-8");
        echo json_encode($resultado);

        exit();
    }




?>
