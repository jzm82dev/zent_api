<?php

include "config.php";
include "utils.php";


$dbConn =  connect($db);

$telefono = $_GET['telefono'];

$sql = $dbConn->prepare("SELECT * FROM user where telephone like :telefono ");
    $sql->bindValue(':telefono', $telefono);
    $sql->execute();
    if( $sql->rowCount()>0 ){
      $data = $sql->fetch(PDO::FETCH_OBJ ) ;
    }

if( $_GET['function'] == 'forgot_pass' ){

    

    if( $data->smsSend == 0 ){
      $data = array(
        'status' => 'error',
        'message' => 'Has superado el número máximo de mensajes enviados para recordar la contraseña');
    }else{

            $telefono = '34'.$data->telephone;
            $nombre = $data->name;
            $newPwd= substr(str_shuffle("0123456789"), 0, 4);
            $pwdCod = md5($newPwd);

            $consulta = "UPDATE user SET password='".$pwdCod."', smsSend=smsSend-1 WHERE id=".$data->id;
            $statement = $dbConn->prepare($consulta);
            $statement->execute();

            $mensaje = 'Hola '.$nombre.', tu contraseña temporal para Zent es '.$newPwd.'. Por favor, accede al apartado de Mis ajustes para cambiarla.';

            $request = '{
                "api_key":"83b906588e7c4c2ba4346f6b3945b3c2",
                "concat":1,
                "messages":[
                    {
                        "from":"ZENT",
                        "to":"'.$telefono.'",
                        "text":"'.$mensaje.'"
                    }
                ]
            }';

            $url = 'https://api.gateway360.com/api/3.0/sms/send';
            $headers = array('Content-Type: application/json');

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request);

            $result = curl_exec($ch);

            if (curl_errno($ch) != 0 ){
              $data = array(
                'status' => 'error',
                'message' => 'Error al enviar SMS. Código de error:'.curl_errno($ch).' Si persigue póngase en contacto con el administrador');
            }else{
              $data = array(
                'status' => 'success',
                'message' => 'Nueva contraseña enviada por mensaje de texto. Recuerde cambiarla lo antes posible'
              );
          }
      }
      header("Content-type: application/json; charset=utf-8");
      echo json_encode($data);
      exit();
    }else{
      $code = $_GET['code'];
      $telefono = '34'.$data->telephone;
            
      $mensaje = 'Hola '.$data->name.', '.$data->verificate_code.' es tu código para darte de alta en la App Zent';

      $request = '{
          "api_key":"83b906588e7c4c2ba4346f6b3945b3c2",
          "concat":1,
          "messages":[
              {
                  "from":"ZENT",
                  "to":"'.$telefono.'",
                  "text":"'.$mensaje.'"
              }
          ]
      }';

      $url = 'https://api.gateway360.com/api/3.0/sms/send';
      $headers = array('Content-Type: application/json');

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $request);

      $result = curl_exec($ch);

      if (curl_errno($ch) != 0 ){
        $data = array(
          'status' => 'error',
          'message' => 'Error al enviar SMS. Código de error:'.curl_errno($ch).' Si persigue póngase en contacto con el administrador');
      }else{
        $data = array(
          'status' => 'success',
          'message' => 'Código de verificación enviado por SMS'
        );
    }

    header("Content-type: application/json; charset=utf-8");
    echo json_encode($data);
    exit();
    } 



?>
