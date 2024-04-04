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


        // Crear un nuevo post
        if ($_SERVER['REQUEST_METHOD'] == 'POST')
        {

            $generatedName= '';

            if(isset($_FILES['image'])){
                $path = 'photos/';
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

            $input = $_POST;
            $user = json_decode($input['usuario']);

            switch( $_GET['option']) {

                case 'nuevo_user':


                    // Comprabamos si hay ya usuario dado de alta con ese telefono
                    $sql = $dbConn->prepare("SELECT telephone FROM user WHERE telephone=:telephone");
                    $sql->bindValue(':telephone', $user->telephone);
                    $sql->execute();
                    if($sql->rowCount() > 0){
                        header("HTTP/1.1 200 OK");
                        echo json_encode('Existe telefono dado de alta');
                        exit();
                    }

                    $verificateCode = rand(1000, 9999);

                    $sql =  $dbConn->prepare("INSERT INTO user
                        (id_firebase, name, email, password, telephone, foto, oneSignalId, recibir_push, verificate_code)
                        VALUES
                        (:idFirebase, :name, :email, :password, :telephone, :fotoName, :oneSignalId, :recibir_push, :code )");

                    $sql->bindValue(':idFirebase', $user->idFirebase);
                    $sql->bindValue(':name', $user->name);
                    $sql->bindValue(':email', $user->email);
                    $sql->bindValue(':password', $user->password);
                    $sql->bindValue(':telephone', $user->telephone);
                    $sql->bindValue(':fotoName', $generatedName);
                    $sql->bindValue(':oneSignalId', $user->oneSignalId);
                    $sql->bindValue(':recibir_push', $user->recibirPush);
                    $sql->bindValue(':code', $verificateCode);

                    $sql->execute();
                    $id = $dbConn->lastInsertId();

                    $resp = getUserById( $id, $dbConn );

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
                        'message' => 'Error al darte de alta.'
                      );
                    }
                  
                    header("Content-type: application/json; charset=utf-8");
                    echo json_encode($data);

                    exit();
                    break;

                case 'modificar_user':

                    $statement = $dbConn->prepare("SELECT foto FROM user WHERE id=:id");
                    $statement->bindValue(':id', $user->id);
                    $statement->execute();
                    if($statement->rowCount() > 0){
                       $row = $statement->fetch();
                       $imagenName = $row['foto'];
                       if(is_file( 'photos/'.$imagenName ))
                           unlink( 'photos/'.$imagenName );
                    }

                    writeInFile("->UPDATE user SET name=:name, email=:email, password=:password, recibir_push=".$user->recibirPush." ,foto=:fotoName  WHERE id=".$user->id);
                    $statement = $dbConn->prepare("UPDATE user SET name=:name, email=:email, recibir_push=:recibePush, password=:password, foto=:fotoName  WHERE id=:id");

                    $statement->bindValue(':name', $user->name);
                    $statement->bindValue(':email', $user->email);
                    $statement->bindValue(':password', $user->password);
                    $statement->bindValue(':id', $user->id);
                    $statement->bindValue(':fotoName', $generatedName);
                    $statement->bindValue(':recibePush', $user->recibirPush);
                    $statement->execute();

                    header("HTTP/1.1 200 OK");
                    $input['foto'] = $generatedName;
                    $input['id'] = $user->id;
                    $input['recibirPush'] = $user->recibirPush;
                    echo json_encode($input);
                    exit();

                break;
            }


        }


        //Actualizar
        if ($_SERVER['REQUEST_METHOD'] == 'PUT')
        {
            $input = $_GET;

            $user = json_decode($input['usuario']);

            $statement = $dbConn->prepare("UPDATE user SET name=:name, email=:email, password=:password, recibir_push=:recibePush WHERE id=:id");
            writeInFile("UPDATE user SET name=:name, email=:email, password=:password, foto=:fotoName  WHERE id=".$user->id);
                    

            $statement->bindValue(':name', $user->name);
            $statement->bindValue(':email', $user->email);
            $statement->bindValue(':password', $user->password);
            $statement->bindValue(':recibePush', $user->recibirPush);
            $statement->bindValue(':id', $user->id);


            $statement->execute();

            header("HTTP/1.1 200 OK");
            echo json_encode($user);
            exit();

        }

        //En caso de que ninguna de las opciones anteriores se haya ejecutado
        header("HTTP/1.1 400 Bad Request");

?>
