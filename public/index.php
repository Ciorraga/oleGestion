<?php
include "../vendor/autoload.php";
require_once "../config.php";
$app = new \Slim\Slim(
    array(
        'view' => new \Slim\Views\Twig(),
        'templates.path' => '../templates',
        'debug' => true
    )
);
$view = $app->view();
$view->parserOptions = array(
    'charset' => 'utf-8',
    'debug' => true,
    'auto_reload' => true,
    'strict_variables' => false,
    'autoescape' => true
);
# Add extensions.
$view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new \Twig_Extension_Debug()
);
session_cache_limiter(false);
session_start();

//Página de inicio de la aplicación
$app->get('/', function() use ($app) {
    if(isset($_SESSION['usuarioLogin'])){
        $app->render('inicio.html.twig', [
            'nombre' => $_SESSION['usuarioLogin']
        ]);
    }else {
        $app->render('login.html.twig');
    }
})->name('inicio');


//Cuando pulsas en logout
$app->get('/logout', function() use ($app) {
    session_destroy();
    //Redirecciona usando URLFOR a una plantila por su NAME (No se le pueden pasar alertas)
    $app->redirect($app->router()->urlFor('inicio'));
});

//Página nuevas notificaciones
$app->get('/nueva_notificacion', function() use ($app) {
    $contratos = ORM::for_table('contrato')
        ->find_many();

    $app->render('nueva_notificacion.html.twig',array('contratos' => $contratos,
        'nombre' => $_SESSION['usuarioLogin']));
    die();
})->name('nueva_notificacion');

//ajax contratos
$app->get('/rellenarContrato/:valor', function($valor){
    //Consulta. Segun el parámetro que se le pase, hace la consulta.
    $usuarios = ORM::for_table('usuario_contrato')
        ->select('usuario.id')
        ->select('usuario.nombre')
        ->where('contrato_id', $valor)
        ->join('usuario', array('usuario_contrato.usuario_id' ,'=', 'usuario.id'))
        ->find_many();

    foreach($usuarios as $user){
        echo "<option value='".$user['id']."'>".$user['nombre']."</option>";
    }
})->name('rellenarContrato');

//Página listar notificaciones
$app->get('/listar_notificacion', function () use ($app) {
    $fecha_actual = date("Y-m-d");
    //Se extraen las notificaciones que se le muestra al administrador
    $notiAdmim = ORM::for_table('notificacion')
        ->join('usuario', array('us1.id', '=', 'notificacion.id_usuario_creador'), 'us1')
        ->join('usuario', array('us2.id', '=', 'notificacion.id_socio'), 'us2')
        ->join('contrato', array('contrato.id', '=', 'notificacion.id_contrato'))
        ->select('us1.nombre', 'nombreCreador')
        ->select('us2.nombre', 'nombreSocio')
        ->select('contrato.boletin')
        ->select('notificacion.id')
        ->select('notificacion.cisternas')
        ->select('notificacion.fecha_carga')
        ->select('notificacion.fecha_alta')
        ->select('notificacion.estado')
        ->where_gte('fecha_carga', $fecha_actual)
        ->order_by_asc('notificacion.fecha_carga')
        ->find_many();
    //Se extraen las notificaciones que se le muestran al resto de los usuarios
    $notificaciones = ORM::for_table('notificacion')
        ->join('usuario', array('us1.id', '=', 'notificacion.id_usuario_creador'), 'us1')
        ->join('usuario', array('us2.id', '=', 'notificacion.id_socio'), 'us2')
        ->join('contrato', array('contrato.id', '=', 'notificacion.id_contrato'))
        ->select('us1.nombre', 'nombreCreador')
        ->select('us2.nombre', 'nombreSocio')
        ->select('contrato.boletin')
        ->select('notificacion.id')
        ->select('notificacion.cisternas')
        ->select('notificacion.fecha_carga')
        ->select('notificacion.fecha_alta')
        ->select('notificacion.estado')
        ->where('id_usuario_creador', $_SESSION['usuarioLogin']['id'])
        ->where_gte('fecha_carga', $fecha_actual)
        ->order_by_asc('notificacion.fecha_carga')
        ->find_many();
    $app->render('listar_notificacion.html.twig', [
        'notificaciones' => $notificaciones,
        'notisAdmin' => $notiAdmim,
        'nombre' => $_SESSION['usuarioLogin']
    ]);
})->name('listar_notificacion');
//Página nuevo contrato
$app->get('/nuevo_contrato', function() use ($app) {
    //Extrae los usuarios que sean socios
    $soc = ORM::for_table('usuario')
    ->where('rol','Socio')
    ->find_many();
    //Extrae los usuarios que sean corredores
    $corr = ORM::for_table('usuario')
    ->where('rol','Corredor')
    ->find_many();
    //Extrae los usuarios que sean compradores
    $comp = ORM::for_table('usuario')
    ->where('rol','Comprador')
    ->find_many();

    $app->render('nuevo_contrato.html.twig', [
        'socios' => $soc,
        'corredores' => $corr,
        'compradores' => $comp,
        'nombre' => $_SESSION['usuarioLogin']
    ]);
    die();
})->name('nuevo_contrato');

//Página listar contratos
$app->get('/listar_contrato', function() use ($app) {
    //Consulta para extraer los datos de la tabla contrato, y sus respectivos valores de las claves foráneas para los usuarios
    $cons = ORM::for_table('contrato')
        ->join('usuario', array('contrato.corredor_id', '=', 'us1.id'),'us1')
        ->join('usuario', array('contrato.comprador_id', '=', 'us2.id'),'us2')
        ->select('contrato.id','contrato_id')
        ->select('contrato.referencia')
        ->select('contrato.fecha_alta')
        ->select('contrato.boletin')
        ->select('contrato.calidad_aov')
        ->select('us1.nombre_usuario','corredor_nombreUser')
        ->select('us1.nombre','corredor_nombre')
        ->select('us1.apellidos','corredor_apellidos')
        ->select('us2.nombre_usuario','comprador_nombreUser')
        ->select('us2.nombre','comprador_nombre')
        ->select('us2.apellidos','comprador_apellidos')
        ->order_by_desc('contrato.id')
        ->find_many();
    //Creo la variable miArray que contendrá la consulta anterior y los socios relacionados con los contratos
    $miArray = [];
    $i = 0;
    foreach($cons as $item){
        //Consulta para extraer los socios de los contratos anteriores
        $socios = ORM::for_table('usuario_contrato')
            ->join('contrato', array('usuario_contrato.contrato_id', '=', 'contrato.id'))
            ->join('usuario', array('usuario_contrato.usuario_id', '=', 'usuario.id'))
            ->where('usuario_contrato.contrato_id',$item['contrato_id'])
            ->select('usuario.nombre')
            ->find_many();

        //Unión en un array de los contratos y sus respectivos socios
        $miArray[$i]['id'] = $item['contrato_id'];
        $miArray[$i]['referencia'] = $item['referencia'];
        $miArray[$i]['boletin'] = $item['boletin'];
        $miArray[$i]['fecha_alta'] = $item['fecha_alta'];
        $miArray[$i]['calidad_aov'] = $item['calidad_aov'];
        $miArray[$i]['corredor_nombreUser'] = $item['corredor_nombreUser'];
        $miArray[$i]['corredor_nombre'] = $item['corredor_nombre'];
        $miArray[$i]['corredor_apellidos'] = $item['corredor_apellidos'];
        $miArray[$i]['comprador_nombreUser'] = $item['comprador_nombreUser'];
        $miArray[$i]['comprador_nombre'] = $item['comprador_nombre'];
        $miArray[$i]['comprador_apellidos'] = $item['comprador_apellidos'];
        $miArray[$i]['socios'] = $socios;
        $i++;
    }
    $app->render('listar_contrato.html.twig', [
        'datosCont' => $miArray,
        'nombre' => $_SESSION['usuarioLogin']
    ]);

})->name('listar_contrato');


//Página nuevo usuario
$app->get('/nuevo_usuario', function() use ($app) {
    $app->render('nuevo_usuario.html.twig', [
        'nombre' => $_SESSION['usuarioLogin']
    ]);
})->name('nuevo_usuario');
//Página listar usuarios
$app->get('/listar_usuario', function() use ($app) {
    //Extrae usuarios
    $usuarios = ORM::for_table('usuario')
        ->find_many();
    $app->render('listar_usuario.html.twig', [
        'usuarios' => $usuarios,
        'nombre' => $_SESSION['usuarioLogin']
    ]);
    die();
})->name('listar_usuario');
// -------------------------------------- BOTONES ------------------------------------------------
$app->post('/', function() use ($app) {
    //Al pulsar el boton de login
    if(isset($_POST['username'])){
        $nombreUser = htmlentities($_POST['username']);
        $passUser = htmlentities($_POST['password']);
        //Extrae el usuario que ha introducido los datos
        $cons = ORM::for_table('usuario')
            ->where('nombre_usuario',$nombreUser)
            ->where('password', $passUser)
            ->find_one();        
        if($cons){
            $datetimeActual = date('Y-m-d H:i:s');
            //Consulta INSERT para la tabla usuario. Le agregamos la fecha de la última conexión
            $userAModificar = ORM::for_table('usuario')->find_one($cons['id']);
            $userAModificar->ultima_conexion = $datetimeActual;
            $userAModificar->save();

            //Extrae el usuario que ha introducido los datos
            $cons = ORM::for_table('usuario')
                ->where('nombre_usuario',$nombreUser)
                ->where('password', $passUser)
                ->find_one();

            //Guardamos en una variable de sesión los datos del usuario logueado para utilizarlos despues.
            $_SESSION['usuarioLogin'] = $cons;
            $app->render('inicio.html.twig', [
                'nombre' => $_SESSION['usuarioLogin']
            ]);
        }else{
            $app->render('login.html.twig',array('errorLogin' => 'ok'));
        }
    }


    //Al pulsar el boton de crear nuevo contrato
    if(isset($_POST['botonCreaContrato'])){
        $num_referencia = htmlentities($_POST['ref']);
        $num_boletin = htmlentities($_POST['bol']);
        $corredor = $_POST['corr'];
        $comprador = $_POST['comp'];
        $socios = $_POST['socios'];
        $calidad = $_POST['calidad'];
        $fecha_actual=date("Y-m-d H:i:s");

        $compRef = ORM::for_table('contrato')
            ->where('referencia',$num_referencia)
            ->find_one();

        $compBol = ORM::for_table('contrato')
            ->where('boletin',$num_boletin)
            ->find_one();

        if($compRef || $compBol){
            $soc = ORM::for_table('usuario')
                ->where('rol','Socio')
                ->find_many();
            $corr = ORM::for_table('usuario')
                ->where('rol','Corredor')
                ->find_many();
            $comp = ORM::for_table('usuario')
                ->where('rol','Comprador')
                ->find_many();
            $app->render('nuevo_contrato.html.twig',array('mensajeError' => 'El número de referencia o el número de boletín ya existe'
            ,'socios' => $soc
            , 'corredores' => $corr
            , 'compradores' => $comp
            , 'nombre' => $_SESSION['usuarioLogin']));
            die();
        }else{
            //INSERCIÓN de contrato
            $nuevoContrato = ORM::for_table('contrato')->create();
            $nuevoContrato->referencia = $num_referencia;
            $nuevoContrato->boletin = $num_boletin;
            $nuevoContrato->corredor_id = $corredor;
            $nuevoContrato->comprador_id = $comprador;
            $nuevoContrato->fecha_alta = $fecha_actual;
            $nuevoContrato->calidad_AOV = $calidad;
            $nuevoContrato->save();

            $consIdCont = ORM::for_table('contrato')
                ->select('id')
                ->where('referencia',$num_referencia)
                ->find_one();

            foreach($socios as $socio){
                //INSERCIÓN de contrato
                $nuevoContratoSocios = ORM::for_table('usuario_contrato')->create();
                $nuevoContratoSocios->usuario_id = $socio;
                $nuevoContratoSocios->contrato_id = $consIdCont['id'];
                $nuevoContratoSocios->save();
            }

            $app->render('inicio.html.twig', [
                'mensajeOk' => 'Contrato añadido con éxito',
                'nombre' => $_SESSION['usuarioLogin']
            ]);
        }
    }

    //Al pulsar para eliminar el contrato deseado
    if(isset($_POST['eliminarContrato'])){
        ORM::for_table('usuario_contrato')
            ->where('contrato_id',$_POST['eliminarContrato'])
            ->delete_many();
        ORM::for_table('contrato')
            ->find_one($_POST['eliminarContrato'])
            ->delete();

        $cons = ORM::for_table('contrato')
            ->join('usuario', array('contrato.corredor_id', '=', 'us1.id'),'us1')
            ->join('usuario', array('contrato.comprador_id', '=', 'us2.id'),'us2')
            ->select('contrato.id','contrato_id')
            ->select('contrato.referencia')
            ->select('contrato.fecha_alta')
            ->select('contrato.boletin')
            ->select('contrato.calidad_aov')
            ->select('us1.nombre_usuario','corredor_nombreUser')
            ->select('us1.nombre','corredor_nombre')
            ->select('us1.apellidos','corredor_apellidos')
            ->select('us2.nombre_usuario','comprador_nombreUser')
            ->select('us2.nombre','comprador_nombre')
            ->select('us2.apellidos','comprador_apellidos')
            ->order_by_desc('contrato.id')
            ->find_many();
        $miArray = [];
        $i = 0;
        foreach($cons as $item){
            $socios = ORM::for_table('usuario_contrato')
                ->join('contrato', array('usuario_contrato.contrato_id', '=', 'contrato.id'))
                ->join('usuario', array('usuario_contrato.usuario_id', '=', 'usuario.id'))
                ->where('usuario_contrato.contrato_id',$item['contrato_id'])
                ->select('usuario.nombre')
                ->find_many();

            $miArray[$i]['id'] = $item['contrato_id'];
            $miArray[$i]['referencia'] = $item['referencia'];
            $miArray[$i]['boletin'] = $item['boletin'];
            $miArray[$i]['fecha_alta'] = $item['fecha_alta'];
            $miArray[$i]['calidad_aov'] = $item['calidad_aov'];
            $miArray[$i]['corredor_nombreUser'] = $item['corredor_nombreUser'];
            $miArray[$i]['corredor_nombre'] = $item['corredor_nombre'];
            $miArray[$i]['corredor_apellidos'] = $item['corredor_apellidos'];
            $miArray[$i]['comprador_nombreUser'] = $item['comprador_nombreUser'];
            $miArray[$i]['comprador_nombre'] = $item['comprador_nombre'];
            $miArray[$i]['comprador_apellidos'] = $item['comprador_apellidos'];
            $miArray[$i]['socios'] = $socios;
            $i++;
        }
        $app->render('listar_contrato.html.twig', [
            'datosCont' => $miArray,
            'mensajeError' => 'Contrato eliminado con éxito',
            'nombre' => $_SESSION['usuarioLogin']
        ]);
    }

    //Al pulsar para editar el contrato deseado. LLevará a una sección que nos permitirá hacer cambios
    if(isset($_POST['editarContrato'])){
        $cons = ORM::for_table('contrato')
            ->where('id',$_POST['editarContrato'])
            ->find_one();
        $sociosSelecc = ORM::for_table('usuario_contrato')
            ->where('contrato_id',$_POST['editarContrato'])
            ->find_many();
        $soc = ORM::for_table('usuario')
            ->where('rol','Socio')
            ->find_many();
        $corr = ORM::for_table('usuario')
            ->where('rol','Corredor')
            ->find_many();
        $comp = ORM::for_table('usuario')
            ->where('rol','Comprador')
            ->find_many();

        $app->render('nuevo_contrato.html.twig', [
            'datosCont' => $cons,
            'sociosSelecc' => $sociosSelecc,
            'socios' => $soc, 'corredores' => $corr,
            'compradores' => $comp,
            'nombre' => $_SESSION['usuarioLogin']
        ]);
    }

    //Al pulsar el boton editar una vez hemos hecho los cambios en el contrato
    if(isset($_POST['botonEditaContrato'])) {
        $num_referencia = htmlentities($_POST['ref']);
        $num_boletin = htmlentities($_POST['bol']);
        $corredor = $_POST['corr'];
        $comprador = $_POST['comp'];
        $socios = $_POST['socios'];
        $calidad = $_POST['calidad'];
        $fecha_actual = date("Y-m-d H:i:s");

        //Borrado de datos de la base de datos
        ORM::for_table('usuario_contrato')
            ->where('contrato_id',$_POST['botonEditaContrato'])
            ->delete_many();

        //Inserción de nuevo contrato
        $nuevoContrato = ORM::for_table('contrato')->find_one($_POST['botonEditaContrato']);
        $nuevoContrato->referencia = $num_referencia;
        $nuevoContrato->boletin = $num_boletin;
        $nuevoContrato->corredor_id = $corredor;
        $nuevoContrato->comprador_id = $comprador;
        $nuevoContrato->fecha_alta = $fecha_actual;
        $nuevoContrato->calidad_AOV = $calidad;
        $nuevoContrato->save();

        foreach($socios as $socio){
            $nuevoContratoSocios = ORM::for_table('usuario_contrato')->create();
            $nuevoContratoSocios->usuario_id = $socio;
            $nuevoContratoSocios->contrato_id = $_POST['botonEditaContrato'];
            $nuevoContratoSocios->save();
        }

        $cons = ORM::for_table('contrato')
            ->join('usuario', array('contrato.corredor_id', '=', 'us1.id'), 'us1')
            ->join('usuario', array('contrato.comprador_id', '=', 'us2.id'), 'us2')
            ->select('contrato.id', 'contrato_id')
            ->select('contrato.referencia')
            ->select('contrato.fecha_alta')
            ->select('contrato.boletin')
            ->select('contrato.calidad_aov')
            ->select('us1.nombre_usuario', 'corredor_nombreUser')
            ->select('us1.nombre', 'corredor_nombre')
            ->select('us1.apellidos', 'corredor_apellidos')
            ->select('us2.nombre_usuario', 'comprador_nombreUser')
            ->select('us2.nombre', 'comprador_nombre')
            ->select('us2.apellidos', 'comprador_apellidos')
            ->order_by_desc('contrato.id')
            ->find_many();
        $miArray = [];
        $i = 0;
        foreach ($cons as $item) {
            $socios = ORM::for_table('usuario_contrato')
                ->join('contrato', array('usuario_contrato.contrato_id', '=', 'contrato.id'))
                ->join('usuario', array('usuario_contrato.usuario_id', '=', 'usuario.id'))
                ->where('usuario_contrato.contrato_id', $item['contrato_id'])
                ->select('usuario.nombre')
                ->find_many();

            $miArray[$i]['id'] = $item['contrato_id'];
            $miArray[$i]['referencia'] = $item['referencia'];
            $miArray[$i]['boletin'] = $item['boletin'];
            $miArray[$i]['fecha_alta'] = $item['fecha_alta'];
            $miArray[$i]['calidad_aov'] = $item['calidad_aov'];
            $miArray[$i]['corredor_nombreUser'] = $item['corredor_nombreUser'];
            $miArray[$i]['corredor_nombre'] = $item['corredor_nombre'];
            $miArray[$i]['corredor_apellidos'] = $item['corredor_apellidos'];
            $miArray[$i]['comprador_nombreUser'] = $item['comprador_nombreUser'];
            $miArray[$i]['comprador_nombre'] = $item['comprador_nombre'];
            $miArray[$i]['comprador_apellidos'] = $item['comprador_apellidos'];
            $miArray[$i]['socios'] = $socios;
            $i++;
        }
        $app->render('listar_contrato.html.twig', [
            'datosCont' => $miArray,
            'mensajeOk' => 'Contrato modificado con éxito',
            'nombre' => $_SESSION['usuarioLogin']
        ]);
    }
    // REGISTRO Y EDICION USUARIOS
    if (isset($_POST['enviar'])) {
        $registros = array();
        $registros = $_POST;
        array_pop($registros);
        $usuario = ORM::for_table('usuario')->find_one($_POST['enviar']);
        $error = "";
        $ok = "";
        $check = false;
        $cons = ORM::for_table('usuario')
            ->where('nombre_usuario', $registros['usuario'])
            ->find_one();
        if ($_POST['enviar'] == "") {
            if ($registros['password'] != $registros['password2']) {
                $error = "Las contraseñas no coinciden";
                $check = false;
                $app->render('nuevo_usuario.html.twig', [
                    'mensajeError' => $error,
                    'nombre' => $_SESSION['usuarioLogin']
                ]);
                die();
            } else {
                $check = true;
            }
        } else {
            if ($registros['password'] != $registros['password2']) {
                $error = "Las contraseñas no coinciden";
                $check = false;
                $app->render('nuevo_usuario.html.twig', [
                    'nombre1' => 'Editar',
                    'mensajeError' => $error,
                    'usuario' => $usuario,
                    'nombre' => $_SESSION['usuarioLogin']
                ]);
                die();
            } else {
                $check = true;
            }
        }
        if ($_POST['enviar'] == "") {
            if ($registros['email'] == $registros['email2']) {
                $error = "Los emails no pueden coincidir";
                $check = false;
                $app->render('nuevo_usuario.html.twig', [
                    'mensajeError' => $error,
                    'nombre' => $_SESSION['usuarioLogin']
                ]);
                die();
            } else {
                $check = true;
            }
        } else {
            if ($registros['email'] == $registros['email2']) {
                $error = "Los emails no pueden coincidir";
                $check = false;
                $app->render('nuevo_usuario.html.twig', [
                    'nombre1' => 'Editar',
                    'mensajeError' => $error,
                    'usuario' => $usuario,
                    'nombre' => $_SESSION['usuarioLogin']
                ]);
                die();
            } else {
                $check = true;
            }
        }
        if ($_POST['enviar'] == "") {
            if ($cons) {
                $error = "Ya existe un usuario registrado con ese nombre";
                $check = false;
                $app->render('nuevo_usuario.html.twig', [
                    'mensajeError' => $error,
                    'nombre' => $_SESSION['usuarioLogin']
                ]);
                die();
            } else {
                $check = true;
            }
        }
        if ($check) {
            if ($_POST['enviar'] != "") {
                $usuario = ORM::for_table('usuario')->find_one($_POST['enviar']);
                $usuario->nombre_usuario = $registros['usuario'];
                $usuario->password = $registros['password'];
                $usuario->nombre = $registros['nombre'];
                $usuario->apellidos = $registros['apellidos'];
                $usuario->direccion = $registros['direccion'];
                $usuario->localidad = $registros['localidad'];
                $usuario->provincia = $registros['provincia'];
                $usuario->cod_postal = $registros['cpostal'];
                $usuario->telefono = $registros['telefono'];
                $usuario->movil = $registros['movil'];
                $usuario->email = $registros['email'];
                $usuario->email_secundario = $registros['email2'];
                $usuario->rol = $_POST['rol'];
                if (isset($_POST['notificaciones'])) {
                    $usuario->notificaciones_carga = true;
                } else {
                    $usuario->notificaciones_carga = false;
                }
                $nuevo_usuario = ORM::for_table('usuario')->find_one($registros['usuario']);
                if ($registros['usuario'] != $nuevo_usuario) {
                    $comp = ORM::for_table('usuario')->find_one($nuevo_usuario);
                    if ($comp) {
                        $error = "Ya existe un usuario registrado con ese nombre";
                        $check = false;
                        $app->render('nuevo_usuario.html.twig', [
                            'mensajeError' => $error,
                            'nombre1' => 'Editar',
                            'usuario' => $usuario,
                            'metodo' => 'editar',
                            'nombre' => $_SESSION['usuarioLogin']
                        ]);
                        die();
                    } else {
                        $usuario->save();
                        $ok = "Usuario modificado correctamente";
                        $usuarios = ORM::for_table('usuario')
                            ->find_many();
                        $app->render('listar_usuario.html.twig', [
                            'mensajeOk' => $ok,
                            'usuarios' => $usuarios,
                            'nombre' => $_SESSION['usuarioLogin']
                        ]);
                        die();
                    }
                }
                $usuario->save();
            } else {
                $usuario = ORM::for_table('usuario')->create();
                $usuario->nombre_usuario = $registros['usuario'];
                $usuario->password = $registros['password'];
                $usuario->nombre = $registros['nombre'];
                $usuario->apellidos = $registros['apellidos'];
                $usuario->direccion = $registros['direccion'];
                $usuario->localidad = $registros['localidad'];
                $usuario->provincia = $registros['provincia'];
                $usuario->cod_postal = $registros['cpostal'];
                $usuario->telefono = $registros['telefono'];
                $usuario->movil = $registros['movil'];
                $usuario->email = $registros['email'];
                $usuario->email_secundario = $registros['email2'];
                $usuario->rol = $_POST['rol'];
                if (isset($_POST['notificaciones'])) {
                    $usuario->notificaciones_carga = true;
                } else {
                    $usuario->notificaciones_carga = false;
                }
                $usuario->save();
                $ok = "Usuario creado correctamente";
            }
        } else {
            $error = "Ha habido un fallo al registrar el usuario";
        }
        $app->render('nuevo_usuario.html.twig', [
            'mensajeOk' => $ok,
            'nombre' => $_SESSION['usuarioLogin']
        ]);
        die();
    }
    // ELIMINAR USUARIOS
    if(isset($_POST['eliminar_user'])){
        $user = ORM::for_table('usuario')
            ->where('id',$_POST['eliminar_user'])
            ->find_one();
        $user->delete();
        $user->save();
        $usuarios = ORM::for_table('usuario')
            ->find_many();
        $app->render('listar_usuario.html.twig', [
            'mensajeOk' => 'Usuario eliminado de forma correcta',
            'usuarios' => $usuarios,
            'nombre' => $_SESSION['usuarioLogin']
        ]);
    }
    // EDITAR USUARIOS
    if(isset($_POST['editar_user'])){
        $user = ORM::for_table('usuario')
            ->where('id',$_POST['editar_user'])
            ->find_one();
        $app->render('nuevo_usuario.html.twig', [
            'nombre1' => 'Editar',
            'usuario' => $user,
            'metodo' => 'editar',
            'nombre' => $_SESSION['usuarioLogin']
        ]);
    }

    // ELIMINAR NOTIFICACIONES
    if(isset($_POST['eliminar_noti'])){
        $kk = ORM::for_table('notificacion')
            ->where('id',$_POST['eliminar_noti'])
            ->find_one();
        $kk->delete();

        $fecha_actual=date("Y-m-d");
        $notificaciones = ORM::for_table('notificacion')
            ->join('usuario', array('us1.id', '=', 'notificacion.id_usuario_creador'),'us1')
            ->join('usuario', array('us2.id', '=', 'notificacion.id_socio'),'us2')
            ->join('contrato', array('contrato.id', '=', 'notificacion.id_contrato'))
            ->select('us1.nombre','nombreCreador')
            ->select('us2.nombre','nombreSocio')
            ->select('contrato.boletin')
            ->select('notificacion.id')
            ->select('notificacion.cisternas')
            ->select('notificacion.fecha_carga')
            ->select('notificacion.fecha_alta')
            ->select('notificacion.estado')
            ->where_gte('fecha_carga',$fecha_actual)
            ->order_by_asc('notificacion.fecha_carga')
            ->find_many();

        $app->render('listar_notificacion.html.twig', [
            'mensajeOk' => 'Notificación eliminada de forma correcta',
            'notificaciones' => $notificaciones,
            'nombre' => $_SESSION['usuarioLogin']
        ]);
        die();
    }

    //CREAR NOTIFICACIONES
    if(isset($_POST['botonCreaNotificacion'])){
        if($_POST['contrato']==0){
            $contratos = ORM::for_table('contrato')
                ->find_many();
            $app->render('nueva_notificacion.html.twig',array('mensajeError' => 'Es necesario seleccionar un contrato','contratos' => $contratos ));
            die();
        }
        if($_POST['socio']==0 ){
            $contratos = ORM::for_table('contrato')
                ->find_many();
            $app->render('nueva_notificacion.html.twig',array('mensajeError' => 'Es necesario seleccionar un socio para el contrato','contratos' => $contratos ));
            die();
        }else{
            if($_POST['cisternas']==0){
                $contratos = ORM::for_table('contrato')
                    ->find_many();
                $app->render('nueva_notificacion.html.twig',array('mensajeError' => 'Debe de seleccionar las cisternas obligatoriamente','contratos' => $contratos ));
                die();
            }
            $compMat = 0;
            $fecha=date("Y-m-d");
            $fecha_y_hora=date("Y-m-d H:i:s");

            $nuevaNotificacion = ORM::for_table('notificacion')
                ->create();

            $nuevaNotificacion->id_contrato = $_POST['contrato'];
            $nuevaNotificacion->id_socio = $_POST['socio'];
            $nuevaNotificacion->cisternas = $_POST['cisternas'];
            $nuevaNotificacion->estado = $_POST['estado'];
            if($_POST['fecha_carga']) {
                $nuevaNotificacion->fecha_carga = $fecha;
            }
            $nuevaNotificacion->fecha_alta = $fecha_y_hora;
            $nuevaNotificacion->fecha_modificacion = $fecha_y_hora;
            $nuevaNotificacion->id_usuario_creador = $_SESSION['usuarioLogin']['id'];
            $nuevaNotificacion->id_usuario_modifica = $_SESSION['usuarioLogin']['id'];
            if($_POST['observaciones']){
                $nuevaNotificacion->observaciones = $_POST['observaciones'];
            }

            //Rellena una variable que indicará si vienen matrículas con el formulario
            for($i=1;$i<=$_POST['cisternas'];$i++){
                if($_POST['matricula_'.$i]){
                    $compMat++;
                }
            }

            //Comprobación de que se han marcado cisternas en la notificación
            if($_POST['cisternas']>1) {
                //Comprobación. Si se han marcado cisternas pero ninguna matrícula se hace la inserción
                if ($compMat == $_POST['cisternas'] || $compMat==0) {
                    $nuevaNotificacion->save();
                // Comprobación de que si se introducen matrículas se introduzcan todas las marcadas en las cisternas
                } else {
                    $contratos = ORM::for_table('contrato')
                        ->find_many();
                    $app->render('nueva_notificacion.html.twig', array('mensajeError' => 'No puede dejar campos vacíos en las matrículas','contratos' => $contratos));
                    die();
                }
            }else{
                if ($compMat == $_POST['cisternas']) {
                    $nuevaNotificacion->save();
                }
            }

            //$nuevaNotificacion->save();
            $idNotificacion = ORM::for_table('notificacion')
                ->order_by_desc('id')
                ->find_many();
            if($compMat!=0){
                for($i=1;$i<=$_POST['cisternas'];$i++){
                    if($_POST['matricula_'.$i]){
                        $idNotificacion = ORM::for_table('notificacion')
                            ->order_by_desc('id')
                            ->find_many();
                        $nuevaMatricula = ORM::for_table('matricula')
                            ->create();
                        $nuevaMatricula->id_notificacion = $idNotificacion[0]['id'];
                        $nuevaMatricula->matricula = $_POST['matricula_'.$i];
                        $nuevaMatricula->save();
                    }
                }
            }
            $app->render('inicio.html.twig',array('mensajeOk' => 'Notificación agregada con éxito',
                'nombre' => $_SESSION['usuarioLogin']));
            die();
        };
    };

    //EDITAR NOTIFICACION
    if(isset($_POST['editar_noti'])){
        $notificacion = ORM::for_table('notificacion')
            ->where('id',$_POST['editar_noti'])
            ->find_one();

        $matriculas = ORM::for_table('matricula')
            ->where('id_notificacion',$_POST['editar_noti'])
            ->find_many();


        $contratos = ORM::for_table('contrato')
            ->find_many();

        $app->render('nueva_notificacion.html.twig',array('contratos' => $contratos,
            'notificacion' => $notificacion,
            'usuarioLogin' => $_SESSION['usuarioLogin'],
            'matriculas' => $matriculas,
            'nombre' => $_SESSION['usuarioLogin'],
            'numMat' => count($matriculas)));
        die();
    }

    if(isset($_POST['botonEditaNotificacion'])){
        if($_POST['contrato']==0){
            $notificacion = ORM::for_table('notificacion')
                ->where('id',$_POST['botonEditaNotificacion'])
                ->find_one();

            $matriculas = ORM::for_table('matricula')
                ->where('id_notificacion',$_POST['botonEditaNotificacion'])
                ->find_many();


            $contratos = ORM::for_table('contrato')
                ->find_many();

            $app->render('nueva_notificacion.html.twig',array('contratos' => $contratos,
                'notificacion' => $notificacion,
                'usuarioLogin' => $_SESSION['usuarioLogin'],
                'matriculas' => $matriculas,
                'mensajeError' => 'Debe seleccionar un contrato',
                'nombre' => $_SESSION['usuarioLogin'],
                'numMat' => count($matriculas)));
            die();
        }

            if($_POST['cisternas']==0){
                $notificacion = ORM::for_table('notificacion')
                    ->where('id',$_POST['botonEditaNotificacion'])
                    ->find_one();

                $matriculas = ORM::for_table('matricula')
                    ->where('id_notificacion',$_POST['botonEditaNotificacion'])
                    ->find_many();


                $contratos = ORM::for_table('contrato')
                    ->find_many();

                $app->render('nueva_notificacion.html.twig',array('contratos' => $contratos,
                    'notificacion' => $notificacion,
                    'usuarioLogin' => $_SESSION['usuarioLogin'],
                    'matriculas' => $matriculas,
                    'mensajeError' => 'Debe seleccionar el número de cisternas',
                    'nombre' => $_SESSION['usuarioLogin'],
                    'numMat' => count($matriculas)));
                die();
            }

            $mat = ORM::for_table('matricula')
                ->where('id_notificacion',$_POST['botonEditaNotificacion'])
                ->find_many();
        foreach($mat as $m){
            $m->delete();
        }

            $compMat = 0;
            $fecha=date("Y-m-d");
            $fecha_y_hora=date("Y-m-d H:i:s");

            $nuevaNotificacion = ORM::for_table('notificacion')
                ->find_one($_POST['botonEditaNotificacion']);

            $nuevaNotificacion->id_contrato = $_POST['contrato'];
            $nuevaNotificacion->id_socio = $_POST['socio'];
            $nuevaNotificacion->cisternas = $_POST['cisternas'];
            $nuevaNotificacion->estado = $_POST['estado'];
            if($_POST['fecha_carga']) {
                $nuevaNotificacion->fecha_carga = $_POST['fecha_carga'];
            }
            $nuevaNotificacion->fecha_modificacion = $fecha_y_hora;
            $nuevaNotificacion->id_usuario_modifica = $_SESSION['usuarioLogin']['id'];
            if($_POST['observaciones']){
                $nuevaNotificacion->observaciones = $_POST['observaciones'];
            }

            //Rellena una variable que indicará si vienen matrículas con el formulario
            for($i=1;$i<=$_POST['cisternas'];$i++){
                if($_POST['matricula_'.$i]){
                    $compMat++;
                }
            }

            //Comprobación de que se han marcado cisternas en la notificación
            if($_POST['cisternas']>1) {
                //Comprobación. Si se han marcado cisternas pero ninguna matrícula se hace la inserción
                if ($compMat == $_POST['cisternas'] || $compMat==0) {
                    $nuevaNotificacion->save();
                    // Comprobación de que si se introducen matrículas se introduzcan todas las marcadas en las cisternas
                } else {
                    $contratos = ORM::for_table('contrato')
                        ->find_many();
                    $app->render('nueva_notificacion.html.twig', array('mensajeError' => 'No puede dejar campos vacíos en las matrículas',
                        'contratos' => $contratos,
                        'nombre' => $_SESSION['usuarioLogin']));
                    die();
                }
            }else{
                if ($compMat == $_POST['cisternas']) {
                    $nuevaNotificacion->save();
                }
            }

            //$nuevaNotificacion->save();
            $idNotificacion = ORM::for_table('notificacion')
                ->order_by_desc('id')
                ->find_many();
            if($compMat!=0){
                for($i=1;$i<=$_POST['cisternas'];$i++){
                    if($_POST['matricula_'.$i]){
                        $idNotificacion = ORM::for_table('notificacion')
                            ->order_by_desc('id')
                            ->find_many();
                        $nuevaMatricula = ORM::for_table('matricula')
                            ->create();
                        $nuevaMatricula->id_notificacion = $idNotificacion[0]['id'];
                        $nuevaMatricula->matricula = $_POST['matricula_'.$i];
                        $nuevaMatricula->save();
                    }
                }
            }
            $app->render('inicio.html.twig',array('mensajeOk' => 'Notificación agregada con éxito',
                'nombre' => $_SESSION['usuarioLogin']));
            die();

    };
});
$app->run();
